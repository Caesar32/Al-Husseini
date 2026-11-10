# Phase 4. Test harness and hygiene

Registry IDs: CI-01, TST-01, PERF-03.

## 1. Baseline (measured 2026-11-10 on `0254f47`, working tree as is)

| Suite | Command | Result |
|---|---|---|
| PHP (Pest) | `php artisan test --compact` | 802 passed, 0 failed, 0 skipped, 29,748 assertions, 232s |
| JS | `node --test tests/js/*.test.mjs` | 79 passed, 0 failed |
| Label layout | `php tests/Browser/label-layout-check.php` | Not run (not part of this scope) |

The brief says "789+". The current number is 802, and it includes the untracked `tests/Feature/Seeders/`, so CI runs fewer (PERF-03).

## 2. Frontend hygiene budgets

Budgets from `tests/Architecture/FrontendHygieneTest.php:8-14`. Current counts were reported by the Codex scan; the suite passes, which confirms each is within its budget.

| Metric | Now | Budget | Room |
|---|---|---|---|
| Inline handlers (`onclick=` ...) | 0 | 0 | none |
| Inline `<script>` blocks | 6 | 6 | none |
| Inline `style=` | 132 | 134 | 2 |
| `<style>` blocks | 12 | 12 | none |
| Raw echo `{!! !!}` | 0 | 0 | none |

Rules: never raise a budget. When a change removes inline code, lower the budget in the same commit. Three of the five budgets have no room, so Phase 1 must add no `<style>` block, no inline script and no handler.

## 3. Defects

### CI-01. JS tests do not run in CI (High)

```diff
--- .github/workflows/ci.yml
@@ -72,3 +72,6 @@
+      - name: Execute JavaScript tests
+        run: node --test tests/js/*.test.mjs
+
       - name: Execute Pest / PHPUnit Test Suite
         run: php artisan test
```

Node 20 is already set up in the workflow (`ci.yml:30-34`). Do this before the Phase 1 changes to `pos.js`.

### TST-01. `PosScreenDataTest` asserts the wrong contract (Medium)

`tests/Feature/Sales/PosScreenDataTest.php:31-35` expects every active product in the first paint and filters customers by phone. The controller sends at most 36 products and uses `Customer::notWalkIn()`. The test passes only because the seed has fewer than 36 products and no NULL-phone customer.

The fix must make the test stricter, not looser:

```diff
@@ -31,5 +31,8 @@
-    $activeProductIds = Product::where('is_active', true)->pluck('id')->sort()->values()->all();
-    expect(collect($products)->pluck('id')->sort()->values()->all())->toBe($activeProductIds);
+    $activeProductIds = Product::where('is_active', true)->pluck('id');
+    expect($products)->toHaveCount(min(36, $activeProductIds->count()));
+    expect(collect($products)->pluck('id')->diff($activeProductIds))->toBeEmpty();
-    $customerIds = Customer::where('is_active', true)->where('phone', '!=', '00000000000')->pluck('id')->sort()->values()->all();
+    $customerIds = Customer::where('is_active', true)->notWalkIn()->pluck('id')->sort()->values()->all();
```

Add two cases so the real limits are exercised:

| New test | Setup | Expect |
|---|---|---|
| First paint is capped | Create 40 active products | Exactly 36 in `products` |
| NULL-phone customer is listed | Create an active customer with `phone = null` | Its id is in `customers`; the walk-in customer is not |

### PERF-03. Untracked tests (Low, owner decision)

`tests/Feature/Seeders/`, `database/seeders/Demo/` and `DemoDataSeeder.php` are untracked. Either commit them (the tests then run in CI) or keep them local and state the CI count separately in `CLAUDE.md`. Nothing is staged without the owner's word.

## 4. New coverage to write

| Phase | Test | Type | File |
|---|---|---|---|
| 3 | Vehicle must belong to the customer (4 cases, see Phase 3) | Pest feature | `tests/Feature/Sales/PosVehicleOwnershipTest.php` |
| 1 | Ribbon buttons: new ids exist, back button carries `ri-arrow-right-s-line`, forward `ri-arrow-left-s-line` | Pest view test | `tests/Feature/Sales/PosScreenDataTest.php` |
| 1 | Ribbon ends: back disabled at the start, forward disabled at the end (stub `scrollLeft`, `scrollWidth`, `clientWidth`) | Node | `tests/js/pos.test.mjs` |
| 1 | Wheel direction follows the element's direction (stub `getComputedStyle`) | Node | `tests/js/pos.test.mjs` |
| 4 | First paint cap and NULL-phone customer | Pest feature | `tests/Feature/Sales/PosScreenDataTest.php` |

Already covered, no new test needed: fractional oil checkout and returns (`FractionalQuantityTest`, `DualUnitOilTest`), oil cart behaviour (`pos.test.mjs:574-639`), customer combobox (`pos.test.mjs:320-418`), list default and infinite scroll (`pos.test.mjs:473-560`).

## 5. What the automated tests cannot prove

The JS tests use a DOM stub (`tests/js/dom-stub.mjs`). They cannot see layout, overflow, real RTL scroll values or script load order (`Admin is not defined`). Those are covered only by the browser check in Phase 1, section 4.

## 6. Gate for every later commit

| # | Check | Command |
|---|---|---|
| 1 | PHP suite, same or higher count, 0 failed, 0 skipped | `php artisan test --compact` |
| 2 | Architecture suite | `php artisan test tests/Architecture --compact` |
| 3 | JS suite | `node --test tests/js/*.test.mjs` |
| 4 | Labels, only if a label file changed | `php tests/Browser/label-layout-check.php` |
| 5 | No test deleted, skipped or loosened; no budget raised | review of the diff |

Do not use `git stash` to compare runs.
