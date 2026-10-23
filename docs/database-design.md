# Database Design

## Database conventions

Use MySQL with InnoDB, foreign keys, and indexed lookup fields. Entered dimensions are integer millimetres. Inventory and calculated consumption use `DECIMAL(18,6)` quantities; do not use binary floating-point values for stock decisions. Store one canonical stock unit per material: `m2`, `m3`, `m`, or `ea`.

The current balance on `materials` supports efficient dashboard reads. `inventory_movements` is the audit trail. Every operation that changes a balance must update the balance and add its movement in the same transaction. A reconciliation query/test should verify that opening movements plus later movements equal each current balance.

## Tables

### `materials`

| Column | Type / constraints | Meaning |
|---|---|---|
| `id` | primary key | Material identifier |
| `name` | string, required | Display name |
| `sku` | string, nullable, unique | Optional stock code |
| `material_group` | string/enum, required | Compatibility group such as wood, ceramic, metal, or upholstery |
| `stock_unit` | enum/string, required | Canonical unit: `m2`, `m3`, `m`, or `ea` |
| `quantity_on_hand` | `DECIMAL(18,6)`, default 0, check >= 0 | Current material balance |
| `reorder_level` | `DECIMAL(18,6)`, nullable | Low-stock threshold in `stock_unit` |
| `visual_key` | string, nullable | Frontend color/texture mapping, not a calculation input |
| `is_active` | boolean, default true | Whether the material can be selected |
| timestamps | | Creation/update time |

Opening stock provided during creation must create an opening movement in the same transaction. Do not allow a stock-unit change after a material has movements unless a deliberate migration/conversion workflow is introduced.

### `furniture_models`

| Column | Type / constraints | Meaning |
|---|---|---|
| `id` | primary key | Model identifier |
| `slug` | string, unique | Stable API/configurator key |
| `name` | string | Display name, e.g. table or bedside cabinet |
| `model_asset_path` | string, nullable | Optional GLB asset location; parametric models may not need one |
| `is_active` | boolean | Whether the model can be produced |
| timestamps | | |

### `model_parts`

| Column | Type / constraints | Meaning |
|---|---|---|
| `id` | primary key | Part identifier |
| `furniture_model_id` | foreign key → `furniture_models.id` | Owning model |
| `code` | string, unique per model | Stable part key such as `top` or `legs` |
| `name` | string | Display name |
| `material_group` | string/enum | Required material compatibility group |
| `consumption_unit` | string/enum | Unit produced by the selected formula |
| `formula_key` | string/enum | Server-supported calculation key |
| `formula_parameters` | JSON | Fixed dimensions/counts/waste factors used by the formula |
| timestamps | | |

Model-part formula keys are a controlled backend vocabulary, not arbitrary expressions submitted by clients. The calculated unit must equal the selected material's `stock_unit`, and the material's group must match the part.

### `production_orders`

| Column | Type / constraints | Meaning |
|---|---|---|
| `id` | primary key | Confirmed production order |
| `furniture_model_id` | foreign key → `furniture_models.id` | Produced model |
| `length_mm`, `width_mm`, `height_mm` | unsigned integers | Validated dimensions in millimetres |
| `quantity` | positive integer | Number of furniture units |
| `configuration_snapshot` | JSON | Per-part material selection and submitted configuration at confirmation |
| `status` | enum/string | MVP status is `confirmed` |
| `idempotency_key` | string, unique | Prevents duplicate confirmation |
| `confirmed_at` | timestamp | Confirmation time |
| timestamps | | |

Store dimension and selection snapshots so future model/recipe edits do not rewrite the historical order configuration.

### `production_consumptions`

| Column | Type / constraints | Meaning |
|---|---|---|
| `id` | primary key | Consumption row |
| `production_order_id` | foreign key → `production_orders.id` | Confirmed order |
| `model_part_id` | foreign key → `model_parts.id` | Consuming part |
| `material_id` | foreign key → `materials.id` | Selected material |
| `quantity_consumed` | `DECIMAL(18,6)`, positive | Quantity deducted for this part/order |
| `stock_unit` | string | Unit snapshot at consumption time |
| timestamps | | |

### `inventory_movements`

| Column | Type / constraints | Meaning |
|---|---|---|
| `id` | primary key | Movement identifier |
| `material_id` | foreign key → `materials.id` | Affected material |
| `movement_type` | enum/string | `opening`, `manual_adjustment`, or `production` for MVP |
| `quantity_delta` | `DECIMAL(18,6)`, non-zero | Signed change in the material's canonical unit |
| `balance_after` | `DECIMAL(18,6)` | Balance immediately after this movement |
| `production_order_id` | nullable foreign key → `production_orders.id` | Set for production deductions |
| `reason` | nullable text | Required explanation for manual adjustments |
| `created_at` | timestamp | Audit time |

Add an index on `(material_id, created_at)` and useful filter indexes such as `movement_type`. A production movement has a negative delta and a production-order reference; opening and manual adjustments can be positive or negative, subject to the non-negative-balance rule.

## Relationships

```text
furniture_models 1 ─── * model_parts
furniture_models 1 ─── * production_orders
production_orders 1 ─── * production_consumptions
model_parts 1 ─── * production_consumptions
materials 1 ─── * production_consumptions
materials 1 ─── * inventory_movements
production_orders 1 ─── * inventory_movements (production movements only)
```

Compatibility is determined by a part's `material_group` and `consumption_unit` versus a material's group and canonical stock unit. This avoids requiring a many-to-many allowed-material table for the MVP; the API can filter active materials by those attributes.

## Dimension-based consumption and units

Dimensions are submitted/stored as integers in millimetres. The backend implements a small set of version-controlled formula keys:

| Formula key | Example calculation | Output unit |
|---|---|---|
| `panel_area_m2` | `length_mm × width_mm ÷ 1,000,000` | `m2` |
| `solid_volume_m3` | `length_mm × width_mm × thickness_mm ÷ 1,000,000,000` | `m3` |
| `linear_length_m` | `length_mm ÷ 1,000` | `m` |
| `fixed_each` | Configured count per furniture unit | `ea` |

Formula parameters can supply fixed thickness, leg count, or a waste factor. Apply the requested furniture quantity after calculating per-unit requirements. For example, a ceramic tabletop can consume area in `m2`; wooden legs can consume solid volume in `m3`. Validate that the formula output and material stock unit match exactly; do not silently convert sheets, square feet, or other unlike units in the MVP.

Round consumption upward to six decimal places to avoid understating material needs. Use BCMath or integer-scaled arithmetic in Laravel/PHP, and treat decimal values as strings at API boundaries where appropriate. Validate dimension bounds before multiplication to prevent nonsensical inputs and integer overflow. The quote endpoint and confirmation service must use the same backend calculation service.

## Atomic confirmation and concurrency

For `POST /api/v1/production-orders/confirm`:

1. Validate the model, dimensions, quantity, part codes, active materials, material groups, and matching units.
2. Calculate material requirements on the server and aggregate quantities by material ID.
3. Start a MySQL/InnoDB transaction and resolve an existing idempotency key.
4. Lock all required material rows with `SELECT ... FOR UPDATE` in ascending material-ID order.
5. Compare each locked balance with its total requirement. If any is insufficient, roll back and return `422`; do not create order, consumption, movement, or balance changes.
6. Create the confirmed order and configuration snapshot; create consumption records; decrement each material balance; and write one negative movement per material with its resulting balance.
7. Commit only after every write succeeds.

Manual adjustments follow the same transaction/row-lock discipline and cannot result in a negative balance. A unique idempotency key makes retrying a successful request safe. If a key already belongs to a different payload, return `409 Conflict`. Locking rows in a consistent order reduces deadlocks; the service can retry a transaction on a recognized deadlock a limited number of times.

All balance mutations must go through these services. Never directly patch `quantity_on_hand` through a generic material update endpoint.
