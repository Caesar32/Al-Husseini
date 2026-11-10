# Phase 3. Database, queries and checkout integrity

Lead: Codex scan, verified by Claude. Registry IDs: INT-01, PERF-01, PERF-02.

Summary: no N+1 query, no missing index and no transaction gap was found. The one real defect is a missing ownership check on the vehicle.

## 1. INT-01. A sale can carry another customer's vehicle (High)

`StorePosInvoiceRequest.php:29` validates `customer_vehicle_id` with `exists:customer_vehicles,id` only. `PosOrderService` writes the value to the invoice (`:321`) and to each warranty (`:420`) without comparing it to the customer. The screen only offers the chosen customer's vehicles, so this needs a hand-made request, but the result is a warranty certificate that names the wrong car.

```diff
--- app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php
@@ -28,2 +28,5 @@
             'customer_id'           => ['nullable', 'exists:customers,id'],
-            'customer_vehicle_id'   => ['nullable', 'exists:customer_vehicles,id'],
+            'customer_vehicle_id'   => ['nullable', Rule::exists('customer_vehicles', 'id')
+                ->where(fn ($q) => $q->where('customer_id', $this->input('customer_id')))],
```

Add `use Illuminate\Validation\Rule;` if the file does not import it.

Check before applying:

- A walk-in sale sends `customer_id: null` (`pos.js:1437`, `:1712`). With the rule above, a vehicle id sent without a customer fails validation, which is the wanted result. Confirm in `pos.js:1660-1713` that `vehicleId` is null for a walk-in sale, so a normal walk-in sale is not rejected.
- The credit check at `StorePosInvoiceRequest.php:286` reads `customer_id` the same way (`$this->input('customer_id')`), so the two stay consistent.

Tests to add (`tests/Feature/Sales/`):

| Case | Expect |
|---|---|
| Vehicle of customer B with `customer_id` A | 422, no invoice, no stock movement |
| Vehicle of customer A with `customer_id` A | 201, invoice and warranty carry the vehicle |
| Vehicle id with no customer | 422 |
| No vehicle | 201 (unchanged) |

## 2. Checked and correct

| Area | Finding | Where |
|---|---|---|
| POS first paint | 36 featured products with `category` eager loaded; ids of best sellers cached 300s | `PosController.php:52-54`, `:266-303` |
| Customers | One query plus one for `vehicles` (eager) | `PosController.php:58-62` |
| Category counts | `withCount`, cached 300s, reused by every search response | `PosController.php:311-326` |
| Search | Selected columns only, `category` eager loaded, paginated, exact-barcode path uses equality | `PosController.php:116-148` |
| Checkout atomicity | One `DB::transaction`; products locked in one `whereIn(...)->lockForUpdate()`; credit customer locked; stock through `StockLedgerInterface::move`; idempotency key re-checked inside the transaction | `PosOrderService.php:125-138`, `:286`, `:382` |
| Returns | Invoice, product and customer locked in one transaction | `SalesReturnService.php:39-123` |
| Receipt | All relations loaded up front | `PosController.php:200-209` |

## 3. Indexes

No new index is recommended.

| Lookup | Index that serves it |
|---|---|
| Scanner: `products.barcode = ?` | unique, `2026_09_21_160007_create_products_and_categories_tables.php:20` |
| Scanner: `products.sku = ?` | unique, same file `:19` |
| Scanner: supplier carton code | `supplier_products.supplier_sku`, `2026_09_23_100001_...:14` and `(supplier_id, supplier_sku)` `:24` |
| `customers.phone = ?` | unique, `2026_09_21_160006_...:12` |
| Customer by name | `customers_name_index`, `customers_is_active_name_index` |
| Category filter | `products_category_is_active_index` |

Customer search on the POS screen runs in the browser over the embedded list, so it uses no index at all. Redundant duplicate indexes were already handled in `2026_10_16_000002_drop_redundant_indexes.php`.

## 4. Low-priority watch items (no action now)

| ID | Item | Act when |
|---|---|---|
| PERF-01 | All active customers and vehicles are embedded in the page (`PosController.php:58-75`). | The customer list passes roughly 2,000 rows or the page weight is noticed. Then move the combobox to a search endpoint like the products. |
| PERF-02a | `%term%` on name, brand, sku, barcode (`:138-141`) scans the table. | Search over 7,340 products feels slow on production. Then consider a prefix search for sku and barcode, or a FULLTEXT index on MySQL (not available on the SQLite test engine, so it needs a driver check). |
| PERF-02b | `paginate()` runs a count query for every scrolled page (`:148`). | Same trigger. `simplePaginate()` removes it; the screen only needs `has_more` after the first page. |
| PERF-02c | Category filter uses `whereHas` (`:134`). | Same trigger. Resolve the slug to an id once and filter on `category_id`. |

Measure before changing any of these: enable the query log on a copy of production data and record the time of the POS page and one search. No measurement was taken in this audit.

## 5. Done when

- INT-01 is fixed with the four tests above.
- `php artisan test --compact` passes with no test removed or loosened.
