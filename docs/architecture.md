# Furniture Workshop Inventory and Production System

## Purpose and MVP scope

This document describes a full-stack interview-assignment MVP for a single furniture workshop. The system supports inventory tracking and production confirmation for two configurable models: a table and a bedside cabinet.

The MVP includes:

- An inventory dashboard with material balances, low-stock indicators, and recent stock movements.
- Material creation, optional opening stock, and reasoned manual quantity adjustments.
- A furniture configurator with validated dimensions and independent material choices for each configurable part.
- Dimension-based consumption estimates and stock-availability feedback.
- Server-validated production confirmation that records an order and deducts materials atomically.
- Auditable inventory transaction history and validation errors for insufficient stock.

Authentication, multiple warehouses, purchasing, supplier management, pricing, stock reservation, and work-in-progress tracking are out of scope for the MVP.

## Assumptions

- The phrase “type of food” in the original requirements means type of wood or other material; selections are made independently per furniture part.
- Dimensions are entered and stored as integer millimetres.
- Each material has one canonical stock unit. The MVP supports square metres (`m2`), cubic metres (`m3`), metres (`m`), each (`ea`), and kilograms (`kg`). Conversions between unlike units are out of scope.
- Production confirmation immediately consumes stock. It does not reserve stock or model later manufacturing stages.
- Consumption is a server-defined estimate based on a small set of formulas and recipe parameters, not a manufacturing-grade cut-list optimizer.

## Technology and system boundaries

- **Frontend:** React and Vite, written in JavaScript.
- **3D configurator:** React Three Fiber, Drei, and Three.js.
- **Backend:** Laravel REST API, authoritative for validation, formulas, inventory balances, and production confirmation.
- **Database:** MySQL with InnoDB tables, foreign keys, decimal stock balances, and row-level locking.

The browser owns transient interaction state: dimensions, selected part materials, and the current view. It calls the API for quotes and submits the selected configuration for confirmation. The backend revalidates and recalculates all requirements; client calculations are never trusted for stock changes.

## Suggested project structure

```text
furniture-workshop/
├── frontend/
│   ├── public/models/
│   └── src/
│       ├── app/                 # routing and application shell
│       ├── features/
│       │   ├── inventory/      # dashboard, material forms, movement history
│       │   ├── configurator/   # dimensions, part selections, quotes
│       │   └── production/     # confirmation and results
│       ├── components/         # shared UI
│       ├── three/
│       │   ├── models/         # table and bedside model components
│       │   └── materials/      # visual material mapping
│       └── api/                # Laravel API client
└── backend/
    ├── app/
    │   ├── Http/Controllers/Api/V1/
    │   ├── Http/Requests/
    │   ├── Models/
    │   └── Services/           # consumption and stock deduction
    ├── database/{migrations,seeders}/
    ├── routes/api.php
    └── tests/Feature/
```

Keep frontend and backend independently buildable. Put business invariants in Laravel services and validate request shape in Form Requests.

## Main interactions

1. The dashboard loads current material balances, low-stock materials, and recent inventory movements.
2. The configurator loads furniture models, their configurable parts, dimension constraints, and compatible material choices.
3. Dimension or part-material changes request a debounced quote from the API. The response reports consumption by material and whether stock is sufficient.
4. On confirmation, the frontend sends the model, dimensions, quantity, and per-part material IDs with an `Idempotency-Key` header.
5. Laravel recalculates requirements and performs the order creation, stock changes, and movement recording in one MySQL transaction. The API returns the confirmed order or a validation/insufficient-stock error.

## REST API surface

All routes are JSON under `/api/v1`.

| Method and path | Responsibility |
|---|---|
| `GET /dashboard` | Summary, low-stock materials, recent movements |
| `GET /materials` | Search and list materials and balances |
| `POST /materials` | Create a material and optionally its opening-stock movement |
| `GET /materials/{id}` | Material details |
| `POST /materials/{id}/adjustments` | Apply a signed adjustment with a reason |
| `GET /inventory-movements` | Paginated/filterable movement history |
| `GET /furniture-models` | Models, parts, constraints, and allowed material choices |
| `POST /production/quote` | Validate configuration and calculate consumption/availability |
| `POST /production-orders/confirm` | Atomically create a confirmed order and consume stock |
| `GET /production-orders/{id}` | Confirmed order and its consumption snapshot |

Return `422 Unprocessable Entity` for invalid configuration or insufficient stock, including material ID/name, required quantity, available quantity, and unit. A reused idempotency key with a different request returns `409 Conflict`.

## 3D configurator approach

Use React state for dimensions and a mapping of `partCode → materialId`. Load compatible choices from the backend and keep the visual appearance mapping separate from inventory calculations: a material can expose a `visual_key`, color, or texture reference.

For the table and bedside cabinet, parametric R3F components are preferred for major parts such as tabletops, legs, sides, shelves, and doors. This keeps geometry changes predictable and allows each part to have an independent material. If GLB assets are used, export configurable parts as separately named meshes, validate node names, and clone shared materials where independent tinting is required. Avoid scaling the whole model in ways that distort its geometry.

Keep model rendering, camera controls, lighting, loading state, and configuration controls as separate components. Debounce quote requests during slider changes. Display quote availability, but always rely on the confirmation response for the final decision.

## Reference repository notes

The inspected configurator repositories demonstrate useful ideas: named per-part configuration and material cloning (`gorhorvat/product-configurator-3d`), named GLB meshes and dimension-driven transforms (`KieArm/configurator-table`), and model-specific options plus shared configuration state (`meesakveld/my-little-configurator`). Their dependency versions differ: the archived table demo uses React 18/R3F 8/Three 0.149, while the newer examples use React 19/R3F 9. Choose a mutually compatible current set rather than copying old dependencies. TypeScript examples can inform JavaScript structure without changing the requested client language.

The Laravel/React `Eddaoust/Stock-App` provides conventional catalog CRUD patterns but its inspected implementation does not supply the production-consumption ledger or atomic deduction needed here. It targets Laravel 5.8/PHP 7.1 and React 16/Laravel Mix, so it should not set this project's dependency baseline.

License metadata for all four repositories is absent. The first configurator README states MIT, but its GitHub metadata is null and the repository tree has no `LICENSE` file. Verify permission before reusing its code; do not assume the other repositories' source code or model assets are licensed for reuse. Prefer independently implementing the patterns described above.

## Reliability principles

- Material balances and movements are changed together in a database transaction.
- Confirmations lock affected material rows in a consistent ascending-ID order.
- Aggregate multiple part requirements for the same material before checking stock.
- Use a unique idempotency key to make repeated confirmation requests safe.
- Keep stock quantities in decimal-safe representations and reject negative balances.
- Never accept client-provided consumption amounts as authoritative.
