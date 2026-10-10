# Batch: Phase 4, POS and credit page on real data (FE-01, FE-04)

Branch `batch/phase4-pos`, based on 0503eb4 (remediation/2026-10 after Phases 3, 5, 6+7 and security). Decisions follow REMEDIATION_EXECUTION_REPORT.md.

## Tests
- Before: 250 passed / 1532 assertions.
- After: 265 passed / 1624 assertions.
- 0 failed, 0 skipped, SQLite constraints enforced.
- No existing test changed.

## Commits
- b15e343: POS backend prerequisite (stock per product across lines; one battery unit per line).
- f4aa7f9: POS screen on server data (FE-01).
- 1f66740: credit page on server data plus the collections endpoint (FE-04).
- docs commit: this report.

## Backend changes
- StorePosInvoiceRequest and PosOrderService:
  - Stock is now compared with the total requested per product across lines, not per line. Before, the same product split across lines could oversell: negative stock on SQLite, an unsigned-column error on MySQL.
  - A battery line must have quantity 1. Warranties are per unique serial, so a line with quantity 2 and one serial issued one warranty for two batteries.
- PosController::index passes trimmed arrays, not models:
  - products: id, name, brand, sku, barcode, category slug and name, is_battery, capacity_ah, stock, retail price, warranty_months, supplier carton SKUs;
  - customers: active, excluding the walk-in account; no national id; vehicles;
  - active scrap tiers.
  - No cost price is sent.
- New `CreditCollectionService`, the read model over payment_collection ledger entries.
- New route `GET admin/credit/payments` (`admin.credit.payments`, can:credit.view), in the delimited "Phase 4" block of routes/web.php. It returns paginated collections (`payments_page`), the total, and collected_this_month.
- CreditCustomerController::index sends trimmed rows (no national id), the net purchases total (countable invoices: final − refunded), the total customer count for the ratio KPI, collected_this_month and the collections count. The JSON response is trimmed the same way.

## Frontend changes
- POS (`resources/views/admin/sales/pos/partials/*`):
  - **Data:** catalog, quick-add, category counts, barcode lookup (barcode, SKU, supplier carton code or exact name) and the customer list come from server data. No `window.AlHusseiniSales` remains in POS views.
  - **Payload:** real numeric ids only; submit is blocked when an id is invalid.
  - **Serials:** each battery unit is its own cart line with a mandatory serial input. The invented `BAT-…` serial is gone.
  - **Idempotency:** a per-checkout UUID `idempotency_key` is kept across retries and renewed after success.
  - **Discount:** new input. The server enforces invoices.discount or a manager override (existing prompt, generic text).
  - **Scrap trade-in:** an Ah input (30-250) plus count. The server prices the trade-in from its tiers unless a manual amount is entered; the client shows the same tier estimate.
  - **Tax:** sent as 0. No tax rule is configured (VAT setting unused, Phase 9).
  - **Customer quick-create:** posts to admin.customers.store with tier standard. The vehicle is optional, with separate brand/model/plate fields.
  - **Vehicle choice:** a vehicle select appears when the customer has vehicles. A single vehicle is preselected; with several the cashier chooses.
  - **Stock limits:** client limits mirror the server (total per product, services included).
  - **Escaping:** all server text rendered with innerHTML is escaped.
  - **Removed:** the mock printable-invoice modal. The real receipt, warranty-certificate and invoice links remain.
  - **Restored:** the dead quick-cash chips container.
- Credit page (`resources/views/admin/sales/credit.blade.php`):
  - KPIs, the customer table, the payments tab (paginated, from the endpoint) and the statement modal use server data.
  - The mock fallback and mirroring are removed, and route() URLs replace hard-coded ones.
  - Customer-type filter: the invented categories were replaced with the real tiers (standard, vip, fleet).
  - Settlement methods are now cash, bank_transfer and card, matching server validation. The optional receipt number was added.
  - The hard-coded "receiver" names were replaced by the signed-in user, whom the server already records as collected_by.
  - CSV export is built from server rows, with a formula-injection guard.
- `sales-store.js` and its include in vendor-scripts are untouched. Phase 10 removes them once the dashboard is the last consumer.

## New tests
- `tests/Feature/Sales/PosCheckoutIntegrityTest.php`:
  - non-numeric product id → 422;
  - battery without serial → 422;
  - battery quantity > 1 → 422;
  - split-line oversell refused, by the request and by the service;
  - two serialized units;
  - repeated idempotency key → same invoice, single stock deduction.
- `tests/Feature/Sales/PosScreenDataTest.php`:
  - embedded product, customer and tier ids equal DB ids;
  - no mock-store, CUST-CASH, BAT- or PROD- usage in the POS script;
  - no cost price or national id exposed;
  - walk-in account not selectable.
- `tests/Feature/Sales/CreditCollectionsPageTest.php`:
  - the payments endpoint returns the settlement row (receipt, customer, collector, amount, month total);
  - 403 without credit.view;
  - the page has no national id or mock store;
  - trimmed index JSON.

## Blocked / notes
- **Tax:** there is no tax rule (VAT setting unused). Tax stays 0 until Phase 9 wires settings with an owner-confirmed rule.
- **Services and stock:** the server checks stock for every product, including the services category; the UI mirrors that. Whether services should be stock-tracked is UNVERIFIED (existing backend behaviour, unchanged).
- **Scrap pricing:** a cashier can still enter a manual trade-in amount (existing server behaviour). Whether that needs a permission or override is an owner decision.
- **Customer list size:** the POS embeds every active customer. With a very large list, switch the select to the admin.customers.search endpoint (a scalability note, not a defect today).
- **Manual check pending:** browser-level checks (scanner focus, modal flows) were not automated; there are no JS/browser tests in the project. A manual UAT sale is recommended before release.
