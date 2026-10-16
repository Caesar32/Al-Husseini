# F1: Atomic components

Tracker: `00_INDEX.md`. Requires F0. Next: F2.

## Rules
- Anonymous Blade components in `resources/views/components/` (`@props`, `$attributes->merge()`, named slots). A class component (`app/View/Components/`) only when the component needs logic beyond a lookup table.
- Output must be byte-for-byte equivalent in classes and text to the markup it replaces (attribute order may differ). No visible change.
- Each component gets a render test in `tests/Feature/Components/` using `$this->blade(...)`.
- Migrate at most 3 call sites per step as proof; mass migration happens in F5. Lower the F0 hygiene budget whenever a step removes inline styles.

## Objective
One source of truth for the smallest repeated elements: 228 hand-written badges, 55 alerts, 21 spinners/loaders, 47 inline empty states, 191 hand-written `ج.م` strings.

## Context (verified)
- Existing: `components/compact-money.blade.php` (23 uses) - keep its API.
- Status badges are mapped in several places: Blade (`dashboard/partials/low-stock.blade.php:29` `$badgeClass`, invoice/warranty/payroll views) and JS strings (`hr/partials/attendance/scripts.blade.php:149-214`, `diagnostics/index.blade.php:322`).
- Statuses live in `App\Enums\InvoiceStatus`; attendance, warranty, claim, payroll and leave statuses are plain strings.

## Components
| Component | Props | Notes |
|---|---|---|
| `<x-button>` | `variant='primary'`, `size=null`, `icon=null`, `type='button'`, `href=null`, `loading=false` | renders `<a>` when `href`; icon-only requires `aria-label` (assert in test) |
| `<x-icon>` | `name` (remix class without `ri-`), `label=null` | `aria-hidden="true"` unless `label`, then `role="img" aria-label` |
| `<x-badge>` | `variant='secondary'`, `soft=false`, `pill=false` | `bg-{variant}` or `bg-{variant}-subtle text-{variant}` |
| `<x-status-badge>` | `type` (invoice/attendance/warranty/claim/payroll/leave), `status` | class component; map in `App\Support\StatusBadges` (label + variant per type/status), also exposed to JS in F4 |
| `<x-alert>` | `variant='info'`, `dismissible=false`, `icon=null` | `role="alert"`, slot `title` |
| `<x-spinner>` | `size='sm'`, `label='جاري التحميل...'` | `role="status"` + visually-hidden label |
| `<x-money>` | `amount`, `currency='ج.م'`, `decimals=2` | `number_format` + currency; numeric font token |
| `<x-empty-state>` | `icon='ri-inbox-line'`, `message`, slot `action` | used in tables/lists |

## Codex Frontend Cards
```text
[PHASE: F1 - Step 1 | TARGET: resources/views/components/{button,icon}.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Create <x-icon> and <x-button> per the Components table in docs/frontend-phases/F1_ATOMIC_COMPONENTS.md.
2. Define `@props([...])` exactly as listed; merge classes with `$attributes->merge(['class' => ...])`; pass through data-*/aria-* attributes.
3. Replace 3 call sites as proof: the print buttons `onclick="window.print()"` markup stays functional but uses <x-button icon="printer-line" ...> (keep the onclick until F4 removes it).
4. Tests tests/Feature/Components/ButtonTest.php, IconTest.php: rendered HTML, <a> vs <button>, aria-hidden, loading state disables the button.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: invoice show print button unchanged. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Components
```
```text
[PHASE: F1 - Step 2 | TARGET: resources/views/components/{badge,status-badge}.blade.php, app/View/Components/StatusBadge.php, app/Support/StatusBadges.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Inventory every status->class/label mapping (grep: `badge bg-` near `status`, `$badgeClass`, `statusBadge`) and copy the existing labels and classes verbatim into StatusBadges::for(string $type, ?string $status): array{label: string, variant: string, soft: bool}; unknown status -> secondary + the raw value.
2. <x-badge> anonymous; <x-status-badge type status> class component using StatusBadges.
3. Replace the invoice status badges in resources/views/admin/sales/invoices.blade.php and invoices/show.blade.php only.
4. Tests: StatusBadges returns the same label/class as the old markup for every InvoiceStatus case; invoices page still renders each status label.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: invoices list badges unchanged for paid/partially_paid/unpaid/cancelled/refunded/partially_refunded. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Components tests/Feature/Sales
```
```text
[PHASE: F1 - Step 3 | TARGET: resources/views/components/{alert,spinner,money,empty-state}.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Create the four components per the table; <x-money> must output exactly number_format($amount, $decimals) . ' ' . $currency.
2. Replace: the session status/error alert block in layouts (if present) or one page's flash alerts; one table empty state in sales/customers.blade.php; money in dashboard/partials/low-stock.blade.php.
3. Tests for each component (roles, labels, money formatting incl. negative and zero).
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: dashboard and customers page unchanged. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Components tests/Feature/Admin
```

## Acceptance criteria
- [ ] 8 components exist, each with a render test; `compact-money` API unchanged.
- [ ] `StatusBadges` is the only server-side status->badge mapping used by migrated views.
- [ ] Full suite green; hygiene budget not raised; screenshots of touched pages unchanged.
