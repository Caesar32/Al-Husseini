# Frontend Roadmap: Index and Progress Tracker

Base: `main` @ `c9327fc` (2026-10-16). Architect: Claude. Executor: Codex (one Frontend Card at a time).

This roadmap follows the backend refactor (`REFACTOR_PLAN.md`). Its goal is a componentized, decoupled and accessible admin UI with **no visible redesign**. Every phase keeps the screens pixel-equivalent unless a step says otherwise.

## Audit summary (2026-10-16, measured with grep over `resources/views` and `public/assets/js`)
| Area | Finding |
|---|---|
| Size | 99 Blade views, 19,919 lines. Largest: `sales/pos/partials/scripts.blade.php` (1,498), `layouts/partials/head-css.blade.php` (929, inline `<style>`), `sales/credit.blade.php` (771), `sales/pos/partials/styles.blade.php` (729). |
| Stack | Static Velzon-style theme: Bootstrap 5.3.6 RTL + LTR CSS, Remix/Feather icons, SweetAlert2, Simplebar, Waves, Flatpickr, Choices.js, all from `public/assets`. Arabic RTL, light/dark via `data-bs-theme`. |
| Pipeline | Vite 7 + Tailwind 4 are configured (`vite.config.js`, `resources/css/app.css`) and built in CI, but **no view uses `@vite`**. Tailwind would collide with Bootstrap; it is not used by this roadmap. |
| Navigation | The layout has a seamless (AJAX) navigation engine (`layouts/partials/vendor-scripts.blade.php`): it swaps `<main>`, runs the new page's scripts, and replays their `DOMContentLoaded` handlers. Every JS change must stay compatible with it. |
| Components | One Blade component (`components/compact-money.blade.php`, 23 uses). No class components, no View Composers. 69 `@include`s. |
| Inline JS | 134 inline event attributes (`onclick=` etc.) in 36 files; 49 inline `<script>` blocks in 24 files; 30 `fetch(` calls; 48 `Swal.fire` calls. Top offenders: POS cart panel (18), HR report filters (16), credit page (14). |
| Escaping | 0 `{!! !!}` in views. 80 `innerHTML` writes, with 6 separate copies of an HTML-escape helper (`esc`, `escapeHtml`). Status badges are built twice: in Blade and again as JS strings (attendance, diagnostics). |
| Styling | 163 `style="..."` attributes in 47 files and 12 `<style>` blocks. A partial token set exists in `head-css` `:root` (fonts, `--brand-*` colors, radii; 73 custom properties). |
| Repeated markup | 29 modals, 43 tables, 228 hand-written badges, 55 alerts, 44 stat cards (`card-animate`), 47 inline empty states, 191 hand-written `ج.م` money strings, 10 paginators. |
| Accessibility | 221 `<label>` but only 67 with `for=`; 85 `aria-label`; icon-only buttons often unlabeled; 19 `<img>`, 19 `alt=`. |
| Test coverage | 25 feature tests render admin pages; `SeamlessNavigationLayoutTest` pins the navigation engine. No JS tests, no visual regression tooling. |

## Progress
| Phase | File | Theme | Depends on | Status |
|---|---|---|---|---|
| F0 | [F0_BASELINE_AND_DESIGN_TOKENS.md](F0_BASELINE_AND_DESIGN_TOKENS.md) | Pipeline decision, tokens file, hygiene ratchet test, screenshots | none | [x] `287306e`, `7f38539`; 478 passed; screenshots (Step 4) pending owner |
| F1 | [F1_ATOMIC_COMPONENTS.md](F1_ATOMIC_COMPONENTS.md) | Button, badge, status badge, alert, spinner, icon, money, empty state | F0 | [x] `146bdad`..`d522488`; 506 passed; StatusBadges has the invoice type (others added as pages migrate) |
| F2 | [F2_FORM_AND_INPUT_CONTROLS.md](F2_FORM_AND_INPUT_CONTROLS.md) | Field wrapper, input, select, date, switch, error states | F1 | [x] `e0e81c8`, `be2b930`; 517 passed. Step 3 (employee modal) BLOCKED: owner HR edits still uncommitted in modal-form.blade.php |
| F3 | [F3_COMPLEX_BLADE_COMPONENTS.md](F3_COMPLEX_BLADE_COMPONENTS.md) | Modal, data table, filter bar, stat card, page header | F2 | [x] `c9e73e6`..`e60a698`; 527 passed |
| F4 | [F4_JAVASCRIPT_DECOUPLING.md](F4_JAVASCRIPT_DECOUPLING.md) | `admin-core.js` (esc, fetch, actions), inline handlers and scripts out of views | F0 | [ ] |
| F5 | [F5_PAGE_VIEWS_REFACTOR.md](F5_PAGE_VIEWS_REFACTOR.md) | POS, dashboard, products (inventory), invoices on the new components | F1-F4 | [ ] |
| F6 | [F6_RESPONSIVE_AND_ACCESSIBILITY.md](F6_RESPONSIVE_AND_ACCESSIBILITY.md) | Labels, ARIA, focus, keyboard, touch targets, mobile viewports | F5 | [ ] |
| F7 | [F7_FINAL_POLISH_AND_BUNDLING.md](F7_FINAL_POLISH_AND_BUNDLING.md) | Asset versioning/caching, optional bundling, CSP readiness, visual review | F0-F6 | [ ] |

F4 depends only on F0 and can run in parallel with F1-F3.

## Recorded values
| Key | Value |
|---|---|
| `FE0_SHA` (F0 start) | `772ddd2` (full suite 465 passed at `c9327fc`; no code changed since) |
| Hygiene baseline (inline handlers / inline scripts / style attrs / `<style>` blocks) | Audit grep: 134 / 49 / 163 / 12. Ratchet regexes (F0): 139 handlers (adds blur/focus/keypress) / 26 inline scripts (no `src`, not JSON) / 163 / 12 |
| Hygiene after each phase | |
| Owner decision D-F1 (JS delivery: static files vs Vite) | Static files under `public/assets/js/admin/` (recommended default; owner asked for autonomous execution without questions, 2026-10-16) |
| Owner decision D-F2 (Alpine.js: no / yes) | No (recommended default, same basis) |
| Owner decision D-F3 (visual regression tool: manual screenshots / Playwright) | Manual screenshots (recommended default); taking them is an owner action |
| Full test count per phase | |

## Owner decisions (do not implement before they are recorded above)
- **D-F1 JS delivery.** Recommended: plain files under `public/assets/js/admin/` loaded with `asset()`, no build step (works on today's deploy, and the navigation engine already awaits `<script src>`). Alternative: `@vite` entries; needs `public/build/manifest.json` on every server or every page returns 500.
- **D-F2 Alpine.js.** Recommended: no. The navigation engine replaces `<main>` and replays page scripts, so Alpine would need `Alpine.initTree`/`destroyTree` hooks in the engine and a new runtime dependency. A delegated `data-action` registry (F4) gives the same decoupling with no dependency.
- **D-F3 Visual regression.** Recommended: a manual screenshot checklist (F0 Step 4). Alternative: Playwright screenshots in CI, which adds a Node dev dependency and a seeded test server.

## Global rules (repeated in every phase file)
- No visible redesign: same layout, colors, texts and RTL behaviour unless a step says otherwise. Arabic copy stays byte-identical.
- Never weaken, skip or delete tests. Every component gets a render test (`$this->blade(...)`); every page change keeps its existing feature test green.
- Server data reaches JS only through `@json`, `data-*` attributes (Blade-escaped) or JSON endpoints, and reaches the DOM only through `textContent` or `Admin.esc()`. No `{!! !!}` without a documented sanitizer.
- Stay compatible with the navigation engine: page scripts must be idempotent, bind through delegation or `DOMContentLoaded`, and never declare top-level `const`/`let` that collide on re-run.
- Keep `@can` checks exactly as they are. Never hide-only.
- Don't delete files without owner approval. Never use `git stash`. Run Pint only on touched PHP files. Commit one step at a time only when the owner has asked for commits.

## Running a step
Card format (every phase file uses it):
```text
[PHASE: FX - Step Y | TARGET: {blade_view_or_asset_path}]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK: ...
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check instruction + Blade syntax check command.
```
Blade syntax check used by every card: `php artisan view:cache && php artisan view:clear` (compiles all 99+ views; fails on any syntax error).
```bash
codex exec -m <model> --config model_reasoning_effort="high" --sandbox read-only --skip-git-repo-check "<one card>" </dev/null 2>/dev/null
```
Files in this repo use CRLF in the working tree; apply Codex diffs with `git apply --recount` after normalizing, or apply the reviewed change by hand.
