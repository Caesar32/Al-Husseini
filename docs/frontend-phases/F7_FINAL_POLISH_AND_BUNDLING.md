# F7: Final polish, bundling and verification

Tracker: `00_INDEX.md`. Requires F0-F6.

## Rules
- Verification first; only the bundling/caching items below change code.
- Removing Vite/Tailwind, unused libraries (`public/assets/libs`, 5.6 MB) or dead JS (`sales-store.js`, `hr-store.js`) needs owner approval; list candidates, don't delete.
- No CDN changes without owner approval (Google Fonts are already external).

## Objective
Ship the new assets with safe caching, check that nothing unused is loaded, and verify the whole UI against the F0 baseline.

## Context (verified)
- Assets are served from `public/assets` via `asset()` without a version query, so browsers can keep stale JS/CSS after a deploy.
- Global libraries loaded on every page: Bootstrap bundle, SweetAlert2, Simplebar, Waves, Feather, Lord-icon, Flatpickr, Choices.js (Flatpickr/Choices used on ~1 page), `plugins.js`, `app.js`.
- `public/assets/css`: both RTL and LTR builds of Bootstrap and the theme are shipped; `head-css` picks one by locale.

## Steps
```text
[PHASE: F7 - Step 1 | TARGET: app/Support/Asset.php (or a Blade helper), layouts]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Add versioned URLs for first-party files only (tokens.css, components.css, pos.css, assets/js/admin/**): asset($path).'?v='.filemtime(public_path($path)) cached per request; fall back to asset($path) if the file is missing.
2. Use it in the layouts and partials that load those files.
3. Test: the rendered URL contains ?v=.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: hard reload after editing core.js picks up the change. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature/Admin
```
```text
[PHASE: F7 - Step 2 | TARGET: resources/views/admin/layouts/partials/vendor-scripts.blade.php]
ROLE: Precision Frontend Implementer.
STANDARDS: Component-driven, zero inline JS, clean semantic HTML.
TASK:
1. Load Flatpickr and Choices.js (JS + CSS) only on pages that use them (@push('vendor')/@stack in the layout); verify each page that references them with grep.
2. Report unused libraries under public/assets/libs (no references in views or JS) in STATUS; do not delete.
OUTPUT CONSTRAINT: Unified diff or component file only. Zero conversational text.
VERIFICATION: Visual check: pages that used Flatpickr/Choices still work after full load and AJAX navigation. php artisan view:cache && php artisan view:clear ; php artisan test --compact tests/Feature
```
Step 3 (owner decision D-F1 = Vite only): move `public/assets/js/admin/**` and the three first-party stylesheets into Vite entries, add `@vite` to the layouts, make sure `npm run build` runs on every deploy (CI already builds), and drop Tailwind from `vite.config.js` with approval.

Step 4 (optional, owner approval): Content-Security-Policy in report-only mode (`script-src 'self'` + the inline navigation-engine script hash). Possible only once the hygiene budget for inline scripts is at its floor.

## Final verification (orchestrator)
| # | Check | Pass condition |
|---|---|---|
| 1 | `php artisan test --compact` | 0 failed, 0 skipped, count >= F0 baseline + new tests |
| 2 | `php artisan view:cache && php artisan view:clear` | succeeds |
| 3 | `FrontendHygieneTest` | handlers 0, inline scripts at floor, `{!!` 0, a11y budgets 0 |
| 4 | `grep -rn "escapeHtml\|function esc" resources/views public/assets/js` | only `admin/core.js` |
| 5 | Screenshot comparison vs F0 set (light/dark, 1440/390) | no unintended differences |
| 6 | Navigation run: every sidebar entry via AJAX and full load, browser console | no errors, no duplicate pollers |
| 7 | Read-only Codex audit of `git diff <FE0_SHA>` | no inline handlers, no unescaped innerHTML, no PHP interpolated into JS, ids used by JS still present |
| 8 | Lighthouse (accessibility + best practices) on dashboard, POS, invoices | record scores; accessibility >= 90 |

## Close-out
- [ ] Update `docs/ai/REMEDIATION_EXECUTION_REPORT.md` with a frontend batch section and `CLAUDE.md` (UI section: components, Admin core, hygiene test).
- [ ] Mark every phase in `00_INDEX.md`.
