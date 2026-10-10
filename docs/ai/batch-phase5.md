# Batch: Phase 5 (sales returns, revenue and credit integrity)

- Branch `batch/phase5-sales`, based on `remediation/2026-10` @ 3e4dc19.
- Commits: 76cba2e (schema/models), 52c7fb8 (behaviour + tests), plus this report.
- The decision classes referenced below come from REMEDIATION_EXECUTION_REPORT.md.

## Tests
- Before: 172 passed / 1029 assertions (worktree baseline at 3e4dc19).
- After: 182 passed / 1091 assertions.
- No existing test was modified. 10 new tests in tests/Feature/Sales/SalesReturnAndRevenueIntegrityTest.php.

## Tasks
- ARC-02 (DONE):
  - App\Enums\InvoiceStatus is the single list of status values; nonCountableValues() returns cancelled and refunded.
  - Invoice::scopeCountable(), Invoice::sumNetAmount() and InvoiceItem::NET_LINE_SQL are the shared helpers.
  - Every hard-coded `['cancelled','refunded','partially_refunded']` list in app/ was replaced (PosOrderService, InvoicePayment::scopeActive, InvoiceFilter::applyForStats, DashboardController). A grep finds none left.
- BIZ-03 (DONE):
  - invoice_items.returned_quantity was added.
  - Returns are resolved per invoice line (invoice_item_id). Legacy product_id rows are accepted only when the product is on a single line.
  - Each line is validated against quantity − returned_quantity under the invoice row lock.
  - Status becomes `refunded` only when every unit of every line has been returned.
  - The return validation moved to App\Http\Requests\Admin\Sales\ProcessSalesReturnRequest (authorize = invoices.cancel). It drops unchecked modal rows, which fixes the unchecked-row bug.
  - The modal posts invoice_item_id, shows units already returned, and caps each line at its returnable quantity.
  - The "return" button checked a status `returned` that does not exist; it now uses the enum and hides itself once nothing is returnable.
  - JSON callers get 422 instead of a redirect on a domain error.
- BIZ-04 (PARTIAL):
  - Done: the invariant that cumulative refunds can never exceed paid_amount + remaining_amount, i.e. the invoice's current worth to the customer, total ≤ final_amount. invoices.refunded_amount is tracked. When the line-price value exceeds that, the refund is capped and the cap is written into the invoice notes.
  - BLOCKED (D3): prorating the invoice discount, scrap deduction or tax per line. Line-price valuation is unchanged.
- BIZ-05 (DONE, with one deviation):
  - Stats, dashboard KPIs, category split, the 7-day trend and getDailyCashierSummary count partially_refunded invoices net of refunds: final − refunded_amount for invoices, (quantity − returned_quantity) × unit_price for lines.
  - InvoicePayment::active() now includes partially refunded invoices (the refund is already a negative payment).
  - The category split previously had no status filter at all (cancelled and refunded sales were included); it now follows the same countable rule.
  - FIFO settlement includes partially_refunded invoices and keeps that status, since payment state lives in remaining_amount.
  - Deviation from the batch brief ("settlement must throw if it cannot allocate"): not done, because evidence shows that unallocated debt is legitimate. SalesAndPosDataSeeder creates opening balances with no invoices, and the existing CreditAndInvoiceManagementTest settles 2000 against such a balance and expects success. The remainder is now recorded explicitly in the ledger notes ("opening balance") instead of being silently absorbed. The real cause of drift, debt sitting on skipped partially_refunded invoices, is fixed.
- BIZ-12a (DONE): numbers come from DocumentNumberService inside the sale transaction, as `INV-{Ymd}-{000001}`, replacing uniqid().
- BIZ-17 (PARTIAL):
  - Guest customer: Laravel 12 firstOrCreate is already race-safe here (savepoint plus the unique phone), so only `withTrashed()` was added, so that a soft-deleted guest record is reused instead of colliding with its phone.
  - BLOCKED (owner/schema decision): reselling a returned battery serial. warranties.serial_number is unique at DB level, including voided rows.
- POS idempotency backend (DONE, for Phase 4):
  - invoices.idempotency_key (nullable unique); StorePosInvoiceRequest accepts `idempotency_key`.
  - processPosSale returns the existing invoice for a repeated key with no side effects. That covers a check inside the transaction and a concurrent unique-violation fallback.
  - The request's after-validator skips re-validation for an already-committed key, because its serials and stock were consumed by the original sale.
- SEC-11 part (DONE): removed `auth()->id() ?? 1` in PosController, SalesInvoiceController and CreditCustomerController.

## Migrations
- 2026_10_01_050000_add_return_tracking_and_idempotency_to_invoices (additive):
  - Adds invoice_items.returned_quantity, invoices.refunded_amount and invoices.idempotency_key (unique).
  - The backfill of refunded_amount uses SET semantics and is idempotent.
  - Verified on isolated SQLite with data: correct sums (700 cash + 300 credit = 1000), a rerun gives the same values, FK enforcement stays on, and down()/up() run cleanly.
  - Not run on dev MySQL (pending `php artisan migrate`).
- Historical data: per-line returned quantities for returns made before this migration cannot be reconstructed and stay 0. Money stays bounded by the refunded_amount cap. Invoices already `refunded` are non-returnable by status.

## Diagnostics follow-ups (not changed; SystemDiagnosticService is out of scope)
- `customer_credit_ledger` check (SystemDiagnosticService ~L74-80) subtracts entry_type `payment_settlement`, which does not exist in the DB enum (the real type is `payment_collection`), and ignores `refund` entries. It therefore over-reports mismatches. This is a pre-existing bug. Suggested formula: Σ invoice_debt − Σ payment_collection − Σ refund ± Σ credit_adjustment.
- Suggested new check: Customer.current_credit_balance versus Σ remaining_amount of countable invoices plus the opening-balance component.
- `invoices_payments_breakdown` is unaffected.

## Blockers
- D3: refund proration.
- Serial resale after return (warranties.serial_number unique, including voided rows).
- D1: production data repair scope (the backfill only covers recorded refunds).
