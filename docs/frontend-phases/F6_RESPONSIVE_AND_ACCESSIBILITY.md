# F6: Responsive layout and accessibility (WCAG 2.1 AA)

Tracker: `00_INDEX.md`. Requires F5. Next: F7.

## Rules
- Target WCAG 2.1 AA on the admin UI. Arabic RTL first; LTR must not break.
- Fixes go into the shared components first, pages second. No redesign: focus rings, labels and sizes may change only where needed for AA.
- Every rule that can be checked in HTML gets an automated assertion (render tests or the hygiene test).

## Objective
Make the admin usable by keyboard and screen reader and on phone/tablet widths (POS cashiers and the owner use tablets and phones).

## Context (verified)
- 221 `<label>`, only 67 with `for`; 85 `aria-label`; icon-only buttons (`btn-icon`, `<i class="ri-...">` alone) often have no accessible name.
- `<html lang="ar" dir="rtl">` is set in `master.blade.php`.
- POS has a dedicated mobile floating bar (`pos/partials/mobile-floating-bar.blade.php`).
- Dark mode via `data-bs-theme`; brand gold `#c59b27` on white is below 4.5:1 for body text (check every use).

## Checklist
| Area | Requirement | How it is checked |
|---|---|---|
| Names | every button/link/input has an accessible name | hygiene test: no `<button>` whose content is only an `<i>`/`<x-icon>` without `aria-label`; `<x-button>` asserts it |
| Labels | every form control has `<label for>` or `aria-label` | component tests (F2) + hygiene count of unlabeled controls |
| Focus | visible `:focus-visible` ring using `--ah-focus-ring`; no `outline: none` without replacement | grep CSS; manual tab-through |
| Keyboard | modals trap focus and return it; dropdowns, POS search results and spotlight search work with arrows/Enter/Esc | manual script per page |
| Landmarks | one `<main>`, `<nav aria-label>` for sidebar and breadcrumbs, `<header>` topbar | render test on layout |
| Live regions | toasts/AJAX results announce via `aria-live="polite"` (`Admin.toast`, result boxes) | render/manual |
| Contrast | text >= 4.5:1, large text/icons >= 3:1 in light and dark | token review with a contrast checker; record results |
| Touch | interactive targets >= 44x44px on touch layouts (`--ah-touch-min`) | CSS review + mobile check |
| Responsive | no horizontal scroll at 360, 390, 768, 1024, 1440 px; tables scroll inside `.table-responsive` | manual viewport pass |
| Motion | animations respect `prefers-reduced-motion` | CSS review |
| Images | every `<img>` has meaningful `alt` or `alt=""` | hygiene test (19 imgs, 19 alts today) |

## Codex Frontend Cards
```text
[PHASE: F6 - Step 1 | TARGET: resources/views/components/*.blade.php, public/assets/css/components.css, public/assets/css/tokens.css]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Add :focus-visible styles on tokens; prefers-reduced-motion block; min touch size for .btn, .form-control, .form-select, .page-link under (pointer: coarse).
2. <x-modal>: return focus to the opener on hide (data-action wiring in core.js); <x-spinner>/<x-alert>: aria-live.
3. Extend component tests for the new attributes.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: tab through dashboard, POS, HR employees; open/close a modal by keyboard. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Components
```
```text
[PHASE: F6 - Step 2 | TARGET: tests/Architecture/FrontendHygieneTest.php + offending views]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Add budgets: icon-only buttons without aria-label, <img> without alt, inputs/selects/textareas without id+label or aria-label; measure, set the budget to the measured value.
2. Fix them view by view (layout topbar/sidebar first), lowering budgets to 0.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: none (attributes only); screen reader spot check on topbar and POS. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Architecture tests/Feature
```
```text
[PHASE: F6 - Step 3 | TARGET: resources/views/admin/layouts/*.blade.php, layouts/partials/{sidebar,topbar}.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Landmarks: <header> topbar, <nav aria-label="القائمة الرئيسية"> sidebar, single <main id="main-content">, skip link "تخطي إلى المحتوى" as first focusable element.
2. Keep every selector the navigation engine and app.js use (verify with grep) and keep SeamlessNavigationLayoutTest green.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: layout identical; skip link appears on Tab. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Admin
```
Step 4 (orchestrator): manual viewport and contrast pass on the F0 screen list; record issues and fixes in this file.

## Acceptance criteria
- [ ] Accessibility budgets in the hygiene test are 0.
- [ ] Keyboard-only run through login, POS sale, HR employee create and invoice print succeeds.
- [ ] No horizontal scroll at 360-1440 px on the F0 screen list; contrast results recorded.
