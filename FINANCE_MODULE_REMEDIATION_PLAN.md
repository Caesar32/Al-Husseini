# Finance Module Remediation Plan

**Repository:** Al-Husseini ERP & POS (Laravel 12.x)  
**Scope:** Sales Invoices, Invoice Payments, Customer Credit, Credit Ledger, FIFO Settlement, Refunds/Returns, Discounts, Scrap Deductions, Branch Isolation, Invoice Filtering, Financial Statistics, Dashboard KPIs, Customer Statements  
**Source of Truth (Reported Issues):** `docs/FINANCE_ERRORS_REPORT.md` (33 issues, 2026-09-30)  
**Plan Version:** 1.0 — PLAN ONLY (no code modified)  
**Date:** 2026-09-30  
**Author:** Senior Laravel Architect — verified against live repository  

> **Critical Rule Observed:** Every reported issue was inspected in the live repository before planning. See §5 Issue Verification Matrix for actual status and discrepancies.

---

## 1. Executive Summary

The finance core is **transactionally sound** (all monetary mutations inside `DB::transaction` + `lockForUpdate` on `Customer` and open `Invoice` rows) but suffers from **three systemic flaws** that make the 33 reported issues inter-dependent:

1. **No single source of truth for filtering.** `PosOrderService::getPaginatedInvoices()` (filter definition), `SalesInvoiceController::index()` (duplicates the same `where*` for `$statsQuery`), and `DashboardController::index()` (re-implements date/branch logic with `Carbon` + `whereHas`) diverge. Fixing any one leaves the other two inconsistent → KPI vs. table totals mismatch.

2. **Ledger ↔ Invoice ↔ Payment triple-book.** `settleCustomerDebt()` writes both `InvoicePayment` (per-invoice, `amount = applied`) and `CreditLedgerEntry` (`payment_collection`, `amount = total`). Revenue was previously double-counted if summed from both tables. Current `DashboardController` avoids double-counting by summing only `InvoicePayment` but the invariant is undocumented and untested.

3. **Schema/Test divergence.** `Invoice` migration `2026_09_21_160008_create_invoices_and_invoice_items_tables.php:10-30` defines `status IN ('paid','partially_paid','unpaid','cancelled','refunded')` and **no** `payment_status` / `invoice_type` / `total_amount`. Yet `tests/Feature/Sales/CreditAndInvoiceManagementTest.php:170,172,215,217` and `resources/views/admin/credit/statement.blade.php:139` and `resources/views/admin/invoices/show.blade.php:336` reference `payment_status`, `invoice_type`, `total_amount`. Those tests pass only because mass-assignment silently discards unknown columns — they test a non-existent behavior.

**Plan ordering principle:** Fix **display/schema integrity first** (so tests mean something), then **domain invariants** (refund/discount/credit), then **filtering abstraction** (so every consumer shares one filter), then **revenue/statement correctness**, then **performance/cleanup**. This avoids re-working reporting twice.

**Result after execution:** All 33 issues resolved, `php artisan test` green, `php artisan migrate:fresh --seed` + `SystemDiagnosticService` ledger check passes, and five financial invariants (see §6) enforced at both DB and domain layers.

---

## 2. Repository Baseline

### 2.1 Environment

| Item | Actual Value | Source |
|------|--------------|--------|
| PHP | 8.2.12 (ZTS VC19) | `php --version` |
| Laravel | 12.69.2 | `php artisan --version` + `composer.json:13` `laravel/framework ^12.0` |
| Test Framework | Pest 3.8 + pest-plugin-laravel 3.2 | `composer.json:25` |
| DB (local dev) | MySQL (`DB_CONNECTION=mysql`, `Al-Husseini` on `127.0.0.1:3306`) | `.env:12-16` |
| DB (testing) | SQLite `:memory:` | `phpunit.xml:26-27` |
| Cache/Session (testing) | `array` | `phpunit.xml:25,31` |
| Node/Vite | 7.x + `@tailwindcss/vite 4.x` | `package.json` |

### 2.2 Migrations & Schema Reality

| Migration | Tables / Columns | Finance Relevance |
|-----------|------------------|-------------------|
| `2026_09_21_160006_create_customers_and_customer_vehicles_tables` | `customers(id, name, phone, national_id, credit_limit DECIMAL(10,2) default 5000, current_credit_balance DECIMAL(10,2) default 0, tier, is_active)` | Credit limit/balance |
| `2026_09_21_160008_create_invoices_and_invoice_items_tables` | `invoices(id, invoice_number UNIQUE, branch_id FK restrict, customer_id FK null, customer_vehicle_id, technician_id, cashier_id, subtotal, discount_amount, scrap_deduction_amount, tax_amount, final_amount, paid_amount, remaining_amount, payment_method ENUM(cash,card,bank_transfer,credit,split), status ENUM(paid,partially_paid,unpaid,cancelled,refunded) INDEX, notes, timestamps; INDEX(branch_id,created_at))` — **No `payment_status`, no `invoice_type`, no `total_amount`** | Core invoice |
| `2026_09_21_160010_create_credit_ledger_entries_table` | `credit_ledger_entries(id, customer_id FK cascade, invoice_id FK null, entry_type ENUM(invoice_debt,payment_collection,credit_adjustment,refund) INDEX, amount, balance_before, balance_after, collected_by FK, receipt_number UNIQUE nullable, notes, timestamps; INDEX(customer_id,created_at))` | Ledger |
| `2026_09_23_100003_create_invoice_payments_table` | `invoice_payments(id, invoice_id FK cascade, payment_method ENUM(cash,card,bank_transfer,credit) INDEX, amount, transaction_reference INDEX, notes, timestamps; INDEX(invoice_id,payment_method))` | Payment legs |
| `2026_09_21_160007_create_products_and_categories_tables` | `products(..., current_stock INT, is_battery BOOL, retail_price DECIMAL)` | Stock check |

**Verified invariant:** Existing code never writes `payment_status`/`invoice_type`/`total_amount` to `invoices`; those strings appear only in **tests/views that are buggy**.

### 2.3 Current Test Baseline

Run: `php artisan test` (Pest). Observed output (2026-09-30, `sqlite :memory:`):

- Total tests discovered: ~146 (matches `tests/` count: `tests/Feature` ~18 + `tests/Unit` ~30 files).
- **Most suites pass.** Two known failures pre-exist and are **caused by finance issues**:
  - `Tests\Feature\Admin\DashboardSalesFilterTest > admin can view dashboard with sales periods filter` — fails `assertSee('إجمالي مبيعات المركز')` because `resources/views/admin/dashboard/partials/kpi-cards.blade.php:13` shows `إيرادات ومبيعات المركز` (FIN-M05-adjacent UI copy, not finance-logic).
  - Indirect: `CreditAndInvoiceManagementTest` **passes** but is **invalid** — it asserts `payment_status` which is never persisted (FIN-C07). Green does not mean correct.

**Conclusion:** Green suite is not trustworthy for finance until FIN-C07/FIN-C01 are fixed.

### 2.4 Finance file inventory (verified to exist)

```
app/Models/Invoice.php:12                     fillable without total_amount/payment_status
app/Models/InvoicePayment.php                 fillable invoice_id,payment_method,amount,transaction_reference,notes
app/Models/CreditLedgerEntry.php:13           entry_type ENUM 4 values, receipt_number UNIQUE
app/Models/Customer.php:24                    attributes default credit_limit 5000
app/Services/Sales/PosOrderService.php:21     getPaginatedInvoices (filters: search,status,branch_id,date_from,date_to)
app/Services/Sales/PosOrderService.php:64     processPosSale
app/Services/Sales/PosOrderService.php:329    processSalesReturn
app/Services/Sales/PosOrderService.php:416    getDailyCashierSummary
app/Services/Sales/PosOrderService.php:471    settleCustomerDebt (FIFO, lockForUpdate)
app/Http/Controllers/Admin/SalesInvoiceController.php:20  index (duplicates filters) + show + processReturn
app/Http/Controllers/Admin/CreditCustomerController.php:23 index (get() no pagination), settlePayment, statement (limit 50/100)
app/Http/Controllers/Admin/DashboardController.php:23    index (revenue = InvoicePayment only, creditCollected separate)
app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php:23 rules (discount_amount min:0 no max, branch_id nullable)
resources/views/admin/credit/statement.blade.php:139    $inv->total_amount (BUG)
resources/views/admin/invoices/show.blade.php:82,336    status match('partial' vs 'partially_paid'), total_amount
routes/web.php                                           credit.settle → can:credit.settle, invoices.return → can:invoices.cancel
```

No `app/Enums/*` exists; enums are inline `->enum()` in migrations. No `app/Policies` for Invoice/Customer — authorization is via `spatie/laravel-permission` + `can:*` middleware.

---

## 3. Finance Architecture

### 3.1 Component map

| Component | File | Responsibility |
|-----------|------|----------------|
| **Invoice** | `app/Models/Invoice.php` | Aggregate root; `branch_id`, `customer_id`, `final_amount`, `paid_amount`, `remaining_amount`, `payment_method`, `status` |
| **InvoiceItem** | `app/Models/InvoiceItem.php` (inferred from `Invoice::items()`) | Line items; `product_id`, `quantity`, `unit_price`, `total_price`, `battery_serial_number` |
| **InvoicePayment** | `app/Models/InvoicePayment.php` / `2026_09_23_100003` | Payment legs; `payment_method IN (cash,card,bank_transfer,credit)`, `amount`, `transaction_reference` |
| **Customer** | `app/Models/Customer.php` | `credit_limit`, `current_credit_balance` (authoritative), `tier` |
| **CreditLedgerEntry** | `app/Models/CreditLedgerEntry.php` | Append-only ledger; `entry_type`, `amount`, `balance_before/after`, `receipt_number UNIQUE`, `collected_by` |
| **PosOrderService** | `app/Services/Sales/PosOrderService.php` | Single transaction owner for `processPosSale`, `processSalesReturn`, `settleCustomerDebt`, `getPaginatedInvoices` |
| **SalesInvoiceController** | `app/Http/Controllers/Admin/SalesInvoiceController.php` | HTTP filter → Service; also builds `$statsQuery` (duplicate) |
| **CreditCustomerController** | `app/Http/Controllers/Admin/CreditCustomerController.php` | `index` (debtors list), `settlePayment` (delegates to `settleCustomerDebt`), `statement` (ledger + open invoices) |
| **DashboardController** | `app/Http/Controllers/Admin/DashboardController.php` | KPI: `totalSales`, `revenue`, `creditCustomersCount`; revenue = `InvoicePayment` where `method != credit` |

### 3.2 Relationships

```
Customer 1—* Invoice (customer_id)
Customer 1—* CreditLedgerEntry
Invoice 1—* InvoiceItem
Invoice 1—* InvoicePayment
Invoice 1—* CreditLedgerEntry (invoice_id nullable)
Invoice *—1 Branch, *—1 CustomerVehicle, *—1 Employee(technician), *—1 User(cashier)
```

### 3.3 Authorization (verified)

- `routes/web.php:163-214`: `admin/invoices*` → `can:invoices.view` / `can:invoices.cancel`; `admin/credit*` → `can:credit.view` / `can:credit.settle`; `admin/pos` → `can:pos.access`.
- `StorePosInvoiceRequest.php:16` `authorize() { return true; }` — **bypasses** the same `can:pos.access` for `POST /pos`; rely on route middleware only.
- `CreditCustomerController::settlePayment` validates `payment_method in:cash,card,bank_transfer` (excludes `credit`, correct) and delegates to service which does `lockForUpdate`.

---

## 4. Financial Data Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ POS Sale (StorePosInvoiceRequest validated)                                  │
│  file: app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php:59             │
│  rules: branch_id nullable, discount_amount min:0 (no max), scrap_*         │
└────────────────────────┬────────────────────────────────────────────────────┘
                         │  PosOrderService::processPosSale(data, cashierId)
                         │  file: app/Services/Sales/PosOrderService.php:64
                         ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ Invoice (single DB::transaction)                                             │
│  table: invoices                                                             │
│  columns: subtotal, discount_amount, scrap_deduction_amount, tax_amount,     │
│           final_amount = max(0, subtotal+tax-discount-scrap)  [FIN-C09]      │
│           paid_amount = totalPaid - creditAmount                             │
│           remaining_amount = creditAmount                                    │
│           payment_method = split|cash|card|credit                            │
│           status = paid|partially_paid|unpaid                                │
│  locking: Product rows lockForUpdate (line 74)                               │
│  invoice_number: 'INV-'.date('Ymd').uniqid(-6)  [not atomic, collision risk]│
└────────┬──────────────────────┬──────────────────────┬────────────────────────┘
         │                      │                      │
         ▼                      ▼                      ▼
┌──────────────┐  ┌──────────────────┐  ┌────────────────────────┐
│InvoiceItem   │  │InvoicePayment    │  │ Customer / Credit      │
│1 per product │  │1 per pay leg     │  │ if creditAmount>0:     │
│total_price   │  │payment_method    │  │ Customer.lockForUpdate │
│battery_serial│  │amount            │  │ newBalance = balance   │
└──────┬───────┘  └──────────────────┘  │           + creditAmt   │
       │                                 │ if newBalance>limit    │
       ▼                                 │  require overrideCode  │
┌──────────────┐                         │ else: Customer.find    │
│Warranty      │                         │ Customer.update(balance)│
│if is_battery │                         │ CreditLedgerEntry      │
│+ serial      │                         │  entry_type=invoice_debt│
└──────────────┘                         │  amount=creditAmount   │
                                         │  balance_before/after │
                                         └──────────┬─────────────┘
                                                    │
                         ┌──────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ Debt Settlement (CreditCustomerController::settlePayment)                    │
│  file: CreditCustomerController.php:63 → PosOrderService::settleCustomerDebt│
│  table: credit_ledger_entries (payment_collection) + invoice_payments        │
│  locking: Customer lockForUpdate (485), open Invoices lockForUpdate (515)   │
│  FIFO: Invoice WHERE remaining_amount>0.009 AND status NOT IN               │
│        (cancelled,refunded) ORDER BY id ASC — no branch filter [FIN-H06]    │
│  for each open invoice: paid_amount+=applied; remaining_amount-=applied;     │
│                         status → paid|partially_paid; InvoicePayment insert│
│  final: CreditLedgerEntry receipt_number UNIQUE [FIN-C06]                    │
└────────────────────────┬────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ Refund / Return (SalesInvoiceController::processReturn)                      │
│  file: PosOrderService::processSalesReturn (329)                             │
│  locking: Invoice with items lockForUpdate, Product lockForUpdate            │
│  current: if remaining_amount>0 → creditRefund = min(refundTotal,            │
│           remaining_amount) → Customer.balance-=creditRefund →               │
│           CreditLedgerEntry entry_type=refund                                 │
│           remaining_amount-=creditRefund                                      │
│           // cash portion NOT refunded [FIN-C05]                              │
│  status: always refunded [FIN-C08] — should be partially_refunded            │
└────────────────────────┬────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ Customer Statement (CreditCustomerController::statement)                     │
│  file: CreditCustomerController.php:119 (limit 50 invoices / 100 ledgers)   │
│  view: resources/views/admin/credit/statement.blade.php:139                  │
│        BUG: $inv->total_amount (no column) → 0.00 [FIN-C01]                 │
│        BUG: match entry_type sale_on_credit vs invoice_debt [FIN-M01]        │
│  Eager: customer->load(invoices: remaining>0, items.product)                 │
└────────────────────────┬────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ Invoice Filtering & Stats (two duplicate implementations)                    │
│  file: PosOrderService::getPaginatedInvoices:21 (search, status, branch_id,  │
│        date_from/to via whereDate) — used for pagination                     │
│  file: SalesInvoiceController::index:30 (duplicates same where* for          │
│        $statsQuery) → total_sales, invoices_count, credit_invoices_count,    │
│        total_remaining_credit, scrap_count                                    │
│  issue: whereDate disables index branch_id,created_at [FIN-M03]; no          │
│         date_from<=date_to guard [FIN-M02]; refunded still counted [FIN-M05]│
└────────────────────────┬────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ Dashboard KPIs (DashboardController::index)                                  │
│  line 31: baseInvoices = where('status','!=','cancelled') — includes       │
│           refunded → overstates sales [FIN-C04]                              │
│  line 54-61: InvoicePayment whereHas invoice !=cancelled AND                │
│            payment_method!='credit' summed 5× (cashToday/Week/Month/Year/All)│
│  line 65-70: CreditLedgerEntry payment_collection summed separately — correct │
│            that revenue = cashToday only, not + creditCollected, but          │
│            undocumented and untested → risk of double-count [FIN-C03]        │
│  line 75-79: round() without decimals → loses halala [FIN-M04]               │
└─────────────────────────────────────────────────────────────────────────────┘
```

For each step the **responsible method / table / columns / locking / validation / branch isolation** are listed inline above.

---

## 5. Issue Verification Matrix

> **Actual Status after repository inspection (2026-09-30).** All files below exist. “Report Outdated” means the report described a prior version; current code is already partially fixed but the invariant is still not enforced/tested.

| ID | Priority | Reported Issue | Actual Status | Files (verified) | Root Cause (verified) | Dependencies | Proposed Resolution (adjusted) | Phase |
|----|----------|----------------|---------------|------------------|-----------------------|--------------|--------------------------------|-------|
| FIN-C01 | P0 | `statement.blade.php:139` shows `$inv->total_amount` (no column) | **Confirmed** | `resources/views/admin/credit/statement.blade.php:139` still `total_amount`; `database/migrations/2026_09_21_160008:10-23` has `final_amount` not `total_amount`; `app/Models/Invoice.php:12` fillable has `final_amount` | Blade typo / copy-paste from purchase context | — | Change to `$inv->final_amount` (or `subtotal` if detail needed) | 1 |
| FIN-C02 | P0 | Duplicate filter logic Controller vs Service | **Confirmed** | `SalesInvoiceController.php:30-52` duplicates `PosOrderService.php:21-59` (`search/status/branch_id/date_from/to`) | DRY violation; statsQuery is separate instance | — | Extract single `InvoiceFilter` value object + `InvoiceQueryBuilder` trait/service; make Controller delegate to Service for both paginator and stats | 5 |
| FIN-C03 | P0 | Dashboard double-counts revenue (InvoicePayment + Ledger) | **Partially Confirmed / Report Outdated** | `DashboardController.php:54-79` **now** computes `revenue = cashToday` (InvoicePayment only) and `creditCollected` separately as “احتياطي”; does **not** sum them. But there is **no test** asserting this invariant, and comment still says “unless business requires it” → risk of regression | Prior double-sum was fixed but not locked | Depends on Phase 5 (unified filter) to avoid re-introducing sum | Document canonical revenue = `SUM(InvoicePayment.amount WHERE method != 'credit' AND invoice.status NOT IN (cancelled,refunded))`; add regression test; remove ambiguous comment | 6 |
| FIN-C04 | P0 | `refunded` still counted in `total_sales` | **Confirmed** | `DashboardController.php:31` `where('status','!=','cancelled')` includes `refunded`; `SalesInvoiceController.php:55` `sum('final_amount')` without status guard | Enum has 5 values, filter excludes only 1 | FIN-C08 (refund status) | Change to `whereNotIn('status',['cancelled','refunded'])` or `where('status','!=','cancelled')->where('status','!=','refunded')`; add `scopeActiveSales()` | 2/6 |
| FIN-C05 | P0 | Return refunds credit only, cash ignored | **Confirmed** | `PosOrderService.php:362-393` only enters `if (remaining_amount>0)` branch; `paid_amount` cash portion never refunded | Incomplete accounting model (no cash-refund leg) | FIN-C08 | Add cash-refund branch: create negative `InvoicePayment` or `CreditLedgerEntry(entry_type=refund, method=cash)` + update `paid_amount`; decide via business decision (see §19) | 2 |
| FIN-C06 | P0 | `receipt_number` UNIQUE fails on FIFO multi-invoice | **Confirmed** | `2026_09_21_160010:18` `receipt_number UNIQUE`; `PosOrderService.php:536-542` passes same `$receiptNumber` to each `InvoicePayment.transaction_reference` and single `CreditLedgerEntry.receipt_number` — duplicate settlement with same receipt_number throws `QueryException`; also FIFO creates multiple InvoicePayments with same reference | Unique constraint too strict for business “one receipt settles many invoices” | FIN-H06 | Make `receipt_number` non-unique **or** add suffix `-1,-2` per invoice + `UNIQUE(customer_id,receipt_number)` partial; add `where receipt_number exists` guard + idempotency key | 4 |
| FIN-C07 | P0 | Phantom columns `payment_status`/`invoice_type` in tests | **Confirmed** | `tests/Feature/Sales/CreditAndInvoiceManagementTest.php:170,172,215,217` uses both; `EndToEnd...:352` uses `payment_status`; `Invoice` migration/model have no such columns; `SearchService.php:394` also `->payment_status` (bug) | Schema/test drift; mass-assignment silently drops | — | Remove those keys from tests/views or add migration + fillable if business wants them; fix `SearchService.php:394` to `status` | 1 |
| FIN-C08 | P0 | `status=refunded` for partial return | **Confirmed** | `PosOrderService.php:397` `update(['status'=>'refunded'])` unconditional | No `isFullReturn` check | FIN-C05 | Compute `isFullReturn = $refundTotal >= $invoice->final_amount - 0.01` → `refunded` else `partially_refunded` (requires enum migration) | 2 |
| FIN-C09 | P0 | `max(0, subtotal+tax-discount-scrap)` hides excessive discount | **Confirmed** | `PosOrderService.php:130` `max(0, ...)` + `StorePosInvoiceRequest.php:44` `discount_amount min:0` no max | No domain guard | FIN-H01 | Throw `DomainException` if `discount+scrap > subtotal+tax`; add `max: subtotal` validation; add service-level guard regardless of request | 3 |
| FIN-H01 | P1 | `discount_amount`/`scrap_deduction_amount` no `max` | **Confirmed** | `StorePosInvoiceRequest.php:44` `nullable|numeric|min:0` only | Allows `discount=999999` → FIN-C09 | FIN-C09 | Same as FIN-C09; add `lte:subtotal` dynamic validation in `withValidator` | 3 |
| FIN-H02 | P1 | Invoice search excludes serial/barcode/product | **Confirmed** | `PosOrderService.php:34-42` searches `invoice_number` + `customer.name/phone` only; `SearchService` does broader | Feature gap | FIN-C02 | Extend `getPaginatedInvoices` search to `orWhereHas(items.product, name/sku/barcode) OR items.battery_serial_number` | 5 |
| FIN-H03 | P1 | `branch_id` nullable → defaults to `1` | **Confirmed** | `StorePosInvoiceRequest.php:24` `nullable`; `PosOrderService.php:193` `?? 1` | Assumes branch 1 exists; breaks multi-branch isolation | FIN-H06 | Make `branch_id required|exists:branches,id` + `Gate::allows('sell-in-branch', branch_id)` | 4 |
| FIN-H04 | P1 | Magic `0.009` / `0.01` thresholds undocumented | **Confirmed** | `PosOrderService.php:512` `>0.009`, `519` `<=0.009`, `493` `+0.01` | Halala tolerance not centralized | FIN-H06 | Define `const EPSILON = 0.01` (config `finance.epsilon`) and use `bccomp` or `Money` | 4 |
| FIN-H05 | P1 | Hardcoded override `9999`/`mgr_override_99` | **Confirmed** | `PosOrderService.php:568` `mgr_override_99` + `config('app.manager_override_code','9999')`; not in `.env.example` | Backdoor / weak default | FIN-H06 | Move to `settings` table hashed + `RateLimiter` + remove hardcoded fallback; add `MANAGER_OVERRIDE_CODE` to `.env.example` | 4 |
| FIN-H06 | P1 | `settleCustomerDebt` no `branch_id` filter | **Confirmed** | `PosOrderService.php:511` `where('customer_id',...)` without `where('branch_id',...)` | Cross-branch settlement leakage | FIN-H03, FIN-C02 | Add `branch_id` scope to settlement (requires business decision: is customer global or branch-scoped?) | 4 |
| FIN-H07 | P1 | `+0.01` allows 1 halala overpay | **Confirmed** | `PosOrderService.php:493` `if ($amount > $currentBalance + 0.01)` | Off-by-one | FIN-H04 | Use `> $currentBalance + EPSILON` with EPSILON=0.005 or strict `>` without epsilon and rely on `round(...,2)` | 4 |
| FIN-H08 | P1 | `getDailyCashierSummary` loads all invoices | **Confirmed** | `PosOrderService.php:417-421` `->get()` then `sum` in PHP | N+1 / memory | FIN-L02 | Replace with `SUM(final_amount)`, `SUM(scrap_deduction_amount)` + `InvoicePayment::whereHas(...)->sum` | 8 |
| FIN-H09 | P1 | `processReturn` `exists:products,id` without branch/active | **Confirmed** | `SalesInvoiceController.php:90` only `exists` | Allows return of discontinued product to wrong branch | FIN-C05 | Add `where is_active` or remove check (return should be allowed even if product inactive) — business decision | 8 |
| FIN-M01 | P2 | `entry_type` badge mismatch `sale_on_credit` vs `invoice_debt` | **Confirmed** | `statement.blade.php:184` `sale_on_credit` / `sales_return_refund` / `payment_received` vs migration `invoice_debt`/`refund`/`payment_collection` — all fall to `default` | Copy-paste from older spec | FIN-C01 | Fix `match` to `invoice_debt`/`refund`/`payment_collection`/`credit_adjustment` | 1 |
| FIN-M02 | P2 | No `date_from <= date_to` guard | **Confirmed** | `SalesInvoiceController.php:47-52` and `PosOrderService.php:53-59` no guard | Returns 0 rows silently | FIN-C02 | Add `withValidator` rule `date_to >= date_from` or auto-swap + flash warning | 5 |
| FIN-M03 | P2 | `whereDate` disables index | **Confirmed** | `SalesInvoiceController.php:48` `whereDate('created_at','>=',date_from)` uses `DATE(created_at)` → loses `INDEX(branch_id,created_at)` | Performance | FIN-C02 | Change to `where('created_at','>=', $dateFrom.' 00:00:00')->where('created_at','<=',$dateTo.' 23:59:59')` or `whereBetween` | 5 |
| FIN-M04 | P2 | `round()` without decimals loses halala | **Confirmed** | `DashboardController.php:75-79,89-90` `round($cashToday)` / `round($salesToday)` | Displays 12346 instead of 12345.67 | — | Use `round(...,2)` + `number_format(...,2)` | 6 |
| FIN-M05 | P2 | `total_remaining_credit` includes refunded | **Confirmed** | `SalesInvoiceController.php:58` `sum('remaining_amount')` without `whereNotIn(status, [refunded,cancelled])` | Overstates receivables | FIN-C04 | Add status guard | 5 |
| FIN-M06 | P2 | `credit.blade.php` KPIs from `localStorage` | **Confirmed** | `resources/views/admin/sales/credit.blade.php:439` `window.AlHusseiniSales.getCreditSummary()` | DB vs localStorage drift | — | Replace with server-rendered `{{ $totalOutstanding }}` or fetch `/admin/credit` JSON; keep `AlHusseiniSales` only as enhancement | 7 |
| FIN-M07 | P2 | Date preset loses other filters (partially) | **Partially Confirmed** | `invoices.blade.php:435-475` `applyDatePreset` submits same form, so `search/status` are preserved (form contains them). But `branch_id` hidden input not in form → lost. | Form incomplete | FIN-C02 | Include `branch_id` hidden input + preserve via `withQueryString` | 5 |
| FIN-M08 | P2 | `statement` limit 50/100 no pagination | **Confirmed** | `CreditCustomerController.php:123-132` `limit(50)` / `limit(100)` | Cuts off history | — | Replace with `paginate` or `cursor` + “Load more” | 7 |
| FIN-M09 | P2 | `InvoicePayment` allows `credit` but settle rejects it | **Confirmed but not a bug** | `invoice_payments:13` enum includes `credit`; `CreditCustomerController:68` `in:cash,card,bank_transfer` excludes `credit` — logically correct (settlement should be cash-like). The report’s “contradiction” is actually intended separation. | Semantic confusion | — | Document that `credit` is only for `InvoicePayment` on sale, never for settlement; rename to `credit_sale` if needed | 7 |
| FIN-L01 | P3 | Double `number_format` + `decimal:2` | **Confirmed** | `statement.blade.php:91,99` `number_format(...,2)` on already-cast `decimal:2` | Cosmetic | — | Use accessor `getFormattedAttribute` | 7 |
| FIN-L02 | P3 | Phantom `current_credit_balance` in tests without ledger | **Confirmed** | `Customer.php:25` default 0, tests create `current_credit_balance=5000` without `CreditLedgerEntry` | Breaks invariant `balance == sum(remaining)` | FIN-C07 | Create ledger entry in factory/seeder or assert via `sum` | 8 |
| FIN-L03 | P3 | `restrictOnDelete` on `branch_id` | **Confirmed** | `2026_09_21_160008:12` `restrictOnDelete` | Prevents branch deletion even if invoices cancelled | — | Change to `nullOnDelete` or add `archived` flag — business decision | 8 |
| FIN-L04 | P3 | No enum casts for `status`/`payment_method` | **Confirmed** | `Invoice.php:31-41` `casts` only decimals, no `status` | String compare brittle | — | Add `casts: status => InvoiceStatus::class` (requires Enum) | 8 |
| FIN-L05 | P3 | Dashboard N+5 `whereHas` duplicated | **Confirmed** | `DashboardController.php:54-61` `whereHas('invoice', !=cancelled)` 5× | DRY | FIN-C03 | Extract `scopeActivePayments()` | 6 |
| FIN-L06 | P3 | `.env.example` missing `MANAGER_OVERRIDE_CODE` | **Confirmed** | `.env.example:1-65` no such key | Env drift | FIN-H05 | Add `MANAGER_OVERRIDE_CODE=` to `.env.example` + `config/app.php` | 4 |

**Summary:** 31/33 Confirmed, 1 Partially Confirmed (FIN-M07), 1 Report Outdated (FIN-C03 now partially fixed but untested), 0 Already Fixed. One “contradiction” (FIN-M09) is actually correct separation but needs documentation.

---

## 6. Financial Invariants

The following invariants must hold **at all times** (enforced at DB + domain + test layers). If the repository contradicts one, the discrepancy is noted.

| # | Invariant | Formula | Enforcement Point | Verified Today |
|---|-----------|---------|-------------------|----------------|
| I-01 | `final_amount` non-negative | `final_amount >= 0` AND `final_amount == round(max(0, subtotal+tax-discount-scrap),2)` with guard `discount+scrap <= subtotal+tax` | `PosOrderService::processPosSale:130` (currently `max(0)` hides violation) → must throw before `max` | ❌ Not enforced (FIN-C09) |
| I-02 | Invoice balance equation | `paid_amount >=0 && remaining_amount>=0 && paid_amount + remaining_amount == final_amount` (within `EPSILON=0.01`) | `PosOrderService::processPosSale:209-210`, `settleCustomerDebt:525-526`, `processSalesReturn:369-390` | Partially (rounding 0.05 vs 0.01) |
| I-03 | Customer credit equals sum of open invoices | `customer.current_credit_balance == SUM(invoice.remaining_amount WHERE customer_id = ? AND status NOT IN ('cancelled','refunded') AND remaining_amount>0)` | `settleCustomerDebt` FIFO keeps it, but no DB constraint or scheduled audit | ❌ No automated check; `SystemDiagnosticService` does ledger check but not this sum |
| I-04 | Ledger balance chain | For each `CreditLedgerEntry` ordered by `id`, `balance_after == balance_before + (entry_type IN ('invoice_debt') ? +amount : -amount)` and first `balance_before == 0` | `PosOrderService:230-241,375-385,548-558` writes `before/after` but no DB trigger | Partially (app-level only) |
| I-05 | Refunded invoice not in active sales | `SUM(final_amount WHERE status NOT IN ('cancelled','refunded')) == Dashboard totalSales` and `SUM(remaining_amount WHERE status NOT IN ('cancelled','refunded')) == totalOutstanding` | `DashboardController:31` currently excludes only `cancelled`; `SalesInvoiceController:55,58` same | ❌ Violated (FIN-C04/M05) |
| I-06 | No negative balances | `customer.current_credit_balance >=0` AND `credit_ledger_entries.balance_after >=0` AND `invoice.remaining_amount >=0` | `max(0, ...)` guards but no CHECK constraint | Partially |
| I-07 | Every ledger entry has valid reference | `customer_id EXISTS, collected_by EXISTS, amount>0, receipt_number UNIQUE IF NOT NULL` | `2026_09_21_160010:11-18` FK + unique | ❌ `receipt_number` unique too strict for FIFO (FIN-C06) |
| I-08 | Payment legs sum to invoice total | `SUM(InvoicePayment.amount WHERE invoice_id=?) == invoice.final_amount` (within EPSILON) and `SUM(WHERE method='credit') == remaining_amount` | `PosOrderService:152-161` checks `totalPaid == finalAmount` with `0.05` | Partially (wrong epsilon) |

**Money precision (verified):**

- All monetary columns are `DECIMAL(10,2)` in migrations (`2026_09_21_160008:17-23`, `2026_09_21_160010:14-16`, `2026_09_23_100003:14`).
- All PHP calculations use `float` + `round(...,2)` (`PosOrderService.php:98,120,130,152`). No `int` minor units, no `Brick\Money`.
- **Repository-consistent approach:** Keep `DECIMAL(10,2)` + `round(...,2)` + `EPSILON=0.01` (do **not** switch to `int`). The plan must **not** introduce floating `double` without rounding; every operation must `round(...,2)` immediately and compare via `bccomp` or `abs(diff) <= 0.01`.
- Rounding policy: **Half-up, 2 decimals, after each addition/subtraction** (e.g., `unitPrice*qty` → `round`, `subtotal+tax-discount-scrap` → `round`, `paid+remaining` → `round`). Document in `config/finance.php` or `app/Support/Money.php`.

---

## 7. Phase 0 — Baseline & Architecture Verification

### Objective

Establish a **frozen, auditable baseline** without modifying code. All later phases depend on this baseline for regression comparison.

### Issues Covered

None directly — this phase **validates** all 33 issues (see §5 table) and produces `BASELINE.md` (or §2 above if file creation is not allowed).

### Preconditions

- `main` branch checked out, `composer install` done, `.env` points to test DB.
- `php artisan migrate:fresh --seed` succeeds (verified 2026-09-30: 23 migrations Ran).

### Repository Files To Inspect

- `composer.json`, `phpunit.xml`, `.env.example`, `.env`, `config/app.php`, `config/database.php`
- `app/Models/*`, `app/Services/Sales/PosOrderService.php`, `app/Http/Controllers/Admin/*`, `app/Http/Requests/Admin/Pos/*`, `resources/views/admin/credit/statement.blade.php`, `resources/views/admin/invoices/show.blade.php`, `routes/web.php`, `database/migrations/*`, `tests/Feature/Sales/*`, `tests/Feature/Admin/DashboardSalesFilterTest.php`

### Files Expected To Change

**No database changes expected.**  
**No application code changes.**  
If `BASELINE.md` is allowed, create it; otherwise embed baseline in the plan (this document §2).

### Backend Changes

None.

### Frontend / Blade Changes

None.

### Test Changes

Run and record (do not modify):

```bash
php artisan test --compact
php artisan test --filter=CreditAndInvoiceManagementTest --compact
php artisan test --filter=DashboardSalesFilterTest --compact
```

Capture: total, passing, failing, skipped, assertions, duration. Mark FIN-C07 tests as “passing but invalid”.

### Edge Cases

- `sqlite :memory:` vs MySQL `DECIMAL` rounding differences — note but do not fix here.
- `DashboardSalesFilterTest` failure is **not** finance-logic, it is copy (`إجمالي مبيعات المركز` vs `إيرادات ومبيعات المركز` at `kpi-cards.blade.php:13`). Record as separate UI-copy issue.

### Concurrency Considerations

None (read-only).

### Data Integrity Considerations

Snapshot `SELECT COUNT(*), SUM(current_credit_balance) FROM customers` and `SELECT COUNT(*), SUM(remaining_amount) FROM invoices WHERE status NOT IN ('cancelled','refunded')` before any phase — to compare after each phase.

### Backward Compatibility

None.

### Migration / Rollback Strategy

No migrations. Baseline is a tag/commit `baseline-finance-2026-09-30`.

### Acceptance Criteria

- [ ] `phpunit.xml` DB is `sqlite :memory:` confirmed.
- [ ] `php artisan migrate:status` shows 23 Ran.
- [ ] `php artisan test --filter=DashboardSalesFilterTest` fails on `assertSee('إجمالي مبيعات المركز')` (proves the test is brittle, not finance-broken).
- [ ] `php artisan test --filter=CreditAndInvoiceManagementTest` passes but `rg -n "payment_status|total_amount" tests` shows 4 hits on phantom columns (proves FIN-C07).
- [ ] `statement.blade.php:139` still `total_amount` (proves FIN-C01).
- [ ] Documented revenue formula is `InvoicePayment only` (proves FIN-C03 partial fix).

### Validation Commands

```bash
php --version
php artisan --version
php artisan migrate:status
php artisan test --compact  # record total/pass/fail
rg -n "total_amount|payment_status|invoice_type" app database tests resources --glob '!vendor'
rg -n "entry_type" app/Models/CreditLedgerEntry.php resources/views/admin/credit/statement.blade.php
```

---

## 8. Phase 1 — Critical Financial Display & Schema/Test Integrity

### Objective

Make **what the user sees and what the tests assert** match the **actual schema**, so every later finance fix can be trusted. No business logic changed except display.

### Issues Covered

```
FIN-C01   // statement total_amount → final_amount
FIN-C07   // phantom columns payment_status/invoice_type
FIN-M01   // entry_type badge mismatch
FIN-C07 (SearchService) // SearchService.php:394 payment_status → status
```

### Preconditions

- Phase 0 baseline accepted; business confirms `Invoice` should **not** have `payment_status`/`invoice_type`/`total_amount` (they are `status`/`final_amount`).

### Repository Files To Inspect

- `resources/views/admin/credit/statement.blade.php:139,184`
- `resources/views/admin/invoices/show.blade.php:82,336,159,182,196`
- `app/Services/SearchService.php:394,405`
- `tests/Feature/Sales/CreditAndInvoiceManagementTest.php:151-258`
- `tests/Feature/Sales/EndToEndSalesAndPurchasesScenarioTest.php:352,556`
- `tests/Unit/Sales/Services/SalesAndPurchasesServicesTest.php:320`
- `app/Models/Invoice.php`, `database/migrations/2026_09_21_160008`

### Files Expected To Change

- `resources/views/admin/credit/statement.blade.php`
- `resources/views/admin/invoices/show.blade.php`
- `app/Services/SearchService.php`
- `tests/Feature/Sales/CreditAndInvoiceManagementTest.php`
- `tests/Feature/Sales/EndToEndSalesAndPurchasesScenarioTest.php`
- (No migration if business chooses “remove phantom columns”; if business wants them, then `database/migrations/2026_09_30_add_payment_status_to_invoices.php` instead — see Business Decision B-01)

### Database Changes

**Option A (Recommended — no schema change):**  
No database changes expected. Keep `invoices.status` as single source. The fix is to **remove** `payment_status`/`invoice_type` from tests/views.

**Option B (If business insists on `payment_status`):**  
Create `2026_09_30_add_payment_status_to_invoices.php`:
```php
$table->enum('payment_status',['paid','partially_paid','unpaid'])->nullable()->after('status');
$table->string('invoice_type',20)->nullable()->after('payment_status');
```
But then `Invoice` needs `fillable`, `casts`, and backfill `status → payment_status`. Prefer Option A.

### Backend Changes

- `SearchService.php:394` `match ($inv->payment_status)` → `match ($inv->status)`.
- No service logic change.

### Frontend / Blade Changes

- `statement.blade.php:139` `number_format($inv->total_amount,2)` → `number_format($inv->final_amount,2)`.
- `statement.blade.php:184` `match($entry->entry_type)`:
  ```php
  match($entry->entry_type) {
    'invoice_debt' => ['label'=>'فاتورة بيع بالآجل','class'=>'bg-danger-subtle text-danger'],
    'payment_collection' => ['label'=>'سداد دفعة نقدية','class'=>'bg-success-subtle text-success'],
    'refund' => ['label'=>'مرتجع مبيعات','class'=>'bg-info-subtle text-info'],
    'credit_adjustment' => ['label'=>'تسوية ائتمانية','class'=>'bg-warning-subtle text-warning'],
    default => ['label'=>$entry->entry_type,'class'=>'bg-secondary-subtle text-secondary']
  };
  $isDebit = $entry->entry_type === 'invoice_debt';
  ```
- `invoices/show.blade.php:82` `match` should be `'paid','partially_paid','unpaid','cancelled','refunded'` (currently `'paid','partial','unpaid','returned'` — wrong keys).
- `invoices/show.blade.php:336` `number_format($invoice->total_amount,2)` → `final_amount`.
- `invoices/show.blade.php:159` `$item->serial_number` → `$item->battery_serial_number`; `182` `$invoice->scrap_discount` → `$invoice->scrap_deduction_amount`; `196` same; `224` `$payment->reference_number` → `$payment->transaction_reference`.

### Test Changes

- In `CreditAndInvoiceManagementTest.php:161` and `:200`:
  ```php
  // Before (invalid):
  Invoice::create(['payment_status'=>'paid','invoice_type'=>'retail', ...])
  // After:
  Invoice::create(['status'=>'paid', ...]) // remove invoice_type, payment_status
  // and assert:
  $this->assertDatabaseHas('invoices',['invoice_number'=>'INV-TEST-9988','status'=>'paid']);
  ```
- In `EndToEnd...:352` remove `payment_status`.
- In `SearchServiceTest` (if any) assert `status` not `payment_status`.

### Edge Cases

- Historical invoices that were created with `payment_status` in a non-pristine DB (if any) — migration rollback must not drop data if Option B was ever deployed. Check `SELECT COUNT(*) FROM invoices WHERE payment_status IS NOT NULL` before dropping.
- `statement.blade.php` with 0 invoices: still shows “لا توجد فواتير”.

### Concurrency Considerations

None (display only).

### Data Integrity Considerations

No balance changes. However, fixing `statement` display will make auditors **see** the correct `final_amount`; before, they saw `0.00` and might have mis-reported.

### Backward Compatibility

- Existing API/UI that read `total_amount` will break if they relied on the bug (they saw `null`). In practice none did — `total_amount` was never written.
- Tests that asserted `payment_status` will now fail until patched — intentional.

### Migration / Rollback Strategy

- If Option A: no migration, rollback is `git revert`.
- If Option B: migration is additive, rollback `php artisan migrate:rollback` drops columns (data loss for those columns only, safe because they were never correctly populated).

### Acceptance Criteria

- [ ] `rg -n "total_amount" resources/views/admin/credit/statement.blade.php` returns 0 hits (all replaced).
- [ ] `rg -n "payment_status" app/Models/Invoice.php` returns 0; `rg -n "payment_status" tests/` returns only Purchase context, not Invoice.
- [ ] `php artisan test --filter=CreditAndInvoiceManagementTest` passes **and** asserts `status` not `payment_status`.
- [ ] `statement.blade.php` renders `final_amount` and badges show `فاتورة بيع بالآجل` not raw `invoice_debt`.

### Validation Commands

```bash
rg -n "total_amount|payment_status|invoice_type" app/Models/Invoice.php resources/views/admin/credit/statement.blade.php resources/views/admin/invoices/show.blade.php
php artisan test --filter=CreditAndInvoiceManagementTest
php artisan test --filter=SearchService
php artisan view:clear
```

---

## 9. Phase 2 — Refund & Return Financial Integrity

### Objective

Make **refunds accounting-correct and auditable** before any reporting change consumes refund data.

### Issues Covered

```
FIN-C05   // cash portion not refunded
FIN-C08   // status always refunded
FIN-C04   // refunded still counted in sales (fix filter, not just display)
FIN-M05   // total_remaining_credit includes refunded
```

### Preconditions

- Phase 1 complete (so `status` values are canonical: `paid,partially_paid,unpaid,cancelled,refunded` and `partially_refunded` added in this phase).
- Business decision B-02 (refund status name) and B-03 (cash refund mechanism) made (see §19).

### Repository Files To Inspect

- `app/Services/Sales/PosOrderService.php:329-404` `processSalesReturn`
- `app/Http/Controllers/Admin/SalesInvoiceController.php:85-118` `processReturn`
- `app/Models/Invoice.php` + migration `2026_09_21_160008:25` enum
- `resources/views/admin/invoices/show.blade.php:61,82`
- `app/Services/Diagnostics/SystemDiagnosticService.php` (ledger check, if any for refunds)

### Files Expected To Change

- `database/migrations/2026_09_30_add_partially_refunded_to_invoices_status.php` (enum extension)
- `app/Services/Sales/PosOrderService.php`
- `app/Http/Controllers/Admin/SalesInvoiceController.php` (minor, for status validation)
- `app/Models/Invoice.php` (casts if enum added)
- `resources/views/admin/invoices/show.blade.php` (status badge)
- `resources/views/admin/sales/invoices.blade.php` (filter dropdown)
- `tests/Feature/Sales/CreditAndInvoiceManagementTest.php:192-258` (assert partially_refunded, cash refund)

### Database Changes

**Migration required:**

```php
// 2026_09_30_add_partially_refunded_to_invoices_status.php
Schema::table('invoices', function (Blueprint $table) {
    $table->enum('status', ['paid','partially_paid','unpaid','cancelled','refunded','partially_refunded'])
          ->default('paid')->change(); // MySQL: requires doctrine/dbal or raw ALTER
});
```

Alternative for SQLite testing: recreate table or use `DB::statement("ALTER TABLE invoices MODIFY status ENUM(...)")`. For `sqlite` in tests, the enum is just `TEXT CHECK`, so change is `TEXT`.

No index changes. No data migration except `UPDATE invoices SET status='refunded' WHERE status='refunded' AND refundTotal < final_amount` is **not** needed because historical data was all marked `refunded` incorrectly — but we should not auto-migrate historical; leave as `refunded` and only new returns get correct status (or run one-off script if business wants correction).

### Backend Changes

**`PosOrderService::processSalesReturn` (329):**

1. After `foreach` computing `$refundTotal` and `increment` stock, compute:
   ```php
   $isFullReturn = round($refundTotal,2) >= round((float)$invoice->final_amount,2) - 0.01;
   // also consider partial quantity: sum(quantity returned) == sum(quantity original)
   ```
2. **Credit portion** (existing, keep):
   ```php
   $creditRefund = min($refundTotal, (float)$invoice->remaining_amount);
   ```
3. **Cash portion** (new, FIN-C05):
   ```php
   $cashRefund = round($refundTotal - $creditRefund, 2);
   if ($cashRefund > 0.01) {
       // Option A: create negative InvoicePayment
       InvoicePayment::create([
           'invoice_id' => $invoice->id,
           'payment_method' => 'cash', // or original method if known
           'amount' => -$cashRefund,
           'transaction_reference' => 'REFUND-'.$invoice->invoice_number,
           'notes' => "مرتجع نقدي لفاتورة {$invoice->invoice_number}. السبب: {$reason}"
       ]);
       // Update paid_amount and final_amount? See invariants.
       // We keep final_amount unchanged (gross), but paid_amount -= cashRefund
       // Or create separate refund invoice — decision B-03
   }
   ```
4. **Update invoice:**
   ```php
   $invoice->update([
       'status' => $isFullReturn ? 'refunded' : 'partially_refunded',
       'paid_amount' => max(0, round((float)$invoice->paid_amount - $cashRefund, 2)),
       'remaining_amount' => max(0, round((float)$invoice->remaining_amount - $creditRefund, 2)),
       // final_amount stays gross; or if net sales should reduce, add refund_amount column — decision B-04
   ]);
   ```
5. **Ledger:** keep `CreditLedgerEntry entry_type=refund` for credit portion; for cash portion, either `InvoicePayment` negative or `CreditLedgerEntry entry_type=refund` with `amount = $cashRefund` but `customer_id` may be null (walk-in). Prefer `InvoicePayment` negative to keep ledger cash-free.

**Locking:** Already `lockForUpdate` on `Invoice` and `Product`; add `Customer lockForUpdate` for cash path too (already there for credit). Keep whole method in `DB::transaction`.

### Frontend / Blade Changes

- `invoices/show.blade.php:61` `@if($invoice->status !== 'returned' && status !== 'cancelled')` — `returned` is not a valid status; fix to `refunded` + `partially_refunded`:
  ```php
  @if(!in_array($invoice->status, ['refunded','partially_refunded','cancelled']))
  ```
- `show.blade.php:82` `match` add `partially_refunded` badge.
- `sales/invoices.blade.php` status filter dropdown add `partially_refunded` option.

### Test Changes

- `CreditAndInvoiceManagementTest:192` currently asserts credit return reduces balance to 0 and status `refunded` with full quantity. **Add:**
  ```php
  test('partial return marks partially_refunded and refunds only returned qty')
  test('cash invoice return creates negative payment and does not touch credit ledger')
  test('full return marks refunded and inventory restored')
  test('second return on same invoice throws DomainException')
  ```
- Use `Given paid invoice 3500 / When return 1 of 2 items (1500) / Then status=partially_refunded, remaining=...`.

### Edge Cases

- Return qty > original qty → `DomainException` (already).
- Return on `cancelled` → throw.
- Return on `refunded` → throw (already).
- Walk-in (customer_id null) with `remaining_amount=0` and cash refund → `InvoicePayment` negative with `customer_id null` is allowed; no ledger entry.
- Concurrent returns on same invoice → `lockForUpdate` prevents double refund; second will hit `status === refunded` check after first commits? Need to re-lock after first: use `SELECT ... FOR UPDATE` already, second transaction will wait then see status.

### Concurrency Considerations

- `DB::transaction` + `lockForUpdate` on `Invoice` and `Product` already; add `Customer` lock for cash path. No weakening.

### Data Integrity Considerations

- After this phase, `refund` must **never** create negative `remaining_amount` or `paid_amount`; use `max(0, ...)`.
- Historical `refunded` invoices that were actually partial remain `refunded` (overstated) — document and offer one-off correction script.

### Backward Compatibility

- Existing `refunded` invoices stay `refunded`; new partial returns become `partially_refunded` — dashboard/report that filtered `status != cancelled` will now need `NOT IN (cancelled,refunded,partially_refunded)` (Phase 6).
- API consumers that check `status == refunded` for “any return” must update to `in_array(..., [refunded,partially_refunded])`.

### Migration / Rollback Strategy

- Migration is **enum extension** → additive, rollback is `ALTER ENUM` removing `partially_refunded` — but MySQL does not allow removing enum value if any row has it; rollback would fail if any `partially_refunded` row exists. Therefore make rollback **non-destructive**: keep enum value but change default; or make rollback a no-op and document.
- Deployment order: deploy code that **writes** `partially_refunded` **after** migration runs. Use `php artisan migrate --force` before `php artisan config:cache`.

### Acceptance Criteria

- [ ] Full return on credit invoice: `Customer.current_credit_balance` reduced by `remaining_amount`, `CreditLedgerEntry(entry_type=refund)` created, `Invoice.status=refunded`, inventory `current_stock` incremented.
- [ ] Partial return (1 of 2 items): `status=partially_refunded`, `remaining_amount` reduced proportionally, not zeroed.
- [ ] Cash invoice return: `InvoicePayment` with negative `amount` created, `paid_amount` reduced, no ledger entry for credit.
- [ ] Second return on same invoice → 422 `DomainException`.
- [ ] `invoices` table has `partially_refunded` in enum (checked via `SHOW COLUMNS` or `SELECT DISTINCT status`).

### Validation Commands

```bash
php artisan migrate
php artisan test --filter="sales return on credit invoice"
php artisan test --filter="partial return"
rg -n "partially_refunded" app database resources
```

---

## 10. Phase 3 — Discount & Invoice Calculation Integrity

### Objective

Enforce **domain-level** validation for `final_amount = subtotal + tax - discount - scrap` so that excessive discounts cannot produce a free invoice, regardless of whether the Request is bypassed.

### Issues Covered

```
FIN-C09   // max(0) hides excessive discount
FIN-H01   // discount/scrap no max
```

### Preconditions

- Phase 1 display fixed, so `final_amount` is now correctly shown; Phase 2 refund logic now correctly uses `final_amount` for `isFullReturn`.

### Repository Files To Inspect

- `app/Services/Sales/PosOrderService.php:128-131` (`$finalAmount = max(0, ...)`)
- `app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php:43-45` (rules `min:0` only) and `withValidator:178-203` (finalAmount vs payments)
- `app/Models/Invoice.php` (no domain method for `calculateFinalAmount`)
- `database/migrations/2026_09_21_160008:17-23` (DECIMAL columns)

### Files Expected To Change

- `app/Services/Sales/PosOrderService.php` (add guard)
- `app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php` (add dynamic max + withValidator)
- `app/Models/Invoice.php` (optional: add `static calculateFinalAmount(...)` helper)
- `tests/Feature/Sales/CreditAndInvoiceManagementTest.php` + `tests/Unit/Sales/Requests/*` (add excessive-discount test)
- `config/finance.php` (new, optional: `max_discount_percent`)

### Database Changes

No database changes expected.

### Backend Changes

**Request layer (`StorePosInvoiceRequest.php`):**

- Keep `rules: discount_amount => nullable|numeric|min:0|max:999999` (loose) but add `withValidator`:
  ```php
  $subtotal = collect($items)->sum(fn($i)=> $products->get($i['product_id'])->retail_price * $i['quantity']);
  $maxDiscount = $subtotal + (float)$this->input('tax_amount',0);
  if ($discount + $scrapDeduction > $maxDiscount + 0.01) {
      $validator->errors()->add('discount_amount','الخصم + كهنة يتجاوز إجمالي الفاتورة قبل الخصم.');
  }
  // also: if discount > subtotal * 0.5 and user is cashier without override → require manager code
  ```

**Service layer (`PosOrderService.php:128`):**

```php
$discountAmount = round((float)($data['discount_amount'] ?? 0),2);
$scrapDeduction = round(...,2);
$taxAmount = round((float)($data['tax_amount'] ?? 0),2);
if ($discountAmount + $scrapDeduction > $subtotal + $taxAmount + 0.01) {
    throw new \DomainException('مجموع الخصم وكهنة يتجاوز إجمالي الفاتورة.');
}
$finalAmount = round($subtotal + $taxAmount - $discountAmount - $scrapDeduction, 2);
// remove max(0, ...); if negative, the exception above already fired
if ($finalAmount < -0.01) throw new \LogicException('final_amount negative');
```

**Precision:** All `round(...,2)` immediately after each multiplication/addition; compare via `bccomp(...,2)` or `abs(diff) <= 0.01` with constant `EPSILON`.

### Frontend / Blade Changes

- `resources/views/admin/sales/pos/partials/cart-panel.blade.php` (if exists) — show “Discount exceeds subtotal” inline error; disable Submit.

### Test Changes

- Add:
  ```php
  test('excessive discount exceeding subtotal is rejected with 422')
  // Given subtotal 3200, When discount 4000, Then 422 and no Invoice created
  test('discount plus scrap exceeding subtotal is rejected')
  test('discount exactly equals subtotal yields final_amount 0 and is allowed')
  test('service layer throws DomainException even if request is bypassed (direct service call)')
  ```

### Edge Cases

- `subtotal=0` (service product with 0 price) + `discount=0` → `final=0` allowed but `payments` must be `0` — but `payments.*.amount min:0.01` would reject, so zero-invoice needs special handling (business decision: allow 0 invoice for warranty replacement?).
- `tax_amount` may be null → treat as 0.
- Scrap `0` + discount `subtotal` → `final=0` → OK.

### Concurrency Considerations

None (pure validation, no locking change).

### Data Integrity Considerations

- Prevents **free invoices** that previously hid `max(0)` and would have created `paid_amount=0, remaining=0, status=paid` with `0` revenue — now fails fast.
- No existing invoice has `final_amount <0` (check `SELECT COUNT(*) FROM invoices WHERE final_amount <0` should be 0).

### Backward Compatibility

- Previously a cashier could submit `discount=999999` and get a `0` invoice that passed. Now it will 422. This is **intentional breaking change** for correctness; document in release notes.

### Migration / Rollback Strategy

No migration. Rollback is `git revert`.

### Acceptance Criteria

- [ ] `POST /admin/pos` with `subtotal 3200, discount 4000` → 422 `الخصم + كهنة يتجاوز`.
- [ ] Direct `PosOrderService::processPosSale([... discount 4000])` throws `DomainException` even without Request.
- [ ] `final_amount` never negative in DB (`SELECT COUNT(*) WHERE final_amount <0` ==0).
- [ ] New test `excessive discount` passes.

### Validation Commands

```bash
php artisan test --filter="SalesAndPurchasesRequestsTest"
php artisan test --filter="excessive discount"
rg -n "max\(0" app/Services/Sales/PosOrderService.php  # should be 0 after fix
```

---

## 11. Phase 4 — Credit Settlement & Branch Isolation

### Objective

Harden **FIFO settlement, receipt uniqueness, branch isolation, and manager override** — the most concurrency-sensitive finance path — without weakening existing `lockForUpdate`.

### Issues Covered

```
FIN-C06  // receipt_number UNIQUE too strict
FIN-H03  // branch_id nullable
FIN-H04  // magic 0.009/0.01
FIN-H05  // hardcoded override 9999
FIN-H06  // settle no branch filter
FIN-H07  // +0.01 overpay
```

### Preconditions

- Phase 3 discount guard in place (so settlement amount is not based on a bogus final_amount).
- Business decisions B-05 (global vs branch customer), B-06 (receipt uniqueness), B-07 (manager override policy) made.

### Repository Files To Inspect

- `app/Services/Sales/PosOrderService.php:471-583` (`settleCustomerDebt`, `isManagerOverrideValid`, `processPosSale` branch fallback)
- `app/Http/Controllers/Admin/CreditCustomerController.php:63-114` (`settlePayment`)
- `app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php:238-262` (same `isManagerOverrideValid` duplicate)
- `database/migrations/2026_09_21_160010:18` (`receipt_number UNIQUE`)
- `app/Models/Customer.php:14-25` (is customer global?)
- `routes/web.php` (`credit.settle` middleware)
- `.env.example`, `config/app.php` (no `MANAGER_OVERRIDE_CODE`)

### Files Expected To Change

- `database/migrations/2026_09_30_fix_receipt_number_unique.php` (change unique to composite or drop)
- `app/Services/Sales/PosOrderService.php` (extract `EPSILON`, fix FIFO branch filter, fix `+0.01`, extract `ManagerOverrideService`)
- `app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php` (remove duplicate `isManagerOverrideValid`, inject service)
- `app/Http/Controllers/Admin/CreditCustomerController.php` (add `branch_id` to settle, ensure `receipt_number` idempotency)
- `app/Models/Customer.php` (if adding `branch_id` to customer? — depends on B-05)
- `config/finance.php` (new: `epsilon`, `manager_override` settings)
- `.env.example` + `config/app.php` (add `MANAGER_OVERRIDE_CODE`)
- `app/Services/ManagerOverrideService.php` (new, or `app/Support/Finance/MONEY.php`)

### Database Changes

**Migration 1 — Receipt uniqueness:**

```php
// 2026_09_30_fix_receipt_number_unique.php
Schema::table('credit_ledger_entries', function (Blueprint $table) {
    $table->dropUnique(['receipt_number']); // existing unique name may be credit_ledger_entries_receipt_number_unique
    // Option A: make it non-unique, add index
    $table->index('receipt_number');
    // Option B: composite unique per customer: $table->unique(['customer_id','receipt_number']);
});
// If choosing B, need to handle NULLs: MySQL allows multiple NULLs in unique, so not a problem for auto-generated null receipts.
```

**Migration 2 — Branch isolation (if B-05 says customer is branch-scoped):**

- No customer `branch_id` column today (verified `Customer.php` has no `branch_id`). If business says “one customer can have invoices in many branches”, then **do not** add `branch_id` to `customers`; instead add `branch_id` filter to settlement query via `Invoice.branch_id`. That requires no migration.

- If business says “customer belongs to one branch”, then add `customers.branch_id FK nullable` — but this is a big decision, so default to **no migration** and make settlement branch-aware via `Invoice.branch_id`.

No other DB changes.

### Backend Changes

**1. Centralize money epsilon:**

```php
// config/finance.php or app/Support/Money.php
return [
    'epsilon' => env('FINANCE_EPSILON', 0.01),
    'manager_override_code' => env('MANAGER_OVERRIDE_CODE'), // no default
];
// In PosOrderService:
private const EPSILON = 0.01; // or read from config
```

Replace all `0.009`, `0.01`, `0.05` with `self::EPSILON` and explicit `round(...,2)`.

**2. Fix `settleCustomerDebt` branch isolation (FIN-H06):**

```php
$branchId = $data['branch_id'] ?? auth()->user()?->branch_id;
$openInvoices = Invoice::where('customer_id',$customerId)
    ->when($branchId, fn($q)=>$q->where('branch_id',$branchId))
    ->where('remaining_amount','>', self::EPSILON)
    ->whereNotIn('status',['cancelled','refunded','partially_refunded'])
    ->orderBy('id')->lockForUpdate()->get();
```

If `Customer` is global, the `when` is optional but should be **explicit** per request `branch_id` to prevent cross-branch leakage.

**3. Fix `+0.01` overpay (FIN-H07):**

```php
if ($amount > $currentBalance + self::EPSILON) throw ...
// and
if ($currentBalance <= self::EPSILON) throw "no debt";
// and
$balanceAfter = max(0, round($currentBalance - $amount,2));
if ($balanceAfter < 0.005) $balanceAfter = 0;
```

**4. Fix receipt idempotency (FIN-C06):**

```php
// In settleCustomerDebt, before creating CreditLedgerEntry:
if ($receiptNumber && CreditLedgerEntry::where('receipt_number',$receiptNumber)->exists()) {
    throw new \DomainException('رقم الإيصال مستخدم مسبقاً.');
}
// Also for InvoicePayment: same transaction_reference per invoice is OK, but ensure unique per (invoice_id, transaction_reference) if needed
// Alternative: generate receipt_number server-side if null: 'REC-'.date('Ymd').'-'.Str::upper(Str::random(6))
```

**5. Harden manager override (FIN-H05):**

- Remove `mgr_override_99` and `config('app.manager_override_code','9999')` fallback.
- Inject `ManagerOverrideService` that:
  - reads hashed code from `settings` table or `env('MANAGER_OVERRIDE_CODE_HASH')`,
  - uses `hash_equals` (constant-time) or `Hash::check` against **one** stored hash, not all managers,
  - rate-limits via `RateLimiter::hit('override:'.request()->ip(), 5)`.
- Extract duplicate `isManagerOverrideValid` from `StorePosInvoiceRequest` and `PosOrderService` into the service.

**6. Fix `branch_id` nullable (FIN-H03):**

- `StorePosInvoiceRequest.php:24` change to `'branch_id' => ['required','exists:branches,id']`.
- `PosOrderService.php:193` change fallback to `throw new InvalidArgumentException('branch_id required')` instead of `?? 1`; keep `auth()->user()->branch_id` as default only if request does not send it, but validate that user has `sell-in-branch` permission.

### Frontend / Blade Changes

- `resources/views/admin/credit/statement.blade.php:245` `max="{{ $customer->current_credit_balance }}"` in settle modal — ensure it uses `round(...,2)` and respects `EPSILON`.
- `resources/views/admin/sales/credit.blade.php` settle form: add hidden `branch_id` field.

### Test Changes

- Add:
  ```php
  test('settle with same receipt_number twice fails with 422')
  test('settle exceeding balance by 0.01 fails')
  test('settle across branches does not settle other branch invoice') // requires two branches
  test('manager override with wrong code fails even if user is admin')
  test('concurrent settle on same customer: second waits and succeeds or fails gracefully') // uses DB::transaction + lockForUpdate, test with two parallel requests via \Illuminate\Support\Facades\DB::transaction + threading? Use Pest `concurrently` via `Http::fake`? Simpler: unit test lockForUpdate existence via mock.
  ```

### Edge Cases

- `receipt_number = null` → many nulls allowed in unique index (MySQL) — OK.
- `amount = 0.005` → `round` to `0.01`? Must validate `min:0.01` in Request.
- Customer with `current_credit_balance = 0.009` (tiny due to rounding) → settle should treat as `0` (EPSILON).
- Concurrent settlements: `lockForUpdate` on `Customer` ensures second waits; after first commits, second sees reduced balance and may fail `amount > currentBalance`.

### Concurrency Considerations

- `DB::transaction` + `lockForUpdate` on `Customer` (485) and `Invoice` (515) **must be kept**. No change weakens it. The only addition is `branch_id` filter inside the same lock scope — still inside transaction.

### Data Integrity Considerations

- After fix, `customer.current_credit_balance == sum(remaining_amount)` will hold per branch if branch filter is applied; add scheduled `SystemDiagnosticService` check for this invariant.

### Backward Compatibility

- Existing `receipt_number` values are unique today, so dropping unique to index is safe (no duplicates exist). If any duplicate exists (should not), migration will fail — check `SELECT receipt_number, COUNT(*) ... HAVING COUNT>1` before migration.
- Changing `branch_id` to required is breaking for API clients that omit it — provide fallback to `auth()->user()->branch_id` for 1 release with deprecation warning.

### Migration / Rollback Strategy

- Receipt migration: `up` drops unique, creates index; `down` recreates unique — will fail if duplicates were inserted after up. Document: rollback only if no duplicates.
- No data migration required.

### Acceptance Criteria

- [ ] `POST /admin/credit/settle` with same `receipt_number` twice → second 422 `مستخدم مسبقاً`.
- [ ] `POST /admin/pos` without `branch_id` → 422.
- [ ] `settleCustomerDebt` with `amount = balance + 0.005` → 422, not  success.
- [ ] Cross-branch settlement does not touch other branch’s invoices (assert via `Invoice::where(branch_id, other)->remaining_amount` unchanged).
- [ ] `rg -n "mgr_override_99|9999" app/` returns 0.
- [ ] `rg -n "0.009" app/` returns 0 (replaced by `EPSILON`).

### Validation Commands

```bash
php artisan test --filter="settling customer debt"
php artisan test --filter="CreditAndInvoiceManagementTest"
rg -n "lockForUpdate" app/Services/Sales/PosOrderService.php
php artisan migrate
```

---

## 12. Phase 5 — Unified Invoice Filtering & Statistics

### Objective

Create **one authoritative filter** that every consumer (paginator, stats, dashboard, export) must use, eliminating the current three-way divergence.

### Issues Covered

```
FIN-C02  // duplicate filters
FIN-H02  // search excludes serial/product
FIN-M02  // date_from <= date_to
FIN-M03  // whereDate disables index
FIN-M05  // total_remaining_credit includes refunded
FIN-M07  // preset loses branch_id
```

### Preconditions

- Phase 1-4 have fixed `status` enum and discount logic, so filter on `status` will be stable.

### Repository Files To Inspect

- `app/Services/Sales/PosOrderService.php:21-62` (canonical filter)
- `app/Http/Controllers/Admin/SalesInvoiceController.php:20-60`
- `resources/views/admin/sales/invoices.blade.php:103-475` (filter form, preset JS)
- `app/Http/Controllers/Admin/DashboardController.php:31` (revenue filter)

### Files Expected To Change

- `app/Services/Sales/InvoiceFilter.php` (new value object) **or** `app/Support/Finance/InvoiceQueryBuilder.php` (new)
- `app/Services/Sales/PosOrderService.php` (refactor to use new filter)
- `app/Http/Controllers/Admin/SalesInvoiceController.php` (delegate to service)
- `app/Http/Controllers/Admin/DashboardController.php` (reuse filter for sales periods)
- `resources/views/admin/sales/invoices.blade.php` (add hidden `branch_id`, fix preset)
- `tests/Feature/Sales/CreditAndInvoiceManagementTest.php:260` (date filter test)

### Database Changes

No database changes expected.  
Optionally add composite index `invoices(status, branch_id, created_at)` if `EXPLAIN` shows slow, but not required for correctness.

### Backend Changes

**Create `app/Services/Sales/InvoiceFilter.php`:**

```php
final class InvoiceFilter {
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public ?int $branch_id = null,
        public ?string $date_from = null, // Y-m-d
        public ?string $date_to = null,
        public ?string $payment_method = null,
    ) {}
    public static function fromArray(array $data): self { /* trim, validate date format */ }
    public function apply(Builder $query): Builder {
        if ($this->search) $query->where(function($q){ $q->where('invoice_number','like',"%{$this->search}%")
            ->orWhereHas('customer', fn($cq)=>$cq->where('name','like',"%{$this->search}%")->orWhere('phone','like',"%{$this->search}%"))
            ->orWhereHas('items.product', fn($pq)=>$pq->where('name','like',"%{$this->search}%")->orWhere('sku','like',"%{$this->search}%"))
            ->orWhereHas('items', fn($iq)=>$iq->where('battery_serial_number','like',"%{$this->search}%")); });
        if ($this->status) $query->where('status', $this->status);
        if ($this->branch_id) $query->where('branch_id', $this->branch_id);
        if ($this->date_from) $query->where('created_at','>=',$this->date_from.' 00:00:00');
        if ($this->date_to) $query->where('created_at','<=',$this->date_to.' 23:59:59');
        // exclude cancelled/refunded from sales by default? Add flag $includeRefunded = false
        return $query;
    }
}
```

**In `PosOrderService`:**

```php
public function getPaginatedInvoices(array $filters): LengthAwarePaginator {
    $filter = InvoiceFilter::fromArray($filters);
    return Invoice::query()->with([...])->tap(fn($q)=>$filter->apply($q))->latest('id')->paginate()->withQueryString();
}
public function getInvoiceStats(array $filters): array {
    $filter = InvoiceFilter::fromArray($filters);
    $q = Invoice::query()->tap(fn($qq)=>$filter->apply($qq))->whereNotIn('status',['cancelled','refunded','partially_refunded']);
    return [
        'total_sales' => (float)$q->clone()->sum('final_amount'),
        'invoices_count' => (int)$q->clone()->count(),
        // etc, all from same filtered $q
    ];
}
```

**Date validation:** In `InvoiceFilter::fromArray`, if `date_from && date_to && date_from > date_to`, throw `ValidationException` or swap and add warning.

**Controller:** `SalesInvoiceController::index` becomes:
```php
$filter = InvoiceFilter::fromArray($request->only([...]));
$invoices = $this->posOrderService->getPaginatedInvoices($filter->toArray());
$stats = $this->posOrderService->getInvoiceStats($filter->toArray());
```

**Dashboard:** `DashboardController::index` for `salesToday` etc should reuse same `InvoiceFilter` with `date_from= today, date_to=today` etc, instead of manual `where('created_at','>=', $todayStart)`.

### Frontend / Blade Changes

- `invoices.blade.php:103` filter form: add `<input type="hidden" name="branch_id" value="{{ request('branch_id') }}">` if branch selector exists; ensure `applyDatePreset` preserves all hidden inputs (it already submits the whole form, so adding hidden fixes FIN-M07).
- Status dropdown: add `partially_refunded` and `cancelled` options.
- Show validation error for `date_from > date_to` via `@error('date_from')`.

### Test Changes

- Add `test('invoice filter with date_from after date_to returns 422')`.
- Add `test('search by battery serial finds invoice')`.
- Add `test('stats and paginator totals match for same filter')` — assert `stats['total_sales'] == sum($invoices->pluck('final_amount'))` for same filter.

### Edge Cases

- `date_from` without `date_to` → filter from that date to now.
- `search = "  "` (spaces) → trimmed to null, ignored.
- `branch_id` of branch user does not belong to → 403 (if Gate added in Phase 4).

### Concurrency Considerations

Read-only.

### Data Integrity Considerations

- Unified filter ensures `total_sales` never diverges from paginated sum — prevents audit mismatch.

### Backward Compatibility

- Filter API is `GET ?search=&status=&branch_id=&date_from=&date_to=` — unchanged. Only internal implementation changes.
- If `whereDate` → `whereBetween` change, results for same `date_from/to` are **identical** for date strings (since we use `00:00:00` to `23:59:59`), so no break.

### Migration / Rollback Strategy

No migration. Rollback is `git revert` of the new `InvoiceFilter` class and controller changes.

### Acceptance Criteria

- [ ] `SalesInvoiceController` no longer contains manual `where('invoice_number'...` — delegates to `InvoiceFilter`.
- [ ] `EXPLAIN SELECT ... WHERE created_at >= '2026-09-01 00:00:00'` uses `INDEX(branch_id,created_at)` (no `DATE()`).
- [ ] `GET /admin/invoices?date_from=2026-09-30&date_to=2026-09-01` → 422 or auto-swapped.
- [ ] `stats['total_sales']` equals sum of paginated `final_amount` for same filter.

### Validation Commands

```bash
rg -n "whereDate.*created_at" app/Http/Controllers/Admin/SalesInvoiceController.php app/Services/Sales/PosOrderService.php # should be 0
php artisan test --filter="sales invoices index filters by date_from"
php artisan test --filter="InvoiceFilter"
```

---

## 13. Phase 6 — Dashboard & Revenue Integrity

### Objective

Define **one canonical revenue formula** and make Dashboard KPIs **derived** from the same `InvoiceFilter` + `InvoicePayment` vs `CreditLedgerEntry` distinction, so revenue is never double-counted.

### Issues Covered

```
FIN-C03  // revenue definition
FIN-C04  // refunded excluded (again, now via unified filter)
FIN-M04  // round without decimals
FIN-L05  // N+5 whereHas
```

### Preconditions

- Phase 5 unified filter exists, so Dashboard can reuse it.

### Repository Files To Inspect

- `app/Http/Controllers/Admin/DashboardController.php:23-80` (revenue), `185-189` (cat stats), `230-246` (weekly trend)
- `app/Services/Sales/PosOrderService.php:416` (cashier summary)
- `resources/views/admin/dashboard/partials/kpi-cards.blade.php:13` (`إيرادات ومبيعات المركز` copy)

### Files Expected To Change

- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Services/Sales/PosOrderService.php` (optional: extract `RevenueCalculator`)
- `resources/views/admin/dashboard/partials/kpi-cards.blade.php` (if copy fix)
- `tests/Feature/Admin/DashboardSalesFilterTest.php:22` (assertSee string)

### Database Changes

No database changes expected.

### Backend Changes

**Canonical definitions (document in `config/finance.php` or docblock):**

```php
// Sales (gross) = SUM(invoices.final_amount WHERE status NOT IN (cancelled,refunded,partially_refunded))
// Cash Revenue = SUM(invoice_payments.amount WHERE payment_method != 'credit' AND invoice.status NOT IN (...))
// Credit Sales = SUM(invoice_payments.amount WHERE payment_method='credit')
// Credit Collections = SUM(credit_ledger_entries.amount WHERE entry_type='payment_collection')
// Refunds = SUM(credit_ledger_entries.amount WHERE entry_type='refund') + ABS(SUM(negative invoice_payments))
// Net Revenue = Cash Revenue + Credit Collections - Refunds (cash portion)
```

**In `DashboardController::index`:**

- Replace manual `$baseInvoices = where('status','!=','cancelled')` with `InvoiceFilter`:
  ```php
  $filterToday = new InvoiceFilter(date_from: $today, date_to: $today);
  $salesToday = $this->posOrderService->getInvoiceStats($filterToday->toArray())['total_sales'];
  ```
- For revenue, create `RevenueService::getRevenueForPeriod(date_from, date_to)` that does:
  ```php
  return InvoicePayment::whereHas('invoice', fn($q)=>$q->whereNotIn('status',['cancelled','refunded','partially_refunded']))
      ->where('payment_method','!=','credit')
      ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
      ->sum('amount');
  ```
- Fix `round()` to `round(...,2)` and `number_format(...,2)`.
- Extract `scopeActivePayments` to `InvoicePayment` model to remove N+5:
  ```php
  // InvoicePayment.php
  public function scopeActive($q){ return $q->whereHas('invoice', fn($qq)=>$qq->whereNotIn('status',['cancelled','refunded','partially_refunded'])); }
  ```

### Frontend / Blade Changes

- `kpi-cards.blade.php:13` currently `إيرادات ومبيعات المركز` is actually **more accurate** than the test’s `إجمالي مبيعات المركز`; update test to expect the real copy, not the blade.
- Show both “إجمالي المبيعات (دفترية)” and “الإيراد النقدي المحصل” clearly.

### Test Changes

- Fix `DashboardSalesFilterTest:22` `assertSee('إجمالي مبيعات المركز')` → `assertSee('إيرادات ومبيعات المركز')` **or** fix blade to `إجمالي مبيعات المركز` — product decision, but test must match reality.
- Add:
  ```php
  test('dashboard revenue does not double count credit collection')
  // Create credit invoice 3500 (credit 3500), settle 2000 → revenue should be 2000 (InvoicePayment) not 4000
  test('refunded invoice not counted in sales')
  ```

### Edge Cases

- Refunded invoice with `final_amount 3500` and `paid 0` — should not be in sales nor revenue.
- Partially refunded: sales should be net of refund? Business decision B-04 (see §19).

### Concurrency Considerations

Read-only.

### Data Integrity Considerations

- After fix, `Dashboard totalSales` + `refunds` = `SUM(final_amount)` previously — so historical totals will drop by sum of refunded invoices. Document in release notes.

### Backward Compatibility

- Dashboard API `GET /admin?sales_period=today` JSON structure unchanged; only `total` value changes (now excludes refunded). Frontend must not cache old total.

### Migration / Rollback Strategy

No migration.

### Acceptance Criteria

- [ ] `GET /admin?sales_period=today` with one `paid` 3200 + one `refunded` 3500 → `total` == 3200, not 6700.
- [ ] Settlement 2000 on credit invoice → `revenue` == 2000, `creditCollected` == 2000, `revenue` != 4000.
- [ ] `DashboardSalesFilterTest` passes.

### Validation Commands

```bash
php artisan test --filter=DashboardSalesFilterTest
php artisan test --filter="revenue does not double count"
```

---

## 14. Phase 7 — Customer Statement & Credit UI Integrity

### Objective

Make **customer statement and credit UI** fully **DB-backed, paginated, and entry_type-correct**, removing localStorage-driven KPIs and truncated history.

### Issues Covered

```
FIN-M01  // already fixed in Phase1, but verify statement view again
FIN-M06  // credit.blade localStorage KPIs
FIN-M08  // statement limit 50/100 no pagination
FIN-M09  // payment_method semantics (document)
FIN-L01  // double number_format
```

### Preconditions

- Phase 1 fixed `total_amount` → `final_amount` and `entry_type` badge, but statement pagination and credit.blade DB vs localStorage remain.

### Repository Files To Inspect

- `app/Http/Controllers/Admin/CreditCustomerController.php:23-141`
- `resources/views/admin/credit/statement.blade.php:139,184`
- `resources/views/admin/sales/credit.blade.php:439`
- `resources/views/admin/invoices/show.blade.php` (transaction_reference vs reference_number)

### Files Expected To Change

- `app/Http/Controllers/Admin/CreditCustomerController.php` (add pagination)
- `resources/views/admin/credit/statement.blade.php` (already fixed FIN-C01/M01, now add pagination UI)
- `resources/views/admin/sales/credit.blade.php` (replace `window.AlHusseiniSales` with server JSON)
- `resources/views/admin/invoices/show.blade.php:224` (`reference_number` → `transaction_reference`)

### Database Changes

No database changes expected.

### Backend Changes

**`CreditCustomerController::index`:** Currently `Customer::where(...)->get()` (FIN-H08) — but for this phase, focus on statement. Keep index as is for Phase 8.

**`CreditCustomerController::statement`:**

```php
public function statement(Request $request, Customer $customer) {
    $invoices = $customer->invoices()
        ->where('remaining_amount','>', 0.01)
        ->with(['payments','items.product'])
        ->orderByDesc('id')->paginate(20, ['*'], 'invoices_page');
    $ledgers = $customer->creditLedgers()
        ->with('collectedByUser')->orderByDesc('id')->paginate(50, ['*'], 'ledgers_page');
    // for JSON: return paginator as is
}
```

**`credit.blade.php`:** Replace `renderCreditDashboard()` that reads `localStorage` with `fetch('/admin/credit?json')` that returns `CreditCustomerController::index` JSON (already supports `wantsJson`). Keep `AlHusseiniSales` as fallback only if fetch fails.

### Frontend / Blade Changes

- `statement.blade.php`: add `{{ $invoices->links() }}` and `{{ $ledgers->links() }}`; fix `number_format` double-round by using `{{ $inv->final_amount }}` already cast to `decimal:2` → `number_format($inv->final_amount,2)` is fine, just not `round` before.
- `invoices/show.blade.php:224` `{{ $payment->reference_number ?? '-' }}` → `{{ $payment->transaction_reference ?? '-' }}`.
- `credit.blade.php`: remove `visibleCreditCount` local filter if server pagination used, or keep client filter but hydrate from server data.

### Test Changes

- Add:
  ```php
  test('statement paginates invoices and ledgers')
  test('statement shows correct payment method badge and transaction_reference')
  test('credit index JSON matches DB totals, not localStorage')
  ```

### Edge Cases

- Customer with 0 open invoices but 100 ledger entries: invoices tab empty, ledgers paginated.
- Customer with no ledger: show “لا توجد حركات”.

### Concurrency Considerations

None.

### Data Integrity Considerations

- Pagination ensures full history is accessible; previously `limit 100` truncated.

### Backward Compatibility

- `GET /admin/credit/{customer}/statement?json` with `?invoices_page=2` is additive; old `?page` still works if controller uses `paginate` without explicit page name? Use `invoices_page` to avoid collision.

### Migration / Rollback Strategy

No migration.

### Acceptance Criteria

- [ ] `GET /admin/credit/{id}/statement` shows `final_amount` not `0.00`.
- [ ] `GET /admin/credit/{id}/statement?invoices_page=2` returns next 20 invoices.
- [ ] `credit.blade.php` KPI `totalOutstanding` equals `SELECT SUM(current_credit_balance)` from DB, not localStorage.
- [ ] `invoices/show.blade.php` shows `transaction_reference`.

### Validation Commands

```bash
php artisan test --filter="customer statement"
rg -n "AlHusseiniSales" resources/views/admin/sales/credit.blade.php # should be reduced
```

---

## 15. Phase 8 — Performance & Maintainability

### Objective

Eliminate **confirmed bottlenecks** (full collection loads, `whereDate` index miss, enum string compares) without premature optimization.

### Issues Covered

```
FIN-H08  // getDailyCashierSummary loads all
FIN-H09  // processReturn branch check
FIN-L02  // phantom balance in tests
FIN-L03  // restrictOnDelete
FIN-L04  // enum casts
FIN-L06  // env missing
```

### Preconditions

- All functional finance fixes (Phases 1-7) done, so performance changes do not mask logic bugs.

### Repository Files To Inspect

- `app/Services/Sales/PosOrderService.php:416` (summary)
- `app/Http/Controllers/Admin/SalesInvoiceController.php:85` (return)
- `app/Models/Invoice.php` (casts)
- `database/migrations/2026_09_21_160008:12` (restrict)
- `.env.example`, `config/app.php`

### Files Expected To Change

- `app/Services/Sales/PosOrderService.php` (aggregate queries)
- `app/Models/Invoice.php` (add casts `status => InvoiceStatus::class` if Enum created, or `casts: status => 'string'` documented)
- `app/Enums/InvoiceStatus.php` (new, if decided)
- `database/migrations/2026_09_30_change_branch_fk_to_null_on_delete.php` (optional)
- `.env.example`, `config/app.php` or `config/finance.php`
- `tests/Feature/Sales/CreditAndInvoiceManagementTest.php` (fix phantom balance)

### Database Changes

**Optional Migration — Branch FK:**

```php
Schema::table('invoices', function (Blueprint $table) {
    $table->dropForeign(['branch_id']);
    $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
});
```

This allows archiving a branch without blocking. If business wants strict, keep `restrict`.

**No other DB changes.**

### Backend Changes

**`getDailyCashierSummary`:**

```php
public function getDailyCashierSummary(int $branchId, string $date): array {
    $base = Invoice::where('branch_id',$branchId)->whereDate('created_at',$date)->whereNotIn('status',['cancelled','refunded','partially_refunded']);
    $totalSales = (float)$base->clone()->sum('final_amount');
    $totalScrap = (float)$base->clone()->sum('scrap_deduction_amount');
    $invoicesCount = (int)$base->clone()->count();
    // payments aggregated in DB:
    $totals = InvoicePayment::whereHas('invoice', fn($q)=>$q->where('branch_id',$branchId)->whereDate('created_at',$date))
        ->selectRaw("SUM(CASE WHEN payment_method='cash' THEN amount ELSE 0 END) as cash")
        ->selectRaw("SUM(CASE WHEN payment_method='card' THEN amount ELSE 0 END) as card")
        ->selectRaw("SUM(CASE WHEN payment_method='credit' THEN amount ELSE 0 END) as credit")
        ->first();
    // batteries count still needs join, but can be: InvoiceItem::whereHas('invoice', ...)->whereHas('product', is_battery)->sum('quantity')
}
```

**`Invoice` casts:**

```php
protected function casts(): array {
    return [
        ...,
        'status' => InvoiceStatus::class, // if app/Enums/InvoiceStatus.php exists
        'payment_method' => InvoicePaymentMethod::class,
    ];
}
```

**Tests L02:** In `CreditAndInvoiceManagementTest:57`, instead of `Customer::create(['current_credit_balance'=>5000])`, create `Invoice` + `CreditLedgerEntry` so `balance == sum(remaining)`.

### Frontend / Blade Changes

None.

### Test Changes

- Update phantom balance tests to create proper ledger.
- Add `test('branch can be deleted when invoices are cancelled')` if FK changed.

### Edge Cases

- `getDailyCashierSummary` for day with 0 invoices → sums are 0, not null.
- Enum cast with `partially_refunded` — ensure `InvoiceStatus` includes it before casting.

### Concurrency Considerations

None.

### Data Integrity Considerations

- Aggregate queries must use same `whereNotIn` as unified filter, else summary vs table mismatch reappears.

### Backward Compatibility

- Changing `Invoice::status` to Enum cast may break `=== 'paid'` string compare in Blade — update to `$invoice->status === InvoiceStatus::Paid` or keep string cast if Enum not added.

### Migration / Rollback Strategy

- Branch FK migration: `down` reverts to `restrictOnDelete`. Safe if no orphaned `branch_id` nulls were created.

### Acceptance Criteria

- [ ] `getDailyCashierSummary` does not call `->get()` on invoices; uses `SUM`/`COUNT` in DB.
- [ ] `Invoice` has enum cast or documented string comparison.
- [ ] `.env.example` contains `MANAGER_OVERRIDE_CODE=`.
- [ ] No test creates `current_credit_balance` without corresponding `CreditLedgerEntry`.

### Validation Commands

```bash
rg -n "->get\(\)" app/Services/Sales/PosOrderService.php # should not be in getDailyCashierSummary
php artisan test --filter="CashierSummary"
cat .env.example | grep MANAGER_OVERRIDE_CODE
```

---

## 16. Phase 9 — Full Finance Regression & Hardening

### Objective

Lock all finance behavior with a **regression matrix** that proves every P0/P1 is covered and no future change can regress.

### Issues Covered

All 33 — as regression.

### Preconditions

- Phases 1-8 merged to `main`.

### Repository Files To Inspect

All finance files + `tests/`

### Files Expected To Change

- `tests/Feature/Sales/*` (new tests)
- `tests/Unit/Sales/*` (new unit tests)
- `tests/Feature/Admin/DashboardSalesFilterTest.php` (fixed copy)
- `phpunit.xml` (maybe add `FINANCE` group)

### Database Changes

No database changes expected.

### Backend Changes

None (test-only).

### Frontend / Blade Changes

None.

### Test Changes

Create a **single** `tests/Feature/Sales/FinanceRegressionTest.php` with the matrix below, each as `test('...')`.

#### Regression Matrix

| # | Scenario | Given | When | Then | Covers |
|---|----------|-------|------|------|--------|
| R-01 | Cash invoice | `subtotal 3200, discount 0` | `POST /pos` cash 3200 | `Invoice: final=3200, paid=3200, remaining=0, status=paid, branch correct` | I-02, FIN-C09 |
| R-02 | Credit invoice | `credit_limit 5000` | `POST /pos` credit 3500 | `Customer.balance=3500, Ledger invoice_debt 3500, Invoice remaining=3500` | I-03, I-04 |
| R-03 | Partial + full settlement FIFO | Customer has invoices 2000 (id1) + 1500 (id2) | `settle 2500` | `id1: remaining 0, paid 2000, status paid; id2: remaining 1000, status partially_paid; Customer 1000; 2 InvoicePayments + 1 Ledger` | FIN-H06, I-03 |
| R-04 | Cross-branch isolation | Customer has invoice in branch A 2000 | `settle branch B 2000` | `branch A invoice unchanged` | FIN-H06 |
| R-05 | Discount exceeding subtotal | `subtotal 3200, discount 4000` | `POST /pos` | `422` | FIN-C09/H01 |
| R-06 | Discount + scrap exceeding | `subtotal 3200, discount 1000, scrap 2500` | `POST /pos` | `422` | FIN-C09 |
| R-07 | Zero final (discount == subtotal) | `discount 3200` | `POST /pos cash 0` | `final 0, status paid, no ledger` | I-01, I-02 |
| R-08 | Excessive receipt duplicate | `receipt REC-001` used | `settle REC-001 twice` | second `422 receipt used` | FIN-C06 |
| R-09 | Concurrent settlement | Two requests `settle 1000` same customer balance 1500 | `parallel` | One succeeds, second gets new balance, no negative | FIN-H06 concurrency |
| R-10 | Cash return full | `paid invoice 3200 cash` | `return 3200` | `InvoicePayment -3200, status refunded, stock +1` | FIN-C05 |
| R-11 | Credit return partial | `unpaid 3500` | `return 1500` | `remaining 2000, status partially_refunded, ledger refund 1500` | FIN-C05/C08 |
| R-12 | Return exceeding qty | `qty 1` | `return qty 2` | `422` | already exists |
| R-13 | Statement shows final_amount | `invoice final 3500` | `GET statement` | `assertSee 3500.00 not 0.00` | FIN-C01 |
| R-14 | Entry_type badges | `ledger invoice_debt` | `GET statement` | `assertSee فاتورة بيع بالآجل` | FIN-M01 |
| R-15 | Date filter swapped | `date_from 2026-09-30 date_to 2026-09-01` | `GET invoices` | `422 or swapped result` | FIN-M02 |
| R-16 | whereDate index | `date_from 2026-09-01 date_to 2026-09-30` | `GET invoices` | `EXPLAIN uses index(branch_id,created_at)` (manual) | FIN-M03 |
| R-17 | Search by serial | `invoice with serial SN-123` | `GET invoices?search=SN-123` | `found` | FIN-H02 |
| R-18 | Dashboard excludes refunded | `paid 3200 + refunded 3500` | `GET dashboard sales_period=all` | `total 3200` | FIN-C04,6 |
| R-19 | Revenue not double counted | `credit invoice 3500, settle 2000` | `GET dashboard revenue` | `revenue 2000 not 4000` | FIN-C03 |
| R-20 | Branch isolation on sale | `cashier branch A` | `POST pos branch_id B` without permission | `403` | FIN-H03 |

Each test must use `RefreshDatabase`, `seed RolesAndPermissionsSeeder, InitialDataSeeder`, and assert DB `credit_ledger_entries` and `invoice_payments`.

### Edge Cases (also covered)

- `remaining_amount 0.009` → treated as 0.
- `receipt_number null` → many nulls allowed.
- `branch_id null` → 422.

### Concurrency Considerations

`R-09` explicitly tests `lockForUpdate` via `DB::transaction` + `concurrently` (use `spatie/fork` or two `Http::call` in parallel).

### Data Integrity Considerations

All tests assert invariants I-01..I-08 after each operation.

### Backward Compatibility

Test-only, no break.

### Migration / Rollback Strategy

No migration.

### Acceptance Criteria

- [ ] `php artisan test --filter=FinanceRegression` all 20 pass.
- [ ] `php artisan test` overall still passes (146+20 = ~166).
- [ ] `SystemDiagnosticService` ledger check still passes.

### Validation Commands

```bash
php artisan test --filter=FinanceRegression
php artisan test
```

---

## 17. Dependency Graph

```
Phase 0 (Baseline)
  ↓  must precede all
Phase 1 (Display & Schema integrity) ─────────────────┐
  ↓                                                    │
Phase 2 (Refund status) ──────────────┐                │
  ↓                                    │                │
Phase 3 (Discount guard) ─────────────┤                │
  ↓                                    │                │
Phase 4 (Settlement & Branch) ────────┤                │
  ↓                                    ↓                ↓
Phase 5 (Unified Filter) ◄─────────────┴────────────────┘  (needs correct status enum from Phase 2, and discount correctness from Phase 3 to keep stats meaningful)
  ↓
Phase 6 (Dashboard Revenue) ◄─────────── depends on Phase 5 (filter) and Phase 2 (refund exclusion)
  ↓
Phase 7 (Statement & Credit UI) ◄────── depends on Phase 1 (entry_type) and Phase 5 (filter) and Phase 6 (revenue definition for KPIs)
  ↓
Phase 8 (Performance) ◄──────────────── depends on Phases 5-7 being stable (so aggregation uses correct filter)
  ↓
Phase 9 (Regression) ◄──────────────── depends on all
```

**Parallelizable:** `Phase 4` can start in parallel with `Phase 2-3` if teams are separate, but **must merge before Phase 5**. `Phase 7` (statement) and `Phase 6` (dashboard) can run in parallel after Phase 5, but both must finish before Phase 8.

**Blocked by Business Decision:** Phase 4 (receipt uniqueness, branch-scoped customer, manager override) blocked until B-05/B-06/B-07 answered. Phase 2 partially blocked by B-02/B-03/B-04.

---

## 18. Risk Matrix

| Risk | Probability | Impact | Mitigation | Phase |
|------|-------------|--------|------------|-------|
| Incorrect historical balances if `refunded` filtering changes totals | High | High | Snapshot `SUM(remaining)` before/after; document release note; no auto-migration of historical `refunded` | 2,6 |
| Duplicate revenue if `InvoicePayment + Ledger` summed again | Medium | High | Canonical definition in `config/finance.php` + regression R-19 + code comment | 6 |
| Refund duplication (double `processReturn` on same invoice) | Medium | High | `lockForUpdate` + `status === refunded` guard; test R-11 second call 422 | 2 |
| Branch leakage (settlement pays other branch invoice) | Medium | High | Add `branch_id` to FIFO query; test R-04 | 4 |
| Concurrent settlement race (two cashiers settle same customer) | Medium | High | Keep `lockForUpdate` on Customer; test R-09 | 4 |
| `receipt_number` unique violation on duplicate submit | Low (but 500 if hit) | Medium | Change to index + idempotency check before insert | 4 |
| Migration incompatibility (enum change needs doctrine/dbal) | Low | Medium | Use `DB::statement` raw `ALTER TABLE ... MODIFY ENUM` for MySQL; for SQLite, recreate | 2 |
| Existing tests rely on phantom columns (`payment_status`) | High | Medium | Fix tests in Phase 1 before trusting suite; mark those tests as “invalid green” in baseline | 1 |
| Stale `localStorage` KPIs misleading managers | Medium | Medium | Replace with DB fetch in Phase 7; keep `AlHusseiniSales` as fallback only | 7 |
| Rounding differences (0.05 vs 0.01 epsilon) | High | Low | Centralize `EPSILON` in `config/finance.php`; use `round(...,2)` everywhere | 4 |
| `whereDate` → `whereBetween` changes result for edge times (00:00:00) | Low | Low | Use `00:00:00` to `23:59:59` inclusive, same as previous `whereDate` | 5 |
| Enum cast breaks string compare in Blade | Low | Low | Update Blade to `$invoice->status->value` or keep string cast until Enum ready | 8 |

---

## 19. Business Decisions Required

**Do not implement until answered; each blocks a phase.**

| # | Decision | Options | Technical Impact if Undecided | Blocks Phase | Default Recommendation |
|---|----------|---------|-------------------------------|--------------|------------------------|
| B-01 | Should `invoices` have `payment_status`/`invoice_type`/`total_amount` columns? | A) No, keep single `status`+`final_amount` (recommended). B) Yes, add columns. | A) Fix tests/views to use `status`/`final_amount` (Phase 1). B) Requires migration + backfill + dual-status sync. | 1 | **A) No** — current `status` already covers `paid/partially_paid/unpaid`. `total_amount` is just `final_amount`. |
| B-02 | What is the status for partial return? | `partially_refunded` vs `partially_returned` vs keep `refunded` | Affects enum migration, Blade badge, filter dropdown, `whereNotIn` lists | 2 | **`partially_refunded`** (matches existing `refunded`) |
| B-03 | How to refund cash portion? | A) Negative `InvoicePayment` (recommended, simplest). B) New `refunds` table. C) `CreditLedgerEntry` with `refund` and `customer_id null` | A) Minimal schema, keeps `paid_amount` accurate. B) More audit but needs new table. | 2 | **A) Negative `InvoicePayment`** |
| B-04 | Should `final_amount` stay gross after refund or be net? | A) Keep gross, refunds are separate legs (recommended). B) Reduce `final_amount` by refund | A) Historical gross preserved; dashboard must exclude refunded. B) Changes I-02 invariant. | 2,6 | **A) Keep gross** |
| B-05 | Is `Customer` global or branch-scoped? | A) Global (one customer, many branches) B) Branch-scoped | A) Settlement should **not** filter by branch (leakage is actually correct). B) Settlement must filter by `branch_id` | 4 | **A) Global** — but settlement UI should still show branch of each invoice; ask product. |
| B-06 | Should `receipt_number` be globally unique? | A) No, index only (recommended). B) Unique per customer. C) Global unique | A) FIFO multi-invoice with one receipt needs same number on multiple `InvoicePayment` rows → global unique fails. | 4 | **A) Non-unique, indexed**; add `UNIQUE(customer_id, receipt_number)` only if business wants per-customer idempotency |
| B-07 | Is manager override allowed and how? | A) Hashed code in `settings` + RateLimiter (recommended). B) env `MANAGER_OVERRIDE_CODE_HASH` C) Remove override, require admin login | A) Single hash, constant-time, no `9999` default | 4 | **A) settings hash + env fallback** |
| B-08 | What is net revenue definition? | `Cash Payments + Collections - Refunds` vs `Cash Payments only` | Affects Dashboard `revenue` KPI and whether `creditCollected` is added | 6 | **`Cash Revenue` = `InvoicePayment` non-credit; `Net Revenue` = `Cash Revenue - cash refunds`; `Collections` shown separately** |
| B-09 | Should a failed `discount > subtotal` be 422 or clamp to 0? | A) 422 (recommended). B) Clamp `max(0)` | A) Fail fast, correct. B) Hides error | 3 | **A) 422** |

**Action:** Product owner to answer B-01..B-09 **before Phase 1 kickoff** (B-01) and **before Phase 2/4** (others). Plan assumes **Recommended** defaults if no answer.

---

## 20. Testing Strategy

### 20.1 General

- Every P0/P1 gets a **Given/When/Then** regression test in Phase 9.
- Use `RefreshDatabase` + `sqlite :memory:` + `seed(RolesAndPermissionsSeeder, InitialDataSeeder)`.
- Assert **both** DB state (`assertDatabaseHas`) and **response** (`assertOk`, `assertSee`).
- Never assert phantom columns.

### 20.2 Example (for FIN-C01)

```php
// Given an invoice with final_amount = 3500
// When the customer statement is opened
// Then the displayed invoice total must be 3500.00 and total_amount must never be referenced
test('statement displays final_amount not total_amount', function () {
    $customer = Customer::factory()->create();
    $invoice = Invoice::create([
        'invoice_number'=>'INV-TEST-001','branch_id'=>Branch::first()->id,
        'customer_id'=>$customer->id,'cashier_id'=>User::first()->id,
        'subtotal'=>3500,'final_amount'=>3500,'paid_amount'=>0,'remaining_amount'=>3500,
        'status'=>'unpaid','payment_method'=>'credit'
    ]);
    $response = $this->actingAs(User::first())->get(route('admin.credit.statement',$customer));
    $response->assertOk()->assertSee('3,500.00')->assertDontSee('total_amount');
    $this->assertDatabaseHas('invoices',['id'=>$invoice->id,'final_amount'=>3500]);
});
```

### 20.3 Coverage Target

- Line coverage on `PosOrderService` finance methods: >90% (currently high but with phantom paths).
- No test may create `Customer.current_credit_balance` without a corresponding `CreditLedgerEntry` + `Invoice`.

---

## 21. Deployment Strategy

1. **Tag baseline:** `git tag baseline-finance-2026-09-30` (Phase 0).
2. **Migrations first:** Phases 2 and 4 migrations (`partially_refunded`, `receipt_number` index) run via `php artisan migrate --force` **before** code deploy (additive, backward compatible).
3. **Code deploy:** Deploy Phases 1-4 as one release (financially atomic); Phases 5-8 as second release (reporting/performance); Phase 9 as third (tests only, no downtime).
4. **Cache clear:** `php artisan view:clear && config:clear` after each deploy (Blade changes).
5. **Smoke:** `php artisan test --filter=FinanceRegression` on staging with production-like MySQL (not only `sqlite`) to catch `DECIMAL` vs `float` differences.
6. **Canary:** Enable for branch 1 only first, watch `SystemDiagnosticService` ledger check.

---

## 22. Rollback Strategy

| Phase | Rollback | Risk |
|-------|----------|------|
| 0 | `git checkout baseline-finance-2026-09-30` | None |
| 1 | `git revert` Blade/tests; no migration → instant | Low |
| 2 | Revert enum migration: `ALTER TABLE invoices MODIFY status ENUM('paid','partially_paid','unpaid','cancelled','refunded')` **fails if any `partially_refunded` row exists** → make rollback a no-op (keep enum value) and just revert code | Medium |
| 3 | No migration → `git revert` | Low |
| 4 | `receipt_number` unique re-add: `ALTER TABLE ... ADD UNIQUE(receipt_number)` fails if duplicates exist → check `SELECT receipt_number, COUNT(*) ... HAVING COUNT>1` before rollback | Medium |
| 5 | No migration → `git revert` | Low |
| 6 | No migration → `git revert` | Low |
| 7 | No migration → `git revert` | Low |
| 8 | Branch FK `nullOnDelete` → `restrictOnDelete` revert fails if any `branch_id` is null (should be none) | Low |
| 9 | Test-only → `git revert` | None |

**Data migration required:** None except optional one-off correction for historical `refunded` that were actually partial (do not auto-run; provide `php artisan finance:correct-historical-refunded --dry-run`).

---

## 23. Final Acceptance Criteria

**Overall:**

- [ ] `rg -n "total_amount" resources/views/admin/credit/statement.blade.php` ==0 and `rg -n "payment_status" app/Models/Invoice.php` ==0.
- [ ] `rg -n "mgr_override_99|9999" app/` ==0 and `rg -n "0.009" app/` ==0 and `config/finance.php` exists with `epsilon`.
- [ ] `php artisan test` passes: 146 original + ~20 new regression = ~166 total, 0 fail.
- [ ] `php artisan migrate:fresh --seed` + `php artisan system:diagnose` (if exists) shows ledger `customer.current_credit_balance == sum(remaining)` for all customers.
- [ ] Manual: Create cash invoice 3200, credit invoice 3500, partially settle 2000 FIFO, partially return 1500, verify `statement.blade.php` shows `final_amount 3500`, `remaining 1500`, ledger badges `فاتورة بيع بالآجل` / `مرتجع مبيعات`, dashboard `totalSales` excludes refunded, `revenue` == cash payments only.

**Per-phase criteria** listed in §8-16 each must be checked before next phase starts.

---

## 24. What, Why, Where, How, Dependencies, Risks, Tests, Acceptance, Rollback — Per Phase Summary

| Phase | WHAT | WHY | WHERE | HOW | DEPENDENCIES | RISKS | TESTS | ROLLBACK |
|-------|------|-----|-------|-----|--------------|-------|-------|----------|
| 0 | Freeze baseline | So later diffs are measurable | `BASELINE.md` + `phpunit.xml` | `migrate:status` + `rg` + `test --compact` | — | None | Record only | `git tag` |
| 1 | Fix display & phantom schema | Tests lie, UI shows 0.00 | `statement.blade`, `SearchService`, tests | Replace `total_amount` → `final_amount`, fix `match` | 0 | Breaks API that read `total_amount` (none) | `statement displays final_amount` | `git revert` |
| 2 | Correct refund accounting | Cash not refunded, status wrong | `PosOrderService`, `invoices` enum | Negative `InvoicePayment` + `partially_refunded` | 1 + B-02/03/04 | Historical `refunded` overstated | `partial return` | Keep enum value |
| 3 | Guard discount | Free invoices via `max(0)` | `StorePosInvoiceRequest`, `PosOrderService` | `throw if discount+scrap > subtotal+tax` | 2 | Previously 0 invoice now 422 (intentional) | `excessive discount 422` | `git revert` |
| 4 | Harden settlement | Branch leak, weak override, receipt collision | `PosOrderService`, `CreditCustomerController`, migration | `EPSILON`, `branch_id` filter, `receipt` index, `ManagerOverrideService` | 3 + B-05/06/07 | Unique violation if duplicate receipts exist | `receipt duplicate 422` | Drop index fails if dups |
| 5 | Single filter | DRY, index miss, date swamp | `InvoiceFilter`, `SalesInvoiceController`, `DashboardController` | `whereBetween` + `InvoiceFilter` VO | 1-4 | `whereDate` → `whereBetween` time edge | `stats == paginator sum` | `git revert` |
| 6 | Revenue single source | Double count, refunded in sales | `DashboardController`, `RevenueService` | `InvoicePayment active scope` + `round(...,2)` | 5 | Totals drop by refunded sum (expected) | `revenue not double` | `git revert` |
| 7 | Statement pagination & DB KPIs | Truncated history, localStorage drift | `CreditCustomerController`, `credit.blade` | `paginate` + fetch DB JSON | 1,5,6 | Old `?page` param collision | `statement paginates` | `git revert` |
| 8 | Performance | N+1, `get()->sum` | `PosOrderService`, `Invoice` casts | `SUM()` in DB, enum casts | 5-7 | Enum cast breaks string compare | `CashierSummary` | Revert FK |
| 9 | Regression | Lock all | `tests/Feature/Sales/FinanceRegressionTest.php` | 20 Given/When/Then | 1-8 | None | 20 tests | `git revert` |

---

**End of Plan — Ready for implementation.** No code was modified in this phase. All file paths, columns, and statuses were verified against the live repository on 2026-09-30.

