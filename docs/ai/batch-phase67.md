# Batch: Phase 6 + 7 (supplier ledger, purchases, warranty, scrap)

Branch `batch/phase67-supplier-warranty`, based on `remediation/2026-10` @ 3e4dc19. Decisions follow REMEDIATION_EXECUTION_REPORT.md; plan items are in MASTER_REMEDIATION_PLAN.md Phases 6 and 7.

## Commits
- 900d88e Purchases: consistent supplier ledger, payment allocation, safe returns
- 1ca8b16 Warranty/scrap: one-way settlement, claim integrity, recorded scrap sales
- 8b32706 Warranty claim forms: send rejection_reason
- (this file)

## Tests
- Before: 172 passed / 1029 assertions. After: 193 passed / 1187 assertions. 0 failed, 0 skipped. SQLite FK and CHECK enforcement stays on.
- New files: tests/Feature/Purchases/SupplierLedgerIntegrityTest.php (10 tests), tests/Feature/Warranty/WarrantyClaimAndScrapIntegrityTest.php (11 tests).
- Existing tests changed only to supply data the removed `?? 1` fallbacks used to invent; no assertion changed:
  - SalesAndPurchasesServicesTest: `branch_id`, `paid_by`.
  - EndToEndSalesAndPurchasesScenarioTest sector 1: `branch_id` ×2, `paid_by`.
- Migrations 060000-060002: up, down and up again verified on in-memory SQLite (FK stays on).

## Done
- BIZ-06:
  - Purchase posts `purchase_invoice` = final_amount, then the on-reception `supplier_payment`. The ledger running balance equals Supplier.current_balance (invariant tested for paid 0, partial and full).
  - The balance semantics are unchanged (before + remaining).
- BIZ-06 repair: `php artisan suppliers:rebuild-ledger-balances [--supplier=ID] [--apply]`.
  - Dry run by default.
  - `--apply` writes `storage/app/private/ledger-backups/supplier-ledger-*.json` first, fixes legacy purchase posting amounts and missing postings, then recomputes the balance_before/after chain.
  - It never changes current_balance; it reports "unexplained diff" for opening balances that have no entries. It is idempotent (tested).
  - NOT run on any real database (D1).
- BIZ-11:
  - Supplier payments are allocated FIFO (invoice_date, id), or only to the given `purchase_invoice_id`. Invoice paid, remaining and payment_status are updated.
  - Any excess stays an unallocated advance (existing policy kept).
  - SupplierController::recordPayment returns 422 instead of 500.
- BIZ-20:
  - The latest purchasing supplier is the single primary supplier.
  - An empty line SKU keeps the known SKU.
- BIZ-18 internals:
  - processPurchaseReturn tracks `purchase_invoice_items.returned_quantity` (no double return).
  - It reverses WAC and reduces invoice remaining first.
  - It no longer clamps the supplier balance at 0, so a credit with the supplier stays visible in the ledger.
- Ledger statement: `total_adjustments` added; the ledger page shows returns and adjustments.
- BIZ-07: settlement state machine.
  - Allowed transitions: pending → sent_to_supplier; pending|sent_to_supplier → settled_replacement|settled_credit_note|rejected.
  - Settled and rejected states are final.
  - settled_replacement requires decision=replaced.
  - A credit note requires a supplier and an amount > 0.
  - `settlement_notes` are persisted. Errors return 422.
- BIZ-13:
  - Claims on claimed or voided warranties are refused (request + service).
  - `rejection_reason` is persisted.
  - The claim supplier is the primary supplier of the sold product.
  - The claim forms now send `rejection_reason`; before this, rejecting a claim from the UI always failed validation.
- BIZ-12b: claim numbers come from DocumentNumberService `CLM-YYYYMM-NNNN` (the format is unchanged).
- BIZ-10:
  - New `scrap_sales` table records each batch (buyer, phone, method, amounts, profit, sold_by, notes), linked by `scrap_batteries_inventory.scrap_sale_id`.
  - The batch number comes from DocumentNumberService `SCRAP-BATCH-YYYYMMDD-NNNN`.
- BIZ-21:
  - The scrap list uses the same branch scope as its metrics; a user without a branch sees all branches instead of branch 1.
  - `InvoiceItem::warranty()` is the original (oldest) warranty; added `warranties()` and `activeWarranty()`.
- Removed `?? 1` fallbacks in WarrantyController, ScrapInventoryController, PurchaseInvoiceController and PurchaseService:
  - A purchase needs a branch_id or the user's branch (otherwise 422).
  - A payment needs paid_by.
  - A claim branch is the user's branch or else the original invoice's branch.

## Blocked / not done (no invented rules)
- BIZ-18 route: no purchase-return permission exists in the catalogue. The owner must choose one (reuse `purchases.create`, or add `purchases.return`).
- Supplier catalog sync over HTTP is not exposed, because it is unsafe. `SupplierService::syncSupplierProducts` uses `sync()`, which detaches every product not sent, wiping purchase-derived pivot rows (last price, SKU); no UI sends `products`. It needs a design decision (merge vs replace).
- Replacement warranty period: still a full new period from the replacement product (owner decision).
- Supplier credit_limit enforcement on purchases: owner decision.
- D6: scrap sales are not linked to any cash or treasury account.
- D1: `suppliers:rebuild-ledger-balances --apply` must not run before the owner confirms the scope; review the dry-run output first.

## Route changes requested from the orchestrator (routes/web.php not edited here)
- `Route::resource('suppliers', …)->except(['create'])`: the `admin.suppliers.create` view does not exist, and creation goes through the index modal (store).
- After the permission decision: `POST admin/purchases/{purchase}/return` → `PurchaseInvoiceController@processReturn` (not implemented, pending the decision).

## Diagnostics follow-ups (SystemDiagnosticService not edited here)
- Required: the supplier_ledger audit check (~L99-125) sums `entry_type = 'payment'`, which does not exist (the type is `supplier_payment`). It also ignores `purchase_return` and `adjustment` and only inspects balance > 0.
  - With the corrected posting, every purchase paid at reception would now be reported as a false mismatch.
  - Replace it with: Σpurchase_invoice − Σsupplier_payment − Σpurchase_return − Σadjustment = current_balance, plus last balance_after = current_balance, for all suppliers.
- Optional: an audit for warranty claims holding more than one adjustment entry or more than one replacement restock (legacy double settlements), and for `sold_to_factory` scrap rows without `scrap_sale_id` (legacy sales without a record).

## Notes for other batches
- PosController::warrantyCert eager-loads `items.warranty`; it now resolves to the original sale warranty (deterministic).
- `ScrapBatteryServiceInterface::getInventoryMetrics(?int)`: null means all branches.
