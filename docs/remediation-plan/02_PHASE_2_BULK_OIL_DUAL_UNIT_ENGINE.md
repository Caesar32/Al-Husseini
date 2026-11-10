# Phase 2. Oil by the liter (dual unit)

Registry IDs: none. The engine was shipped in `0254f47` and both reviewers found no defect in it. This file records what exists, how the brief maps to it, and the owner actions left.

## 1. Brief versus implementation

| Brief | Implemented as | Where |
|---|---|---|
| `allows_fraction = true` attribute | Not a column. Derived from the product unit (`ProductUnit::allowsFraction()`), sent to the POS as `allows_fraction`. | `app/Models/Product.php:94-97`, `PosController.php:252` |
| `package_size` | Column on `products`; counts only when the unit is liter and the size is above zero. | migration `2026_11_07_000001_add_package_size_and_stock_factor.php`, `Product.php:44-49` |
| `price_per_liter = package_price / package_size` at sale time | Different on purpose: prices, cost and stock of a liter product are **stored per liter**. The division happens once, in `ProductService::convertToLiters`. | `CLAUDE.md` (oil by the liter), `app/Services/Catalog/ProductService.php` |
| Decimal input "اللترات المستخدمة (لتر)" | Present in the cart line, `step="any"`, minimum 0.001. | `pos.js:141-142`, `:1287` |
| Presets 3.5, 4.0, 4.5, full package | 3.5, 4, 4.5, **5** liters and "جركن كامل" (or "برميل كامل" from 100 liters). No full-package chip when the product has no package size. | `pos.js:138-139`, `Product.php:51-54` |
| Stock in liters, shown as liters plus packages | Stock is in liters. Catalog shows "(ما يعادل 186 جركن و 1.5 لتر)"; back office uses `Product::stockReadout()`. | `pos.js:112-126`, `Product.php:56-59` |
| Receipt: quantity × liter price = total | Printed on each line with the unit label. | `resources/views/admin/pos/receipt.blade.php:242` |

Behaviour worth knowing:

- Adding oil from the catalog puts one full package in the cart; the stepper and presets then fine-tune liters; adding the same product again adds another package (`pos.js:1130-1167`).
- Lines created before a conversion keep their package quantity through `stock_factor`. Any code that moves stock from an invoice or purchase line must call `stockMilli()` (`InvoiceItem.php:32`, `PurchaseInvoiceItem.php:24`); `SalesReturnService.php:74` does.
- Quantities are exact to 3 decimals (milli-units) on both sides.

## 2. Test coverage that exists

| Area | File |
|---|---|
| Conversion, old lines, sales and purchase returns, readout, receipt, POS payload | `tests/Feature/Catalog/DualUnitOilTest.php` |
| Fractional checkout and returns | `tests/Feature/Sales/FractionalQuantityTest.php` |
| Fractional purchases | `tests/Feature/Purchases/FractionalPurchaseTest.php` |
| Stock ledger with fractions | `tests/Feature/Catalog/StockLedgerFractionTest.php` |
| Owner API | `tests/Feature/Api/Owner/OwnerFractionalQuantityTest.php` |
| Cart: presets, package-first add, stock cap, piece products get no presets | `tests/js/pos.test.mjs:574-639` |
| Rounding | `tests/js/pos-math.test.mjs:58`, `tests/Unit/Support/QuantityTest.php` |

## 3. Do not change

- Do not add an `allows_fraction` column or a computed price per liter. Both would create a second source of truth next to the unit and the stored liter price.
- Do not convert products by category. The oils category also holds filters and pumps. Conversion is per product, through `ProductService::convertToLiters` (product form, or `products:convert-oils-to-liters`, which is a dry run by default).

## 4. Left to do (owner actions, no code)

| # | Action | Note |
|---|---|---|
| 1 | Confirm whether the conversion command has been applied on production, and to which products. | Not verifiable from the repository. |
| 2 | Dry-run `php artisan products:convert-oils-to-liters` on a copy of production data and review the list before applying. | Filters and pumps must not appear in it. |
| 3 | Sell one oil product in the browser: 3.5 liters, then a full package; print the receipt. | Receipt line must read liters × liter price = total. |
| 4 | Return part of an invoice created before the conversion. | Stock must rise by packages × package size, in liters. |
| 5 | Replace book stock with a physical count (`inventory:import-stock`) before trusting liter balances. | `CLAUDE.md`: imported stock overstates the shelves. |

## 5. Optional improvement (ask the owner first)

The preset list is fixed in JS (`pos.js:138`). If the shop pours other common amounts, the list could come from a setting. This is new behaviour, not a fix.
