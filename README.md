# Furniture Workshop Inventory and Production System

A full-stack interview-assignment MVP for a single furniture workshop.

- **Frontend:** React 19 + Vite (JavaScript)
- **Backend:** Laravel 12 REST API
- **Database:** MySQL intended for production; SQLite used for local development/testing
- **3D configurator:** React Three Fiber, Drei, and Three.js

## Project structure

```text
.
├── docs/                 # architecture.md, database-design.md, implementation-plan.md
├── backend/              # Laravel API
│   ├── app/
│   │   ├── Http/Controllers/Api/V1
│   │   ├── Http/Requests
│   │   ├── Models
│   │   └── Services
│   ├── database/migrations
│   ├── database/seeders
│   ├── routes/api.php
│   └── tests/Feature
└── frontend/             # React + Vite inventory UI and 3D configurator
    ├── src/api
    ├── src/components
    ├── src/features/inventory
    ├── src/features/configurator
    └── src/lib
```

## Backend

### Requirements

- PHP 8.2+ with extensions: `pdo_sqlite`, `pdo_mysql`, `mbstring`, `openssl`, etc.
- Composer
- MySQL (for production) or SQLite (for local development)

### Setup

```bash
cd backend
composer install

cp .env.example .env
# For local SQLite development the shipped .env already uses sqlite:
# DB_CONNECTION=sqlite

php artisan key:generate
php artisan migrate --seed
```

### Running the API locally

`php artisan serve` currently fails on this Windows environment because the Symfony process environment block exceeds the OS limit. Use the PHP built-in server directly instead:

```bash
cd backend
php -S 127.0.0.1:8000 -t public
```

The API will be available at `http://127.0.0.1:8000/api/v1`.

### Tests

```bash
cd backend
vendor/bin/phpunit --filter MaterialApiTest
vendor/bin/phpunit --filter DashboardApiTest
vendor/bin/phpunit --filter FurnitureModelApiTest
vendor/bin/phpunit --filter ProductionQuoteApiTest
vendor/bin/phpunit --filter ProductionOrderConfirmApiTest
```

Current results:

- `MaterialApiTest`: 21 tests, 72 assertions — OK
- `DashboardApiTest`: 1 test, 12 assertions — OK
- `FurnitureModelApiTest`: 4 tests, 17 assertions — OK
- `ProductionQuoteApiTest`: 9 tests, 35 assertions — OK
- `ProductionOrderConfirmApiTest`: 8 tests, 49 assertions — OK
- Frontend unit tests: 9 tests — OK

## Frontend

### Setup

```bash
cd frontend
npm install
```

Create a local environment file if your backend runs somewhere other than the default:

```bash
cp .env.example .env.local
# VITE_API_BASE_URL=http://localhost:8000/api/v1
```

### Running locally

```bash
cd frontend
npm run dev
```

Then open `http://localhost:5173`. Use the top navigation to switch between Inventory and Configurator.

### Build and lint

```bash
cd frontend
npm run build
npm run lint
```

`npm run build` completes successfully. `npm run lint` reports `set-state-in-effect` warnings in data-fetching effects; there are 0 lint errors. `npm test` runs the Vitest suite.

```bash
cd frontend
npm run build
npm run lint
npm test
```

## Implemented so far

### Stage 1 — Laravel inventory foundation

- Material CRUD endpoints under `/api/v1/materials`.
- Atomic stock adjustments through `StockAdjustmentService` (row locking, transactions, BCMath).
- Inventory movement history append-only ledger.
- Validation via Form Requests, supporting `m2`, `m3`, `m`, `ea`, and `kg`.
- Migrations and models for future `furniture_models`, `model_parts`, `production_orders`, and `production_consumptions`.
- Feature tests for materials and adjustments.

### Stage 2 — React inventory frontend

- Dashboard summary cards: total materials, active, low stock, out of stock.
- Searchable, group-filterable, low-stock-filterable paginated materials table.
- Add and edit material forms that mirror backend validation and lock the stock unit once movements exist.
- Stock adjustment modal with signed quantity + reason; always refreshes balances from the API.
- Movement history modal per material with pagination.
- Reusable API client layer using `VITE_API_BASE_URL`.

### Stage 3A — Furniture models, parts, and 3D configurator foundation

- Added dimension constraints (`default/min/max_length_mm`, etc.) to `furniture_models` via a new migration.
- Added `is_configurable` and `sort_order` to `model_parts` via a new migration.
- Seeded two active models: **Dining table** and **Bedside cabinet**, each with configurable parts.
- Added API endpoints:
  - `GET /api/v1/furniture-models` — list active models with dimension ranges.
  - `GET /api/v1/furniture-models/{slug}` — model detail with parts and compatible active materials.
- Material compatibility is enforced by exact `material_group` and `consumption_unit` ↔ `stock_unit` match; unlike units are never converted or treated as interchangeable.
- Added `FurnitureModelApiTest` covering listing, detail, compatibility filtering, and inactive-model exclusion.
- Added a React Three Fiber/Drei configurator page with:
  - Model selector (table / bedside cabinet).
  - Bounded integer-millimetre dimension controls.
  - Per-part material selectors populated from the API.
  - Procedural 3D models (table and bedside cabinet) that update geometry and materials interactively.
  - Reset-to-defaults action and loading/empty/error states.
- Visual appearance is driven by an explicit `visual_key` → Three.js material mapping; it is kept separate from inventory calculations.

### Stage 3B — Backend material consumption and production quotes

- Added `POST /api/v1/production/quote` to calculate read-only production quotes.
- Added `FormulaCalculator` service supporting:
  - `panel_area_m2`
  - `solid_volume_m3`
  - `cabinet_carcass_m3`
  - `fixed_each`
  - Unknown formula keys throw an explicit error.
- Added `ProductionQuoteService` to validate configuration, calculate component consumption, aggregate by material, and compare against current stock.
- Rounding strategy: component consumption is rounded **up** to 6 decimal places, then multiplied by the production quantity. Aggregated per-material totals are the sum of those rounded component totals.
- Updated `ModelPartSeeder` with `dimension_axes` hints and `foot_height_mm` for feet.
- Added `ProductionQuoteApiTest` covering valid quotes, aggregation of shared materials, invalid dimensions, missing parts, incompatible units, unknown formulas, insufficient stock, inactive materials, and no side effects on inventory.
- Replaced the configurator's dimension number boxes with slider + numeric input.
- Added a **Quantity** selector and a live quote panel showing component breakdown, aggregated material requirements, stock balances, and sufficiency status.
- Quote requests are debounced (350 ms) and stale responses are discarded via `AbortController`.

### Stage 4 — Production order confirmation and atomic inventory deduction

- Added `POST /api/v1/production-orders/confirm` to create production orders and deduct stock atomically.
- Added `ProductionConfirmationService` that:
  - Validates the `Idempotency-Key` header and persists it on `production_orders` with a unique constraint.
  - Reuses `ProductionQuoteService` for server-side recalculation instead of trusting client quantities.
  - Locks affected `materials` rows in ascending material-ID order using `lockForUpdate()`.
  - Rechecks stock after locking and returns `422` if any material is insufficient.
  - Creates the production order, component-level `production_consumptions`, negative `inventory_movements`, and updates material balances inside a single database transaction.
  - Detects idempotency-key races via the unique constraint and returns either the existing order (same payload, `200`) or a `409` conflict (different payload).
- Added `ProductionOrderConfirmApiTest` covering:
  - Successful confirmation with order/consumption/movement creation and balance updates.
  - Idempotent retry with the same key and payload.
  - Conflicting key reuse with a different payload (`409`).
  - Insufficient-stock rollback (no order, no consumption, no movement, balances unchanged).
  - Invalid dimensions and incompatible material rejection.
  - Bedside cabinet aggregation of shared materials into a single inventory movement.
  - Missing idempotency-key rejection.
- Wired the configurator to show a **Place Production Order** button, enabled only when the current quote is valid, current, and reports sufficient stock.
- The frontend generates one idempotency key per configuration and disables the button during submission to prevent double-clicks.
- After a successful confirmation the UI refreshes the model detail, recalculates the quote, and signals the inventory page to refresh its dashboard and material list.

### Stage 5A — Material appearance system + configurator layout improvement

- Added `color_hex` and `texture_path` to `materials` via an additive migration.
- Added a backfill migration so existing seeded materials receive a default `color_hex` derived from their `visual_key`.
- Updated `StoreMaterialRequest`/`UpdateMaterialRequest` to validate `color_hex` and `texture` (JPEG/PNG/WebP, max 5 MB).
- Updated `MaterialController` to store uploaded textures on the `public` disk under `textures/`, replace textures safely, and remove old files only after a successful update.
- Added a `texture_url` accessor to the `Material` model so every material API response includes a public URL.
- Updated `FurnitureModelController::show` to expose `color_hex` and `texture_url` in the list of compatible materials.
- Updated `MaterialFormModal` with:
  - A color picker synchronized with a hex text input.
  - Texture file upload, preview, replace, and remove controls.
  - FormData submission so images upload correctly.
- Updated `frontend/src/api/client.js` to avoid setting `Content-Type: application/json` when the body is a `FormData`.
- Replaced the configurator page layout:
  - Main workspace: model selector bar, 3D preview, dimension controls directly underneath.
  - Side panel: material selectors, quantity, quote panel (material requirements + component breakdown), and Place Production Order.
  - Responsive stacking on narrow screens.
- Rewrote `visualMaterials.js`:
  - `getMaterialProps` now prefers `color_hex`, then `visual_key`, then material group fallback.
  - Added `usePartMaterial(material, repeatScale)` hook that loads textures asynchronously, caches them, repeats them based on part size, and disposes per-part materials on change/unmount.
- Updated `TableModel` and `BedsideModel` to use `usePartMaterial` with size-appropriate repeat scales.
- Added Vitest + `@testing-library/react` + jsdom and frontend tests for color/texture controls and `getMaterialProps`.

## Consumption formulas and assumptions

| Model | Part | Formula | Notes |
|---|---|---|---|
| Table | Top | `panel_area_m2` | Full length × width footprint, times waste factor. Thickness ignored. |
| Table | Legs | `solid_volume_m3` | Four rectangular prisms of full table height × profile width × profile thickness, times waste factor. Top thickness is not subtracted. |
| Bedside | Carcass | `cabinet_carcass_m3` | Simplified five-panel box: two sides, top/bottom, back. No shelves, drawers, or internal divisions. |
| Bedside | Door | `panel_area_m2` | Full front-face area (length × height), times waste factor. Gaps and hardware ignored. |
| Bedside | Handle | `fixed_each` | Configured count per furniture unit. |
| Bedside | Feet | `solid_volume_m3` | Four rectangular feet of fixed `foot_height_mm` (60 mm) × profile dims, times waste factor. |

All calculations use BCMath decimal arithmetic. Unlike units are never converted.

## API endpoints

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/v1/dashboard` | Inventory summary |
| `GET` | `/api/v1/materials` | List/search materials |
| `POST` | `/api/v1/materials` | Create a material |
| `GET` | `/api/v1/materials/{material}` | Material details |
| `PUT/PATCH` | `/api/v1/materials/{material}` | Update material details |
| `POST` | `/api/v1/materials/{material}/adjust` | Adjust stock |
| `GET` | `/api/v1/materials/{material}/movements` | Movement history |
| `GET` | `/api/v1/furniture-models` | List furniture models |
| `GET` | `/api/v1/furniture-models/{slug}` | Model detail with parts + compatible materials |
| `POST` | `/api/v1/production/quote` | Read-only consumption and availability quote |
| `POST` | `/api/v1/production-orders/confirm` | Confirm production order and deduct stock |

### Example quote request

```bash
curl -X POST http://127.0.0.1:8000/api/v1/production/quote \
  -H "Content-Type: application/json" \
  -d '{
    "furniture_model_slug": "table",
    "length_mm": 1200,
    "width_mm": 700,
    "height_mm": 740,
    "quantity": 2,
    "parts": [
      {"code": "top", "material_id": 1},
      {"code": "legs", "material_id": 3}
    ]
  }'
```

Response includes the validated configuration, component-level consumption, aggregated material requirements, available stock, and an overall `is_sufficient` flag.

### Example confirmation request

```bash
curl -X POST http://127.0.0.1:8000/api/v1/production-orders/confirm \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{
    "furniture_model_slug": "table",
    "length_mm": 1200,
    "width_mm": 700,
    "height_mm": 740,
    "quantity": 1,
    "parts": [
      {"code": "top", "material_id": 1},
      {"code": "legs", "material_id": 3}
    ]
  }'
```

- `201 Created` is returned for a new order.
- `200 OK` is returned when the same key + payload is replayed.
- `409 Conflict` is returned when the same key is reused with a different payload.
- `422 Unprocessable Content` is returned for invalid input or insufficient stock.
- The idempotency key is required in the `Idempotency-Key` header.

## Known environment notes

- The local dev database is SQLite because no MySQL server was available in this environment.
- Migrations were reordered so foreign-key references are MySQL-safe when you switch to MySQL in production.
- The `database/database.sqlite` file is ignored by Git; regenerate it locally with `php artisan migrate --seed`.
- `php artisan serve` is blocked by a Windows environment-block size error, but `php -S` works as a workaround.
- Browser-level 3D interaction was not verified with automated tools; only the build, lint, API responses, and static bundle contents were checked.
- Texture URLs are generated from `APP_URL`. For local development `APP_URL` should point at the API host (e.g. `http://127.0.0.1:8000`). On first setup run `php artisan storage:link` so `public/storage` serves `storage/app/public`.

## Remaining work / limitations

- Concurrency safety under MySQL/InnoDB has not been verified. SQLite tests pass, but true row-locking and unique-constraint race behaviour must be validated against MySQL with concurrent requests.
- No production-order listing, cancellation, or status transitions beyond `confirmed`.
- No notification or reorder workflow when confirmation drives a material below its reorder level.
- End-to-end browser tests for the full inventory → configure → quote → confirm → inspect balances flow are not implemented.
- Wood texture repeat scaling is a simple heuristic; it is not a physically accurate UV mapping.
- Full visual verification of the new layout and textured materials in a real browser was not performed.
