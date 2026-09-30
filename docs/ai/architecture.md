# Architecture Map

Snapshot 2026-09-30 (HEAD 45b9ccc plus the uncommitted finance/POS working tree). This file builds on module docs sales.md, purchases.md, warranty-scrap.md, hr.md, admin-core.md, database.md and routes.md; details live there. Finding IDs (SEC-, BIZ-, DB-, FE-, TST-, ARC-) are defined in security-audit.md, business-integrity.md, testing-audit.md and this file.

## Stack and runtime
- Laravel 12.69.2, PHP 8.2.12. spatie/laravel-permission ^6.25. Tests use Pest 3 on SQLite `:memory:`; the dev database is MySQL (.env DB_CONNECTION=mysql). Session, cache and queue drivers are all `database`, and no queued jobs exist.
- UI: server-rendered Blade using a static Bootstrap 5 RTL theme from public/assets. There is no SPA or API. The Vite/Tailwind pipeline is configured but unused, since only welcome.blade.php calls @vite and no route renders it. Two global localStorage mock stores (public/assets/js/sales-store.js and hr-store.js) are loaded on every page by layouts/partials/vendor-scripts.blade.php.
- The app has none of these: custom middleware, Policies, Events, Listeners, Jobs, Actions or Traits. Authorization is only route `can:` middleware, plus Gate::before, which grants every ability to super-admin (AppServiceProvider::boot).

## Layers
- Route (routes/web.php, 96 routes, all in the `web` group) → `auth` / `guest` → `can:<permission>` → Controller (app/Http/Controllers/{Admin,Hr,Auth}) → FormRequest (14 classes; 13 have authorize() return true, the exception is StoreDeductionRequest) or inline `$request->validate` → Service, bound to an interface in HrServiceProvider / SalesAndPurchasesServiceProvider / AppServiceProvider → Eloquent models (32) under DB::transaction with lockForUpdate → Blade view, or JSON when wantsJson.
- Services: Hr/{Employee,Attendance,Leave,Deduction,Payroll,Notification}Service; Sales/{PosOrderService,WarrantyService,ScrapBatteryService,InvoiceFilter}; Purchases/{SupplierService,PurchaseService}; Finance/ManagerOverrideService (concrete, no interface); SearchService; Diagnostics/SystemDiagnosticService; PermissionRegistry (UI metadata only).
- Observers: two are registered (AttendanceObserver, WarrantyClaimObserver). Two are dead: InvoiceObserver and PurchaseInvoiceObserver still duplicate service side effects, and registering them would double-post (ARC-03).
- Notifications: three, database channel only, sent synchronously inside HR transactions.

## Modules and dependencies
- Sales/POS (PosOrderService) writes Invoice, InvoiceItem, InvoicePayment, CreditLedgerEntry, Customer.current_credit_balance, Product.current_stock, Warranty and ScrapBatteriesInventory. It reads ScrapPricingTier and Employee (technician) and calls ManagerOverrideService.
- Purchases (PurchaseService) writes PurchaseInvoice(+Item), Product.current_stock and cost_price (WAC), SupplierProduct, Supplier.current_balance and SupplierLedgerEntry.
- Warranty (WarrantyService) writes WarrantyClaim, Warranty, Product.current_stock, Supplier.current_balance and SupplierLedgerEntry. This is a cross-module write into the Purchases ledger.
- Scrap (ScrapBatteryService) writes ScrapBatteriesInventory status only. Its rows are created by PosOrderService.
- HR: PayrollService reads Attendance, EmployeeLeave, EmployeeDeduction and TechnicianCommission, and writes Payroll, PayrollItem and EmployeePayrollDebt. AttendanceObserver writes EmployeeDeduction (automatic late penalty).
- Diagnostics (SystemDiagnosticService) calls PosOrderService::processPosSale and PurchaseService::createDirectPurchase, creates Product/Customer/Employee rows and approves commissions. It depends on the legacy override codes (SEC-03), so it couples to every module.
- Dashboard (DashboardController) queries Invoice, InvoicePayment scopes, CreditLedgerEntry, Customer, Product and Attendance directly. It has no service and duplicates the status-exclusion rules found in PosOrderService, InvoicePayment::scopeActive and InvoiceFilter (ARC-02).
- Shared mutable aggregates that several services write to directly: Product.current_stock (POS sale/return, purchase, purchase return, warranty replace, warranty settle), Supplier.current_balance (purchase, supplier payment, purchase return, warranty credit note), Customer.current_credit_balance (sale, return, settlement). There is no stock-movement or balance-audit table (ARC-04).
- Dependency direction: HR has no link to Sales except TechnicianCommission, which no production code path creates (BIZ-19). Sales → Warranty/Scrap happens within one service. Warranty → Purchases goes through the ledger. No circular class dependencies were found.

## Major execution flows (details in the module docs)
- POS sale: POST admin/pos → can:pos.access → StorePosInvoiceRequest (stock, serial, scrap, discount/override, payment sum, credit limit) → PosController::store → PosOrderService::processPosSale (one transaction: locks products and customer; creates invoice via withoutEvents, payments, ledger, items, stock decrement, warranties, scrap rows) → JSON receipt URLs. Frontend payload issues are in FE-01.
- Sales return: POST admin/invoices/{invoice}/return → can:invoices.cancel → inline validation → PosOrderService::processSalesReturn (lock invoice, stock increment, void warranty, credit refund then cash refund as a negative InvoicePayment, status refunded or partially_refunded). See BIZ-03/04/05.
- Credit collection: POST admin/credit/settle → can:credit.settle → PosOrderService::settleCustomerDebt (lock customer, FIFO across open invoices, InvoicePayment per invoice, one ledger entry).
- Purchase: POST admin/purchases → auth only (SEC-02) → StorePurchaseInvoiceRequest → PurchaseService::createDirectPurchase (lock supplier and products, stock plus WAC, catalog, balance, ledger). See BIZ-06.
- Warranty claim: POST admin/warranties/claims → can:warranties.claim → ProcessWarrantyClaimRequest → WarrantyService::processInstantClaim. Settlement goes through settleClaimWithSupplier (BIZ-07).
- Payroll: generate → approve → disburse (PayrollService). Approve and disburse call assertPayrollTotalsConsistent, and disburse applies debt FIFO. See BIZ-08.
- Attendance punch: RecordPunchRequest → AttendanceService::recordPunch → the AttendanceObserver saving/saved hooks, then EmployeeLateNotification.

## Architectural risks
- ARC-01 (HIGH, CONFIRMED) Frontend and backend are split-brained. Several screens run on the localStorage mock stores instead of the backend: POS, products, customers, HR reports, parts of the credit page and a dead dashboard path. The backend is exercised mostly by tests and diagnostics, not by the UI. See FE-01..FE-05.
- ARC-02 (MEDIUM, CONFIRMED) The "countable invoice" rule (status NOT IN cancelled/refunded/partially_refunded) is copied into PosOrderService (L463, 483, 556), InvoicePayment::scopeActive (L24) and DashboardController (L31, 188, 250). There is no single scope or enum, and status values exist only in migrations.
- ARC-03 (MEDIUM, CONFIRMED) InvoiceObserver and PurchaseInvoiceObserver are dead code that duplicates service logic. Registering either would double-apply stock and ledger changes.
- ARC-04 (MEDIUM, CONFIRMED) Stock and balances change through raw increment/decrement/update in five or more services with no movement journal. Reconciliation depends on SystemDiagnosticService heuristics.
- ARC-05 (MEDIUM, CONFIRMED) Authorization lives only in route middleware. FormRequests return true, there are no policies, and there is no branch scoping (SEC-02, SEC-08). Any route added without `can:` is open to every authenticated user.
- ARC-06 (LOW, CONFIRMED) Fat controller and service boundary problems: DashboardController::index does all its aggregation inline (21+ queries for the trend); SettingController writes arbitrary keys; controllers for returns, settlement, supplier payments and scrap tiers use inline validation instead of FormRequests.
- ARC-07 (LOW, CONFIRMED) The `?? 1` fallbacks for the user, branch and employee id are in PosController, PurchaseInvoiceController, WarrantyController, ScrapInventoryController and PosOrderService. They silently attribute records to id 1.
- ARC-08 (LOW, CONFIRMED) The Settings table is written but never read by business code, so VAT, negative stock, prefixes and similar settings have no effect (BIZ-16).
