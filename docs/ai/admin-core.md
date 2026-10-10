# Admin Core (cross-cutting features)

## Purpose
- Auth (login/logout/lockscreen), users, roles/permissions (spatie/laravel-permission ^6.25), settings, dashboard, global search, profile, system diagnostics, locale switch, CSRF keep-alive, providers, config, layouts/frontend, seeders.
- Stack: PHP ^8.2, Laravel ^12.0. All admin routes in routes/web.php under prefix `admin`, name prefix `admin.`. No API routes file used for admin.

## Key files
- routes/web.php — all web routes (closures for `/`, `/lang/{locale}`, `/refresh-csrf`, `/admin/starter`, `/admin/404`, `/admin/hr/reports`, `/admin/sales/customers`, `/admin/sales/products`).
- bootstrap/app.php — redirectGuestsTo admin.login, redirectUsersTo admin.dashboard; aliases role / permission / role_or_permission (Spatie middleware); health route `/up`; exceptions block empty.
- bootstrap/providers.php — AppServiceProvider, HrServiceProvider, SalesAndPurchasesServiceProvider.
- app/Providers/AppServiceProvider.php::register — binds SearchServiceInterface -> SearchService.
- app/Providers/AppServiceProvider.php::boot — Model::preventLazyLoading(!production); Gate::before super-admin bypass; Attendance::observe(AttendanceObserver); WarrantyClaim::observe(WarrantyClaimObserver).
- app/Http/Controllers/Auth/AuthController.php::showLoginForm, ::login, ::logout
- app/Http/Controllers/Auth/LockScreenController.php::show, ::unlock
- app/Http/Controllers/Admin/UserController.php::index, ::store, ::update, ::updateRole, ::toggleStatus, ::resetPassword
- app/Http/Controllers/Admin/RoleController.php::index, ::create, ::store, ::edit, ::update, ::destroy
- app/Services/PermissionRegistry.php::getGroupedPermissions (UI groups/labels, is_sensitive flag), ::getRoleMetadata (Arabic labels/badges per role slug)
- app/Http/Controllers/Admin/SettingController.php::index, ::update; app/Models/Setting.php::get, ::set, ::getGroup, ::setMany
- app/Http/Controllers/Admin/DashboardController.php::index -> view admin.dashboard (+ resources/views/admin/dashboard/partials/*: charts, credit-dues, invoice-modal, kpi-cards, low-stock, recent-invoices, scripts, welcome-bar, workshop-attendance)
- app/Http/Controllers/Admin/SearchController.php::globalSearch; app/Services/SearchService.php::search, ::getSearchVariants; app/Contracts/SearchServiceInterface.php
- app/Http/Controllers/Admin/ProfileController.php::index, ::updateInfo, ::updatePassword, ::updateAvatar
- app/Http/Controllers/Admin/SystemDiagnosticController.php::index, ::runAudit, ::runSimulation; app/Services/Diagnostics/SystemDiagnosticService.php::runFullAudit, ::runLiveSimulation; app/Console/Commands/SystemDiagnoseCommand.php (`system:diagnose`)
- resources/views/admin/layouts/master.blade.php, master-without-nav.blade.php, partials/{head-css, vendor-scripts, topbar, sidebar, footer, customizer, page-title}.blade.php
- database/seeders/*.php; config/finance.php

## Flow per action
- GET `/` — guest -> admin.login; cashier role OR (no dashboard.view AND has pos.access) -> admin.pos.index; else admin.dashboard.
- Login (POST admin/login, guest mw) — field `login` (fallback `email`) + `password`; email-format -> Auth::attempt by email; else attempt by phone; else first User where name = input + Hash::check. Throttle key = lower(login)|ip, 5 attempts, 300s decay (RateLimiter); lockout seconds flashed to session for UI countdown. Inactive user (`is_active` false) -> logged out with error. Success: clear limiter, regenerate session, redirect()->intended (POS for cashier-like users, else dashboard).
- Register GET admin/register — just redirects to login with info message (no self-registration).
- Logout (POST, auth) — Auth::logout, session invalidate + regenerateToken, redirect login.
- Lockscreen GET admin/lockscreen — sets session `lockscreen_locked=true`, renders admin.auth.lockscreen with lockout countdown. POST unlock — validates password; per user+IP limiter: 5 failed attempts -> 300s lockout (separate attempts/lockout keys); success clears keys + session flag, redirect intended dashboard.
- Users (admin/users*, can:users.manage) — index filters search/branch_id/role/status, paginate 15; store requires role (exists:roles,name), password min:6, is_active=true, assignRole; update syncRoles single role, optional password; updateRole; toggleStatus; resetPassword (min:6).
- Roles (Route::resource admin/roles, can:roles.manage) — store: name regex [a-zA-Z0-9-_], Str::slug, guard web, syncPermissions; update: super-admin blocked (warning), system roles cannot be renamed; destroy: system roles and roles with users blocked; each write calls PermissionRegistrar::forgetCachedPermissions.
- Settings (GET/POST admin/settings, can:settings.manage) — validates company_name, company_phone, currency (required), vat_percentage 0-100, default_grace_period 0-120, monthly_working_days 1-31, daily_working_hours 1-24; checkboxes allow_negative_stock / lateness_alert_enabled / email_notifications_enabled forced to '1'/'0'; every other request field saved via Setting::set with group inferred from key name (company_/currency -> general; grace/shift/working/overtime -> hr; vat/invoice/scrap/warranty/stock -> sales; else security).
- Dashboard (GET admin, can:dashboard.view) — query `sales_period` in today|week(last 7 days)|month|year|all (invalid -> all). Sales = Invoice.final_amount excluding status cancelled/refunded/partially_refunded; cash revenue = InvoicePayment::active()->cash() by payment created_at; credit_collected = CreditLedgerEntry entry_type=payment_collection (informational); totals: Customer.current_credit_balance, customers/vehicles/products counts, low stock (current_stock <= reorder_threshold); category split (batteries / oils / greases / services by category slug) + scrap deduction; 7-day trend; recent invoices, debtors, low-stock list, today's attendance (present/late/absent+leave). Charts via ApexCharts in partials/scripts.blade.php.
- Global search GET admin/global-search?q= (or `query`) — JSON {success, data:{query,total_count,sections}}; < 2 chars -> empty; 5 results per section across employees, customers, vehicles, products, invoices, warranties, suppliers, purchase_invoices, warranty_claims; Arabic normalization variants (strip tashkeel; initial ا/أ/إ/آ; final ة/ه; final ي/ى). Frontend: public/assets/js/global-spotlight-search.js fetches `/admin/global-search`.
- Profile (auth only) — updateInfo name/email(unique)/phone; updatePassword requires current_password, min 8, confirmed; updateAvatar image jpeg/png/jpg/webp max 2MB, moved to public/uploads/avatars/avatar_{id}_{time}.{ext} (old file not deleted).
- Diagnostics (can:settings.manage) — index runs runFullAudit synchronously; POST audit -> JSON; POST simulate -> runLiveSimulation(request boolean `rollback`, default true). Audit checks: negative_stock, customer_credit_ledger, supplier_ledger, duplicate_warranty_serials, payroll_math_integrity, attendance_anomalies, orphan_scrap_batteries, invoices_payments_breakdown -> health_score. Simulation sectors: purchases/WAC, POS, credit, warranties, scrap, attendance, payroll, spotlight search; runs inside DB::transaction and throws `__SIMULATION_ROLLBACK__` to roll back.
- CLI `php artisan system:diagnose [--audit] [--simulate] [--keep]` — no flags = both; `--keep` persists simulation data.
- Locale GET /lang/{locale} (name switch-lang, no auth) — accepts ar|en, stores session('locale'), redirect back.
- CSRF refresh GET /refresh-csrf (name refresh_csrf, no auth) — returns {csrf_token, status:'active'}; vendor-scripts polls every 15 min and on tab visibilitychange, updates meta csrf-token and all input[name=_token].

## Permission model
- Guard `web`; teams false; wildcard off; cache expiration 24h (config/permission.php). Enforced mostly by route `can:` middleware, sidebar `@can`, and StorePosInvoiceRequest (invoices.discount).
- Super-admin bypass: AppServiceProvider Gate::before returns true if user hasRole('super-admin') (also seeded with all permissions).
- Permissions (52 in seeder, same set in PermissionRegistry): branches.view/create/edit/delete; pos.access; invoices.view/create/print/cancel/discount; customers.view/create/edit/delete; credit.view/settle/adjust_limit; products.view/create/edit/delete; scrap.view/transfer; suppliers.view/create/edit/delete; purchases.view/create/settle_payment; warranties.view/claim/approve_replace; employees.view/create/edit/delete; attendance.view/manual_punch; deductions.manage; leaves.manage; payroll.generate/approve/disburse; reports.financial/sales/hr; dashboard.view; notifications.view; roles.manage; users.manage; settings.manage.
- super-admin: all.
- accountant: pos.access, invoices.view/create/print, customers.view/create, credit.view/settle, products.view, scrap.view, purchases.view, purchases.settle_payment, suppliers.view, warranties.view, reports.financial, reports.sales.
- cashier: pos.access, invoices.view/create/print, customers.view/create, products.view, scrap.view, warranties.view.
- branch-manager: pos.access, invoices.view/create/print/discount, customers.view/create/edit, credit.view/settle, products.view/create/edit, scrap.view/transfer, warranties.view/claim, employees.view, attendance.view/manual_punch, deductions.manage, leaves.manage, payroll.generate, reports.sales, reports.hr.
- workshop-supervisor: products.view, warranties.view/claim/approve_replace, scrap.view, attendance.view.
- System roles (undeletable, RoleController::$systemRoles): super-admin, branch-manager, accountant, cashier, workshop-supervisor. Custom roles allowed via UI.
- Admin-core route gates: dashboard -> dashboard.view; settings + diagnostics + scrap tiers PUT -> settings.manage; roles resource -> roles.manage; users/* -> users.manage; profile, lockscreen, global-search, starter, 404 -> auth only.
- Permissions defined but never checked anywhere in app/routes/views (verified by grep): branches.*, invoices.create, customers.create/edit/delete, credit.adjust_limit, products.create/edit/delete, suppliers.create/edit/delete, purchases.create, reports.sales, reports.financial.

## Business rules
- Last super-admin protection: update/updateRole refuse to change the role of the only super-admin; toggleStatus refuses to deactivate the only active super-admin; users cannot deactivate themselves.
- Users have exactly one role (syncRoles with single value).
- Role slug = Str::slug(name); super-admin permissions cannot be edited via UI.
- Login identifiers: email, phone, or exact name; inactive accounts rejected at login.
- Password policies differ: admin-set passwords min 6; self-service profile change min 8.
- Dashboard excludes cancelled/refunded/partially_refunded invoices from sales and scrap totals; revenue excludes InvoicePayment method credit (via cash() scope).
- config/finance.php: epsilon (FINANCE_EPSILON, 0.01) for money comparisons; manager_override_code (MANAGER_OVERRIDE_CODE) / manager_override_hash (MANAGER_OVERRIDE_CODE_HASH), no default.

## Side effects
- Cache: Setting::get uses Cache::rememberForever("setting.{key}"); getGroup caches "settings.group.{group}"; set() forgets both keys. Spatie permission cache flushed on role store/update/destroy and in RolesAndPermissionsSeeder. RateLimiter (cache store) for login and lockscreen.
- Default cache store = database (CACHE_STORE), queue = database, session driver = database, SESSION_LIFETIME = 1440 (non-default; Laravel default 120). Timezone UTC. APP_LOCALE en in config/.env.example.
- Notifications (all `database` channel only): EmployeeLateNotification (AttendanceService), LeaveRequestedNotification (LeaveService), PayrollGeneratedNotification (PayrollService). Topbar polls `/admin/hr/notifications` every 30s (public/assets/js/admin-notifications.js); non-OK responses silently ignored.
- Observers registered: AttendanceObserver, WarrantyClaimObserver. InvoiceObserver and PurchaseInvoiceObserver exist in app/Observers but are intentionally NOT registered (logic lives in PosOrderService / PurchaseService per AppServiceProvider comment).
- Avatar upload writes to public/uploads/avatars (not storage disk).
- Diagnostics simulation with rollback=false (web `rollback=0` or CLI `--keep`) writes real records (may create a user/branch if none exist).
- No view composers, no Paginator::useBootstrap* calls, no custom gates besides Gate::before (verified by grep).

## Frontend stack
- Admin UI is a static-asset Bootstrap 5 admin theme served from public/assets (asset() helper), NOT Vite. RTL by default: head-css loads bootstrap-rtl.min.css + app-rtl.min.css, or LTR bootstrap.min.css + app.min.css when English; icons.min.css; public/assets/js/layout.js in head.
- vendor-scripts.blade.php loads: bootstrap.bundle, sweetalert2, simplebar, node-waves, feather-icons, lord-icon-2.1.0, flatpickr, choices.js, assets/js/plugins.js, sales-store.js, hr-store.js (localStorage-based stores), assets/js/app.js, global-spotlight-search.js, admin-notifications.js; then @yield('script') / @stack('scripts'); inline scripts: theme (light/dark) sync via localStorage/sessionStorage, window.showHrToast (Swal toast), fullscreen "seamless navigation" (fetch + swap .main-content, eval page scripts, rewrites let/const to var), CSRF keep-alive.
- Dashboard: apexcharts from public/assets/libs/apexcharts in partials/scripts.blade.php; bootstrap.Modal for invoice print modal.
- External: Google Fonts (Cairo, Almarai, Outfit); cdn.lordicon.com JSON.
- Vite (vite.config.js): laravel-vite-plugin inputs resources/css/app.css + resources/js/app.js, @tailwindcss/vite (Tailwind 4). resources/js only sets up axios + X-Requested-With. @vite used only in resources/views/welcome.blade.php, which no route renders. public/build exists (manifest.json).
- No translation files (no lang/ dir) and no __()/@lang in views; UI strings are hard-coded Arabic.

## Seeders
- DatabaseSeeder calls: RolesAndPermissionsSeeder, InitialDataSeeder, HrFactorySeeder, SettingsSeeder, ScrapPricingTiersSeeder, SupplierProductsSeeder; plus SalesAndPosDataSeeder unless env testing.
- InitialDataSeeder: MAIN branch, departments, job titles, and default users admin@ / accountant@ / cashier@alhusseini.com (roles super-admin / accountant / cashier) with a shared, weak, hard-coded default password (change in production).
- SettingsSeeder keys: company_* (name, short_name, tax_id, cr_id, phone, mobile, email, address), currency, default_grace_period, default_shift_start/end, monthly_working_days, daily_working_hours, overtime_rate_multiplier, vat_percentage, invoice_prefix, scrap_prefix, warranty_months_default, allow_negative_stock, lateness_alert_enabled, session_timeout_minutes, email_notifications_enabled.
- No seeded users for branch-manager or workshop-supervisor.

## Gotchas (verified)
- No app/Http/Middleware directory; no custom middleware at all. `is_active` is only checked at login — deactivating a user does not end existing sessions.
- Lockscreen is cosmetic: session `lockscreen_locked` is set/cleared but never enforced; any other URL works while "locked".
- Locale not applied: /lang/{locale} only stores session('locale'); nothing calls App::setLocale. Layouts read session('locale') (or `?lang=en`) only to pick dir/lang attrs and RTL vs LTR CSS.
- /lang/{locale} and /refresh-csrf are outside auth (refresh-csrf hands a token to guests too; harmless by design but note it).
- Global search has no permission checks: any authenticated user (e.g. cashier) can search employees (national_id, phone), suppliers, purchase invoices, credit balances. Many result URLs point to generic pages (dashboard, sales.invoices), not the record.
- Privilege escalation: a non-super-admin holding users.manage can assign role super-admin (validation only exists:roles,name) and reset any user's password including super-admins; holder of roles.manage can grant any permission to any non-super-admin role.
- Route::resource('roles') registers GET admin/roles/{role} (show) but RoleController has no show() -> error if hit.
- Suppliers (Route::resource suppliers, except ledger/payments) and purchases (index/create/store/show) have NO route middleware, no authorize in controllers, and FormRequests authorize() return true -> any authenticated user can create/edit/delete suppliers and record purchase invoices.
- Only super-admin has dashboard.view in seeds. workshop-supervisor (no dashboard.view, no pos.access) is redirected after login to admin.dashboard -> 403. branch-manager/accountant go to POS.
- Settings: SettingController::update persists every request field (any arbitrary key) to settings. No app code reads Setting values (only SettingController::index) — vat_percentage, allow_negative_stock, invoice_prefix, session_timeout_minutes, warranty_months_default etc. have no runtime effect (verified by grep).
- Setting::get caches the $default forever when the key is missing (cached until set() is called for that key).
- Dashboard donut shows fake 50/30/10/10 percentages when there are no category sales.
- Dashboard trend loop runs 3 whereHas queries per day for 7 days (21+ queries); not cached.
- preventLazyLoading is on outside production — lazy relation access throws in local/testing.
- Avatar replacement leaves old files in public/uploads/avatars.
- Seamless fullscreen navigation evals inline scripts and re-dispatches DOMContentLoaded/load, so page scripts may double-bind listeners (UNVERIFIED impact per page).
- RolesAndPermissionsSeeder assigns super-admin to User::first() — on a fresh DB no users exist yet at that point; InitialDataSeeder assigns roles itself.
- Diagnostics index runs the full audit on every page load (synchronous DB scans).
