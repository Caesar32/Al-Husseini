# Routes

- Source: routes/web.php only (routes/console.php has just the default `inspire` command; no api.php). Generated from `php artisan route:list --except-vendor -v` on 2026-09-30: 96 routes.
- Every route carries the `web` group. `auth` redirects guests to admin.login and `guest` sends logged-in users to admin.dashboard (bootstrap/app.php).
- Permission checks happen through route middleware `can:<permission>` (Spatie permissions are resolved through Gate). The aliases `role`, `permission` and `role_or_permission` are registered but no route uses them.
- No custom middleware classes exist (there is no app/Http/Middleware directory).
- Format below: METHOD URI → Controller@method [middleware beyond web] {FormRequest} (route name).

## Public / root
- GET / → closure. A guest goes to admin.login. A user with the `cashier` role, or one who lacks dashboard.view but has pos.access, goes to admin.pos.index. Everyone else goes to admin.dashboard.
- GET lang/{locale} → closure: sets session `locale` when the value is ar or en, then redirects back (switch-lang). App::setLocale is never called. The value is only read in layouts master, master-without-nav and head-css to switch between RTL and LTR CSS (a `?lang=en` query also does this).
- GET refresh-csrf → closure: returns JSON {csrf_token, status}. Used as a session keep-alive (refresh_csrf).
- GET up → framework health check.

## Auth (prefix admin, name admin.)
- GET admin/login → Auth\AuthController@showLoginForm [guest] (admin.login)
- POST admin/login → Auth\AuthController@login [guest] (admin.login.submit)
- GET admin/register → closure: redirects to login with a flash saying registration is admin-only [guest] (admin.register)
- POST admin/logout → Auth\AuthController@logout [auth] (admin.logout)
- GET admin/lockscreen → Auth\LockScreenController@show [auth] (admin.lockscreen)
- POST admin/lockscreen/unlock → Auth\LockScreenController@unlock [auth] (admin.lockscreen.unlock)

## Core admin (all [auth])
- GET admin → Admin\DashboardController@index [can:dashboard.view] (admin.dashboard)
- GET admin/global-search → Admin\SearchController@globalSearch [auth only] (admin.global_search)
- GET admin/profile → Admin\ProfileController@index (admin.profile)
- PUT admin/profile/info → ProfileController@updateInfo (admin.profile.info)
- PUT admin/profile/password → ProfileController@updatePassword (admin.profile.password)
- POST admin/profile/avatar → ProfileController@updateAvatar (admin.profile.avatar)
- GET admin/settings → Admin\SettingController@index [can:settings.manage] (admin.settings)
- POST admin/settings → SettingController@update [can:settings.manage] (admin.settings.update)
- GET admin/system-diagnostics → Admin\SystemDiagnosticController@index [can:settings.manage] (admin.diagnostics.index)
- POST admin/system-diagnostics/audit → SystemDiagnosticController@runAudit [can:settings.manage] (admin.diagnostics.run_audit)
- POST admin/system-diagnostics/simulate → SystemDiagnosticController@runSimulation [can:settings.manage] (admin.diagnostics.run_simulation)
- Resource admin/roles → Admin\RoleController (index, create, store, show, edit, update, destroy) [can:roles.manage] (admin.roles.*). RoleController has no show() method, so GET admin/roles/{role} errors.
- GET admin/users → Admin\UserController@index [can:users.manage] (admin.users.index)
- POST admin/users → UserController@store [can:users.manage] (admin.users.store)
- PUT admin/users/{user} → UserController@update [can:users.manage] (admin.users.update)
- POST admin/users/{user}/role → UserController@updateRole [can:users.manage] (admin.users.role)
- POST admin/users/{user}/toggle-status → UserController@toggleStatus [can:users.manage] (admin.users.toggle-status)
- POST admin/users/{user}/reset-password → UserController@resetPassword [can:users.manage] (admin.users.reset-password)
- GET admin/starter → closure: view admin.starter (admin.starter)
- GET admin/404 → closure: view admin.errors.404 (admin.error.404)

## HR (prefix admin/hr, name admin.hr., all [auth])
- GET hr/employees → Hr\EmployeeController@index [can:employees.view] (admin.hr.employees)
- POST hr/employees → EmployeeController@store [can:employees.create] {StoreEmployeeRequest} (admin.hr.employees.store)
- GET hr/employees/{employee} → EmployeeController@show [can:employees.view] (admin.hr.employees.show)
- PUT hr/employees/{employee} → EmployeeController@update [can:employees.edit] {UpdateEmployeeRequest} (admin.hr.employees.update)
- DELETE hr/employees/{employee} → EmployeeController@destroy [can:employees.delete] (admin.hr.employees.destroy)
- GET hr/attendance → Hr\AttendanceController@index [can:attendance.view] (admin.hr.attendance)
- POST hr/attendance/punch → AttendanceController@recordManual [can:attendance.manual_punch] {RecordPunchRequest} (admin.hr.attendance.punch)
- POST hr/attendance/mark-absent → AttendanceController@markAbsent [can:attendance.manual_punch] (admin.hr.attendance.mark_absent)
- GET hr/leaves → Hr\LeaveController@index [can:leaves.manage] (admin.hr.leaves.index)
- POST hr/leaves → LeaveController@store [can:leaves.manage] {StoreLeaveRequest} (admin.hr.leaves.store)
- POST hr/leaves/{leave}/status → LeaveController@updateStatus [can:leaves.manage] {UpdateLeaveStatusRequest} (admin.hr.leaves.status)
- GET hr/deductions → Hr\DeductionController@index [can:deductions.manage] (admin.hr.deductions.index)
- POST hr/deductions → DeductionController@store [can:deductions.manage] {StoreDeductionRequest} (admin.hr.deductions.store)
- POST hr/deductions/{deduction}/status → DeductionController@updateStatus [can:deductions.manage] {UpdateDeductionStatusRequest} (admin.hr.deductions.status)
- GET hr/payroll → Hr\PayrollController@index [can:payroll.generate] (admin.hr.payroll)
- POST hr/payroll/generate → PayrollController@generate [can:payroll.generate] {GeneratePayrollRequest} (admin.hr.payroll.generate)
- GET hr/payroll/{payroll} → PayrollController@show [can:payroll.generate] (admin.hr.payroll.show)
- POST hr/payroll/{payroll}/approve → PayrollController@approve [can:payroll.approve] (admin.hr.payroll.approve)
- POST hr/payroll/{payroll}/disburse → PayrollController@disburse [can:payroll.disburse] (admin.hr.payroll.disburse)
- GET hr/reports → closure: view admin.hr.reports [can:reports.hr] (admin.hr.reports)
- GET hr/notifications → Hr\NotificationController@index [can:notifications.view] (admin.hr.notifications.index)
- POST hr/notifications/{id}/read → NotificationController@markAsRead [can:notifications.view] (admin.hr.notifications.read)
- POST hr/notifications/read-all → NotificationController@markAllAsRead [can:notifications.view] (admin.hr.notifications.readAll)

## Suppliers and purchases (all [auth])
- Resource admin/suppliers → Admin\SupplierController (index, create, store {StoreSupplierRequest}, show, edit, update {UpdateSupplierRequest}, destroy) (admin.suppliers.*). NO `can:` middleware. The controller has no authorize/Gate calls and both FormRequest::authorize() methods return true, so any authenticated user, cashiers included, can create, update or delete suppliers.
- GET admin/suppliers/{supplier}/ledger → SupplierController@ledger [can:suppliers.view] (admin.suppliers.ledger)
- POST admin/suppliers/{supplier}/payments → SupplierController@recordPayment [can:purchases.settle_payment] (admin.suppliers.payments)
- Resource admin/purchases (except edit/update/destroy) → Admin\PurchaseInvoiceController (index, create, store {StorePurchaseInvoiceRequest}, show) (admin.purchases.*). NO `can:` middleware and no in-controller authorization, the same gap as suppliers. The route parameter is {purchase}.
- GET admin/purchases/{purchase}/print → PurchaseInvoiceController@print [can:purchases.view] (admin.purchases.print)

## Sales / POS (all [auth])
- GET admin/pos → Admin\PosController@index [can:pos.access] (admin.pos.index)
- POST admin/pos → PosController@store [can:pos.access] {StorePosInvoiceRequest} (admin.pos.store)
- GET admin/pos/{invoice}/receipt → PosController@receipt [can:invoices.print] (admin.pos.receipt)
- GET admin/pos/{invoice}/warranty → PosController@warrantyCert [can:warranties.view] (admin.pos.warranty_cert)
- GET admin/invoices → Admin\SalesInvoiceController@index [can:invoices.view] (admin.invoices.index)
- GET admin/invoices/{invoice} → SalesInvoiceController@show [can:invoices.view] (admin.invoices.show)
- POST admin/invoices/{invoice}/return → SalesInvoiceController@processReturn [can:invoices.cancel] (admin.invoices.return)
- GET admin/credit → Admin\CreditCustomerController@index [can:credit.view] (admin.credit.index)
- POST admin/credit/settle → CreditCustomerController@settlePayment [can:credit.settle] (admin.credit.settle)
- GET admin/credit/{customer}/statement → CreditCustomerController@statement [can:credit.view] (admin.credit.statement)
- Legacy aliases under admin/sales (name admin.sales.) point to the same actions with the same permissions: GET sales/pos → PosController@index (admin.sales.pos); GET sales/invoices → SalesInvoiceController@index (admin.sales.invoices); GET sales/credit → CreditCustomerController@index (admin.sales.credit); POST sales/credit/settle → @settlePayment (admin.sales.credit.settle); GET sales/credit/{customer}/statement → @statement (admin.sales.credit.statement).
- GET admin/sales/customers → closure: view admin.sales.customers [can:customers.view]. GET admin/sales/products → closure: view admin.sales.products [can:products.view]. Neither view gets data from a controller; whether they are static or placeholder pages is UNVERIFIED.

## Warranty and scrap (all [auth])
- GET admin/warranties → Admin\WarrantyController@index [can:warranties.view] (admin.warranties.index)
- GET admin/warranties/verify → WarrantyController@verify [can:warranties.view] (admin.warranties.verify)
- POST admin/warranties/claims → WarrantyController@storeClaim [can:warranties.claim] {ProcessWarrantyClaimRequest} (admin.warranties.claims.store)
- POST admin/warranties/claims/{claim}/settle → WarrantyController@settleSupplier [can:warranties.approve_replace] (admin.warranties.claims.settle)
- GET admin/scrap-inventory → Admin\ScrapInventoryController@index [can:scrap.view] (admin.scrap.index)
- POST admin/scrap-inventory/sell-batch → ScrapInventoryController@sellBatch [can:scrap.transfer] {StoreScrapSaleBatchRequest} (admin.scrap.sell_batch)
- PUT admin/scrap-inventory/tiers → ScrapInventoryController@updateTiers [can:settings.manage] (admin.scrap.update_tiers)
