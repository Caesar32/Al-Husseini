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
```text
[PHASE: F5 - Step 1 | TARGET: resources/views/admin/dashboard.blade.php, resources/views/admin/dashboard/partials/*.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Replace stat cards with <x-stat-card>, money with <x-money>/<x-compact-money>, badges with <x-status-badge>, tables with <x-data-table>, empty states with <x-empty-state>, header with <x-page-header>.
2. Keep every @can block and the period switcher behaviour (sales_period AJAX).
3. Move remaining inline styles to components.css.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: dashboard vs F0 screenshots (light/dark, desktop/mobile), period switch. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Admin tests/Architecture
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
