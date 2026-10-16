# F4: JavaScript decoupling

Tracker: `00_INDEX.md`. Requires F0 and owner decisions D-F1 (delivery) and D-F2 (Alpine). Can run in parallel with F1-F3. Next: F5.

## Rules
- Behaviour, endpoints, HTTP methods, headers, payloads and Arabic messages stay identical.
- Must work with the seamless navigation engine (`layouts/partials/vendor-scripts.blade.php`): it swaps `<main>`, awaits new `<script src>` tags, then replays the page's `DOMContentLoaded` handlers. Therefore:
  - global handlers are bound once on `document` (delegation) and survive navigation;
  - page modules are idempotent (re-running them must not double-bind or redeclare top-level `const`);
  - polling/timers are created through `Admin.page.onLeave()` so they stop when the page is swapped.
- Server data to JS: `@json` in a `<script type="application/json" id="...-data">` block or `data-*` attributes. Never interpolate PHP into JS source.
- DOM writes of server data: `textContent`, or `Admin.esc()` inside templates. No new `innerHTML` with unescaped values.
- Assumes D-F1 = static files under `public/assets/js/admin/`, D-F2 = no Alpine. If the owner chooses otherwise, rewrite Steps 1-2 before running them.

## Objective
Move the 134 inline event attributes and 49 inline script blocks out of Blade, collapse the 6 duplicated escape helpers and the ad-hoc fetch/loading/error code into one shared core, and make page scripts cacheable files.

## Context (verified)
- Escape helpers duplicated in: `sales/pos/partials/scripts.blade.php:48`, `sales/credit.blade.php:439`, `sales/customers.blade.php:384`, `hr/partials/payroll/scripts.blade.php:235`, `hr/partials/reports/scripts.blade.php:21`, `warranties/index.blade.php:309`, `public/assets/js/global-spotlight-search.js:269`.
- Inline handler hotspots: POS cart panel (18), HR report filters (16), credit page (14), HR attendance scripts (10), POS search/categories (9), POS scripts (9), invoices (6). Most common: `window.print()` (10), `setQuickTabFilter` (8), `punchDirect` (5), `applyDatePreset` (5), `loadReport` (5).
- Functions called from inline handlers are attached to `window` (e.g. `window.openClaimModal`); removing the attribute requires keeping or replacing that entry point.
- `resources/views/admin/scrap/index.blade.php:279` nests `@section('scripts')` inside `content`, so its page JS never renders (found in the backend roadmap; owner decision). Moving it to a module fixes it; get approval first.
- 48 `Swal.fire` calls; 30 `fetch(` calls with repeated CSRF/Accept/JSON-error boilerplate.

## Shared core (`public/assets/js/admin/core.js`, loaded once in `vendor-scripts` before page scripts)
```js
window.Admin = window.Admin || {
  esc(value),                          // the single HTML escaper
  json(id),                            // JSON.parse(document.getElementById(id).textContent)
  route(name, params),                 // reads <meta name="route-*"> (backend Phase 5), replaces __ID__
  fetchJson(url, {method, body, signal}),  // CSRF + Accept + X-Requested-With; throws {status, message, errors} on !ok
  form: { showErrors(form, errors), clearErrors(form), setBusy(button, busy) },
  actions: { register(name, handler) },    // delegated: [data-action="name"] on click/change/submit/input
  page: { onLeave(fn) },                   // cleanup hook called by the navigation engine before swapping <main>
  toast(type, message), confirm(options),  // thin SweetAlert2 wrappers keeping current texts
};
```
- Delegation: one listener per event type on `document`; `event.target.closest('[data-action]')`; handlers receive `(element, event)`; `data-*` carry arguments (e.g. `data-action="cart.remove" data-index="2"`).
- The navigation engine gets a 3-line hook to call `Admin.page` cleanup before swapping `<main>` (covered by `SeamlessNavigationLayoutTest`).

## Codex Frontend Cards
```text
[PHASE: F4 - Step 1 | TARGET: public/assets/js/admin/core.js, resources/views/admin/layouts/partials/vendor-scripts.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Implement Admin core exactly as specified in docs/frontend-phases/F4_JAVASCRIPT_DECOUPLING.md (no dependencies; ES2019; guard against double load with `if (window.Admin) return;`).
2. Load it with `<script src="{{ asset('assets/js/admin/core.js') }}">` right after bootstrap/sweetalert in vendor-scripts, before app.js.
3. Add the page-leave hook in the navigation engine where it swaps the main content.
4. Expose StatusBadges (F1) to JS only if F1 is done: `<script type="application/json" id="status-badges-data">@json(App\Support\StatusBadges::all())</script>` in the layout, plus Admin.statusBadge(type, status) returning an element.
5. Test tests/Feature/Admin/AdminCoreScriptTest.php: layout includes core.js before page scripts; SeamlessNavigationLayoutTest unchanged and green.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: navigate dashboard -> POS -> HR -> dashboard with the AJAX engine; no console errors; notifications poll only once. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Admin
```
```text
[PHASE: F4 - Step 2 | TARGET: resources/views/**/*.blade.php (onclick="window.print()") ]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Register action `print` in core.js (window.print()).
2. Replace all 10 `onclick="window.print()"` with `data-action="print"`.
3. Lower the hygiene budget `handlers` by 10.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: print from invoice show, POS receipt, HR reports, credit statement. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Architecture
```
```text
[PHASE: F4 - Step 3 | TARGET: resources/views/admin/hr/partials/{attendance,employees,payroll,reports}/*.blade.php -> public/assets/js/admin/hr/{attendance,employees,payroll,reports}.js]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Per partial: move the <script> body into the module file wrapped in `document.addEventListener('DOMContentLoaded', ...)` (engine replays it); server values (ROUTES map, ids, permissions) move to a `<script type="application/json" id="hr-{page}-data">@json([...])</script>` block read with Admin.json().
2. Replace every inline on*= attribute in these partials with data-action + data-* args; register the actions in the module.
3. Replace local escapeHtml/fetch boilerplate with Admin.esc / Admin.fetchJson / Admin.form; keep messages identical.
4. The partial keeps only the JSON data block and `<script src="{{ asset('assets/js/admin/hr/{page}.js') }}"></script>`.
5. Lower the hygiene budgets accordingly.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: HR employees (list, filter, create, edit, delete), attendance (load, punch, mark absent, quick deduction), payroll (generate, approve, disburse, payslip), reports (each mode and preset), also after arriving through AJAX navigation. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Hr tests/Feature/Admin tests/Architecture
```
```text
[PHASE: F4 - Step 4 | TARGET: resources/views/admin/sales/credit.blade.php, sales/customers.blade.php, sales/invoices.blade.php, warranties/index.blade.php, diagnostics/index.blade.php, users/index.blade.php -> public/assets/js/admin/{sales,warranties,diagnostics,users}/*.js]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Same pattern as Step 3, one page per commit.
2. diagnostics: build the status badge with Admin.statusBadge / textContent instead of the HTML string at diagnostics/index.blade.php:322.
3. warranties: keep window.openClaimModal only if another page calls it; otherwise use an action.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check per page (credit collect + statement, customers CRUD, invoices filters/actions, warranty verify + claim + settle, diagnostics audit/simulate, users CRUD). php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature tests/Architecture
```
```text
[PHASE: F4 - Step 5 | TARGET: resources/views/admin/sales/pos/partials/*.blade.php -> public/assets/js/admin/pos/{state,cart,catalog,payment,checkout}.js]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Split the 1,498-line POS script by responsibility: state (cart model, totals), catalog (search, categories, barcode focus), cart (render, qty, remove), payment (modes full/partial/remaining, amounts), checkout (idempotency key, submit, receipt).
2. Totals and payment math must give identical results; add a pure-function file pos/math.js and keep piaster rounding exactly as today.
3. Replace the 36 inline handlers in cart-panel, search-and-categories and scripts with data-action.
4. Keep window entry points that the receipt/print flow uses.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: full POS run (search, barcode, categories, add/remove/qty, scrap trade-in, full/partial/credit payment, manager override, receipt print, retry with the same idempotency key), desktop and mobile floating bar. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Sales tests/Architecture
```
```text
[PHASE: F4 - Step 6 | TARGET: resources/views/admin/scrap/index.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Only after owner approval: move the page script (currently never rendered) into public/assets/js/admin/scrap.js and close the content section correctly.
2. Report in STATUS any behaviour that starts working because the script now runs.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: scrap page actions. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Warranty tests/Architecture
```

## Acceptance criteria
- [ ] `Admin` core loaded once; exactly one HTML escaper left in the codebase (grep `escapeHtml\|function esc` returns only core.js).
- [ ] Hygiene budget: `handlers` = 0, `scripts` limited to JSON data blocks (`type="application/json"`) and the layout's engine script; recorded per step.
- [ ] Every migrated page works on a full load and after AJAX navigation, with no duplicate polling.
- [ ] Full suite green.
