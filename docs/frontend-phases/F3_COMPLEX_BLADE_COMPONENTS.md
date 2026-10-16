# F3: Complex Blade components

Tracker: `00_INDEX.md`. Requires F2. Next: F5 (with F4).

## Rules
- Components render structure only; data formatting that needs logic goes into a View Model / View Composer (`app/View/...`), never into `@php` blocks inside the component.
- Modal ids and element ids that JS uses stay identical (grep the scripts before renaming anything).
- Bootstrap behaviour (`data-bs-toggle`, `data-bs-dismiss`) stays; no new JS framework.

## Objective
Remove the large copy-pasted blocks: 29 modals, 43 tables, 44 stat cards, 11 page headers (`partials.page-title`), filter bars on list pages, and 10 paginators.

## Context (verified)
- Modals: 29 `class="modal fade"`, mostly in `sales/pos/partials/modals.blade.php`, HR partials (`modal-*.blade.php`), users, warranties, credit.
- Tables: list pages (`sales/invoices`, `sales/customers`, `sales/products`, `purchases/index`, `suppliers/index`, `users/index`, `warranties/index`, `scrap/index`, `credit/statement`) share header/striped/empty-row/pagination markup.
- Stat cards: `card card-animate border-0 shadow-sm ... style="..."` in dashboard, diagnostics, HR stats, credit.
- Dashboard data is shaped by `DashboardController` + `DashboardMetricsService` (backend Phase 3); keep that split.

## Components
| Component | Props / slots | Notes |
|---|---|---|
| `<x-modal>` | `id`, `title`, `size=null` (`sm/lg/xl`), `static=false`; slots `body` (default), `footer` | `aria-labelledby="{id}-title"`, `tabindex="-1"`, close button with `aria-label="إغلاق"` |
| `<x-confirm-modal>` | `id`, `title`, `message`, `confirmLabel`, `variant='danger'` | optional replacement for some `Swal.fire` confirms (owner choice per page; SweetAlert stays) |
| `<x-data-table>` | `columns` (key=>label or array with `class`/`sortable`), `rows` (slot), `empty` slot, `paginator=null`, `caption=null` | `<caption class="visually-hidden">`, `scope="col"`, responsive wrapper, renders `<x-empty-state>` and `{{ $paginator->links() }}` |
| `<x-filter-bar>` | `action`, `method='GET'`; slots `fields`, `actions` | GET form, reset link, keeps query string |
| `<x-stat-card>` | `label`, `value`, `icon=null`, `variant='primary'`, `trend=null`, `href=null` | replaces inline `style=` gradients with token classes `.ah-stat--{variant}` in a stylesheet |
| `<x-page-header>` | `title`, `breadcrumbs=[]`; slot `actions` | wraps/replaces `partials.page-title` |

## Codex Frontend Cards
```text
[PHASE: F3 - Step 1 | TARGET: resources/views/components/{modal,confirm-modal}.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Create <x-modal> and <x-confirm-modal> per the table in docs/frontend-phases/F3_COMPLEX_BLADE_COMPONENTS.md.
2. Migrate resources/views/admin/hr/partials/attendance/modal-deduction.blade.php and one users/index modal; keep their ids and inner field ids.
3. Tests: aria-labelledby resolves to the title element; footer slot optional; size class mapping.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: open/close the deduction modal (button, Esc, backdrop) and the users modal. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Components tests/Feature/Hr tests/Feature/Admin
```
```text
[PHASE: F3 - Step 2 | TARGET: resources/views/components/{data-table,filter-bar}.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Create both components per the table.
2. Migrate resources/views/admin/suppliers/index.blade.php (small, server-rendered list) end to end: filter bar + table + empty state + pagination.
3. Tests: header cells have scope=col; empty slot shown when no rows; paginator links rendered and query string preserved.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: suppliers list, search, empty search, page 2. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Components tests/Feature/Purchases
```
```text
[PHASE: F3 - Step 3 | TARGET: resources/views/components/{stat-card,page-header}.blade.php, public/assets/css/components.css]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Create both components; move the inline gradient/border styles of the diagnostics stat cards into components.css classes built on tokens.css variables (same computed colors).
2. Migrate resources/views/admin/diagnostics/index.blade.php stat cards and its page title.
3. Link components.css after tokens.css in both layouts.
4. Lower the hygiene budget for `style="` by the number removed.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: diagnostics page identical in light and dark mode. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Components tests/Feature/Admin tests/Architecture
```

## Acceptance criteria
- [ ] 6 components with render tests; migrated pages keep their feature tests green.
- [ ] Every migrated modal has `aria-labelledby`, a labelled close button and works with keyboard (Esc, focus return).
- [ ] Every migrated table has `scope="col"` headers and a caption.
- [ ] Inline `style=` count lowered and recorded.
