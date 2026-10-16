# Phase 5: Hard-coded admin URLs in JS (FE-08)

Status tracker: `REFACTOR_PLAN.md`. Requires Phase 1. It is independent of Phases 2-4. Next phase: `06_phase_data_model_fixes.md`.

## Rules
- Laravel 12 Blade with static JS under `public/assets/js` (no bundler). Pest tests on SQLite.
- Never weaken, skip or delete tests. HTTP methods, headers, payloads and UI behaviour must not change.
- Use only route names that exist in `php artisan route:list`. Never invent route names.
- Don't commit or push unless the owner asks.

## Objective
Replace the 20 literal `/admin/...` paths in JS with URLs the server generates. Today the app breaks when it is deployed under a sub-path, and any route rename silently breaks the JS.

SOLID:
- DIP: JS depends on server-provided routes.
- OCP: renaming a route needs no JS edit.

## Target sites (from grep)
| File | Lines |
|---|---|
| `public/assets/js/admin-notifications.js` | 13, 83, 105 |
| `public/assets/js/global-spotlight-search.js` | 127 |
| `resources/views/admin/hr/partials/attendance/scripts.blade.php` | 30, 317, 455, 510 |
| `resources/views/admin/hr/partials/employees/scripts.blade.php` | 24, 227, 344, 390, 453 |
| `resources/views/admin/hr/partials/payroll/scripts.blade.php` | 29, 70, 121, 161, 197 |
| `resources/views/admin/warranties/index.blade.php` | 368 |

`payroll/scripts.blade.php:231` already uses `{{ url(...) }}`. Convert it to `route()` for consistency.

## Contract
```js
// Blade partials (inline scripts): one map per partial
const ROUTES = @json(['employees' => route('<name>'), /* ... */]);
fetch(`${ROUTES.employees}/${encodeURIComponent(id)}`)

// Static .js files: meta tags emitted once in the admin layout <head>
// <meta name="route-hr-notifications" content="{{ route('<name>') }}">
const url = document.querySelector('meta[name="route-hr-notifications"]').content;
```

## Codex prompts
```text
[TASK: Phase 5 - Step 1]
ROLE: Strict Code Executor.
ACTION: Replace hard-coded admin URLs in Blade inline scripts (DIP/OCP).
TARGET_FILE: `resources/views/admin/hr/partials/attendance/scripts.blade.php` (L30,317,455,510), `resources/views/admin/hr/partials/employees/scripts.blade.php` (L24,227,344,390,453), `resources/views/admin/hr/partials/payroll/scripts.blade.php` (L29,70,121,161,197,231), `resources/views/admin/warranties/index.blade.php` (L368)
CHANGES_REQUIRED:
1. Run `php artisan route:list --path=admin/hr --json` and `--path=admin/warranties --json`; use only names found there.
2. Per file add one `const ROUTES = @json([...]);` at the top of the script with base index/collection URLs (route('name')); for parameterised routes use the collection base + `/${encodeURIComponent(id)}` + fixed suffix (e.g. '/approve').
3. Replace each literal path; query strings unchanged.
4. No other edits.
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Hr tests/Feature/Warranty`.
```
```text
[TASK: Phase 5 - Step 2]
ROLE: Strict Code Executor.
ACTION: Replace hard-coded URLs in static JS via layout meta tags.
TARGET_FILE: `public/assets/js/admin-notifications.js` (L13,83,105), `public/assets/js/global-spotlight-search.js` (L127), `resources/views/admin/layouts/master.blade.php` (next to `<meta name="csrf-token"`), `tests/Feature/Admin/SeamlessNavigationLayoutTest.php`
CHANGES_REQUIRED:
1. Get route names from `php artisan route:list --path=admin/hr/notifications --json` and `--path=admin/global-search --json`.
2. In master.blade.php, next to csrf-token meta, add (inside @auth): route-hr-notifications, route-hr-notifications-read-all, route-global-search meta tags with route() values; per-id read URL = notifications base + `/${id}/read`.
3. In the JS files read them once at the top: `const meta = n => document.querySelector(`meta[name="${n}"]`)?.content;` and use them; keep `encodeURIComponent(query)`.
4. Test: GET an admin page as super-admin; assertSee each of the 3 meta names.
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Admin`.
```

## Acceptance criteria
- [ ] Steps 1-2 are applied and each verification command passes.
- [ ] ``grep -rnE "(fetch\(|action\s*=)\s*['\"\`]/admin/" public/assets/js/*.js resources/views`` returns nothing.
- [ ] The full suite shows 0 failed and 0 skipped.
- [ ] Manual browser check:
  - HR: employee list, filter, edit, delete and create; attendance load and punch; mark absent; add a deduction.
  - Payroll: generate, approve and disburse.
  - Notifications: list, mark read and mark all read.
  - Global search, and the warranty settle form.
- [ ] Record the passed count as `N5`.
