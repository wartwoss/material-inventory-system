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
```

Current results:

- `MaterialApiTest`: 13 tests, 41 assertions — OK
- `DashboardApiTest`: 1 test, 12 assertions — OK
- `FurnitureModelApiTest`: 4 tests, 17 assertions — OK

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

`npm run build` completes successfully. `npm run lint` reports a small number of `set-state-in-effect` warnings in data-fetching effects; there are 0 lint errors.

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

## Known environment notes

- The local dev database is SQLite because no MySQL server was available in this environment.
- Migrations were reordered so foreign-key references are MySQL-safe when you switch to MySQL in production.
- The `database/database.sqlite` file is ignored by Git; regenerate it locally with `php artisan migrate --seed`.
- `php artisan serve` is blocked by a Windows environment-block size error, but `php -S` works as a workaround.
- Browser-level 3D interaction was not verified with automated tools; only the build, lint, API responses, and static bundle contents were checked.

## Remaining work before Stage 3B / Stage 4

- Implement `POST /api/v1/production/quote` to calculate per-material consumption using the server-side formula keys and parameters.
- Implement `POST /api/v1/production-orders/confirm` with idempotency-key handling and atomic stock deduction.
- Wire the quote result into the configurator UI (consumption estimate, stock-availability feedback).
- Add feature tests for production quotes and confirmations, including insufficient-stock rollback.
- Add end-to-end tests for the full inventory → configure → quote → confirm → inspect balances flow.
