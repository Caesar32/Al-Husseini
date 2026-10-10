# Purchases & Suppliers (AI reference)

## Purpose
- Manage suppliers (the companies that supply batteries and other products), each supplier's product catalog (the `supplier_products` pivot), and the amount owed to each supplier (`suppliers.current_balance` = money the shop owes the supplier).
- Record direct purchase invoices (goods received). Each one increases stock, recalculates the weighted average cost (WAC) `products.cost_price`, updates the supplier catalog and posts supplier ledger entries.
- Record supplier payments (payment vouchers) against the supplier balance.

## Key files
- Routes: routes/web.php L164-171 (inside `auth` group, prefix `admin`, name `admin.`).
- app/Http/Controllers/Admin/SupplierController.php::index, create, store, show, edit, update, destroy, ledger, recordPayment
- app/Http/Controllers/Admin/PurchaseInvoiceController.php::index, create, store, show, print
- app/Http/Requests/Admin/Suppliers/StoreSupplierRequest.php, UpdateSupplierRequest.php (both `authorize()` return true)
- app/Http/Requests/Admin/Purchases/StorePurchaseInvoiceRequest.php::rules, withValidator (`authorize()` returns true)
- app/Services/Purchases/SupplierService.php::getPaginatedSuppliers, getAllActiveSuppliers, createSupplier, updateSupplier, deleteSupplier, syncSupplierProducts, getSupplierStatistics
- app/Services/Purchases/PurchaseService.php::getPaginatedInvoices, createDirectPurchase, recordSupplierPayment, processPurchaseReturn, getSupplierLedgerStatement
- app/Contracts/Purchases/{SupplierServiceInterface,PurchaseServiceInterface}.php are bound in app/Providers/SalesAndPurchasesServiceProvider.php::register (plain `bind`, not singleton).
- Models: Supplier (SoftDeletes), SupplierLedgerEntry, SupplierProduct (Pivot, incrementing id), PurchaseInvoice, PurchaseInvoiceItem, Product (SoftDeletes).
- app/Observers/PurchaseInvoiceObserver.php::created exists but is NOT registered (see Side effects).
- Migrations: database/migrations/2026_09_21_170001_create_suppliers_and_purchases_tables.php, 2026_09_23_100001_create_supplier_products_table.php
- Permissions: app/Services/PermissionRegistry.php (group `purchases_suppliers`) and database/seeders/RolesAndPermissionsSeeder.php. Permissions: suppliers.view/create/edit/delete, purchases.view/create/settle_payment. The accountant role gets purchases.view, purchases.settle_payment and suppliers.view.
- Views: resources/views/admin/suppliers/{index,show,edit,ledger}.blade.php and resources/views/admin/purchases/{index,create,show,print}.blade.php. The sidebar gates menu entries with `@can('purchases.view')` / `@can('suppliers.view')`.
- Other code that touches this data: app/Services/Sales/WarrantyService.php (posts `adjustment` ledger entries and reduces the supplier balance), app/Services/Diagnostics/SystemDiagnosticService.php (compares the ledger with the balance and calls createDirectPurchase in its simulation), app/Services/SearchService.php (searches suppliers and purchase invoices).

## Flow per action
- suppliers.index: GET /admin/suppliers → `auth` only → SupplierController::index → SupplierService::getPaginatedSuppliers(search, is_active). Returns JSON if `wantsJson`. Paginates 15 per page and eager-loads products.
- suppliers.create: GET → `auth` only → returns view `admin.suppliers.create`, but that view FILE DOES NOT EXIST, so the route errors. Creating a supplier in the UI uses the modal in suppliers/index.blade.php.
- suppliers.store: POST → `auth` only → StoreSupplierRequest → SupplierService::createSupplier (DB::transaction; current_balance forced to 0; optional products sync) → redirect to index, or 201 JSON.
- suppliers.show: GET → `auth` only → loads products and SupplierService::getSupplierStatistics.
- suppliers.edit / update: GET / PUT → `auth` only → UpdateSupplierRequest (phone unique, ignoring the current supplier) → SupplierService::updateSupplier (transaction; `$supplier->update($data)`).
- suppliers.destroy: DELETE → `auth` only → SupplierService::deleteSupplier. Throws DomainException (caught; returns 422 JSON or back with errors) if the supplier has any purchase invoices or abs(balance) > 0.01. Otherwise the supplier is soft-deleted.
- suppliers.ledger: GET /admin/suppliers/{supplier}/ledger → `can:suppliers.view` → PurchaseService::getSupplierLedgerStatement(date_from, date_to). The date filter uses ledger `created_at`. Entries are ordered by id ascending.
- suppliers.payments: POST /admin/suppliers/{supplier}/payments → `can:purchases.settle_payment` → inline `$request->validate` (amount ≥ 0.01; method cash/bank_transfer/cheque; cheque_number, receipt_number, notes) → PurchaseService::recordSupplierPayment.
- purchases.index: GET → `auth` only → PurchaseService::getPaginatedInvoices (filters: search on invoice_number or supplier name/company_name, supplier_id, branch_id, payment_status, date_from/date_to on invoice_date; 15 per page).
- purchases.create: GET → `auth` only → active suppliers, active products (with category and suppliers), active branches.
- purchases.store: POST → `auth` only → StorePurchaseInvoiceRequest → PurchaseService::createDirectPurchase($validated, auth()->id() ?? 1) → redirect to purchases.show.
- purchases.show: GET → `auth` only → loads supplier, branch, receivedByUser, items.product.category, ledgerEntries.
- purchases.print: GET /admin/purchases/{purchase}/print → `can:purchases.view`.
- No edit, update or destroy routes exist for purchases; invoices cannot be changed after they are saved.

## Business rules
- Invoice numbering: `invoice_number` is typed in manually (it is the supplier's document number; there is no generator). It must be unique across ALL purchase invoices (DB unique index and validation rule), not per supplier.
- Totals (both the service and the FormRequest compute them): subtotal = Σ(qty × unit_cost), where each line is rounded to 2 decimals in the service. final = max(0, subtotal + tax − discount). remaining = max(0, final − paid).
- Validation: `paid_amount` must be ≤ final + 0.01. Items need qty ≥ 1 (integer) and unit_cost ≥ 0. `payment_method` is required even when paid = 0.
- Status: `payment_status` is paid if remaining ≤ 0.001, partially_paid if paid > 0, otherwise unpaid (DB enum: paid, partially_paid, unpaid). It is set only when the invoice is created.
- Stock increase: each line sets `current_stock` = max(0, old) + qty. A negative stock value is clamped to 0 before the qty is added.
- WAC cost update: new cost_price = (oldStock × oldCost + qty × unitCost) / (oldStock + qty), rounded to 2 decimals. It is computed line by line, so if the same product appears on two lines, the in-memory model is updated twice in sequence.
- Supplier catalog: `SupplierProduct::updateOrCreate(supplier, product)` sets last_purchase_price = unit cost, `is_primary_supplier` = true, and `supplier_sku` = the line's SKU (or null).
- Supplier balance: current_balance += remaining_amount (the unpaid part only).
- Ledger on purchase: a `purchase_invoice` entry is written only when remaining > 0, with amount = remaining. If paid > 0, a `supplier_payment` entry is also written with balance_before = the balance after adding remaining, and balance_after = that value − paid.
- Supplier payment: this is the only guard against overpaying (throws DomainException): if the balance is ≤ 0 AND the amount is > 1000. Otherwise the balance can go negative (an advance to the supplier). The payment is not linked to any invoice by the controller (purchase_invoice_id = null).
- Credit limit: `credit_limit` is stored and displayed but is NOT enforced when a purchase is saved.
- Purchase return (PurchaseService::processPurchaseReturn): checks that the return qty is ≤ the invoiced qty and ≤ current stock, then decrements stock and reduces the balance by Σ(qty × unit_cost), clamped at 0. It writes a `purchase_return` ledger entry. It does NOT revert WAC and does NOT change the invoice amounts or status. No route or controller calls it (UNVERIFIED whether anything else is planned to use it).
- Ledger statement totals sum only purchase_invoice, supplier_payment and purchase_return entries. `adjustment` entries (warranty credit notes from WarrantyService) are listed but not totalled.
- Supplier statistics: total_paid = Σ invoice.paid_amount, which counts only payments made on the invoice itself; later payments are excluded. unpaid_invoices_count uses the invoice status that was set at creation.

## Side effects
- createDirectPurchase runs in one DB::transaction. It locks, with `lockForUpdate`, the supplier row and all products on the invoice (fetched in bulk with whereIn). The invoice is created inside `PurchaseInvoice::withoutEvents` to avoid double posting.
- recordSupplierPayment: DB::transaction with the supplier row locked (`lockForUpdate`).
- processPurchaseReturn: transaction. It locks the invoice (which is eager-loaded with items), the supplier and each product.
- SupplierService create/update run in transactions; deleteSupplier does not use a transaction.
- PurchaseInvoiceObserver: it duplicates the service logic (stock, WAC, catalog, balance, ledger). It is NOT registered: AppServiceProvider::boot has a comment saying it is intentionally unused, and there is no `#[ObservedBy]` attribute. If someone registers it, stock and balance would be posted twice for invoices created outside `withoutEvents`.
- Cache: none is used in these services or controllers. No events, jobs or notifications are dispatched.
- `Gate::before` grants every ability to `super-admin`. `Model::preventLazyLoading` is on outside production, which is why the controllers eager-load explicitly.

## Gotchas
- MISSING AUTHORIZATION:
  - Both `Route::resource('suppliers')` and `Route::resource('purchases')` have no `can:` middleware.
  - The controllers make no `authorize`/Gate calls, there is no constructor middleware, and the base Controller class is empty.
  - The FormRequests' `authorize()` returns true.
  - Result: any authenticated active user can list, create, edit and delete suppliers, and can list, create and view purchase invoices (stock and WAC change).
  - Only the ledger, payments and print routes are gated.
  - The permissions suppliers.view/create/edit/delete and purchases.create are defined, but only the sidebar checks any of them.
  - The 403 test (tests/Feature/Sales/SalesAndPurchasesControllersTest.php) does not cover suppliers or purchases.
- Ledger chain inconsistency when paid > 0 and remaining > 0. Example: final 1000, paid 400 → entries +600 (0→600) and −400 (600→200), but current_balance = 600. The last balance_after (200) ≠ current_balance, and purchases − payments ≠ balance. When the invoice is fully paid, the payment entry shows the balance dropping by `paid` even though current_balance did not change. SystemDiagnosticService's ledger-vs-balance check may flag these (UNVERIFIED which formula it uses).
- Later supplier payments never update any invoice's paid_amount, remaining_amount or payment_status, so invoice statuses become stale.
- The supplier catalog sync is unreachable over HTTP: neither the Store nor the Update request has a `products` rule, so `validated()` never contains `products`.
- Every purchase sets `is_primary_supplier = true` for that supplier, so a product can end up with several primary suppliers (WarrantyService uses `primarySupplier()->first()`). A purchase line with no SKU overwrites the existing supplier_sku with null.
- Controller fallbacks: `received_by` falls back to user id 1 when there is no auth user. `branch_id` falls back to the user's branch, then 1. The create form requires a branch.
- An inactive supplier can still receive purchases: the request only checks `exists:suppliers,id`. The create form lists only active suppliers.
- The rule `exists:products,id` does not exclude soft-deleted products. The service query excludes trashed products and throws InvalidArgumentException, which is uncaught (UNVERIFIED: probably a 500).
- recordPayment does not catch DomainException, and bootstrap/app.php withExceptions is empty, so the overpay guard probably surfaces as a 500 (UNVERIFIED).
- The ledger payment modal has no `cheque_number` input, even though the validator accepts one.
- `supplier_sku` is accepted on purchase lines but is not a column or fillable on purchase_invoice_items; it is stored only in supplier_products.
- The client-side JS in purchases/create.blade.php only computes totals for display. When a product is selected it pre-fills unit cost from `data-cost` (the current WAC). Rows are cloned and re-indexed. The server recomputes all totals.
- Supplier route model binding excludes soft-deleted suppliers. `phone` stays unique at the DB level even after soft delete, so a soft-deleted supplier's phone cannot be reused.
- Tests:
  - tests/Unit/Sales/Services/SalesAndPurchasesServicesTest.php (catalog sync, WAC and ledger)
  - tests/Unit/Sales/Requests/SalesAndPurchasesRequestsTest.php (duplicate phone; paid > final)
  - tests/Feature/Sales/EndToEndSalesAndPurchasesScenarioTest.php::test_sector_1 (multi-supplier WAC)
  - tests/Feature/Sales/SalesAndPurchasesControllersTest.php (super-admin access, supplier store)
