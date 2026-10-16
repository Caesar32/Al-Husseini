# F5: Page views refactor

Tracker: `00_INDEX.md`. Requires F1-F4. Next: F6.

## Rules
- Pages consume the F1-F3 components and the F4 modules; no new visual design. Texts, permissions (`@can`) and element ids used by tests stay.
- One page per step and per commit. Each step ends with the page's feature tests, the hygiene test and a screenshot comparison.
- Formatting logic that lives in `@php` blocks moves to a View Model (`app/View/Models/...`) or the existing service (e.g. `DashboardMetricsService`); controllers stay thin.

## Objective
Bring the key screens onto the component system: POS, dashboard, products (inventory), invoices list + show. Remaining pages follow the same card afterwards.

## Context (verified)
| Screen | Views | Size / notes |
|---|---|---|
| POS | `sales/pos.blade.php` + 8 partials (`header-bar`, `search-and-categories`, `catalog-panel`, `cart-panel`, `mobile-floating-bar`, `modals`, `styles` 729 lines, `scripts` 1,498 lines) | 36 inline handlers; own stylesheet |
| Dashboard | `dashboard.blade.php` + `dashboard/partials/*` (stat cards, charts, low-stock, recent invoices, attendance) | data from `DashboardMetricsService` |
| Products (inventory) | `sales/products.blade.php` (425 lines) | table + filters + modal CRUD |
| Invoices | `sales/invoices.blade.php` (515), `invoices/show.blade.php` (414) | status badges, filters, print |

## Codex Frontend Cards
Step 1 (dashboard) runs as five sub-steps, one commit each. Shared rules for 1a-1e: Arabic copy byte-identical; every `@can` kept; every id read by `public/assets/js/admin/dashboard.js` or a test kept; layout classes unchanged; removed inline styles become classes in `public/assets/css/components.css` with the same computed values; lower the hygiene `styles` budget by the number removed. Owner decision (2026-10-17): no F0 screenshots; the guard is byte-identical copy, unchanged layout classes, view compilation and the test suites.
Verification for every sub-step: `php artisan view:cache && php artisan view:clear ; node --test tests/js/*.mjs ; php artisan test --compact tests/Feature/Admin tests/Architecture`.

```text
[PHASE: F5 - Step 1a | TARGET: resources/views/admin/dashboard.blade.php, resources/views/admin/dashboard/partials/welcome-bar.blade.php]
TASK: page title include -> <x-page-header> (same title, breadcrumbs, bg-galaxy-transparent); welcome-bar quick links -> <x-button href icon>; 3 inline styles -> components.css; drop the duplicated data-period attribute on the period buttons.
```
```text
[PHASE: F5 - Step 1b | TARGET: resources/views/admin/dashboard/partials/kpi-cards.blade.php, public/assets/js/admin/dashboard.js]
TASK: <x-stat-card> / <x-compact-money> where the markup matches; keep the 7 @can blocks and every dash* id. The Blade-computed display:none on #dashCreditCollectedWrap becomes the d-none class and dashboard.js toggles the class (update tests/js/dashboard.test.mjs). Other inline styles -> components.css.
```
```text
[PHASE: F5 - Step 1c | TARGET: resources/views/admin/dashboard/partials/recent-invoices.blade.php, app/Support/StatusBadges.php, app/View/Models/*]
TASK: <x-data-table>, <x-money>; payment-method badges from a new StatusBadges type `payment_method` with today's labels and classes byte-identical; the item-summary @php moves to a View Model; column widths -> components.css.
```
```text
[PHASE: F5 - Step 1d | TARGET: resources/views/admin/dashboard/partials/low-stock.blade.php, workshop-attendance.blade.php, app/View/Models/*]
TASK: <x-data-table>, <x-badge>, <x-empty-state>; stock-level and attendance-status @php logic moves to a View Model (labels and classes byte-identical); inline styles -> components.css.
```
```text
[PHASE: F5 - Step 1e | TARGET: resources/views/admin/dashboard/partials/charts.blade.php, credit-dues.blade.php]
TASK: chart container heights and remaining styles -> components.css (chart ids unchanged); credit-dues on <x-money>/<x-empty-state> where the markup matches; final hygiene budget recorded in 00_INDEX.md.
```
```text
[PHASE: F5 - Step 2 | TARGET: resources/views/admin/sales/invoices.blade.php, resources/views/admin/invoices/show.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. List: <x-page-header>, <x-filter-bar>, <x-data-table> with <x-status-badge type="invoice">, <x-money>, pagination.
2. Show: sections as cards with <x-money>, scrap section (scrapBatteries count + capacities), payments table, print via data-action.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: invoices list filters/pagination; invoice show for paid, partially_refunded and an invoice with 2 scrap batteries; print preview. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Sales tests/Architecture
```
```text
[PHASE: F5 - Step 3 | TARGET: resources/views/admin/sales/products.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Table, filters, create/edit modal with <x-modal> + <x-form.*>; low-stock badge via <x-badge>.
2. current_stock is display-only (stock changes only through the ledger); do not add an editable stock field.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: products search, create, edit, deactivate; low-stock highlight. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Admin tests/Architecture
```
```text
[PHASE: F5 - Step 4 | TARGET: resources/views/admin/sales/pos.blade.php, resources/views/admin/sales/pos/partials/*.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Partials use <x-button>, <x-icon>, <x-money>, <x-modal>, <x-form.*>; keep all ids the F4 POS modules query.
2. Move pos/partials/styles.blade.php into public/assets/css/pos.css on tokens (same computed styles); the partial becomes a <link>.
3. No change to cart/payment math or request payload.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: full POS run as in F4 Step 5, desktop + mobile, light + dark. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Sales tests/Architecture
```

## Acceptance criteria
- [ ] The four screens use components for buttons, badges, money, modals, forms, tables and stat cards; no inline handlers, no inline styles left in them.
- [ ] Feature tests for each screen green; screenshots match F0.
- [ ] Hygiene budget lowered and recorded.
