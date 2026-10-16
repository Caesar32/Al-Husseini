# Phase 2: Stock movement journal (ARC-04)

Status tracker: `REFACTOR_PLAN.md`. Requires Phase 1. Phase 4 and Phase 6 depend on it. Next phase: `03_phase_shared_dashboard_metrics.md`.

## Rules
- Laravel 12, PHP 8.2, Pest. Tests run on SQLite `:memory:` with FK/CHECK enforced; never disable it.
- Never weaken, skip or delete tests. Behaviour, messages and response shapes must not change.
- Stock and money writes run inside `DB::transaction` with `lockForUpdate`. Bind each service interface in its `*ServiceProvider`.
- `app/Observers/InvoiceObserver.php` is dead code: never edit it or register it.
- Don't commit or push unless the owner asks.

## Objective
Make one service the only writer of `products.current_stock`. Every change also writes an append-only `stock_movements` row.

SOLID:
- SRP: stock mutation leaves the domain services.
- DIP: those services depend on `StockLedgerInterface`.
- OCP: a new movement kind is a new enum case.

Smells fixed:
- Shotgun surgery: seven write points in four files.
- No audit trail.
- Purchase return computes the new stock from a stale read before calling `update()`.

## Current writers (verified by grep)
| Writer | Location | Movement type | Delta |
|---|---|---|---|
| POS sale | `app/Services/Sales/PosOrderService.php:356` | `sale` | −qty |
| Sales return | `app/Services/Sales/PosOrderService.php:476` | `sale_return` | +qty |
| Instant warranty replacement to customer | `app/Services/Sales/WarrantyService.php:171` (`processInstantClaim`; the claim is created later, at ~L203) | `warranty_replacement` | −1 |
| Supplier sends a replacement unit | `app/Services/Sales/WarrantyService.php:255` (`settleClaimWithSupplier`, action `settled_replacement`) | `supplier_replacement` | +1 |
| Purchase receive | `app/Services/Purchases/PurchaseService.php` (receive path; grep `current_stock`) | `purchase` | +qty |
| Purchase return | `app/Services/Purchases/PurchaseService.php:351` | `purchase_return` | −qty |
| Stock import | `app/Console/Commands/ImportStockCommand.php:117` (already inside 500-row chunk transactions, L109-110) | `adjustment` | set |

These are not movements and are excluded from the single-writer rule:
- `app/Observers/InvoiceObserver.php:24` and `app/Observers/PurchaseInvoiceObserver.php:22-33`: dead, never registered (see `AppServiceProvider`).
- `app/Console/Commands/ImportLegacyDataCommand.php:674`: `'current_stock' => 0` when a product is first created.
- `app/Models/Product.php:28,41`: `$fillable` and `$casts`.

## Contract
```php
// app/Enums/StockMovementType.php
enum StockMovementType: string {
    case Sale = 'sale'; case SaleReturn = 'sale_return';
    case Purchase = 'purchase'; case PurchaseReturn = 'purchase_return';
    case WarrantyReplacement = 'warranty_replacement'; case SupplierReplacement = 'supplier_replacement';
    case Adjustment = 'adjustment';
}

// app/Contracts/Catalog/StockLedgerInterface.php
interface StockLedgerInterface {
    /** Must run inside DB::transaction; locks the product row; returns stock after. */
    public function move(int $productId, int $delta, StockMovementType $type, Model $source, ?int $userId = null, ?float $unitCost = null): int;
    /** Absolute set; records delta = new - old; type Adjustment. */
    public function setTo(int $productId, int $newStock, Model $source, ?int $userId = null): int;
}
// app/Services/Catalog/StockLedgerService.php implements it.
// Throws LogicException when DB::transactionLevel() === 0.

// Table stock_movements (migration 2026_10_16_000001_create_stock_movements_table):
// id; product_id FK; branch_id nullable FK; type string(30); quantity_delta int; stock_after int;
// unit_cost decimal(12,2) nullable; source_type, source_id (morphs); user_id nullable FK; created_at;
// index(product_id, created_at). down() drops the table.
```
Weighted-average cost (WAC) stays in `PurchaseService`. The ledger stores `unit_cost` and does not compute it.

`branch_id` comes from `$source->branch_id` when the source has one. An import adjustment uses the `Product` as its source, which has no branch, so `branch_id` is null.

## Codex prompts
```text
[TASK: Phase 2 - Step 1]
ROLE: Strict Code Executor.
ACTION: Add stock ledger contract + implementation (SRP, DIP, OCP). No caller changes.
TARGET_FILE: new `app/Enums/StockMovementType.php`, new `app/Contracts/Catalog/StockLedgerInterface.php`, new `app/Services/Catalog/StockLedgerService.php`, new `app/Models/StockMovement.php`, new `database/migrations/2026_10_16_000001_create_stock_movements_table.php`, the ServiceProvider binding `ProductServiceInterface` (grep it).
CHANGES_REQUIRED:
1. Enum + interface + table exactly per "Contract" in docs/refactor/02_phase_stock_movement_journal.md.
2. move(): if DB::transactionLevel()===0 throw LogicException; $p = Product::lockForUpdate()->findOrFail($productId); $after = (int)$p->current_stock + $delta; $p->current_stock = $after; $p->save(); StockMovement::create([...,'branch_id'=>$source->branch_id ?? null]); return $after.
3. setTo(): same lock; delta = $newStock - old; type Adjustment.
4. StockMovement model: $guarded=[]; casts type => StockMovementType; morphTo source; belongsTo product; UPDATED_AT = null.
5. Bind StockLedgerInterface => StockLedgerService.
6. New tests/Feature/Catalog/StockLedgerTest.php: move -2 and +3 update stock + one row each with correct stock_after; call outside transaction throws LogicException; setTo records delta; rollback leaves no row and unchanged stock.
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Catalog`.
```
```text
[TASK: Phase 2 - Step 2]
ROLE: Strict Code Executor.
ACTION: Route sales + warranty stock writes through StockLedgerInterface (DIP, constructor injection).
TARGET_FILE: `app/Services/Sales/PosOrderService.php` (~L356, ~L476), `app/Services/Sales/WarrantyService.php` (~L171, ~L255)
CHANGES_REQUIRED:
1. Inject `private StockLedgerInterface $stock` in both constructors.
2. PosOrderService ~356 (sale): replace decrement with `$stockAfter = $this->stock->move($product->id, -$pItem['quantity'], StockMovementType::Sale, $invoice, $cashierUserId);`; push-alert condition becomes `$stockBeforeDecrement > 0 && $stockAfter <= 0`.
3. PosOrderService ~476: move(+$qty, StockMovementType::SaleReturn, $invoice, $cashierUserId).
4. WarrantyService::processInstantClaim: keep the lockForUpdate + `current_stock < 1` check where it is (~L165-168); delete the decrement at ~L171 and instead, immediately after `$claim = WarrantyClaim::create([...])` (~L203, same transaction), call `$after = $this->stock->move($replacementProduct->id, -1, StockMovementType::WarrantyReplacement, $claim, $receivedByUserId);`; move the 1->0 push alert after it using $replacementStockBefore and $after.
5. WarrantyService::settleClaimWithSupplier ~L255: replace increment with move($product->id, +1, StockMovementType::SupplierReplacement, $claim, $userId).
6. No other logic/message/response change.
7. Extend tests/Feature/Sales/PosCheckoutIntegrityTest.php and tests/Feature/Warranty/WarrantyClaimAndScrapIntegrityTest.php: assert stock_movements row (type, quantity_delta) per sale/return/replacement and that sum(quantity_delta) == current_stock - initial_stock.
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Sales tests/Feature/Warranty`.
```
```text
[TASK: Phase 2 - Step 3]
ROLE: Strict Code Executor.
ACTION: Route purchase + import stock writes through StockLedgerInterface.
TARGET_FILE: `app/Services/Purchases/PurchaseService.php` (every current_stock write; return path ~L340-352), `app/Console/Commands/ImportStockCommand.php:~117`
CHANGES_REQUIRED:
1. Inject StockLedgerInterface into PurchaseService constructor.
2. Receive path: keep WAC computation in PurchaseService using the pre-move stock; then move(+$qty, Purchase, $purchaseInvoice, $userId, $unitCost); then update only cost_price.
3. Return path: $stockBefore = locked current stock; $newStock = $this->stock->move($product->id, -$take, PurchaseReturn, $purchaseInvoice, $userId, (float)$line->unit_cost_price); WAC reversal uses $stockBefore/$newStock exactly as today; update only cost_price.
4. ImportStockCommand: keep the existing 500-row chunk DB::transaction (L109-110); inject StockLedgerInterface via handle() method injection; inside the chunk loop replace the direct assignment/save (~L117) with $this->ledger->setTo($id, $u['new'], Product::find($id)) (or the product already loaded there). Partial-failure semantics unchanged (completed chunks kept).
5. Existing WAC assertions must stay unchanged and pass. Extend tests/Feature/Purchases/SupplierLedgerIntegrityTest.php and tests/Feature/Admin/ImportStockCommandTest.php with stock_movements assertions.
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Purchases tests/Feature/Admin/ImportStockCommandTest.php tests/Feature/Sales/EndToEndSalesAndPurchasesScenarioTest.php`.
```
```text
[TASK: Phase 2 - Step 4]
ROLE: Strict Code Executor.
ACTION: Enforce single-writer invariant + read-only diagnostics check.
TARGET_FILE: new `tests/Architecture/StockWriterTest.php`, `app/Services/Diagnostics/SystemDiagnosticService.php`, `phpunit.xml` (add tests/Architecture testsuite if absent)
CHANGES_REQUIRED:
1. Pest test: scan app/**/*.php; EXCLUDE app/Services/Catalog/StockLedgerService.php, app/Observers/InvoiceObserver.php, app/Observers/PurchaseInvoiceObserver.php (dead), app/Models/Product.php (fillable/casts); ALLOW-LIST app/Console/Commands/ImportLegacyDataCommand.php only for the literal `'current_stock'     => 0` creation line. Fail with file:line on regex /(increment|decrement)\(\s*'current_stock'|update\(\s*\[[^\]]*'current_stock'\s*=>|->current_stock\s*=(?!=)/. Add a self-check test that the regex matches a sample violating string, so the guard cannot silently pass.
2. Diagnostics: add read-only check "stock_journal_consistency": per product with movements, last row stock_after == products.current_stock. Report mismatches; never writes.
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact` and `php artisan system:diagnose --audit`.
```

## Acceptance criteria
- [ ] Steps 1-4 are applied and each verification command passes.
- [ ] The full suite shows 0 failed and 0 skipped, with passed ≥ `N1`.
- [ ] `tests/Architecture/StockWriterTest.php` passes, so no direct stock writer remains.
- [ ] On SQLite: `php artisan migrate:fresh --seed` succeeds, and `php artisan migrate:rollback --step=1 && php artisan migrate` is clean.
- [ ] `php artisan system:diagnose --audit` shows no new failures compared with `docs/refactor/baseline-diagnose.txt`.
- [ ] The owner is told that the migration needs `php artisan migrate` on dev MySQL. Do not run it there yourself.
- [ ] Record the passed count as `N2`.
