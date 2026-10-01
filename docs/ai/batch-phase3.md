# Batch: Phase 3 catalog backend (FE-02)

Branch `batch/phase3-catalog` (from remediation/2026-10 @ 3e4dc19). Feature commit e550d0a.

## Tasks done
- FE-02: products, customers and vehicles now have real backend CRUD. The products and customers pages no longer use `window.AlHusseiniSales`. The POS, credit page, dashboard and sales-store.js are untouched (Phase 4/10).
- Permissions products.view/create/edit/delete, customers.view/create/edit/delete and credit.adjust_limit are now enforced; previously they were dead. Each route has `can:` middleware and each FormRequest authorize() checks the same permission.

## Files
- New:
  - app/Contracts/Catalog/ProductServiceInterface.php, app/Services/Catalog/ProductService.php
  - app/Contracts/Sales/CustomerServiceInterface.php, app/Services/Sales/CustomerService.php
  - app/Http/Controllers/Admin/{ProductController,CustomerController}.php
  - app/Http/Requests/Admin/Products/{Store,Update}ProductRequest.php
  - app/Http/Requests/Admin/Customers/{StoreCustomer,UpdateCustomer,CustomerVehicle}Request.php
  - tests/Feature/Admin/CatalogManagementTest.php
- Changed:
  - app/Providers/SalesAndPurchasesServiceProvider.php (2 binding lines)
  - routes/web.php (the two closures replaced, plus a delimited "Phase 3" block)
  - resources/views/admin/sales/{products,customers}.blade.php (rewritten)
- No migrations.

## Routes (all inside `auth`, prefix admin, name admin.)
- GET sales/products → ProductController@index [products.view] (admin.sales.products, name kept)
- GET sales/customers → CustomerController@index [customers.view] (admin.sales.customers, name kept)
- GET products/search?q= → @search [products.view]: active products only; fields id, name, brand, sku, barcode, capacity_ah, retail_price, current_stock, is_battery, warranty_months, category_slug, category_name
- POST products [products.create]; PUT products/{product} [products.edit]; DELETE products/{product} [products.delete]
- GET customers/search?q= → @search [customers.view]: active customers, excluding walk-in phone 00000000000; fields id, name, phone, tier, credit_limit, current_credit_balance, vehicles[]
- GET customers/{customer} → @show JSON profile [customers.view]: last 20 invoices with items and warranty, last 20 warranties
- POST customers [customers.create]; PUT customers/{customer} [customers.edit]; DELETE customers/{customer} [customers.delete]
- POST customers/{customer}/vehicles; PUT and DELETE customers/{customer}/vehicles/{vehicle} [customers.edit; scopeBindings]

## Business rules applied (derived from existing schema and invariants, not invented)
- products.current_stock is never accepted from input. It is 0 on create; stock moves only through purchases, sales, returns and claims (plan Phase 3, ARC-04).
- cost_price is accepted only as the initial cost on create. Afterwards it is the purchases WAC, so update ignores it.
- Product delete is blocked when current_stock > 0 or the product is referenced by invoice_items, purchase_invoice_items or warranty_claims (replacement_product_id). Deactivation (is_active) is the retire path. Delete is a soft delete.
- Customer current_credit_balance is never accepted. credit_limit is rejected (`prohibited`, 422) unless the user has credit.adjust_limit; on create without it, the DB default (5000) applies, as before.
- Customer delete is blocked when balance > finance.epsilon, and for the walk-in account (phone 00000000000, used by PosOrderService for walk-in battery warranties: deleting it would make PosOrderService::firstOrCreate collide with the unique phone). The walk-in phone cannot be edited.
- Vehicle plate is unique per customer (matches the DB unique). Vehicle delete is blocked if referenced by invoices or warranties (the FKs are nullOnDelete, so deleting would silently erase history).
- Tier labels in the UI reuse the existing credit page mapping: standard=ملاكي, vip=تاكسي وأوبر, fleet=ورش وشركات. The mock-only type "نقل وتريلات" has no DB tier and was dropped.
- "Purchases total" per customer = Σ final_amount of invoices excluding cancelled and refunded. The partially_refunded semantics are owned by Phase 5 (BIZ-05); this figure may need revisiting after Phase 5 adds refunded_amount.

## Tests
- Before: 172 passed (1029 assertions). After: 191 passed (1141 assertions).
- 19 new tests:
  - per-role access: the cashier gets 403 on product create/update/delete and on customer edit/delete/vehicle; the branch-manager can create and edit products but gets 403 on delete; super-admin is allowed; a role without customers.view (workshop-supervisor) gets 403 on the profile;
  - validation: duplicate sku/barcode, missing price, credit_limit prohibited;
  - guards: stock is not editable, WAC is not editable, delete is blocked with stock, with a sale reference, with a balance, and for the walk-in account;
  - scoped vehicle binding returns 404;
  - search payloads come from real DB rows;
  - the index pages render DB rows and do not contain "AlHusseiniSales".

## Notes and gaps for other batches
- Phase 4 (POS) can now use `admin.products.search` and `admin.customers.search`, and `admin.customers.store` for quick-add.
- The old mock-only fields (scrap value per product, unit, technology type) do not exist in the schema and were removed from the products UI. Scrap pricing stays in ScrapPricingTier (unchanged).
- Product JSON search matches the barcode exactly but the name/brand/sku partially.
- Blocked: none.
