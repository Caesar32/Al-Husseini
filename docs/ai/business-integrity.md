# Business, Data and Frontend Integrity Audit

Snapshot 2026-09-30, working tree included. Evidence tags:
- [re-verified] means the code was read directly in this audit pass.
- [module-doc] means it was confirmed from source by the module-doc pass (sales.md, purchases.md, hr.md, warranty-scrap.md) and spot-checked, but not every line was re-read here.

Flow mechanics are not repeated; see the module docs. Security findings are in security-audit.md.

## A. Frontend ↔ backend integration (FE)

### FE-01 CRITICAL: POS UI sells from a mock catalog [re-verified]
- The catalog, search and customer list read `window.AlHusseiniSales` (public/assets/js/sales-store.js). It is a localStorage store seeded with string IDs `PROD-101…`/`CUST-…`, and nothing syncs it with the server: there is no fetch or axios call in sales-store.js.
- PosController::index passes `$products`, `$customers` and `$scrapTiers`, but no POS view uses them. Only `$technicians` is rendered.
- Payload builder in resources/views/admin/sales/pos/partials/scripts.blade.php:
  - `product_id: !isNaN(parseInt(id)) ? parseInt(id) : 1` (~L944), so every line becomes product id 1.
  - `customer_id` comes from parseInt of the mock ID, so it is null (~L941) and every sale is a walk-in.
  - The battery serial falls back to an invented `BAT-{timestamp}-{n}` (~L947-948), which defeats the mandatory unique-serial rule.
  - `discount_amount: 0` and `tax_amount: 0` are hard-coded (~L955-956).
- Impact: every sale through the real UI decrements stock of product #1, creates a warranty with a fake serial against product #1, and records the wrong revenue split. Credit sales are impossible, because a null customer is rejected for the credit method. The backend flow is correct only for direct service or test callers.
- Blocks: all sales reporting, stock, warranty and credit data produced via the UI.

### FE-02 CRITICAL: no backend for products, customers or vehicles [re-verified]
- resources/views/admin/sales/products.blade.php and customers.blade.php are routes returning bare views (routes/web.php L207, L210). All CRUD in them (saveProduct, saveCustomer) writes to localStorage only.
- In app/ there is no Product::create, Customer::create or CustomerVehicle::create outside SystemDiagnosticService (L287, L348, L356). The only other creation is the guest customer firstOrCreate (PosOrderService L281).
- Impact: the real catalog, customers and vehicles can only come from seeders or diagnostics. Purchases (`exists:products,id`) and POS need DB products that no UI can create. The permissions products.create/edit/delete and customers.create/edit/delete are never enforced because no endpoint exists.
- This is a prerequisite for fixing FE-01.

### FE-03 HIGH: HR reports page is fully mock [re-verified]
- The route closure at routes/web.php ~L154 passes no data. resources/views/admin/hr/partials/reports/scripts.blade.php L126, L136 and L151 build daily, monthly and range reports from `window.AlHusseiniHR` (hr-store.js).
- Impact: attendance and HR reports shown to management do not reflect the attendances table.

### FE-04 MEDIUM: credit page is partly mock [re-verified]
- Server-backed:
  - resources/views/admin/sales/credit.blade.php L421-426 hydrates `window.serverCreditData` from the controller, and KPIs and customer rows prefer it.
  - Settlement POSTs to route('admin.credit.settle') with numeric server IDs (L731-734).
- Still mock: the "collected this month" figure (L471-474), the payments tab (L463-464) and the payment badge (L486) come from localStorage.
- After a successful settlement the payment is also mirrored into the mock store.
- Impact: collections totals and payment history shown on this page are fake.

### FE-05 LOW: dashboard mock loaders are dead code, one path is live [re-verified]
- loadRecentInvoicesTable, loadCreditDuesList and loadLowStockAlerts (dashboard/partials/scripts.blade.php L158, L224, L267) read the mock store, but nothing calls them. The server-rendered widgets are authoritative.
- loadHRDashboardStats (L311) is bound to the `alhusseini-hr-updated` event (L113-116) and reads `AlHusseiniHR`. When that event fires is UNVERIFIED. If it fires, the attendance widget would be overwritten with mock numbers.

### FE-06 MEDIUM: payroll payslip and breakdown are estimates [module-doc]
- hr/partials/payroll/breakdown-table and modal-payslip compute basic + allowances − the latest 20 approved deductions system-wide. They ignore absence, overtime, commissions and debt, and do not read PayrollItem.

### FE-07 LOW: locale switch only changes direction [re-verified]
- See security-audit.md "Previous leads". /lang/en flips RTL/LTR CSS only. There are no translations.

### FE-08 LOW: mock stores and hard-coded URLs load on every page [re-verified]
- sales-store.js and hr-store.js load globally (vendor-scripts.blade.php) and seed localStorage.
- Several screens hard-code URLs such as `/admin/credit/{id}/statement`, `/admin/warranties/claims/{id}/settle` and `/admin/hr/...` instead of route() helpers.

## B. Business logic integrity (BIZ)

### Sales / Returns / Credit
- BIZ-01: see FE-01 (POS mock payload). It is the root cause of the sales data corruption on the UI path.
- BIZ-03 HIGH: the same items can be returned more than once [re-verified].
  - Where: PosOrderService::processSalesReturn L340+. Items are matched with `keyBy('product_id')` (L353), and each call checks only `qty <= original line qty` (L360).
  - Root cause: no returned-quantity tracking (no column or table), and `partially_refunded` invoices remain returnable.
  - Impact: repeating a partial return re-increments stock and re-refunds cash or credit without bound.
  - `keyBy` also collapses duplicate product lines.
- BIZ-04 HIGH: refund valuation ignores discounts [re-verified].
  - The refund is `qty × item.unit_price` (L367) and ignores invoice discount, scrap deduction and tax. The full-return test (L377) compares only this call's refund.
  - Impact: a refund can exceed what the customer paid. paid_amount is clamped at 0, but the negative InvoicePayment still records the full refund.
- BIZ-05 HIGH: partially refunded invoices vanish from totals [re-verified].
  - status `partially_refunded` is excluded wholesale from sales stats (PosOrderService L463, L483), from FIFO debt settlement (L556), from InvoicePayment::scopeActive (L24) and from the dashboard (DashboardController L31, L188, L250).
  - Impact: revenue is understated by the full invoice value, not just the returned part. Credit remaining on such invoices is never allocated by FIFO, while settleCustomerDebt still reduces Customer.current_credit_balance and writes the ledger. Balance and invoice remaining_amount then drift apart.
- BIZ-12a MEDIUM: invoice number from uniqid [re-verified]. `INV-{Ymd}-{substr(uniqid(),-6)}` (L205) has no sequence. A collision surfaces as a DB unique violation, which becomes a 500.
- BIZ-17 LOW:
  - Serial uniqueness checks all Warranty rows, including voided ones, so a returned battery cannot be resold. [module-doc]
  - The guest customer is created with firstOrCreate without a lock (L281), so it can race. [re-verified]

### Purchases / Suppliers
- BIZ-06 HIGH: supplier ledger disagrees with the balance when a purchase is paid at receipt [re-verified].
  - Where: PurchaseService::createDirectPurchase L176-215.
  - Behaviour: the balance increases only by `remaining`, yet the ledger writes `+remaining` and then `−paid` starting from the post-remaining balance.
  - Example: final 1000, paid 400 gives ledger 0→600→200 while current_balance is 600. Ledger sums ≠ balance.
  - Correct model: post `+final`, then `−paid`, and the balance ends at remaining.
- BIZ-11 MEDIUM: supplier payments not linked to invoices [module-doc, guard re-verified].
  - recordSupplierPayment (L222) is not allocated to purchase invoices, so paid, remaining and payment_status are frozen at creation.
  - The overpayment guard is only `balance <= 0 && amount > 1000` (L235).
  - A DomainException thrown there is not caught in SupplierController::recordPayment. The resulting 500 is UNVERIFIED.
- BIZ-18 MEDIUM: purchase returns unreachable [re-verified]. processPurchaseReturn (L262) exists in the interface and service but has no route or controller. It also does not revert WAC.
- BIZ-20 LOW: catalog and supplier gaps [module-doc].
  - Every purchase marks its supplier `is_primary_supplier=true`, so a product can have several primaries.
  - Supplier catalog sync is unreachable over HTTP because no `products` rule exists.
  - The suppliers.create view file is missing.
  - Supplier credit_limit is not enforced.

### Warranty / Claims / Scrap
- BIZ-07 HIGH: supplier settlement can repeat [re-verified].
  - WarrantyService::settleClaimWithSupplier L198-240 has no guard on the current supplier_resolution.
  - Repeating `settled_replacement` increments stock again. Repeating `settled_credit_note` reduces Supplier.current_balance again and writes another ledger entry.
- BIZ-13 MEDIUM: claim gaps [module-doc].
  - An already `claimed` warranty can receive further claims.
  - A replacement gets a fresh full warranty period on the same invoice_item_id, which makes the InvoiceItem::warranty hasOne ambiguous.
  - The supplier is taken from the replacement product's primary supplier.
  - rejection_reason and settle `notes` are validated but not stored.
- BIZ-12b MEDIUM: claim number race [re-verified]. WarrantyClaimObserver L11-17 numbers claims as this month's count + 1, which can collide under concurrency or after deletions, against the unique claim_number.
- BIZ-10 MEDIUM: scrap sale has no money record [re-verified]. ScrapBatteryService::dispatchScrapSaleBatch (L76-115) only updates status and batch_number. Buyer, payment method, sale amount and gross profit are echoed back but never persisted.
- BIZ-21 LOW: scrap odds and ends [module-doc].
  - Sales returns do not reverse scrap rows.
  - The scrap index list ignores branch while its metrics are branch-scoped.
  - Statuses `recycled` and warranty `expired` are never written.

### HR / Attendance / Leaves / Payroll
- BIZ-08 HIGH: payroll with overtime cannot be approved from the UI [re-verified].
  - resources/views/admin/hr/partials/payroll/batches-table.blade.php L39 computes the expected net as basic + allowances − deductions + carried, with no overtime.
  - PayrollService::assertPayrollTotalsConsistent (~L265-270) includes overtime, but only in the uncommitted working tree (`git diff app/Services/Hr/PayrollService.php`). Committed HEAD 2468e9b omits it, so at HEAD the server itself rejects every batch with overtime.
  - Impact: any batch with overtime is flagged "needs review" and its approve and disburse buttons are hidden (L53, L67).
- BIZ-09 MEDIUM: approved leave never ends [re-verified]. LeaveService::updateLeaveStatus L67 sets `employee.status = on_leave`, and no code reverts it. Payroll generation includes only `active` employees, so the employee silently drops out of every later payroll.
- BIZ-14 MEDIUM: attendance observer side effects.
  - AttendanceObserver::saving L23-24 sets status `late` whenever the grace period is exceeded, which overwrites the `holiday` status AttendanceService sets for employees on leave. [re-verified]
  - The late notification (AttendanceService L184) also fires again on check-out. [module-doc]
- BIZ-15 MEDIUM: payroll detail page missing [re-verified]. PayrollController::show L72 renders `admin.hr.payroll_show`, which does not exist (resources/views/admin/hr has only attendance, employees, payroll and reports), so the non-JSON request errors.
- BIZ-19 MEDIUM: commissions are never created [module-doc; test asserts it]. No production path creates TechnicianCommission rows (only SalesAndPosDataSeeder and diagnostics do), yet PayrollService adds approved commissions to gross pay.
- BIZ-22 LOW: payroll rule inconsistencies [module-doc].
  - The zero-net guard blocks a batch fully absorbed by debt even with confirm_debt_review.
  - The late penalty uses basic/30 while payroll uses basic/daysInMonth, and absence uses a fixed 26-day norm.
  - DeductionRule calculation fields are unused.
  - There are no leave balances and no overlap check.

### Admin / Settings
- BIZ-16 LOW [re-verified]: no business code reads Setting values (grep finds Setting::get / getGroup only in SettingController). VAT, allow_negative_stock, invoice_prefix, warranty_months_default and session_timeout_minutes have no effect.

## C. Transactions, concurrency, idempotency (verified summary)
- Sound:
  - processPosSale, processSalesReturn, settleCustomerDebt, createDirectPurchase, recordSupplierPayment, processInstantClaim, settleClaimWithSupplier, dispatchScrapSaleBatch, generateMonthlyPayroll and disbursePayroll all run in DB::transaction with lockForUpdate on the aggregates they mutate.
- Not idempotent:
  - Returns (BIZ-03) and supplier settlement (BIZ-07) have no state guard.
  - Client retries of POS submit create duplicate invoices because there is no idempotency key.
  - Receipt-number dedupe is now app-level only, via an exists() check under the customer lock (migration 000002 dropped the unique index). That check does not block the same receipt number across customers.
- Not transactional [module-doc]: applyForLeave, applyDeduction, updateDeductionStatus, approvePayroll and SupplierService::deleteSupplier.

## D. Database (DB)
- DB-01 HIGH: the SQLite migration disables constraint enforcement [re-verified].
  - Where: database/migrations/2026_09_30_000001 L18-33 edits sqlite_master with `writable_schema`, then runs `PRAGMA foreign_keys=OFF` and `ignore_check_constraints=ON` on the migrating connection, including in the catch path.
  - Impact: tests run on `:memory:` with RefreshDatabase in 31 of 34 test files, on the same connection. So every test after migration runs with no FK and no enum CHECK enforcement (TST-01). Dev MySQL uses a real `ALTER … ENUM`, so test and dev behaviour diverge.
  - pgsql is a no-op, so `partially_refunded` would violate the CHECK there.
- DB-02 MEDIUM: receipt-number migration can silently half-apply [re-verified from database.md evidence]. 2026_09_30_000002 swallows drop errors, so on MySQL the unique index can survive with a plain index added beside it. Receipt dedupe is now application-only.
- DB-03 LOW: redundant indexes [module-doc]. 2026_09_22_215442 adds a duplicate payrolls unique index because it checks the wrong index name. Several columns carry `unique()->index()` duplicates.
- DB-04 LOW: model and schema mismatches [module-doc].
  - Invoice::scrapBattery and technicianCommission are hasOne while the DB allows many rows.
  - received_by means employee on scrap rows but user on purchases.
  - No enum values are cast or centralised.
  - Soft-deleted rows keep their unique phone, sku and national_id values.
- FALSE POSITIVE (impact): database.md says "warranties.customer_id NOT NULL means a walk-in invoice cannot produce a warranty". PosOrderService attaches walk-in warranties to the firstOrCreate guest customer (L281), so warranties are produced. The schema difference is real but has no functional impact.
- FALSE POSITIVE (partial): sales.md says the credit page's "KPIs and customer rows fall back to localStorage". They prefer the always-present server data. Only the collected figure and payments tab are mock (FE-04).

## Previous leads: classification
- Mock POS products and customers: CONFIRMED (FE-01).
- Hard-coded product and customer IDs in sales: CONFIRMED (FE-01, product_id → 1 and customer_id → null).
- Duplicate returns: CONFIRMED (BIZ-03).
- Payroll overtime excluded from expected net: CONFIRMED in the register view (BIZ-08). The service side is fixed only in uncommitted code.
- SQLite constraints disabled by migration: CONFIRMED (DB-01).
