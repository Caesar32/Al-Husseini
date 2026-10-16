# Phase 4: Split `PosOrderService` and remove service location

Status tracker: `REFACTOR_PLAN.md`. Requires Phase 2, because the moved return code calls `StockLedgerInterface`. Next phase: `05_phase_hardcoded_urls.md`.

## Rules
- Laravel 12, PHP 8.2, Pest. Tests run on SQLite `:memory:` with FK/CHECK enforced; never disable it.
- Never weaken, skip or delete tests. These are pure moves: logic, messages, exceptions and response shapes stay identical.
- Money writes stay inside `DB::transaction` with `lockForUpdate`. Bind interfaces in `app/Providers/SalesAndPurchasesServiceProvider.php`.
- Don't commit or push unless the owner asks.

## Objective
`PosOrderService` (799 lines) has five responsibilities. Split it along its existing public methods. `PosOrderServiceInterface` stays source-compatible: the moved methods delegate.

SOLID:
- SRP: sale, return and debt collection become separate services.
- ISP: callers depend on narrow interfaces.
- DIP: constructor injection replaces `app()` service location.

## Context (`app/Services/Sales/PosOrderService.php`)
| Method | Lines | Destination |
|---|---|---|
| `getPaginatedInvoices`, `getInvoiceStats` | 33-68 | stays |
| `processPosSale`, `findByIdempotencyKey`, `createPosSale` | 69-442 | stays |
| `processSalesReturn`, `resolveReturnLines` | 443-622 | `SalesReturnService` |
| `calculateScrapDeduction`, `getDailyCashierSummary` | 623-682 | stays |
| `settleCustomerDebt` | 683-~790 | `CreditCollectionService` |
| `isManagerOverrideValid` (`app(ManagerOverrideService::class)`) | 794-797 | deleted; injected instead |

Other smells:
- `PosOrderService.php:286` calls `app(DocumentNumberService::class)` for the invoice number. Inject it in Step 3.
- `app/Services/Diagnostics/SystemDiagnosticService.php` uses `app()` on purpose: its simulation resolves services on demand. It is out of scope and excluded from the acceptance grep.
- `app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php:297-300` duplicates `isManagerOverrideValid()` through `app()`. It is used at L189, L204 and L280.
- `app/Services/Sales/CreditCollectionService.php:11` says it reads the rows that `PosOrderService::settleCustomerDebt` writes. The read side and the write side of one concept live in different classes.
- Caller: `app/Http/Controllers/Admin/CreditCustomerController.php:133`. The controller already injects `PosOrderServiceInterface` and the concrete `CreditCollectionService` (L17-20). Only the settlement call moves to `CustomerDebtServiceInterface`. Read-side calls are unchanged, and `PosOrderServiceInterface` stays injected only if the controller still calls other methods on it.

## Contract
```php
// app/Contracts/Sales/SalesReturnServiceInterface.php
interface SalesReturnServiceInterface {
    public function processSalesReturn(int $invoiceId, array $items, string $reason, int $cashierUserId): Invoice;
}
// app/Contracts/Sales/CustomerDebtServiceInterface.php
interface CustomerDebtServiceInterface {
    // Verbatim from PosOrderService::settleCustomerDebt (L683-690).
    public function settleCustomerDebt(
        int $customerId,
        float $amount,
        string $paymentMethod,
        int $collectedBy,
        ?string $receiptNumber = null,
        ?string $notes = null
    ): CreditLedgerEntry;
}
// SalesReturnService implements SalesReturnServiceInterface.
// CreditCollectionService implements CustomerDebtServiceInterface (write + read sides together).
// PosOrderService: constructor-injects SalesReturnServiceInterface, CustomerDebtServiceInterface, ManagerOverrideService;
//   processSalesReturn() / settleCustomerDebt() become one-line delegations.
// StorePosInvoiceRequest: withValidator() has no container injection; resolve ManagerOverrideService once
//   via $this->container->make() inside the after() closure.
```

## Codex prompts
```text
[TASK: Phase 4 - Step 1]
ROLE: Strict Code Executor.
ACTION: Extract SalesReturnService (SRP, ISP). Pure move.
TARGET_FILE: `app/Services/Sales/PosOrderService.php` (L443-622), new `app/Contracts/Sales/SalesReturnServiceInterface.php`, new `app/Services/Sales/SalesReturnService.php`, `app/Providers/SalesAndPurchasesServiceProvider.php`
CHANGES_REQUIRED:
1. Move processSalesReturn + resolveReturnLines verbatim into SalesReturnService. Before moving, list every `$this->...` and static/facade collaborator used in L443-622; constructor-inject exactly those (at least StockLedgerInterface from Phase 2); copy private helpers they call (e.g. epsilon()) rather than reaching back into PosOrderService. Remove from PosOrderService's constructor any dependency no longer used there.
2. PosOrderService constructor-injects SalesReturnServiceInterface; processSalesReturn() body = `return $this->salesReturns->processSalesReturn($invoiceId, $items, $reason, $cashierUserId);`.
3. Bind SalesReturnServiceInterface => SalesReturnService.
4. Remove now-unused imports from PosOrderService.
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Sales`.
```
```text
[TASK: Phase 4 - Step 2]
ROLE: Strict Code Executor.
ACTION: Move settleCustomerDebt into CreditCollectionService (SRP).
TARGET_FILE: `app/Services/Sales/PosOrderService.php` (L683-~790), `app/Services/Sales/CreditCollectionService.php`, new `app/Contracts/Sales/CustomerDebtServiceInterface.php`, `app/Http/Controllers/Admin/CreditCustomerController.php:133`, `app/Providers/SalesAndPurchasesServiceProvider.php`
CHANGES_REQUIRED:
1. Interface with the exact current signature of settleCustomerDebt.
2. Move method verbatim into CreditCollectionService (implements CustomerDebtServiceInterface); inject its collaborators via constructor.
3. Bind CustomerDebtServiceInterface => CreditCollectionService.
4. CreditCustomerController: constructor-inject CustomerDebtServiceInterface and call it at L133.
5. PosOrderService::settleCustomerDebt delegates to the injected interface.
6. CreditCollectionService class docblock: replace "written by PosOrderService::settleCustomerDebt" with "written by settleCustomerDebt() in this class".
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Sales/CreditAndInvoiceManagementTest.php tests/Feature/Sales/CreditCollectionsPageTest.php tests/Feature/Admin/DiagnosticsLedgerChainTest.php`.
```
```text
[TASK: Phase 4 - Step 3]
ROLE: Strict Code Executor.
ACTION: Remove service locator (DIP).
TARGET_FILE: `app/Services/Sales/PosOrderService.php` (~L264, ~L286, L794-797), `app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php` (withValidator L62, its `$validator->after(function ...)` closure L64, calls L189/L204/L280, helper L297-300)
CHANGES_REQUIRED:
1. PosOrderService: constructor-inject `private ManagerOverrideService $override` and `private DocumentNumberService $documentNumbers`; replace `$this->isManagerOverrideValid($overrideCode)` with `$this->override->isValid($overrideCode)`; replace `app(DocumentNumberService::class)->nextFormatted(...)` (~L286) with `$this->documentNumbers->nextFormatted(...)`; delete isManagerOverrideValid().
2. StorePosInvoiceRequest: keep signature `withValidator(Validator $validator): void`. At the top of the `$validator->after(function ($validator) {` closure add `$override = $this->container->make(\App\Services\Finance\ManagerOverrideService::class);` (add `use ($override)` to nested closures as needed); replace the three calls with `$override->isValid($overrideCode)`; delete isManagerOverrideValid().
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Security/ManagerOverrideTest.php tests/Feature/Sales`.
```

## Acceptance criteria
- [ ] Steps 1-3 are applied and each verification command passes.
- [ ] `PosOrderService.php` is ≤ 450 lines.
- [ ] `grep -rnE "app\([A-Za-z\\\\]*Service::class\)" app/Services app/Http/Requests | grep -v Diagnostics/` returns nothing.
- [ ] The full suite shows 0 failed and 0 skipped, with passed ≥ the previous phase's count.
- [ ] Manual browser check: one POS sale, one partial return and one credit collection. Confirm the invoice, `stock_movements` and the customer ledger rows.
- [ ] Record the passed count as `N4`.
