# Warranties, Warranty Claims and Scrap Batteries (مخزن الكهنة)

## Purpose
- Warranties: electronic warranty certificate per sold battery serial (issued at POS checkout), serial verification, instant in-store claim tickets (replace / recharge / repair / reject), and follow-up settlement of the defective unit with the supplier.
- Scrap: old batteries taken as trade-in at POS (deducted from invoice) are stored per unit in `scrap_batteries_inventory`, then sold in batches to lead-recycling factories. Trade-in price comes from amp-hour (Ah) pricing tiers.

## Key files
- Routes: `routes/web.php` (admin group, lines ~177, 185-193).
- `app/Http/Controllers/Admin/WarrantyController.php` :: index, verify, storeClaim, settleSupplier.
- `app/Http/Controllers/Admin/ScrapInventoryController.php` :: index, sellBatch, updateTiers.
- `app/Http/Controllers/Admin/PosController.php` :: warrantyCert (view `admin.pos.warranty_cert`); also passes active `scrapTiers` to the POS view in index.
- `app/Http/Requests/Admin/Warranties/ProcessWarrantyClaimRequest.php`; `app/Http/Requests/Admin/Scrap/StoreScrapSaleBatchRequest.php`; POS side: `app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php` (serial and scrap checks).
- `app/Services/Sales/WarrantyService.php` :: getPaginatedClaims, verifyBatterySerial, issueWarranty, processInstantClaim, settleClaimWithSupplier.
- `app/Services/Sales/ScrapBatteryService.php` :: getPaginatedInventory, getInventoryMetrics, dispatchScrapSaleBatch, updatePricingTiers.
- `app/Services/Sales/PosOrderService.php` :: store flow steps 2 (scrap calc), 7 (warranty issue), 8 (scrap deposit); processReturn (voids warranty); calculateScrapDeduction.
- Contracts `app/Contracts/Sales/{WarrantyServiceInterface,ScrapBatteryServiceInterface}.php`, bound in `app/Providers/SalesAndPurchasesServiceProvider.php`.
- `app/Observers/WarrantyClaimObserver.php` (registered in `AppServiceProvider`).
- Models: `Warranty`, `WarrantyClaim`, `ScrapBatteriesInventory` (table `scrap_batteries_inventory`), `ScrapPricingTier`; `InvoiceItem::warranty` (hasOne), `Product::warrantyClaims`.
- Migrations: `2026_09_21_160009_create_warranties_and_warranty_claims_tables`, `2026_09_23_100004_enhance_warranty_claims_table`, `2026_09_21_160011_create_scrap_batteries_inventory_table`, `2026_09_23_100002_create_scrap_pricing_tiers_table`. Seeder `ScrapPricingTiersSeeder`.
- Views: `resources/views/admin/warranties/{index,verify}.blade.php`, `resources/views/admin/scrap/index.blade.php`.
- Tests: `tests/Feature/Sales/EndToEndSalesAndPurchasesScenarioTest.php` (sector_2 POS scrap+warranty, sector_4 verify+instant replace, sector_5 supplier settlement, sector_6 scrap batch sale).

## Flow per action
- GET `pos/{invoice}/warranty` (admin.pos.warranty_cert) → `can:warranties.view` → PosController::warrantyCert → loads only items with non-null battery_serial_number + product + warranty, customer, vehicle, technician, branch → printable view.
- GET `warranties` (admin.warranties.index) → `can:warranties.view` → WarrantyController::index → WarrantyService::getPaginatedClaims(filters search/decision/supplier_resolution/supplier_id/branch_id, 15/page) → JSON if wantsJson, else view with counts (active warranties, pending claims), active suppliers, active employees (technicians), in-stock active battery products (replacement options).
- GET `warranties/verify` (admin.warranties.verify) → `can:warranties.view` → verify: browser request without `serial_number`, or with `view=1`, renders `verify` view (runs lookup if serial given); otherwise JSON (422 if empty serial) → WarrantyService::verifyBatterySerial.
- POST `warranties/claims` (admin.warranties.claims.store) → `can:warranties.claim` → ProcessWarrantyClaimRequest → storeClaim → WarrantyService::processInstantClaim(validated, auth id ?? 1) → Warranty, Product, WarrantyClaim. DomainException → back with `claim_error`; 201 JSON if wantsJson.
- POST `warranties/claims/{claim}/settle` (admin.warranties.claims.settle) → `can:warranties.approve_replace` → inline validate (action in sent_to_supplier|settled_replacement|settled_credit_note|rejected, credit_amount nullable numeric>=0, notes) → settleSupplier → WarrantyService::settleClaimWithSupplier → Product / Supplier / SupplierLedgerEntry / WarrantyClaim. Settle form action URL is hardcoded in JS as `/admin/warranties/claims/{id}/settle`.
- GET `scrap-inventory` (admin.scrap.index) → `can:scrap.view` → index: branchId = request branch_id ?: user branch ?: 1 → getInventoryMetrics(branchId) + getPaginatedInventory(status/capacity_ah/batch_number) → JSON or view with tiers and active branches.
- POST `scrap-inventory/sell-batch` (admin.scrap.sell_batch) → `can:scrap.transfer` → StoreScrapSaleBatchRequest → sellBatch → ScrapBatteryService::dispatchScrapSaleBatch → ScrapBatteriesInventory. View JS builds hidden `scrap_battery_ids[]` from checked rows (checkboxes rendered only for `in_stock` rows).
- PUT `scrap-inventory/tiers` (admin.scrap.update_tiers) → `can:settings.manage` → inline validate (tiers[].id exists, default_scrap_price >=0, is_active boolean optional) → ScrapBatteryService::updatePricingTiers.
- POS checkout (POST `pos`, `can:pos.access`) → StorePosInvoiceRequest → PosOrderService store → creates Warranty rows and ScrapBatteriesInventory rows (see side effects).
- All FormRequests `authorize()` return true; authorization is only the route `can:` middleware.

## Business rules
- Warranty period: Product.warranty_months (default 12) copied to InvoiceItem.warranty_duration_months; Warranty start = today, end = today + months. Issued only for `is_battery` products with a non-empty battery_serial.
- Warranty status enum: active, expired, claimed, voided. `expired` is never written by app code (no scheduler found); expiry is computed from end_date at read time. UNVERIFIED whether any command sets it.
- Serial uniqueness: `warranties.serial_number` unique in DB; POS request rejects duplicate serials within the order and any serial already in `warranties` (any status). Claim request rejects replacement serial equal to defective or already in `warranties`.
- Guest warranties: if POS sale has no customer, warranty is attached to a firstOrCreate guest customer (phone `00000000000`, name "عميل نقدي عابر").
- Verification (verifyBatterySerial): exact trimmed serial match; is_valid = not expired (end_date past) and not voided and not claimed; returns days_remaining (>=0), warranty with customer, vehicle, invoiceItem.product, invoiceItem.invoice, claims.
- Claim validation: defective_serial must exist in warranties; technician_id exists in employees; battery_voltage_tested 0-25; cca_tested 0-2000 optional; issue_description 5-1000; decision in replaced|recharged|repaired|rejected; replacement_product_id + replacement_battery_serial required_if replaced; rejection_reason required_if rejected. After-hook blocks expired or voided warranty and out-of-stock replacement product.
- Claim processing (transaction, warranty lockForUpdate): rechecks expired/voided (DomainException). Does NOT block an already `claimed` warranty (request does not either) — a claimed serial can get another claim ticket. UNVERIFIED if intended.
- Decision `replaced`: lock replacement product, require stock >= 1, decrement current_stock by 1, old warranty → `claimed`, new Warranty for replacement serial (same invoice_item_id, customer, vehicle; period = replacement product warranty_months ?? 12, i.e. fresh full period not remaining period), supplier_id = replacement product primarySupplier (not the original battery supplier).
- Decisions recharged/repaired/rejected: only the claim row is created; warranty status unchanged; supplier_id null; supplier_resolution still `pending`. rejection_reason is validated but never stored (no column).
- Claim fields: claim_number via observer `CLM-YYYYMM-NNNN` (sequence = count of claims created this month + 1); branch_id = data branch_id (not in FormRequest, so effectively user branch ?? 1); claim_date today; received_by_user_id; supplier_resolution `pending`.
- Supplier settlement actions: `sent_to_supplier` (status only); `settled_replacement` → increments replacement product current_stock by 1 (if replacement_product_id set); `settled_credit_note` (only if claim.supplier_id) → credit = credit_amount ?? replacementProduct.cost_price ?? 0, if > 0 reduces supplier.current_balance and writes SupplierLedgerEntry (entry_type `adjustment`, payment_method `cash`, notes with claim number); `rejected` (status only). All set supplier_resolution = action, settled_by_user_id, resolved_at.
- Scrap trade-in pricing at POS (has_scrap): priority 1 `scrap_deduction_amount` (total), 2 `scrap_price_override` x scrap_count, 3 tier price for scrap_capacity_ah (default 70) x count via ScrapPricingTier::findPriceForCapacity (active tier with min <= Ah <= max); no matching tier → 0. scrap_capacity_ah validated 30-250. discount + scrap deduction must not exceed subtotal + tax (+0.01).
- Seeded tiers: 40-55 Ah 600, 56-75 Ah 800, 76-100 Ah 1100, 101-150 Ah 1700 EGP. Capacities outside tiers (30-39, 151-250) yield 0 deduction unless manually priced.
- Scrap weight: lead_weight_kg = Ah x 0.17 (hardcoded estimate). Stored per unit; capacity_ah stored as string like `70Ah`.
- Scrap unit value: scrap_value = total deduction / count (rounded 2dp) — represents the cost basis of the scrap.
- Metrics (per branch, in_stock only): units, total scrap_value, total lead kg, metric tons, breakdown by capacity_ah.
- Batch sale: all ids must exist and be `in_stock` (request + locked recheck); batch_number `SCRAP-BATCH-YYYYMMDD-XXXX` (uniqid suffix); rows → status `sold_to_factory` + batch_number; returns cost value, sale price (total_amount), gross_profit = sale - cost. Status `recycled` exists but is never set.
- Tier update: only default_scrap_price and is_active of existing tiers; no create/delete; UI does not send is_active (toggle not editable from UI).

## Side effects
- WarrantyClaimObserver::creating → claim_number + received_at defaults. No observers on Warranty or scrap models found.
- Stock: POS sale decrements product stock; claim replacement decrements 1; supplier `settled_replacement` increments 1; POS return increments stock and sets Warranty `voided` (notes include reason) for the item's serial. No stock-movement/audit table is written (only raw increment/decrement).
- Ledger: only `settled_credit_note` writes SupplierLedgerEntry and changes Supplier.current_balance. Claim replacement creates no invoice (replacement_invoice_id never set), no cost/expense entry.
- Scrap sale: NO money record — buyer_name, buyer_phone, payment_method, notes, total_amount and gross_profit are only echoed in the response/flash; nothing persisted except status + batch_number. No treasury/cash entry.
- Scrap deposit at POS: one ScrapBatteriesInventory row per scrap_count, received_by = technician_id ?? 1 (employee FK), invoice_id linked; only when scrap deduction > 0.
- Transactions: processInstantClaim, settleClaimWithSupplier, dispatchScrapSaleBatch, updatePricingTiers run in DB::transaction with lockForUpdate where noted.

## Gotchas
- Settlement has no state guard: a claim can be settled repeatedly; `settled_replacement` / `settled_credit_note` repeated → double stock increment / double supplier credit. Also settleable while decision is not `replaced`.
- `settled_replacement` adds stock of the replacement product, assuming supplier returns the same SKU.
- Credit note on a claim with null supplier_id silently does nothing but still marks it settled.
- Replacement Warranty reuses the original invoice_item_id, so InvoiceItem::warranty (hasOne) becomes ambiguous; warranty cert may show either row. UNVERIFIED which one.
- Claim observer sequence uses count+1 per month: concurrent inserts or deleted rows can collide with the unique claim_number.
- Scrap index: metrics are branch-scoped but the inventory list ignores branch (controller does not pass branch_id to filters).
- capacity_ah filter must match stored string (`70Ah`), not an integer.
- Fallback user/branch ids `?? 1` (auth()->id(), branch, received_by) can silently attribute records to id 1.
- verify(): browser GET with `serial_number` but no `view=1` returns JSON, not HTML.
- WarrantyController::index loads technicians as all active employees; Branch import unused.
- Settle `notes` validated but not stored on the claim.
