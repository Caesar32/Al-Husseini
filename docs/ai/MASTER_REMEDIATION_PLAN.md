# Master Remediation Plan

Date 2026-09-30. Planning only; no code has been changed.

Finding IDs refer to:
- security-audit.md (SEC)
- business-integrity.md (FE, BIZ, DB)
- testing-audit.md (TST)
- architecture.md (ARC)

The module docs (sales.md, purchases.md, hr.md, warranty-scrap.md, admin-core.md, database.md, routes.md) hold the file and flow detail each phase relies on.

Global rules for every phase:
- Work on a feature branch per phase, keep `php artisan test` green, and add tests before or with each fix.
- Never edit an already-deployed migration; add new ones. Migrations 2026_09_30_000001 and 000002 are untracked and may still be edited, provided they have not been run anywhere other than dev.
- Keep business rules in services and controllers thin. Use FormRequests for new validation, and put authorization in both the route `can:` middleware and FormRequest::authorize().

## Open decisions (owner: business/product; they block the tasks marked [Dn])
- D1: Is there production data created through the current UI? This decides whether Phase 4 needs a data-repair script.
- D2: Manager override model. Option A: a single configured hash. Option B: per-manager PIN or password plus a recorded approver (recommended).
- D3: Refund valuation policy: prorate the invoice discount and scrap deduction over lines by line value (recommended), or refund the net line price.
- D4: Technician commission rules (rate or fixed per product or category; when approved). Alternatively, remove commissions from payroll.
- D5: Leave semantics. Should approved leave change employee.status at all? Recommended: no. Derive on-leave from EmployeeLeave dates and include those employees in payroll.
- D6: Scrap sale accounting: which record, and whether it links to a cash or treasury concept (none exists today).
- D7: Locale: implement real translations, or remove the /lang switch and keep Arabic only.
- D8: The working tree: commit the current uncommitted finance/POS/payroll changes as-is after review, or rework them.

---

## Phase 0: Baseline settlement
- Goal: a clean, reviewed starting point so later diffs are attributable.
- Scope:
  - The uncommitted working tree: 23 modified and 10 untracked files, including PosOrderService, ManagerOverrideService, InvoiceFilter, InvoicePayment scopes, PayrollService, migrations 2026_09_30_000001 and 000002, and FinanceRegressionTest.
  - The root docs BASELINE.md and FINANCE_MODULE_REMEDIATION_PLAN.md.
- Files/areas affected: git only.
- Dependencies: D8.
- Implementation tasks:
  1. Review `git diff` per file group (finance services, views, tests, PayrollService).
  2. Commit it in logical commits on a branch, `chore/baseline-2026-09-30`: finance fixes, payroll overtime guard, docs.
  3. Tag it `baseline-2026-09-30`.
  4. Record in BASELINE.md the test count (168 passed / 686 assertions) and the list of known-open findings from master-audit.md.
  5. Mark in FINANCE_MODULE_REMEDIATION_PLAN.md which of its items are superseded by Phases 5 and 6 here.
- Database changes: none. Do not run migrations 000001 or 000002 on any shared database yet; Phase 1 rewrites 000001.
- Authorization/security changes: none.
- Tests required: the full suite stays green (168).
- Acceptance criteria: a clean `git status`; the tag exists; the suite is green.
- Rollback strategy: `git reset` to 45b9ccc on the branch; nothing is deployed.
- Risks: committing unreviewed logic. Mitigate with a per-file review, and keep migration 000001 uncommitted if Phase 1 rewrites it.

## Phase 1: Test harness integrity
- Goal: tests enforce FK and enum constraints and exercise realistic data, so later phases can be accepted on test evidence.
- Scope: DB-01, TST-01, TST-07 (optional), and factories.
- Files/areas affected:
  - database/migrations/2026_09_30_000001_add_partially_refunded_to_invoices_status.php
  - tests/TestCase.php
  - database/factories (new)
  - phpunit.xml (optional MySQL job)
- Dependencies: Phase 0.
- Implementation tasks:
  1. Rewrite migration 000001 `up()`:
     - Use the schema builder for all drivers: `Schema::table('invoices', fn($t) => $t->enum('status', [paid, partially_paid, unpaid, cancelled, refunded, partially_refunded])->default('paid')->change())`. Laravel 12 rebuilds the SQLite table natively.
     - Remove every PRAGMA and all `writable_schema` usage.
     - Keep the index on status (verify it survives the rebuild; re-add it if not).
     - `down()`: guarded revert for all drivers when no row has partially_refunded.
     - If the old version already ran on dev MySQL, the resulting schema is identical, so no new migration is needed. Otherwise add a new migration instead of editing.
  2. In tests/TestCase.php setUp, for the sqlite driver, assert that `PRAGMA foreign_keys` returns 1, and fail loudly otherwise.
  3. Add factories: Product (with a Category), Customer, CustomerVehicle, Invoice (+items/payments states), Supplier, PurchaseInvoice, Warranty, WarrantyClaim, ScrapBatteriesInventory, TechnicianCommission, EmployeePayrollDebt.
  4. Run the suite and fix any test that only passed because constraints were off. Record each one in the commit message.
  5. Optional: a CI job on MySQL 8 running migrate:fresh plus the suite.
- Database changes: the rewritten migration 000001 only (semantics unchanged: adds partially_refunded).
- Authorization/security changes: none.
- Tests required: the FK assertion, plus a test that inserting an invalid invoice status fails on SQLite.
- Acceptance criteria:
  - The suite is green with FK and CHECK enforced.
  - `migrate:fresh` works on SQLite and MySQL.
  - No PRAGMA statements remain in migrations (grep).
- Rollback strategy: revert the commit. The migration semantics are unchanged, so no data impact.
- Risks: hidden test failures surface. That is expected, and they are real defects; fix or document each.

## Phase 2: Security and authorization hardening
- Goal: close the exploitable and bypassable controls.
- Scope: SEC-01, SEC-02, SEC-03, SEC-04, SEC-05, SEC-06, SEC-07, SEC-09, SEC-10, SEC-11 (HTTP paths), SEC-12, SEC-13, TST-03, TST-04.
- Files/areas affected:
  - Profile and storage: app/Http/Controllers/Admin/ProfileController.php::updateAvatar; views that render the avatar (topbar and profile; find them with `grep -rn "avatar" resources/views`); config/filesystems.php (the public disk and the storage:link).
  - Routes and requests: routes/web.php L165 and L170; app/Http/Requests/Admin/Suppliers/*, Purchases/*.
  - Override: app/Services/Finance/ManagerOverrideService.php; config/finance.php; .env.example; resources/views/admin/sales/pos/partials/scripts.blade.php L994; app/Services/Diagnostics/SystemDiagnosticService.php L463; tests RoleAndCashierTest L98 and EndToEndSalesAndPurchasesScenarioTest L310.
  - Sessions: new app/Http/Middleware/EnsureUserIsActive.php; bootstrap/app.php; UserController::toggleStatus.
  - Search and user management: SearchService and SearchController; UserController; RoleController; PermissionRegistry.
  - Other: InitialDataSeeder; SettingController; LockScreenController; the controllers with `?? 1`.
- Dependencies: Phase 1 (trustworthy tests). D2 for the override design.
- Implementation tasks:
  1. SEC-01 avatar storage:
     - Store with `$file->store('avatars', 'public')`, which gives a hashName with the extension from the MIME guess, and save the path in users.avatar.
     - Render via Storage::url.
     - Delete the previous file.
     - Migrate existing public/uploads/avatars files: a one-off artisan command that moves them and updates users.avatar.
     - Add to docs/DEPLOYMENT_GUIDE.md an nginx `location ^~ /storage/ { location ~ \.php$ { deny all; } }`, and the same for /uploads.
  2. SEC-02 route gates. Replace the resource routes with explicit middleware per action:
     - suppliers: index/show → suppliers.view; create/store → suppliers.create; edit/update → suppliers.edit; destroy → suppliers.delete.
     - purchases: index/show → purchases.view; create/store → purchases.create.
     - Also have the FormRequests' authorize() check the same permission.
     - Grant purchases.create and suppliers.create/edit to the roles that need them (accountant, branch-manager). Confirm with the business, then update RolesAndPermissionsSeeder and ship a permission-sync migration or command for existing databases.
  3. SEC-03 manager override:
     - Remove the legacy codes and the manager-password fallback, and fail closed when no hash is configured.
     - [D2-B] Accept a manager's own PIN (new users.override_pin_hash column), return the approving user id, and persist it on invoices (new invoices.override_approved_by nullable FK to users).
     - Rate-limit on `manager-override:{userId}|{ip}`.
     - Remove the "(الافتراضي 9999)" hint.
     - Update SystemDiagnosticService to set a temporary config hash inside its simulation transaction. Rewrite the two tests to configure a hash.
  4. SEC-04 active sessions:
     - Add middleware EnsureUserIsActive, appended to the `auth` group via bootstrap/app.php (a web group append or an alias applied in routes). It logs out and redirects when `!is_active`.
     - In toggleStatus, on deactivate, delete the rows in `sessions` where user_id matches.
  5. SEC-05 global search: filter each SearchService section by permission (employees.view, customers.view, products.view, invoices.view, warranties.view, suppliers.view, purchases.view). Drop national_id from the displayed or matched fields for users without employees.view.
  6. SEC-06 user and role escalation:
     - In UserController store, update and updateRole, reject role `super-admin` unless auth user hasRole super-admin. In update and resetPassword, reject a target that is super-admin unless auth user is super-admin.
     - In RoleController store and update, reject permissions flagged `is_sensitive` in PermissionRegistry (users.manage, roles.manage, settings.manage) unless auth user is super-admin.
  7. SEC-07 seed passwords: InitialDataSeeder reads SEED_ADMIN_PASSWORD etc. from env, or generates random ones and prints them. Document this in DEPLOYMENT_GUIDE.
  8. SEC-09 settings keys: allow-list the keys in SettingController::update (the SettingsSeeder key list in admin-core.md).
  9. SEC-10 lock screen: enforce `lockscreen_locked` in middleware (redirect to admin.lockscreen except for lockscreen, logout and refresh-csrf), or remove the feature. Default: enforce.
  10. SEC-11 id fallbacks: remove `auth()->id() ?? 1` in controllers (auth is guaranteed); services receive explicit ids.
  11. SEC-12 password policy: set admin-set passwords to min:8 and align with profile.
  12. SEC-13 roles show: `Route::resource('roles', …)->except('show')`.
- Database changes:
  - [D2-B] users.override_pin_hash (nullable string).
  - invoices.override_approved_by (nullable FK users, nullOnDelete).
  - A permission sync for new role grants (a seeder or command, idempotent).
- Authorization/security changes: all of the above.
- Tests required:
  - Auth matrix: every route in routes.md × each seeded role → expected 200/302/403.
  - Avatar: `.php` upload results in a stored filename that does not end in .php and is not under public/uploads.
  - Deactivated user → next request redirects to login.
  - Non-super-admin with users.manage cannot assign or modify super-admin.
  - Search sections are filtered by permission.
  - Override: '9999' rejected; configured hash accepted; approver recorded.
  - Settings reject unknown keys; the lockscreen blocks other routes.
- Acceptance criteria:
  - All new tests pass.
  - `grep -rn "9999\|mgr_override" app resources` shows only non-override uses.
  - No route in `route:list` lacks `can:` except those in the documented auth-only allow-list (dashboard redirect, profile, lockscreen, logout, notifications-own, refresh-csrf, lang).
- Rollback strategy:
  - Per-task commits, so revert individually.
  - The avatar move command is reversible: keep the originals until verified.
  - The new columns are nullable, so a migration rollback is safe.
- Risks:
  - Legitimate users lose access they relied on (cashiers posting purchases). Mitigate by confirming the role grants first.
  - Diagnostics simulation breakage. Covered by SystemDiagnosticsTest.

## Phase 3: Product, customer and vehicle backend
- Goal: real CRUD for the catalog and customers so the UI can stop using mock stores.
- Scope: FE-02. It enforces the currently dead permissions: products.view/create/edit/delete, customers.view/create/edit/delete, credit.adjust_limit.
- Files/areas affected:
  - New app/Http/Controllers/Admin/{ProductController,CustomerController,CustomerVehicleController}.php.
  - New app/Services/Catalog/ProductService.php and app/Services/Sales/CustomerService.php, with interfaces bound in SalesAndPurchasesServiceProvider.
  - New FormRequests under app/Http/Requests/Admin/{Products,Customers}/*.
  - routes/web.php: replace the closures at L207 and L210.
  - resources/views/admin/sales/{products,customers}.blade.php.
  - Models Product, Customer, CustomerVehicle, Category.
- Dependencies: Phase 2 (auth pattern).
- Implementation tasks:
  1. Routes under admin/, each guarded by its matching permission:
     - products index/store/update/destroy.
     - customers index/store/update/destroy, plus customers/{customer}/vehicles store/update/destroy.
     - JSON search endpoints `products/search?q=` and `customers/search?q=` for POS, returning id, name, sku, barcode, retail_price, current_stock, is_battery, warranty_months and category slug.
  2. Product rules:
     - sku unique (the rule should ignore soft-deleted rows only if the DB unique is relaxed; otherwise keep it strict and document).
     - barcode unique nullable, category exists, prices ≥ 0.
     - current_stock NOT editable here: stock changes only via purchases, sales, returns and claims (ARC-04).
     - capacity_ah stays a string such as "70Ah".
  3. Customer rules: phone unique; credit_limit editable only with credit.adjust_limit; tier in standard/vip/fleet. current_credit_balance is never mass-assignable from requests.
  4. Rewrite products.blade and customers.blade to render server data (Blade tables plus fetch to the JSON endpoints). Remove every `window.AlHusseiniSales` call from them.
  5. Soft-delete behaviour: block deleting a product that has stock > 0 or open purchase or sale references (restrict FKs exist); block deleting a customer with balance > epsilon.
- Database changes: none required. Optional: a partial unique index on sku/phone ignoring soft-deleted rows (MySQL cannot do partial indexes; skip it and document).
- Authorization/security changes: new routes guarded; FormRequest authorize() checks the same permission.
- Tests required:
  - CRUD feature tests per role (403 for cashier on create and edit, if that is the business rule).
  - Validation tests.
  - credit_limit change requires credit.adjust_limit.
  - The JSON search returns only active products.
- Acceptance criteria: a product or customer created in the UI exists in the DB, and a purchase can be posted for a UI-created product; grep finds no `AlHusseiniSales` in the products and customers views.
- Rollback strategy: new files and routes only, so revert the commits. No destructive migration.
- Risks: scope creep into inventory adjustments. Keep stock edits out and route them to Phase 10's movement journal if needed.

## Phase 4: POS and credit UI on real data
- Goal: the browser POS and the credit page drive the real backend with real IDs.
- Scope: FE-01, FE-04, TST-02, D1 data-impact assessment.
- Files/areas affected:
  - resources/views/admin/sales/pos.blade.php and pos/partials/* (scripts.blade.php around L60-140, L190-460, L850-1000); PosController::index.
  - resources/views/admin/sales/credit.blade.php L420-500 and L731-910; CreditCustomerController (new payments endpoint).
  - vendor-scripts.blade.php (stop loading sales-store.js on these pages).
- Dependencies: Phase 3 (search endpoints). Phase 2 (override UX).
- Implementation tasks:
  1. POS catalog and customers:
     - Replace the `AlHusseiniSales.getProducts/getCustomers/getProductById/getProductByBarcode/saveCustomer` calls with the server `$products`/`$customers` (`@json`, trimmed to the fields needed) plus the Phase 3 search endpoints.
     - Customer quick-create posts to the Phase 3 customer store.
  2. Payload builder:
     - Send the real numeric product_id and customer_id. Delete the `: 1` fallback and the NaN-to-null logic; block submit if any ID is not numeric.
     - Remove the auto-generated `BAT-…` serial: require a scanned or typed serial for battery lines and block submit otherwise.
     - Add discount and tax inputs (discount requires invoices.discount or the override prompt) instead of the hard-coded 0.
     - Take scrap tiers from the server `$scrapTiers` and send scrap_capacity_ah and scrap_count, so the server computes the deduction unless an override amount is entered.
  3. Override prompt: generic text; send the code; on success show the approver name returned by the API (after Phase 2).
  4. Idempotency:
     - Generate a client UUID per checkout and send it as `idempotency_key`.
     - Add invoices.idempotency_key (nullable unique).
     - PosOrderService returns the existing invoice when the key repeats.
  5. Credit page:
     - Add an endpoint `credit/payments` (can:credit.view) returning payment_collection ledger entries paginated, plus the "collected this month" sum.
     - Replace the mock payments tab and figure with it.
     - Remove the mirroring into the mock store after settlement.
     - Use route() for the statement URL.
  6. [D1] Data-impact assessment:
     - A read-only artisan command `audit:ui-sales` that reports invoices whose items all use product_id 1 plus `BAT-\d{6}-\d+` warranty serials, and their stock effect.
     - Decide a repair with the business. Any repair is a separate reviewed command, never an automatic migration.
- Database changes: invoices.idempotency_key nullable unique string(64).
- Authorization/security changes: unchanged routes. The new credit/payments route is gated can:credit.view.
- Tests required:
  - A feature test rendering the POS view asserts the embedded product IDs equal DB IDs.
  - POST with a non-numeric product_id → 422.
  - Repeated idempotency_key → the same invoice and no double stock decrement.
  - The credit payments endpoint returns ledger rows.
  - A battery line without a serial → 422 (already exists; keep it).
- Acceptance criteria:
  - A manual UAT sale in the browser creates an invoice with the chosen product, customer and serial, and stock drops for that product only.
  - Grep finds no `AlHusseiniSales` in the POS and credit views.
- Rollback strategy: revert the view commits (the backend is unchanged except the additive column); the idempotency column is nullable.
- Risks:
  - Cashier workflow change (serial now mandatory). Coordinate training.
  - Performance of embedding the product list. Use the search endpoint if more than ~2k products.

## Phase 5: Sales returns, revenue and credit integrity
- Goal: returns are bounded and correctly valued, totals include partially refunded invoices net of refunds, and the credit balance equals the open invoice remaining amounts.
- Scope: BIZ-03, BIZ-04, BIZ-05, BIZ-12a, BIZ-17, ARC-02, TST-05. Reconcile with FINANCE_MODULE_REMEDIATION_PLAN.md.
- Files/areas affected:
  - app/Services/Sales/PosOrderService.php (processSalesReturn L340-440, getInvoiceStats L460-490, settleCustomerDebt L508-580, invoice number L205, guest customer L281).
  - app/Services/Sales/InvoiceFilter.php; app/Models/Invoice.php, InvoiceItem.php, InvoicePayment.php (scopes).
  - app/Http/Controllers/Admin/DashboardController.php L31, L188, L250; SalesInvoiceController::processReturn (move to a FormRequest).
  - resources/views/admin/invoices/show.blade.php (return modal).
- Dependencies: Phase 1. D3. The UI part (modal) is independent of Phase 4.
- Implementation tasks:
  1. ARC-02 status scope:
     - Add Invoice::scopeCountable (excludes cancelled and refunded only) and a single place for status constants (a PHP backed enum App\Enums\InvoiceStatus with values matching the DB).
     - Replace every `whereNotIn('status', [...])` list in PosOrderService, InvoicePayment::scopeActive, InvoiceFilter::applyForStats and DashboardController with it.
  2. BIZ-05 net revenue: revenue = Σ final_amount − Σ refunded amounts. Add invoices.refunded_amount dec(10,2) default 0, maintained by processSalesReturn. Stats and dashboard use `final_amount - refunded_amount`, and scopeActive keeps partially_refunded payments.
  3. BIZ-05 FIFO: settleCustomerDebt includes partially_refunded invoices with remaining_amount > eps. Assert that `amount applied == amount requested` or throw (no silent unallocated remainder).
  4. BIZ-03 returned quantity:
     - Add invoice_items.returned_quantity uint default 0.
     - processSalesReturn validates qty ≤ quantity − returned_quantity per invoice_item_id (not product_id), then increments returned_quantity.
     - Change the request payload to `items[].invoice_item_id` and update the modal accordingly.
     - Status becomes `refunded` when all lines are fully returned.
  5. BIZ-04 [D3] valuation:
     - Refund per unit = unit_price × (1 − (discount_amount + scrap_deduction_amount − tax_amount) / subtotal) (the proration factor), rounded, with the last-unit remainder correction.
     - Cap the cumulative refund at final_amount.
     - Persist the per-return record: new table sales_returns (id, invoice_id, user_id, reason, refund_total, credit_part, cash_part, timestamps) and sales_return_items (sales_return_id, invoice_item_id, quantity, refund_amount).
  6. BIZ-12a invoice numbering:
     - Use a per-day sequence via a counters table (document_sequences: key, next_value) locked with lockForUpdate inside the sale transaction.
     - Format INV-{Ymd}-{000001}. Keep the uniqueness index.
  7. BIZ-17 guest customer: create it once in a seeder or migration (phone 00000000000) and look it up by a config key, instead of firstOrCreate inside the transaction.
  8. Credit invariant check: add to SystemDiagnosticService a check that Customer.current_credit_balance equals Σ remaining_amount of the customer's countable invoices.
- Database changes:
  - invoices.refunded_amount; invoice_items.returned_quantity.
  - New tables sales_returns, sales_return_items, document_sequences.
  - A backfill migration: refunded_amount from negative REFUND-* InvoicePayments. returned_quantity cannot be reconstructed per item from existing data (UNVERIFIED): backfill it from the notes only if parseable, otherwise leave 0 and mark such invoices non-returnable (status refunded or partially_refunded treated as closed). Needs business sign-off.
- Authorization/security changes: the return request moves to a FormRequest with authorize() checking invoices.cancel.
- Tests required:
  - Two sequential partial returns cannot exceed the sold quantity.
  - The prorated refund never exceeds paid plus credit.
  - Revenue with a partially_refunded invoice equals final − refunded.
  - FIFO allocates to a partially_refunded invoice.
  - The settlement throws when it cannot allocate.
  - Sequence numbering is unique under a loop of 100 sales.
  - The credit invariant diagnostic passes after mixed flows.
- Acceptance criteria: the tests above pass, the diagnostics credit check passes on seeded data, and FINANCE_MODULE_REMEDIATION_PLAN.md items are mapped to done or superseded.
- Rollback strategy: additive migrations with down() methods; feature commits revertible. The backfill is idempotent and recorded.
- Risks:
  - Historical returns cannot be fully reconstructed.
  - Reports change values (net revenue). Communicate this to finance.

## Phase 6: Purchases and supplier ledger integrity
- Goal: the supplier ledger is a faithful journal whose running balance equals Supplier.current_balance, invoice payment state stays current, and returns are possible.
- Scope: BIZ-06, BIZ-11, BIZ-18, BIZ-20.
- Files/areas affected:
  - app/Services/Purchases/PurchaseService.php (createDirectPurchase L60-215, recordSupplierPayment L222-260, processPurchaseReturn L262+, getSupplierLedgerStatement ~L330).
  - SupplierService; SupplierController (recordPayment and the missing create view); PurchaseInvoiceController (new return route).
  - resources/views/admin/suppliers/*, purchases/*.
  - WarrantyService credit-note posting (keep consistent).
- Dependencies: Phase 1; Phase 2 (routes gated).
- Implementation tasks:
  1. BIZ-06 ledger posting: on purchase, post `purchase_invoice` for the FULL final_amount (balance += final), then, if paid > 0, post `supplier_payment` for paid (balance −= paid). current_balance ends at +remaining, and the ledger running balance matches.
  2. Data repair command `suppliers:rebuild-ledger-balances`:
     - Recompute balance_before and balance_after chronologically per supplier from the corrected semantics. For historical purchases with paid > 0, insert the missing (final − remaining) portion by rewriting the purchase_invoice entry amount to final.
     - Dry-run by default; report differences against current_balance.
  3. BIZ-11 payment allocation:
     - recordSupplierPayment accepts an optional purchase_invoice_id. Without one, allocate FIFO across the supplier's invoices with remaining > eps, oldest invoice_date first.
     - Update paid_amount, remaining_amount and payment_status.
     - Replace the `balance <= 0 && amount > 1000` guard with: amount ≤ balance + eps, unless the `advance` flag is set and the user holds purchases.settle_payment.
     - Catch the DomainException in SupplierController::recordPayment and return 422/back-with-errors.
  4. BIZ-18 purchase returns:
     - Add the route POST purchases/{purchase}/return (can:purchases.create or a new purchases.return permission), a FormRequest and a view modal.
     - processPurchaseReturn also reverts WAC: new cost = (stock×cost − qty×unit_cost)/(stock − qty) when the remaining stock is > 0, else keep the cost.
     - Reduce the invoice remaining or paid amounts and set payment_status.
     - Track purchase_invoice_items.returned_quantity.
  5. BIZ-20 catalog and supplier gaps:
     - When marking a primary supplier on purchase, unset other primaries for that product.
     - Keep an existing supplier_sku when the line SKU is empty.
     - Add a `products` rule to the Store and Update supplier requests (array of product_id, supplier_sku, last_purchase_price), or delete the unreachable sync code.
     - Remove the `create` action from the suppliers resource, or add the view.
     - Decide whether to enforce credit_limit at purchase (warning versus block).
  6. Ledger statement totals include `adjustment` entries.
- Database changes: purchase_invoice_items.returned_quantity uint default 0. No change to the existing ledger schema; the repair command rewrites data (reviewed, dry-run first).
- Authorization/security changes: the return route is gated; supplier payment advances require explicit permission.
- Tests required:
  - Ledger invariant after purchase paid 0, partial and full.
  - Payment allocation updates invoice statuses.
  - Overpayment returns 422, not 500.
  - Return reverts stock, WAC and balance.
  - A single primary supplier per product.
  - The rebuild command dry-run reports zero diff on fresh data.
- Acceptance criteria: the SystemDiagnosticService supplier_ledger check passes, with the formula updated to Σpurchase − Σpayment − Σreturn ± Σadjustment = balance, and all tests pass.
- Rollback strategy: code commits revertible. The repair command writes a JSON backup of affected ledger rows before modifying anything, and a restore mode reads it.
- Risks: historical ledger rewrite. Require D1 sign-off and run in a maintenance window.

## Phase 7: Warranty and scrap integrity
- Goal: claim and settlement flows are state-guarded and fully recorded, and scrap sales produce a financial record.
- Scope: BIZ-07, BIZ-10, BIZ-12b, BIZ-13, BIZ-21.
- Files/areas affected:
  - app/Services/Sales/WarrantyService.php (processInstantClaim, settleClaimWithSupplier L198-240); app/Observers/WarrantyClaimObserver.php.
  - app/Services/Sales/ScrapBatteryService.php (dispatchScrapSaleBatch L76-115); ScrapInventoryController::index.
  - ProcessWarrantyClaimRequest; resources/views/admin/warranties/*, scrap/index.blade.php.
- Dependencies: Phase 1. D6. Phase 6 task 1 for ledger semantics (credit notes).
- Implementation tasks:
  1. BIZ-07 settlement state machine. Allowed transitions:
     - pending → sent_to_supplier
     - pending | sent_to_supplier → settled_replacement | settled_credit_note | rejected
     - settled_* and rejected are terminal.
     - Throw a DomainException (caught, 422) otherwise.
     - Require claim.decision = replaced for settled_replacement.
     - Reject settled_credit_note when supplier_id is null instead of silently succeeding.
  2. BIZ-13 claim fields:
     - Block new claims on a warranty with status claimed or voided in both the request after-hook and the service.
     - Add columns warranty_claims.rejection_reason (text nullable) and settlement_notes (text nullable), and persist them.
     - Replacement warranty period: [decision] remaining period of the original, or a full new period. Default: keep the current full period, but document it on the certificate.
     - Choose the supplier from the original sold product's primary supplier (warranty.invoiceItem.product), not the replacement product.
  3. BIZ-12b claim number: use the document_sequences table from Phase 5 task 6 (key CLM-YYYYMM) under lock, instead of count+1.
  4. BIZ-10 [D6] scrap sale record:
     - New table scrap_sales (id, batch_number unique, branch_id, buyer_name, buyer_phone, payment_method, total_amount, cost_value, gross_profit, sold_by FK users, notes, timestamps) and scrap_batteries_inventory.scrap_sale_id nullable FK.
     - dispatchScrapSaleBatch creates it inside the transaction.
     - The scrap index shows sales history.
  5. BIZ-21 scrap odds and ends:
     - Sales returns: decide whether scrap rows from a returned invoice are reversed (only if the trade-in is refunded). Default: no reversal; document it.
     - Pass branch_id to the scrap index list filter.
     - Either write `recycled` and `expired` statuses (an expiry sweep command scheduled daily) or remove them from the enums in a later migration.
  6. InvoiceItem::warranty ambiguity: change it to hasMany `warranties()` plus `activeWarranty()` (latestOfMany where status != voided), and update the warranty cert view.
- Database changes:
  - warranty_claims.rejection_reason and settlement_notes.
  - scrap_sales table.
  - scrap_batteries_inventory.scrap_sale_id.
  - document_sequences (shared with Phase 5).
- Authorization/security changes: none new. Keep the existing can: gates.
- Tests required:
  - A second settlement is rejected.
  - Settled_replacement requires decision replaced.
  - A claim on a claimed warranty is rejected.
  - rejection_reason is persisted.
  - The claim number stays unique in a loop.
  - A scrap sale persists a record with the correct gross_profit.
  - The scrap list is filtered by branch.
- Acceptance criteria: all tests pass; diagnostics show no duplicate warranty serials and no orphan scrap rows.
- Rollback strategy: additive migrations with down() methods; revertible commits.
- Risks: existing claims already settled twice may exist. Add a read-only diagnostics query to list them.

## Phase 8: HR and payroll integrity
- Goal: payroll with overtime is approvable, and one calculation is shown everywhere. Leave, attendance and commission semantics are correct, and HR reports are real.
- Scope: BIZ-08, BIZ-09, BIZ-14, BIZ-15, BIZ-19, BIZ-22, FE-03, FE-06, TST-06.
- Files/areas affected:
  - PayrollService (assertPayrollTotalsConsistent ~L255-290, generateMonthlyPayroll); resources/views/admin/hr/partials/payroll/{batches-table (L35-70), breakdown-table, modal-payslip, scripts}.blade.php; PayrollController::show.
  - LeaveService::updateLeaveStatus L57-70; AttendanceService::recordPunch L77-190; AttendanceObserver L11-60.
  - Reports: routes/web.php hr.reports; new HrReportController and HrReportService; resources/views/admin/hr/partials/reports/scripts.blade.php.
- Dependencies: Phase 0 (overtime fix committed), Phase 1. D4, D5.
- Implementation tasks:
  1. BIZ-08 single source of truth:
     - Extract the payroll totals check into a public PayrollService::evaluateConsistency(Payroll): array{expected_net, stored_net, consistent, zero_with_components, reasons}. It includes overtime and is used by assertPayrollTotalsConsistent.
     - The controller passes the results per payroll to the view, and batches-table.blade.php uses them instead of recomputing (delete L35-43 inline math).
  2. BIZ-15 payroll detail page: create resources/views/admin/hr/payroll_show.blade.php, rendering PayrollItem rows (basic, allowances, overtime, deductions, debt_repayment, carried_debt, net) and the consistency reasons. Alternatively, make `show` JSON-only and remove the view branch. Default: create the view.
  3. FE-06 payslip: modal-payslip and breakdown-table render stored PayrollItem values via the JSON show endpoint, not client estimates.
  4. BIZ-09 [D5] leave status:
     - Stop setting employee.status = on_leave on approval.
     - Payroll includes employees with status active (and on_leave, for existing data).
     - AttendanceService derives on-leave from EmployeeLeave date overlap only.
     - Data fix: a command resetting on_leave to active where no approved leave covers today.
  5. BIZ-14 observer fixes:
     - AttendanceObserver::saving must not override status `holiday` or `excused`.
     - Send the late notification only on the transition from 0 to > 0 late minutes (check `wasChanged('late_minutes')` or send only on check-in).
  6. BIZ-19 [D4] commissions:
     - Either implement creation in PosOrderService (per technician per invoice at the decided rate, status pending, approved by branch-manager) with payroll setting payroll_id and status paid at disbursement,
     - or remove commissions from payroll gross and from the UI.
     - Update EndToEnd sector 2, which asserts that no commission exists.
  7. BIZ-22 payroll rule settings:
     - Unify the late penalty day rate with payroll (basic/daysInMonth, or the configured monthly_working_days once Phase 9 wires settings).
     - Allow a zero-net batch to be approved when confirm_debt_review is true and all nets are 0 because of carried debt.
     - Mark deductions `applied` and set their payroll link at disbursement (the enum value already exists).
  8. FE-03 HR reports:
     - HrReportService with daily, monthly and range aggregates from attendances, leaves and deductions.
     - JSON endpoints under hr/reports/* (can:reports.hr).
     - The reports scripts fetch them; remove every AlHusseiniHR usage.
- Database changes:
  - Optional: employee_deductions.payroll_id nullable FK.
  - technician_commissions is used as-is.
  - No change for leaves.
- Authorization/security changes: the new report endpoints are gated can:reports.hr.
- Tests required:
  - A batch with overtime approves and disburses.
  - The register view renders with no "needs review" for a consistent overtime batch.
  - payroll show HTML returns 200.
  - An approved leave does not exclude the employee from next month's payroll.
  - A late check-in during leave keeps holiday.
  - A single late notification per day.
  - Debt carries over and is repaid across two months.
  - Commissions per D4.
  - The report endpoints return DB aggregates.
- Acceptance criteria: all tests pass; the SystemDiagnosticService payroll_math_integrity check uses evaluateConsistency; grep finds no AlHusseiniHR in the hr views.
- Rollback strategy: revertible commits. The leave status data-fix command logs affected employee ids for restore.
- Risks: changing who is in payroll can alter already-drafted batches. Regenerate only draft batches, and never touch approved ones.

## Phase 9: Branch isolation, settings wiring, locale
- Goal: multi-branch readiness and effective configuration.
- Scope: SEC-08, BIZ-16, SEC-09 follow-up, FE-07.
- Files/areas affected: all Admin and Hr controllers and services with branch_id filters; the Setting model and its consumers (PosOrderService VAT, stock checks, AttendanceService grace, PayrollService working days); layouts; a new lang/ dir if D7 = translate.
- Dependencies: Phases 2-8 (touches the same services; do it last to avoid conflicts). D7.
- Implementation tasks:
  1. SEC-08 branch scope:
     - A BranchScope helper: `auth()->user()->hasRole('super-admin') ? request branch or all : user.branch_id`.
     - Apply it to every index/query method and every create path (ignore a client branch_id for non-super-admin).
     - Model-bound routes ({invoice}, {payroll}, {claim}, {supplier}, {purchase}, {customer}) check that the record's branch equals the user's branch (or a policy per model).
  2. BIZ-16 settings:
     - Read the settings that are meant to have an effect: vat_percentage (server-side tax computation, replacing client-sent tax_amount), allow_negative_stock (POS stock checks), invoice_prefix and scrap_prefix (Phase 5/7 sequences), warranty_months_default (fallback in place of the hard-coded 12), default_grace_period, monthly_working_days and daily_working_hours (payroll and attendance), session_timeout_minutes (config session lifetime at boot).
     - Remove unused keys from SettingsSeeder.
  3. Fix the Setting::get caching of defaults: do not cache a missing key's default.
  4. FE-07 [D7]: either add lang/ar and lang/en with App::setLocale in a middleware reading session('locale'), or remove the /lang route and the switcher UI.
- Database changes: none (settings exist). Optionally, users.branch_id is required for non-super-admin (a validation rule, not the schema).
- Authorization/security changes: branch scoping.
- Tests required: a branch-manager of branch A gets 403 or 404 on branch B records and lists; VAT is computed server-side; allow_negative_stock toggles the POS behaviour; the locale per D7.
- Acceptance criteria: the tests pass; grep finds Setting::get consumers for each retained key.
- Rollback strategy: revertible commits; the feature-flag branch scope can be disabled via config (`app.branch_scoping`) initially.
- Risks: wide surface. Land it per module in separate commits.

## Phase 10: Technical debt cleanup
- Goal: remove dead code and latent traps; add an audit trail.
- Scope: ARC-03, ARC-04, ARC-06, ARC-07, FE-05, FE-08, DB-02, DB-03, DB-04, the SEC-11 remainder.
- Files/areas affected:
  - app/Observers/{InvoiceObserver,PurchaseInvoiceObserver}.php; public/assets/js/{sales-store,hr-store}.js; vendor-scripts.blade.php; dashboard/partials/scripts.blade.php.
  - DashboardController → a new DashboardService.
  - Migrations for redundant indexes; the services mutating stock.
- Dependencies: Phases 3, 4 and 8 (the mock stores are no longer used by any page).
- Implementation tasks:
  1. Delete InvoiceObserver and PurchaseInvoiceObserver, and the AppServiceProvider note (ARC-03).
  2. Delete sales-store.js and hr-store.js and their include in vendor-scripts, once grep shows no `AlHusseiniSales` or `AlHusseiniHR` references. Delete the dashboard mock loaders (FE-05).
  3. ARC-04 stock journal:
     - A stock_movements table (product_id, branch_id, qty_delta, reason enum sale/return/purchase/purchase_return/warranty_replace/warranty_settle/adjustment, reference morph, user_id, timestamps).
     - A StockService::move() used by all five writers.
     - A diagnostics check that Σ movements equals current_stock.
  4. ARC-06: move the DashboardController aggregation into a DashboardService and batch the 7-day trend into one grouped query. FormRequests for the remaining inline validations (settlePayment, recordPayment, settleSupplier, updateTiers, markAbsent).
  5. FE-08: replace hard-coded `/admin/...` URLs in JS with route() values injected by Blade.
  6. DB-03: a migration dropping the duplicate payrolls unique index (`payrolls_branch_year_month_unique`) and the redundant `->index()` duplicates of unique columns. Check index names per driver.
  7. DB-04: change Invoice::scrapBattery to hasMany scrapBatteries (update the receipt view) and Invoice::technicianCommission to hasMany (or add unique invoice_id if one-per-invoice is the rule).
  8. DB-02: verify on each environment that credit_ledger_entries has no unique index on receipt_number (a SHOW INDEX query); document the result.
  9. Vite: remove the unused Tailwind/Vite pipeline, or document it as intentionally unused.
- Database changes: the stock_movements table; index drops.
- Authorization/security changes: none.
- Tests required: a stock journal invariant after mixed flows; the dashboard service returns the same figures as before (snapshot test on seeded data); no JS references to deleted stores (a grep-based test is optional).
- Acceptance criteria: the suite is green; diagnostics add a stock journal check that passes; no dead observers or mock stores remain.
- Rollback strategy: revertible commits. The index drops have down() methods re-adding them.
- Risks: the stock journal backfill for existing stock. Start the journal with one opening-balance movement per product.

---

## Phase dependency graph (summary)
- 0 → 1 → 2 → 3 → 4.
- 1 → 5 (the UI modal part is independent of 4), 1+2 → 6, 1+6(task 1) → 7, 0+1 → 8.
- 2..8 → 9 → 10 (10 also needs 3, 4 and 8 for the mock-store removal).
- Parallelisable after Phase 2: {5, 6, 8} concurrently, then 7 after 6 task 1.
