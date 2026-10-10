# Sales Module (POS, Invoices, Returns, Credit/آجل, Payments, Manager Override)

Snapshot of code on disk as of 2026-09-30, including uncommitted working-tree changes (PosOrderService, StorePosInvoiceRequest, SalesInvoiceController, CreditCustomerController, InvoicePayment, InvoiceFilter, Finance/ManagerOverrideService, config/finance.php, 2 new migrations). Verify again before relying on it; this module is being actively remediated.

## Purpose
- Cashier POS for batteries, oils, greases and workshop services. Supports split payments, a scrap battery trade-in (كهنة) deduction, battery serial and warranty issuance, and credit (آجل) sales to registered customers.
- Sales invoice listing, filtering and stats, invoice detail, and partial or full sales returns.
- Credit customer overview, debt collection (FIFO allocation across open invoices), and customer statement (ledger plus open invoices).
- Manager override code: required when a sale pushes a customer past the credit limit, and for discounts or below-retail prices by users without `invoices.discount`.

## Key files
- Routes: routes/web.php (inside the `admin` prefix, `admin.` name, `auth` group). Lines ~174-211 cover POS, invoices, credit and the `sales.*` aliases.
- app/Http/Controllers/Admin/PosController.php::index, store, receipt, warrantyCert
- app/Http/Controllers/Admin/SalesInvoiceController.php::index, show, processReturn (inline validation, no FormRequest)
- app/Http/Controllers/Admin/CreditCustomerController.php::index, settlePayment (inline validation), statement
- app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php::rules, withValidator, isManagerOverrideValid
- app/Contracts/Sales/PosOrderServiceInterface.php is bound to app/Services/Sales/PosOrderService.php in app/Providers/SalesAndPurchasesServiceProvider.php::register (the same provider also binds WarrantyService and ScrapBatteryService)
- app/Services/Sales/PosOrderService.php::getPaginatedInvoices, getInvoiceStats, processPosSale, processSalesReturn, calculateScrapDeduction, getDailyCashierSummary, settleCustomerDebt, isManagerOverrideValid
- app/Services/Sales/InvoiceFilter.php::fromArray, apply, applyForStats, toArray
- app/Services/Finance/ManagerOverrideService.php::isValid
- config/finance.php: `epsilon` (env FINANCE_EPSILON, default 0.01), `manager_override_code` (MANAGER_OVERRIDE_CODE), `manager_override_hash` (MANAGER_OVERRIDE_CODE_HASH). The last two are listed in .env.example.
- Models: Invoice, InvoiceItem, InvoicePayment (scopes `active`, `cash`), CreditLedgerEntry, Customer (scopes `inDebt`, `exceededLimit`; defaults credit_limit 5000, tier standard), CustomerVehicle, Product (`current_stock`, `retail_price`, `is_battery`, `warranty_months`), Category, TechnicianCommission, Warranty, ScrapBatteriesInventory, ScrapPricingTier::findPriceForCapacity
- app/Observers/InvoiceObserver.php: DEAD. It is not registered anywhere (AppServiceProvider has a NOTE explaining why, and there is no ObservedBy attribute).
- Migrations: 2026_09_21_160008 (invoices, invoice_items; invoice_number unique; payment_method enum cash/card/bank_transfer/credit/split), 2026_09_21_160010 (credit_ledger_entries; entry_type enum invoice_debt/payment_collection/credit_adjustment/refund), 2026_09_23_100003 (invoice_payments; method enum cash/card/bank_transfer/credit), 2026_09_30_000001 (adds `partially_refunded` to invoices.status), 2026_09_30_000002 (drops unique on credit_ledger_entries.receipt_number and replaces it with a plain index)
- Views: resources/views/admin/sales/pos.blade.php plus sales/pos/partials/* (the JS is in scripts.blade.php), admin/pos/receipt and warranty_cert, admin/invoices/index (just `@include('admin.sales.invoices')`), admin/invoices/show (includes the return modal), admin/sales/credit.blade.php, admin/credit/statement.blade.php. The client-side mock store is public/assets/js/sales-store.js, loaded globally from layouts/partials/vendor-scripts.blade.php.
- Tests: tests/Feature/Sales/{FinanceRegressionTest (R-01..R-20), CreditAndInvoiceManagementTest, SalesAndPurchasesControllersTest, EndToEndSalesAndPurchasesScenarioTest (sectors 2, 3 and 7 are sales)}, tests/Unit/Sales/Requests/SalesAndPurchasesRequestsTest

## Routes and permissions
- All routes below are prefixed `/admin`, named `admin.*`, and wrapped in the `auth` middleware. Permission checks use `can:` middleware. Gate::before grants everything to the `super-admin` role.
- GET pos → `pos.index` (can:pos.access). POST pos → `pos.store` (can:pos.access).
- GET pos/{invoice}/receipt → `pos.receipt` (can:invoices.print). GET pos/{invoice}/warranty → `pos.warranty_cert` (can:warranties.view).
- GET invoices → `invoices.index` and GET invoices/{invoice} → `invoices.show` (can:invoices.view). POST invoices/{invoice}/return → `invoices.return` (can:invoices.cancel).
- GET credit → `credit.index` and GET credit/{customer}/statement → `credit.statement` (can:credit.view). POST credit/settle → `credit.settle` (can:credit.settle).
- Aliases under the `sales.` prefix: GET sales/pos, sales/invoices, sales/credit, POST sales/credit/settle, GET sales/credit/{customer}/statement. They use the same controllers and permissions. sales/customers and sales/products are closure views (can:customers.view, can:products.view).
- Web root redirects cashiers, or users with pos.access but without dashboard.view, to `admin.pos.index` (web.php ~line 39).

## Flow: POS sale (POST admin/pos)
- The route checks can:pos.access, then StorePosInvoiceRequest runs. Its `authorize()` returns true, so authorization rests on the route middleware only.
- Rules:
  - `technician_id` is required (employees).
  - `items[]` needs at least 1 entry with product_id, quantity (integer, at least 1), and optional unit_price and battery_serial.
  - Scrap fields: has_scrap, scrap_capacity_ah (30-250), scrap_count, scrap_price_override, scrap_deduction_amount.
  - discount_amount and tax_amount.
  - `payments[]` needs at least 1 entry. method is one of cash/card/bank_transfer/credit; amount is at least 0.01; reference is optional.
  - Also: manager_override_code, notes, and nullable branch_id, customer_id, customer_vehicle_id.
- `withValidator` checks, in order:
  - Stock covers each quantity (no lock at this point).
  - Battery products need a non-empty serial. The serial must not repeat within the order and must not match any existing Warranty row, whatever its status.
  - Scrap deduction is computed.
  - Discount or unit_price below `retail_price` without `invoices.discount` needs a valid override code.
  - discount + scrap must not exceed subtotal + tax (0.01 tolerance).
  - The payments total must equal the final amount within epsilon.
  - A credit payment needs a customer_id. If current balance + credit exceeds credit_limit, a valid override code is needed.
- PosController::store takes the cashier from `auth()->id() ?? 1` and calls PosOrderService::processPosSale(validated, cashierId).
- Responses: JSON 201 {invoice_id, invoice_number, receipt_url, warranty_cert_url}, or a redirect to the receipt. DomainException or InvalidArgumentException become 422 JSON or back() with the `pos_error` key.
- processPosSale runs inside one DB::transaction:
  1. Locks the products with `lockForUpdate` and re-checks stock (DomainException).
  2. Uses unit_price when given, otherwise retail_price. Line total is round(qty*price, 2).
  3. Computes the scrap deduction, rounds discount and tax, and rejects discount + scrap > gross + 0.01. final = gross - discount - scrap.
  4. Sums payments and rejects a mismatch with final beyond epsilon. Re-checks technician_id.
  5. If there is credit, locks the customer (`lockForUpdate`) and re-checks the limit plus override.
  6. branch_id comes from the request, else the cashier User's branch_id, else the auth user's branch. No branch at all throws InvalidArgumentException.
  7. Creates the Invoice inside `Invoice::withoutEvents`, then the InvoicePayments, the credit ledger entry, items with stock decrement and warranties, and scrap inventory.
  8. Returns `fresh()` with relations loaded.
- Views: receipt loads items.product, customer, customerVehicle, technician, cashier, payments, branch, scrapBattery. warranty_cert loads only items that have a battery_serial_number, with their warranty.

## Flow: invoice list and show
- SalesInvoiceController::index takes only search, status, branch_id, date_from, date_to and calls getPaginatedInvoices (15 per page, latest id) plus getInvoiceStats.
- If date_from > date_to, InvoiceFilter throws a ValidationException. The controller returns 422 JSON or back() with errors.
- InvoiceFilter search matches invoice_number, customer name or phone, product name/sku/barcode, and item battery_serial_number (test R-17). Dates use a `created_at >=` 'Y-m-d 00:00:00' and `<=` '23:59:59' range.
- InvoiceFilter also supports `payment_method`, but the controller never passes it (it is not in `$request->only`).
- Stats (applyForStats): when no status filter is set, cancelled, refunded AND partially_refunded invoices are excluded. The stats are total_sales (sum of final_amount), invoices_count, credit_invoices_count (remaining > 0), total_remaining_credit, and scrap_count.
- The JSON response returns the paginator only. The view is `admin.invoices.index`, which includes sales/invoices.
- show eager-loads customer, vehicle, cashier, technician, branch, items.product.category, payments, scrapBattery, technicianCommission.employee. The return modal renders only under @can('invoices.cancel').

## Flow: sales return (POST admin/invoices/{invoice}/return)
- The route checks can:invoices.cancel. Inline validation requires `reason` (max 500), and `items[]` with product_id (exists) and quantity (at least 1).
- The controller calls processSalesReturn(invoice id, items, reason, auth id or 1).
- Success returns JSON {success, data} or a redirect to show. Only DomainException is caught, and the non-JSON path always redirects back() with `return_error`.
- processSalesReturn runs inside one DB::transaction:
  - Locks the invoice. Rejects status `refunded` or `cancelled`. `partially_refunded` invoices can be returned against again.
  - Items are matched by `keyBy('product_id')` and the quantity must not exceed the original line quantity. It does not track quantities already returned by earlier returns.
  - For each item it locks the product and increments stock. refund += qty * the item's unit_price. If the line has a battery serial, the Warranty rows with that serial are updated to `voided` with the reason in notes.
  - isFullReturn is true when this call's refundTotal is at least final_amount - 0.01.
  - Split for registered customers: creditRefund = min(refund, remaining_amount) and cashRefund = the rest. Walk-in customers get the whole refund as cash.
  - Credit part: locks the customer, lowers current_credit_balance (clamped at 0), and writes a CreditLedgerEntry `refund`. It also reduces invoice.remaining_amount.
  - Cash part: writes an InvoicePayment with method cash, a NEGATIVE amount, and reference `REFUND-{invoice_number}`. It also reduces paid_amount (clamped at 0). Test R-10 covers this.
  - Sets status to `refunded` (full) or `partially_refunded` (tests R-10, R-11) and appends the reason and amount to notes.

## Flow: credit overview, collection and statement
- index lists customers with current_credit_balance > 0 and eager-loads vehicles and the last 5 ledgers. Totals are totalOutstanding, customersCount, and exceededLimitCount (only customers with limit > 0 and balance > limit). The view is `admin.sales.credit`.
- settlePayment inline validation: customer_id (exists), amount (at least 0.01), payment_method (cash, card or bank_transfer; credit is not allowed), receipt_number (max 50), notes.
- It calls settleCustomerDebt and returns JSON {new_balance, receipt_number} or a redirect to `admin.sales.credit`.
- settleCustomerDebt runs inside one DB::transaction:
  - Locks the customer.
  - Rejects a receipt_number that already exists on any CreditLedgerEntry (DomainException, test R-08).
  - Rejects a balance at or below epsilon, and an amount greater than balance + epsilon (test: 422).
  - Sets the balance to max(0, balance - amount).
  - FIFO: locks the customer's invoices with remaining > eps and status NOT IN (cancelled, refunded, partially_refunded), ordered by id ascending. For each, it applies min(amount left, remaining), updates paid, remaining and status (paid or partially_paid), and creates an InvoicePayment with the collection method and transaction_reference = receipt_number (test R-03).
  - Creates one CreditLedgerEntry `payment_collection` with invoice_id null.
- statement:
  - Open invoices (remaining > 0.01) are paginated 20 per page (`invoices_page`) with payments and items.product.
  - Ledgers are paginated 50 per page (`ledgers_page`) with collectedByUser.
  - The JSON response returns the paginators. For Blade, setRelation replaces the relations with the page collections.

## Business rules
- Payment methods:
  - The POS request accepts cash, card, bank_transfer and credit. The invoice-level `payment_method` is the single method used, or `split` when more than one distinct method appears.
  - Collections accept cash, card and bank_transfer. The UI's `instapay` is mapped to bank_transfer client-side.
- Amounts and status at sale:
  - paid_amount = total paid - credit portion, and remaining_amount = credit portion.
  - Status is `paid` with no credit, `partially_paid` when 0 < credit < final, and `unpaid` when credit >= final.
- Invoice statuses (DB enum after migration 000001): paid, partially_paid, unpaid, cancelled, refunded, partially_refunded. No code path in this module sets `cancelled`.
- Tax and discount:
  - Both are single invoice-level amounts sent by the client. The server calculates no tax rate.
  - The POS UI always sends discount_amount 0 and tax_amount 0.
  - A discount of exactly the subtotal is allowed, giving a final of 0 (test R-07). A discount above subtotal is rejected (R-05).
- Scrap trade-in (has_scrap):
  - Deduction precedence: `scrap_deduction_amount` (total), then `scrap_price_override` * count, then the ScrapPricingTier price for the capacity (active tier whose min_ah <= Ah <= max_ah) * count. With no matching tier the deduction is 0.
  - Capacity defaults to 70 when missing. Count is at least 1.
  - One ScrapBatteriesInventory row is created per scrap battery, but only if the deduction is > 0. Each row has capacity_ah "{Ah}Ah", scrap_value = deduction/count, lead_weight_kg = Ah*0.17, status in_stock, and received_by = technician_id (or 1).
- Stock:
  - The FormRequest pre-checks stock without a lock. The service re-checks under `lockForUpdate` and then runs `decrement('current_stock')` per line.
  - All products decrement stock, including service-category items (UNVERIFIED whether services carry stock).
  - Returns increment stock.
  - Test sector 7 covers overselling protection.
- Warranty:
  - Created only when the product is_battery and a serial exists. Duration is the product's warranty_months, or 12. Start is today, status active, and the item stores warranty_duration_months.
  - A walk-in battery sale attaches the warranty to a shared guest customer, created with firstOrCreate on phone `00000000000` ("عميل نقدي عابر") and credit_limit 0.
- Credit limit:
  - The check is `balance + credit > credit_limit`, so a customer with limit 0 always needs an override for any credit.
  - It is checked in both the FormRequest and the service (the service re-checks under lock).
  - Walk-in customers cannot buy on credit.
- Manager override (ManagerOverrideService::isValid):
  - Empty code returns false.
  - Rate limited to 5 attempts per key `manager-override:{ip}`, with a 300 s decay on a miss. A success clears the key.
  - Checks, in order: config hash (Hash::check), then config plain code (hash_equals), then the hard-coded legacy codes `mgr_override_99` and `9999`, which are ALWAYS accepted (marked "TODO remove").
  - Fallback, only when neither a hash nor a code is configured: any user with an admin/manager/branch_manager/super-admin variant role whose password matches.
  - It is used in both StorePosInvoiceRequest and PosOrderService.
- Invoice numbering: `INV-{Ymd}-{last 6 of uniqid() uppercased}`. There is no sequence or lock; it relies on the unique DB index (a collision would surface as a DB exception, not a 422).
- Technician commission: NOT created by the sales flow. Test sector 2 asserts that no TechnicianCommission exists for a POS invoice. Commissions exist only from the seeder (SalesAndPosDataSeeder) and are consumed by PayrollService.
- Monetary tolerance: `config('finance.epsilon')` (0.01) is used for payment matching and FIFO. A hard-coded +0.01 is used for the discount cap and the full-return check.

## Side effects
- DB transactions: processPosSale, processSalesReturn and settleCustomerDebt each wrap all their writes in one DB::transaction.
- Row locks (`lockForUpdate`): products (sale, return), customer (credit sale, return credit part, settlement), invoice (return), and open invoices (FIFO).
- Ledger (credit_ledger_entries): `invoice_debt` on a credit sale (with balance_before/after and collected_by = cashier), `refund` on a return's credit part, and `payment_collection` on settlement. `credit_adjustment` is never written by this module.
- invoice_payments:
  - Sale: one row per payment entry, including the `credit` method rows.
  - Settlement: one row per FIFO-applied invoice.
  - Return: one negative cash row.
  - The dashboard uses scope `cash` (method != credit) and scope `active`. Test R-19 checks that settlement does not double count revenue.
- Customer.current_credit_balance is updated directly (sale +, return -, settlement -). No observer or event is involved.
- Warranties are created (sale) or updated to voided (return). ScrapBatteriesInventory rows are created on sale; a return does NOT reverse them.
- Observers, notifications, cache: none in this flow. InvoiceObserver is unregistered, and invoices are created with `withoutEvents` anyway. There are no Cache:: or notify calls in the sales services or controllers.
- getDailyCashierSummary is not called by any controller (only the interface and service define it). UNVERIFIED whether anything else uses it.
- SystemDiagnosticService calls processPosSale directly for simulations. This is why the legacy override codes were kept.

## Front-end (JS) behaviour
- POS (sales/pos/partials/scripts.blade.php):
  - The catalog and customer dropdown are read from `window.AlHusseiniSales` (public/assets/js/sales-store.js), a localStorage mock seeded with string IDs like `PROD-101` and `CUST-101`.
  - The `$products`, `$customers` and `$scrapTiers` passed by PosController::index are NOT used by the JS. Only `$technicians` is rendered, in Blade.
- POS submit builds the payload client-side:
  - Subtotal is priceNew*qty. The scrap discount is the manual scrap price input.
  - Credit mode sends a cash deposit plus a credit remainder.
  - `instapay` is mapped to bank_transfer.
  - discount_amount and tax_amount are hard-coded to 0.
- It POSTs JSON to `admin.pos.store`. On a 422 that has an override-related error key, it prompts with a SweetAlert password field ("الافتراضي 9999") and resends the request with manager_override_code.
- Credit page (sales/credit.blade.php):
  - Says it is "server-backed", but KPIs, customer rows and the payments tab still fall back to the localStorage mock.
  - Settlement POSTs JSON to `admin.credit.settle` with customer_id parseInt'd (null for mock IDs, so it fails validation), then mirrors the payment into the mock store.
  - Statement is fetched from a hard-coded `/admin/credit/{id}/statement`.
- Credit statement (credit/statement.blade.php): a normal form POST to credit.settle. Amount max is the current balance and the receipt_number input is optional.
- Invoice show return modal: each line has a checkbox named `items[i][product_id]` and a quantity field `items[i][quantity]`, both prefilled to return everything.

## Gotchas
- POS ID mismatch:
  - `product_id: !isNaN(parseInt(it.product.id)) ? parseInt(...) : 1`. Mock IDs like `PROD-101` parse to NaN, so every line is sent as product_id 1.
  - Mock customer IDs parse to NaN, so customer_id is null (walk-in).
  - Whether real DB products ever reach the POS catalog is UNVERIFIED (see sales-store.js saveProduct).
- The POS JS invents a battery serial `BAT-{timestamp}-{n}` when none is entered, which defeats the mandatory-serial rule.
- Serial uniqueness checks every Warranty row regardless of status. A battery that was returned (warranty voided) cannot be resold under the same serial.
- Returns:
  - Earlier returns are not tracked, so the same quantity can be returned repeatedly while the status is partially_refunded.
  - The refund is valued at the line unit_price, ignoring invoice discount, scrap deduction and tax, so it can exceed final_amount or paid_amount (paid is clamped at 0 but the negative payment is still recorded).
  - `keyBy('product_id')` collapses duplicate product lines.
  - The full-return test compares only the current call's refund.
  - An unchecked item in the modal still posts its quantity without a product_id, which fails validation for the whole request.
  - There is no branch check on returned stock. Scrap inventory is not reversed.
- `partially_refunded` invoices are excluded entirely from stats, the dashboard and InvoicePayment::active (the whole invoice value disappears, not just the returned part). They are also excluded from FIFO settlement, so remaining_amount left on them is never collected by FIFO. customer balance can then drift from the sum of invoice remaining_amount.
- Settlement over-collection guard is per customer only. The ledger may record the full amount even if FIFO found fewer open invoices (for example when debt sits on partially_refunded invoices), leaving `remainingToApply` unallocated with no error.
- The receipt_number unique index was dropped (migration 000002) because FIFO writes the same reference on many invoice_payments rows. Idempotency now relies on the service's exists() check under the customer lock, which does not block a race across different customers.
- The ManagerOverrideService legacy codes `9999` and `mgr_override_99` are always valid, even when a hash is configured. The rate limiter is keyed by IP only.
- The guest customer is created with firstOrCreate without a lock, so concurrent first-ever walk-in battery sales could race (as reported in POS_ERRORS_REPORT, consistent with the code).
- `auth()->id() ?? 1` fallbacks exist in the controllers, the same with `received_by ?? 1` in the service.
- A LogicException for a negative final amount is not caught by PosController and would surface as a 500. It is unreachable in practice because of the earlier check.
- Migration 000001 on SQLite rewrites sqlite_master and turns on `PRAGMA ignore_check_constraints=ON` and `foreign_keys=OFF` for that connection. Treat it as a test/dev hack.
- SalesInvoiceController::index catches ValidationException only around the service calls. The non-JSON path redirects back(), so a bad date range on first load bounces to the previous page.
- The InvoiceObserver file still contains old logic (stock, warranty, ledger, scrap). Registering it would double-apply every side effect (as warned in POS_ERRORS_REPORT P0-09 and the AppServiceProvider note).
- docs/FINANCE_ERRORS_REPORT.md and docs/POS_ERRORS_REPORT.md list issues. Several are fixed on disk: FIFO InvoicePayments, cash refunds, partially_refunded, the date-order check, serial search, and statement pagination. Others remain: legacy override codes, uniqid numbering, and the guest customer race. Re-verify before citing any ID from them.
