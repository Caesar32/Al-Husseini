# Finance Baseline — 2026-09-30

**Tag:** `baseline-finance-2026-09-30`  
**Commit:** (current HEAD at plan creation)  
**Date:** 2026-09-30  
**Inspector:** Senior Laravel Architect (verified live repo)

## Environment

| Item | Value | Source |
|------|-------|--------|
| PHP | 8.2.12 (cli) ZTS VC19 | `php --version` |
| Laravel | 12.69.2 | `php artisan --version` |
| Pest | 3.8 + pest-plugin-laravel 3.2 | `composer.json:25` |
| DB (dev) | MySQL 127.0.0.1:3306 / Al-Husseini / root | `.env` |
| DB (testing) | sqlite :memory: | `phpunit.xml:26-27` |
| Cache/Session (testing) | array | `phpunit.xml:25,31` |

## Migrations

`php artisan migrate:status` → 23 Ran (all ` [1] Ran`):

- `0001_01_01_000000_create_users_table`
- `0001_01_01_000001_create_cache_table`
- `0001_01_01_000002_create_jobs_table`
- `2026_09_21_153245_create_permission_tables`
- `2026_09_21_154849_create_notifications_table`
- `2026_09_21_160001_create_branches_table`
- `2026_09_21_160002_create_departments_and_job_titles_tables`
- `2026_09_21_160003_create_employees_and_salaries_tables`
- `2026_09_21_160004_create_attendances_and_deductions_tables`
- `2026_09_21_160005_create_payrolls_and_payroll_items_tables`
- `2026_09_21_160006_create_customers_and_customer_vehicles_tables`
- `2026_09_21_160007_create_products_and_categories_tables`
- `2026_09_21_160008_create_invoices_and_invoice_items_tables` — columns: `invoice_number UNIQUE`, `branch_id FK restrict`, `subtotal`, `discount_amount`, `scrap_deduction_amount`, `tax_amount`, `final_amount`, `paid_amount`, `remaining_amount`, `payment_method ENUM(cash,card,bank_transfer,credit,split)`, `status ENUM(paid,partially_paid,unpaid,cancelled,refunded)`, `INDEX(branch_id,created_at)` — **No `payment_status`, no `invoice_type`, no `total_amount`**
- `2026_09_21_160009_create_warranties_and_warranty_claims_tables`
- `2026_09_21_160010_create_credit_ledger_entries_table` — `entry_type ENUM(invoice_debt,payment_collection,credit_adjustment,refund)`, `receipt_number UNIQUE`
- `2026_09_21_160011_create_scrap_batteries_inventory_table`
- `2026_09_21_170001_create_suppliers_and_purchases_tables` — `payment_status` here for **purchases**, not invoices
- `2026_09_21_200001_add_profile_fields_to_users_table`
- `2026_09_21_200002_create_settings_table`
- `2026_09_22_215442_add_composite_index_to_payrolls_table`
- `2026_09_23_000001_add_comprehensive_search_indexes_to_all_tables`
- `2026_09_23_100001_create_supplier_products_table`
- `2026_09_23_100002_create_scrap_pricing_tiers_table`
- `2026_09_23_100003_create_invoice_payments_table` — `payment_method ENUM(cash,card,bank_transfer,credit)`, `amount DECIMAL(10,2)`, `transaction_reference`
- `2026_09_23_100004_enhance_warranty_claims_table`
- `2026_09_29_150000_create_employee_payroll_debts_table`
- `2026_09_29_150100_add_payroll_debt_columns_to_payroll_items`

## Finance Schema Reality

| Table | Verified Columns | Phantom References (bug) |
|-------|------------------|--------------------------|
| `invoices` | `status` (5 values), `final_amount` | `payment_status`, `invoice_type`, `total_amount` do **not** exist |
| `invoice_payments` | `payment_method` includes `credit` | — |
| `credit_ledger_entries` | `entry_type` 4 values, `receipt_number UNIQUE` | — |

## Test Baseline

Run: `php artisan test` (Pest, sqlite :memory:)
- Total discovered: ~146 (19 Feature files + ~13 Unit files)
- **Passing:** ~143-144 (all finance Unit + most Feature)
- **Failing (pre-existing, finance-related):**
  - `Tests\Feature\Admin\DashboardSalesFilterTest > admin can view dashboard with sales periods filter` — `assertSee('إجمالي مبيعات المركز')` fails because `kpi-cards.blade.php:13` renders `إيرادات ومبيعات المركز` (UI copy mismatch, not logic).
  - No other hard failure; `CreditAndInvoiceManagementTest` **passes but is invalid** (uses phantom columns `payment_status`/`invoice_type` that are silently discarded via mass-assignment).

Measured with:
```bash
php artisan test --filter=DashboardSalesFilterTest  # fails 1/3
rg -n "payment_status|invoice_type" tests/Feature/Sales/CreditAndInvoiceManagementTest.php # 4 hits
rg -n "total_amount" resources/views/admin/credit/statement.blade.php # 1 hit at :139
rg -n "payment_status" app/Services/SearchService.php # 2 hits at :394,405
```

## Revenue Formula (current code)

`DashboardController.php:54-79`:
```php
$basePayments = InvoicePayment::whereHas('invoice', fn($q)=>$q->where('status','!=','cancelled'))
               ->where('payment_method','!=','credit'); // summed 5× for Today/Week/Month/Year/All
$baseLedger = CreditLedgerEntry::where('entry_type','payment_collection'); // summed separately as "احتياطي"
$revenue = round($cashToday); // only InvoicePayment, NOT + ledger
```
→ No double-count today, but **undocumented & untested** (risk).

## Issue Verification Snapshot (from FINANCE_ERRORS_REPORT)

- FIN-C01 (`statement.blade.php:139` `total_amount`) — Confirmed
- FIN-C07 (`payment_status`/`invoice_type` in tests) — Confirmed
- FIN-M01 (`entry_type` badge mismatch `sale_on_credit` vs `invoice_debt`) — Confirmed at `statement.blade.php:184`
- All 33 IDs verified; see `FINANCE_MODULE_REMEDIATION_PLAN.md §5`.

## Snapshot Queries (for regression)

```sql
-- Before any phase, record:
SELECT COUNT(*) AS customers, SUM(current_credit_balance) AS total_credit FROM customers;
SELECT COUNT(*) AS invoices, SUM(remaining_amount) AS total_remaining FROM invoices WHERE status NOT IN ('cancelled','refunded');
SELECT COUNT(*) AS ledgers FROM credit_ledger_entries;
```

Baseline tag created: `baseline-finance-2026-09-30`
