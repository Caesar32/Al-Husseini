# Security and Authorization Audit

Snapshot 2026-09-30, working tree included. CONFIRMED means the code was read directly for this audit. The permission catalogue and role matrix are in admin-core.md, and routes and middleware are in routes.md. Nothing below repeats them.

## Authorization model (verified)
- Authentication is session-based on the `web` guard (AuthController::login). A login is throttled 5 times per (login|ip) with a 300 s lockout. `is_active` is checked only at login (AuthController L87).
- Authorization is route `can:` middleware only. There are no Policies. FormRequest::authorize() returns true everywhere except StoreDeductionRequest. No controller calls authorize() or Gate. Gate::before lets `super-admin` bypass everything.
- Branch isolation does not exist. No controller or service restricts queries or writes to the user's branch_id. Request-supplied branch_id is accepted (for example ScrapInventoryController::index, PayrollController::generate via GeneratePayrollRequest, StorePosInvoiceRequest).

## Confirmed findings

### SEC-01 CRITICAL: avatar upload can store a server-executable file
- File: app/Http/Controllers/Admin/ProfileController.php::updateAvatar L71-94.
- Evidence: validation is `image|mimes:jpeg,png,jpg,webp|max:2048`, and both rules inspect the content-sniffed MIME type. The saved name, however, uses `getClientOriginalExtension()` (L85), and the file is moved into `public_path('uploads/avatars')` (L86, L92).
- Root cause: the extension comes from the client, the file is stored inside the web root, and the name is predictable (`avatar_{id}_{time}`).
- Impact: any authenticated user, including a cashier (the profile route is auth-only), can upload a valid image that carries a PHP payload under a `.php` extension. The nginx config in docs/DEPLOYMENT_GUIDE.md has `location ~ \.php$ { fastcgi_pass … }`, which would execute it, giving remote code execution. That the production config matches the guide is UNVERIFIED.
- Remediation: save through the Storage `public` disk (or a private disk) with `hashName()` / `guessExtension()` and an extension whitelist. Serve avatars via a URL, not from a raw public path. Add an nginx rule denying script execution under /uploads. Delete the old avatar file on replacement.

### SEC-02 HIGH: supplier and purchase routes have no permission checks
- Files: routes/web.php L165 (`Route::resource('suppliers')`) and L170 (`Route::resource('purchases')->except(edit,update,destroy)`). SupplierController and PurchaseInvoiceController contain no authorization. StoreSupplierRequest, UpdateSupplierRequest and StorePurchaseInvoiceRequest all return true from authorize().
- Impact: any authenticated user (cashier, workshop-supervisor) can:
  - create, edit and soft-delete suppliers;
  - post purchase invoices, which raise stock, rewrite Product.cost_price (WAC), set primary suppliers and raise the supplier balance;
  - read all purchase data.
- The permissions suppliers.view/create/edit/delete and purchases.view/create exist but are not enforced. The sidebar hides the links, which gives a false sense of control.
- Tests: SalesAndPurchasesControllersTest covers 403 only for other routes (TST-03).
- Remediation: add `can:` middleware for each action (suppliers.view/create/edit/delete, purchases.view/create), or authorize in the FormRequests. Add 403 tests.

### SEC-03 HIGH: manager override accepts hard-coded codes
- File: app/Services/Finance/ManagerOverrideService.php::isValid L42-47. `in_array($code, ['mgr_override_99','9999'])` is accepted unconditionally, even when a hash is configured.
- The POS UI advertises it: resources/views/admin/sales/pos/partials/scripts.blade.php L994 has the placeholder "(الافتراضي 9999)".
- Config state: the local .env sets neither MANAGER_OVERRIDE_CODE nor MANAGER_OVERRIDE_CODE_HASH (only key presence was checked). That also enables fallback step 3 (L50-61): any password of a super-admin or branch-manager user works as an override code.
- Impact: any user with pos.access can exceed customer credit limits and sell below retail or with a discount without `invoices.discount`. It defeats the only financial approval control in POS.
- Rate limiting is keyed by IP only (L23). Behind a reverse proxy, all users would share one bucket. TrustProxies is not configured in bootstrap/app.php; whether a proxy is actually used in deployment is UNVERIFIED.
- Blocking dependencies:
  - SystemDiagnosticService (L463) uses 'mgr_override_99'.
  - Tests use them: RoleAndCashierTest L98 ('9999') and EndToEndSalesAndPurchasesScenarioTest L310 ('mgr_override_99').
- Remediation:
  - Remove the legacy codes and make a configured hash mandatory (fail closed).
  - Store an approver identity: switch to per-manager PIN verification and record `override_by_user_id` on the invoice.
  - Key the rate limiter by user and IP.
  - Update the diagnostics and tests to use the configured hash.

### SEC-04 MEDIUM: deactivated users keep their sessions
- AuthController::login L87 is the only `is_active` check. There is no middleware (the app/Http/Middleware directory does not exist). SESSION_LIFETIME is 1440 minutes.
- Impact: a disabled or terminated user keeps working for up to 24 hours. UserController::toggleStatus does not invalidate sessions, and the sessions table has user_id.
- Remediation: add an EnsureUserIsActive middleware to the auth group. On deactivation, delete that user's session rows.

### SEC-05 MEDIUM: global search ignores permissions
- app/Http/Controllers/Admin/SearchController.php::globalSearch L19 and app/Services/SearchService.php contain no permission checks. The search matches employee national_id (L123, L167) and returns customer credit balances in subtitles (L186), suppliers, purchase invoices and warranty claims.
- Impact: any authenticated user can enumerate PII and financial data outside their permissions.
- Remediation: filter each section by the matching `*.view` permission and exclude national_id from results.

### SEC-06 MEDIUM: user and role management allow privilege escalation
- UserController::store L72, update L108 and updateRole L153 validate `role` only with `exists:roles,name`, so a holder of users.manage can assign `super-admin`. resetPassword (L201) can reset any user's password, including a super-admin's, with a minimum of 6 characters.
- RoleController::store/update can grant any permission, including users.manage and roles.manage, to custom roles.
- Mitigation today: the seeders grant users.manage and roles.manage only to super-admin, so this is defense in depth. Delegating either permission would enable a full takeover.
- Remediation:
  - Only a super-admin may assign super-admin, or modify or reset a super-admin.
  - Mark sensitive permissions (PermissionRegistry already has an `is_sensitive` flag) as grantable only by super-admin.

### SEC-07 MEDIUM: default users share a hard-coded weak password
- database/seeders/InitialDataSeeder.php L57, L68 and L79 give admin@, accountant@ and cashier@alhusseini.com the same short numeric password (not reproduced here).
- Impact: if the seeders were run in production, all three accounts are trivially guessable. docs/DEPLOYMENT_GUIDE.md does not mention seeding, so production exposure is UNVERIFIED.
- Remediation: read the initial passwords from env, or generate random ones printed once. Force a password change on first login.

### SEC-08 MEDIUM: no branch isolation
- See the authorization model above. It is latent today because there is one operational branch (MAIN). Once multi-branch is used, a branch-manager could read, generate payroll for, or post sales into any branch.
- Affected: every Sales, HR, Scrap, Warranty and Purchases controller.
- Remediation: a branch scope policy (global scope or policy) based on user.branch_id, with super-admin exempt.

### SEC-09 LOW: settings endpoint accepts arbitrary keys
- SettingController::update L40-56 persists `$request->except(['_token','_method'])` wholesale. Any key a settings.manage holder sends becomes a row.
- The impact is low because business code reads no settings (BIZ-16).
- Remediation: allow-list the keys.

### SEC-10 LOW: lock screen is cosmetic
- LockScreenController sets and clears `lockscreen_locked` (L28, L85), but nothing enforces it. Every URL keeps working while "locked".
- Remediation: enforce it in middleware, or remove the feature.

### SEC-11 LOW: records fall back to user id 1
- `auth()->id() ?? 1` appears at PosController L58, PurchaseInvoiceController L50, SalesInvoiceController L83, CreditCustomerController L85 and WarrantyController L126. PosOrderService L323 uses `technician_id ?? 1`.
- These routes require auth, so the fallback cannot fire over HTTP. It can only mis-attribute records when a service is invoked from CLI or diagnostics.
- Remediation: drop the fallback and pass the user explicitly.

### SEC-12 LOW: admin-set password policy is weak
- Admin-set passwords need only 6 characters (UserController store and resetPassword), while the self-service minimum is 8.

### SEC-13 LOW: missing RoleController::show() causes a 500
- Route::resource('roles') registers GET admin/roles/{role}, but RoleController has no show(). A request to it produces a 500 (roles.manage holders only).
- Remediation: add `->except('show')`.

## Unverified
- Stored XSS via client-side `innerHTML` template strings:
  - Customer, product and invoice fields are interpolated without escaping in the POS, credit and dashboard mock renderers. The global spotlight search does escape (global-spotlight-search.js L187, L221).
  - Exploitability depends on which fields reach these templates from server data. For the credit page, that is `@json($customers)` at credit.blade.php L425.
  - Not traced sink by sink.
- Customer PII in page source: credit.blade.php L425 serializes full Customer models, which may include national_id, into the page for credit.view users. Whether Customer defines `$hidden` was not checked.
- Rate-limit bucket sharing behind a proxy (SEC-03 and login throttle) depends on the deployment topology.

## Previous leads: classification
- Supplier create/edit/delete permission gap: CONFIRMED (SEC-02).
- Purchase invoice permission gap: CONFIRMED (SEC-02).
- Hard-coded manager override codes: CONFIRMED (SEC-03), and worse than first reported because the manager-password fallback is also active in the local config.
- Missing RoleController::show(): CONFIRMED (SEC-13).
- Locale switch changes direction only: CONFIRMED functional issue, not security. routes/web.php `/lang/{locale}` stores the session value, but nothing calls App::setLocale. The only readers are layouts master, master-without-nav and head-css for dir/CSS. There are no lang files and the UI strings are hard-coded Arabic. Tracked as FE-07.
