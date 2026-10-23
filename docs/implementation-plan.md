# Implementation Plan

This plan delivers the interview-assignment MVP described in `architecture.md` and `database-design.md`. Implement the React/Vite JavaScript frontend and Laravel REST API in small increments; keep MySQL balances and movements consistent throughout. Do not add authentication, purchasing, multiple warehouses, reservations, or advanced cut-list optimization to the MVP.

## 1. Bootstrap the application and database

- Create the Laravel API and MySQL connection with InnoDB migrations.
- Create the React + Vite JavaScript application and a small API client.
- Establish `/api/v1` JSON error conventions and frontend environment configuration.
- Add migrations for materials, furniture models, model parts, production orders, production consumptions, and inventory movements.
- Seed table and bedside models, formula definitions/parameters, and sample compatible materials.

**Verify:** migrations run from an empty database; foreign keys, unique idempotency keys, non-negative balance constraints, decimal scales, and indexes are present; frontend development and production builds succeed.

## 2. Implement materials, adjustments, and dashboard data

- Add material listing/detail/create endpoints, including optional opening stock.
- Record opening quantities as movements in the same transaction as material creation.
- Add signed manual adjustments with a required reason and non-negative-balance validation.
- Add paginated/filterable movement history and a dashboard summary endpoint.
- Centralize all balance changes in a Laravel inventory service.

**Verify:** feature tests cover positive/negative adjustments, insufficient balance rejection, opening movements, required reason, and agreement between current balance and the movement ledger. Test low-stock summary and movement filtering.

## 3. Implement authoritative consumption quotes

- Add model/part/compatible-material API data with dimension constraints.
- Implement the approved formula keys using integer millimetre dimensions and decimal-safe arithmetic.
- Validate unit and material-group compatibility; apply quantity and configured waste factors; round consumption upward to six decimal places.
- Implement `POST /api/v1/production/quote` to return calculated requirements and available stock without changing inventory.

**Verify:** unit tests cover each formula, expected unit, per-unit and multi-unit quantities, waste-factor behavior, rounding, min/max dimensions, and incompatible material choices. Confirm quote results report insufficient balances without side effects.

## 4. Build inventory frontend flows

- Build the inventory dashboard, materials table, low-stock indicators, material creation/opening-stock form, and adjustment form.
- Build recent and paginated movement history with material/type filters.
- Display Laravel validation and insufficient-stock errors clearly.

**Verify:** manually create a material, adjust it in both directions, and verify the dashboard/history reflect the updated balance. Confirm an adjustment that would go below zero leaves data unchanged.

## 5. Build the furniture configurator and 3D models

- Build table and bedside model selection, bounded dimension controls, and per-part material selectors.
- Implement parametric React Three Fiber models for major parts, or load GLB assets with separately named configurable meshes.
- Use Drei for model loading and camera helpers; use independent per-part materials and keep visual `visual_key` mapping separate from inventory formulas.
- Debounce quote requests while dimensions/material selections change and display consumption by material.

**Verify:** each dimension updates the intended geometry and quote; selecting a material for one part does not change another part; incompatible materials are unavailable or rejected; loading and error states work.

## 6. Implement atomic production confirmation

- Implement `POST /api/v1/production-orders/confirm` to repeat all server-side validation and calculations.
- Require an `Idempotency-Key`; store a configuration snapshot and consumption rows.
- Aggregate needs by material, lock required material rows in ascending ID order, check every balance, then update balances and movements in one transaction.
- Return a useful insufficient-stock response without partial writes; add an order-detail endpoint for the confirmation result.

**Verify:** feature tests cover successful deduction, insufficient-stock rollback across multiple materials, repeated idempotent request, conflicting reuse of a key, multiple parts sharing a material, invalid units/groups, and transaction consistency. Add a concurrency test or integration test with competing confirmations to prove stock cannot go negative or be oversold.

## 7. End-to-end review and assignment readiness

- Connect quote and confirmation flows to the inventory history and balances.
- Test the full path: create/open stock → configure product → quote → confirm → inspect order/movements and resulting balances.
- Verify frontend production build, backend test suite, formatting/lint checks, and a fresh-database migration/seed process.
- Document local startup, sample data, API examples, and the simplified nature of consumption formulas.

**Completion criteria:** a reviewer can configure both supported furniture models, see dimension-derived material requirements, receive an accurate insufficient-stock error, confirm a producible configuration exactly once, and trace the resulting inventory deductions in history.
