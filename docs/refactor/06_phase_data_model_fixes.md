# Phase 6: Data model fixes (DB-04 relation cardinality, DB-03 redundant indexes)

Status tracker: `REFACTOR_PLAN.md`. Requires Phases 2 and 4. Next phase: `07_phase_final_verification.md`.

## Rules
- Laravel 12, PHP 8.2, Pest. Tests run on SQLite `:memory:` with FK/CHECK enforced; never disable it. Dev DB is MySQL.
- Never weaken, skip or delete tests. Every migration needs a working `down()` and must run on both SQLite and MySQL.
- Never run migrations or write data on dev or production MySQL. The owner does that.
- Don't commit or push unless the owner asks.

## Objective
Make the declared Eloquent relations match the real cardinality, and drop indexes that duplicate a unique index on the same columns.

SOLID:
- LSP: a `hasOne` over rows that are many by design quietly returns an arbitrary single row, so it breaks the contract it declares.

## Context (verified)
- Both relations are many by design:
  - **Scrap:** one sale writes `scrap_count` rows in a loop (`app/Services/Sales/PosOrderService.php:410-420`), all with the same `invoice_id`.
  - **Commissions:** `technician_commissions` is unique on `(employee_id, invoice_id)`, so one invoice can hold one commission per technician.
- So both become `hasMany`. A unique index on `invoice_id` would break checkout.
- Relations: `app/Models/Invoice.php:104` `scrapBattery(): HasOne` and `:114` `technicianCommission(): HasOne`.
- Every reader (`grep`, verified):

| Reader | Use |
|---|---|
| `app/Http/Controllers/Admin/PosController.php:208` | eager load `scrapBattery` (receipt/warranty) |
| `app/Http/Controllers/Admin/SalesInvoiceController.php:60-61` | eager load `scrapBattery`, `technicianCommission.employee` |
| `app/Http/Controllers/Api/V1/Owner/SalesController.php:65` | eager load `scrapBattery` |
| `app/Http/Resources/Owner/InvoiceDetailResource.php:94-98` | `scrap_battery` object |
| `resources/views/admin/invoices/show.blade.php:184-193` | `$invoice->scrapBattery` / `->capacity_ah` |
| Any view that reads `technicianCommission` | grep again at execution time |

- DB-03, defined in `docs/ai/MASTER_REMEDIATION_PLAN.md:461`, covers two kinds of redundant index:
  - The duplicate payroll unique index: `2026_09_22_215442` adds `payrolls_branch_year_month_unique` on top of the default `payrolls_branch_id_year_month_unique` from `2026_09_21_160005:23`.
  - `->unique()->index()` columns, where the plain index duplicates the unique one:

| Table | Column | Redundant index (Laravel default name) | Kept |
|---|---|---|---|
| `customers` | `phone` | `customers_phone_index` | `customers_phone_unique` |
| `products` | `sku` | `products_sku_index` | `products_sku_unique` |
| `products` | `barcode` | `products_barcode_index` | `products_barcode_unique` |
| `invoices` | `invoice_number` | `invoices_invoice_number_index` | `invoices_invoice_number_unique` |
| `warranties` | `serial_number` | `warranties_serial_number_index` | `warranties_serial_number_unique` |
| `purchase_invoices` | `invoice_number` | `purchase_invoices_invoice_number_index` | `purchase_invoices_invoice_number_unique` |
| `payrolls` | `branch_id, year, month` | `payrolls_branch_year_month_unique` | `payrolls_branch_id_year_month_unique` |

- `docs/ai/database.md:257-262` says SQLite FK enforcement is off. That is stale: the remediation turned enforcement on. Don't rely on that note.

## Step 0: Index name check (orchestrator, read-only)
Confirm that each name in the table exists on both drivers:
- SQLite: `PRAGMA index_list('<table>')` after `php artisan migrate:fresh`.
- Dev MySQL: `SHOW INDEX FROM <table>` (read-only).

Drop a row from the plan if the name differs on either driver. Record the result in `REFACTOR_PLAN.md`.

## Codex prompts
```text
[TASK: Phase 6 - Step 1 (DB-04)]
ROLE: Strict Code Executor.
ACTION: Correct relation cardinality (LSP): both relations to hasMany.
TARGET_FILE: `app/Models/Invoice.php:104-117`, `app/Http/Controllers/Admin/PosController.php:208`, `app/Http/Controllers/Admin/SalesInvoiceController.php:60-61`, `app/Http/Controllers/Api/V1/Owner/SalesController.php:65`, `app/Http/Resources/Owner/InvoiceDetailResource.php:94-98`, `resources/views/admin/invoices/show.blade.php:184-193`, plus any further hit of `grep -rn "scrapBattery\|technicianCommission" app resources`
CHANGES_REQUIRED:
1. Invoice: replace scrapBattery(): HasOne with scrapBatteries(): HasMany; technicianCommission(): HasOne with technicianCommissions(): HasMany. Update imports.
2. Eager loads: 'scrapBattery' -> 'scrapBatteries'; 'technicianCommission.employee' -> 'technicianCommissions.employee'.
3. InvoiceDetailResource: add 'scrap_batteries' => list of {capacity_ah, scrap_value (float), lead_weight_kg (float), status}; keep 'scrap_battery' = first item or null (backward compatibility for the mobile app; add comment "deprecated: remove after the Flutter app reads scrap_batteries").
4. invoices/show.blade.php: condition `$invoice->scrapBatteries->isNotEmpty() || scrap_deduction_amount > 0`; render count and each capacity_ah (escaped {{ }}).
5. Any technicianCommission view usage: iterate the collection.
6. Tests: invoice with scrap_count=2 via POS checkout -> GET owner API invoice detail returns 2 items in scrap_batteries and scrap_battery not null; admin invoice show page lists both; invoice with 2 technician commissions -> SalesInvoiceController show renders both employees.
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Sales tests/Feature/Api/Owner`.
```
```text
[TASK: Phase 6 - Step 2 (DB-03)]
ROLE: Strict Code Executor.
ACTION: Drop indexes that duplicate a unique index on the same columns.
TARGET_FILE: new `database/migrations/2026_10_16_000002_drop_redundant_indexes.php`
CHANGES_REQUIRED:
1. up(): for each (table, index) row in the DB-03 table of docs/refactor/06_phase_data_model_fixes.md that survived Step 0: if (Schema::hasIndex($table, $index)) Schema::table($table, fn ($t) => $t->dropIndex($index)) — use dropUnique for payrolls_branch_year_month_unique.
2. down(): recreate each with original name: $t->index($column, $index) / $t->unique(['branch_id','year','month'], 'payrolls_branch_year_month_unique'), each guarded by !Schema::hasIndex.
3. Top-of-file comment listing each dropped index and the unique index that covers it.
4. Test tests/Feature/Admin/RedundantIndexMigrationTest.php: after migrate, Schema::hasIndex false for each dropped name and true for each kept unique.
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact` and on SQLite `php artisan migrate:rollback --step=1 && php artisan migrate`.
```

## Acceptance criteria
- [ ] The Step 0 index names are confirmed on SQLite and dev MySQL and recorded.
- [ ] Steps 1-2 are applied and each verification command passes.
- [ ] `grep -rn "scrapBattery\b\|technicianCommission\b" app resources` returns nothing; only the plural names remain.
- [ ] On SQLite, all migrations added in Phases 2 and 6 roll back and re-migrate cleanly. Use `php artisan migrate:rollback --step=2 && php artisan migrate` after both exist. `PRAGMA foreign_key_check` returns nothing.
- [ ] The full suite shows 0 failed and 0 skipped.
- [ ] The owner is told that the Flutter app should move to `scrap_batteries`, after which `scrap_battery` can be removed.
- [ ] The owner has approved running `php artisan migrate` on dev MySQL. Do not run it yourself.
- [ ] Record the passed count as `N6`.
