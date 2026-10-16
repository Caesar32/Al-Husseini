# Phase 3: Shared dashboard metrics (ARC-06)

Status tracker: `REFACTOR_PLAN.md`. Requires Phase 1. It is independent of Phases 2 and 4 and can run in parallel with them. Next phase: `04_phase_split_pos_order_service.md`.

## Rules
- Laravel 12, PHP 8.2, Pest. Tests run on SQLite `:memory:` with FK/CHECK enforced; never disable it.
- Never weaken, skip or delete tests. Every displayed number must stay identical.
- Bind service interfaces in a `*ServiceProvider`. Don't commit or push unless the owner asks.

## Objective
Move KPI aggregation out of the two dashboard controllers into one service, and collapse the 7-day category trend from 21 queries to 1.

SOLID:
- SRP: controllers only shape the response.
- DIP: both controllers depend on the interface.
- ISP: small methods scoped by period.

## Context
- `app/Http/Controllers/Admin/DashboardController.php` (348 lines):
  - L46-58 invoice counts per period; L67-71 cash collected; L76-80 credit collected; L204 credit customers.
  - L210 low stock: `Product::whereColumn('current_stock','<=','reorder_threshold')`, which includes inactive products.
  - L214-220 category statistics.
  - L252-273 a loop of 7 days × 3 categories, i.e. 21 queries.
  - L303-305 attendance.
- `app/Http/Controllers/Api/V1/Owner/DashboardController.php` (275 lines): `live()` at L25-190 and `periods()` at L195+. Both are wrapped in `App\Support\OwnerPulseCache`.
  - L145 low stock: `Product::active()->lowStock()`, which excludes inactive products.
- **Period semantics differ and must be preserved:**
  - Admin "week" is the last 7 days including today (`Carbon::today()->subDays(6)`, L37). The owner "week" starts at `startOfWeek()`.
  - Admin cash is the `InvoicePayment` sum. Read the owner live/periods cash logic and keep it exactly as it is.
  - So the service takes explicit `$from`/`$to`, and each controller keeps computing its own period boundaries.
- **Drift:** the two low-stock definitions disagree. Which one is canonical is an owner decision, so keep both through `lowStockCount(bool $activeOnly)`.
- Shared building blocks: the `Invoice::countable()` scope, `InvoiceItem::NET_LINE_SQL`, and the local scopes `Product::scopeLowStock` / `scopeActive` (`app/Models/Product.php:86-94`, called as `->lowStock()` / `->active()`).

## Contract
These are the minimum shared methods. Step 2 moves every aggregate query out of both controllers: sales totals, counts, category statistics, recent invoices, top debtors, low-stock lists, returns and period payloads. Add one method per remaining metric, each with explicit `$from`/`$to`/`$branchId`. A metric whose meaning differs between the two surfaces gets two separately named methods (for example `cashCollectedFromPayments()` and `ownerSafeCash()`), never a flag that switches semantics.
```php
// app/Contracts/Dashboard/DashboardMetricsInterface.php
interface DashboardMetricsInterface {
    public function invoiceCount(?CarbonInterface $from, ?int $branchId = null): int;   // null $from = all time
    public function cashCollected(?CarbonInterface $from, ?int $branchId = null): float;
    public function creditCollected(?CarbonInterface $from, ?int $branchId = null): float;
    public function lowStockCount(bool $activeOnly): int;
    /** @return array<string, array{battery: float, oil: float, service: float}> keyed Y-m-d, zero-filled, ONE query */
    public function categoryTrend(CarbonInterface $from, CarbonInterface $to): array;
    /** @return array{present: int, late: int, absent: int} */
    public function attendanceToday(?int $branchId = null): array;
}
// app/Services/Dashboard/DashboardMetricsService.php implements it; bound in AppServiceProvider.
```

## Codex prompts
```text
[TASK: Phase 3 - Step 1]
ROLE: Strict Code Executor.
ACTION: Characterization tests only. No production change.
TARGET_FILE: new `tests/Feature/Admin/DashboardMetricsCharacterizationTest.php`
CHANGES_REQUIRED:
1. $this->travelTo fixed date (mid-week, mid-month). Seed via factories/models: invoices today, earlier this week, earlier this month, last year (incl. one partially_refunded and one non-countable status); invoice_payments; customer credit ledger payment_collection rows; products battery/oil/service incl. one inactive low-stock and one active low-stock; attendance present/late/absent today.
2. GET admin dashboard as super-admin: assertViewHas for every numeric KPI variable passed to the view (read the controller's compact/view call to list them) with exact expected values.
3. Sanctum::actingAs(superAdmin, ['owner:monitor']): GET /api/v1/owner/dashboard/live and /api/v1/owner/dashboard/periods?period=month; assert every numeric JSON field with exact values.
4. Compute expected values by hand from seeded rows, not by calling the controllers' logic.
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Admin/DashboardMetricsCharacterizationTest.php`.
```
```text
[TASK: Phase 3 - Step 2]
ROLE: Strict Code Executor.
ACTION: Extract DashboardMetricsService (SRP, DIP, ISP).
TARGET_FILE: `app/Http/Controllers/Admin/DashboardController.php`, `app/Http/Controllers/Api/V1/Owner/DashboardController.php`, new `app/Contracts/Dashboard/DashboardMetricsInterface.php`, new `app/Services/Dashboard/DashboardMetricsService.php`, `app/Providers/AppServiceProvider.php`
CHANGES_REQUIRED:
1. Create interface per "Contract" in docs/refactor/03_phase_shared_dashboard_metrics.md.
2. Move query logic from both controllers into the service; controllers inject the interface via constructor and only map results to view vars / JSON keys (names unchanged).
3. Admin controller uses lowStockCount(false); Owner API uses lowStockCount(true).
4. categoryTrend: single query on invoice_items join invoices (countable scope conditions) join products, SELECT DATE(invoices.created_at) AS d, category flag, SUM(NET_LINE_SQL) GROUP BY DATE(invoices.created_at), flag (repeat the expression in GROUP BY; no alias grouping); zero-fill days in PHP. DATE() exists on both SQLite and MySQL; created_at is stored as 'Y-m-d H:i:s' on both.
5. Keep OwnerPulseCache wrapping and formatCompactCurrency in their controllers.
6. Characterization test must pass unmodified.
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Admin tests/Feature/Api/Owner`.
```
```text
[TASK: Phase 3 - Step 3]
ROLE: Strict Code Executor.
ACTION: Lock in the query reduction.
TARGET_FILE: `tests/Feature/Admin/DashboardMetricsCharacterizationTest.php`
CHANGES_REQUIRED:
1. Add test: DB::enableQueryLog(); GET admin dashboard; count queries whose SQL references invoice_items. Before the refactor this is 21 (trend) + the category-stat queries (count them from the Step 1 run and hard-code that baseline as BEFORE in the test). Assert after <= BEFORE - 20 (trend collapsed to exactly 1 query). Print both counts in the assertion message.
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Admin/DashboardMetricsCharacterizationTest.php`.
```

## Acceptance criteria
- [ ] The characterization test from Step 1 is committed before Step 2 and passes unchanged afterwards.
- [ ] Both dashboard controllers are ≤ 120 lines and contain no Eloquent aggregate calls.
- [ ] The query-count test passes. Record the admin dashboard's total queries before and after.
- [ ] The full suite shows 0 failed and 0 skipped, with passed ≥ the previous phase's count.
- [ ] Manual check: each surface, the admin dashboard and the owner app, shows the same numbers as its own Phase 0 screenshot. The two surfaces are expected to differ from each other, because their semantics differ.
- [ ] Record the passed count as `N3`.
