# Phase 1. POS UI and RTL layout

Lead: Claude (UI/UX). Registry IDs: UI-01, UI-02, UI-03, UI-04. Files: `resources/views/admin/sales/pos/partials/*`, `public/assets/js/admin/pos/pos.js`.

## 1. Already done (no work)

| Item | Where |
|---|---|
| No rigid grid width. The header is a wrapping flexbox: customer `flex: 3 1 400px`, vehicle and technician `flex: 1 1 165px`, badges pushed to the end. | `styles.blade.php:32-52` |
| Container queries on the catalog panel: 900px (shortcut words hide), 600px (customer takes a full row, badges hide), 420px (everything stacks, new customer button full width). | `styles.blade.php:304-358` |
| A long cashier name is cut at 130px with an ellipsis. | `styles.blade.php:190-195` |
| Category ribbon cannot widen the page: `grid-template-columns: minmax(0, 1fr)`. | `styles.blade.php:200-202` |
| List view is the default; the view toggle still works; more rows load from a sentinel with `IntersectionObserver`. | `search-and-categories.blade.php:21`, `pos.js:724-725` |

These were read in the source and are covered by `tests/js/pos.test.mjs:473-560`. They were **not** seen in a browser in this audit. `CLAUDE.md` already lists the manual browser check as pending; section 4 is that check.

## 2. Defects to fix

### UI-01. Ribbon arrows point the wrong way in RTL (Medium, confirm in browser)

The page is `dir="rtl"`, so the first button in the row is on the right.

| Position | Today | Wanted |
|---|---|---|
| Right | icon `‹`, moves the ribbon right (back to the start), id `catScrollLeftBtn` | icon `›`, back |
| Left | icon `›`, moves the ribbon left (forward), id `catScrollRightBtn` | icon `‹`, forward |

The movement and the titles are right. Only the icons (and the misleading ids) are swapped. No rule that mirrors these icons in RTL was found in `public/assets/css/app-rtl.min.css`.

```diff
--- resources/views/admin/sales/pos/partials/search-and-categories.blade.php
@@ -28,3 +28,3 @@
-        <button ... id="catScrollLeftBtn" data-action="pos.ribbon" data-direction="1" title="للخلف" ...>
-            <i class="ri-arrow-left-s-line"></i>
+        <button ... id="catScrollBackBtn" data-action="pos.ribbon" data-direction="1" title="للخلف" ...>
+            <i class="ri-arrow-right-s-line"></i>
@@ -44,3 +44,3 @@
-        <button ... id="catScrollRightBtn" data-action="pos.ribbon" data-direction="-1" title="للأمام" ...>
-            <i class="ri-arrow-right-s-line"></i>
+        <button ... id="catScrollForwardBtn" data-action="pos.ribbon" data-direction="-1" title="للأمام" ...>
+            <i class="ri-arrow-left-s-line"></i>
```

Follow-up in the same commit: the two ids in `pos.js:315-316`, and the comment at `pos.js:306` ("‹ always pans physically left"), which is false today.

### UI-02. Mouse wheel does nothing at the start of the ribbon (Medium, confirm in browser)

In RTL, current Chrome, Firefox and Safari report `scrollLeft` as 0 at the start and negative as the ribbon moves forward. `pos.js:262` adds 120 on wheel down, which is past the start, so nothing moves until the cashier wheels up.

```diff
--- public/assets/js/admin/pos/pos.js
@@ -260,3 +260,4 @@
                 e.preventDefault();
-                ribbon.scrollLeft += (e.deltaY > 0 ? 120 : -120);
+                const forward = getComputedStyle(ribbon).direction === 'rtl' ? -1 : 1;
+                ribbon.scrollLeft += forward * (e.deltaY > 0 ? 120 : -120);
```

### UI-03. Arrows stay active at the two ends (Low)

`updateCategoryNavArrows` (`pos.js:314-322`) only hides both arrows when nothing overflows. Add a disabled state at each end:

```diff
@@ -320,2 +320,5 @@
             leftBtn.classList.toggle('d-none', !hasOverflow);
             rightBtn.classList.toggle('d-none', !hasOverflow);
+            const pos = Math.abs(ribbon.scrollLeft);
+            backBtn.disabled = pos < 1;
+            forwardBtn.disabled = pos >= ribbon.scrollWidth - ribbon.clientWidth - 1;
```

(`leftBtn` / `rightBtn` take the new names from UI-01.)

### UI-04. Technician select appears twice (Low, owner decision)

The select is in the header (`header-bar.blade.php:25-33`) and again in the cart with a red "إجباري" badge (`cart-panel.blade.php:139-151`). They are synced by `pos.sync-tech`.

Recommendation: keep the header select, and replace the cart block with one line that shows the chosen technician, or a red "اختر الفني" link that focuses the header select. This returns about 50px to the cart list on short screens. The submit check at `pos.js:1654` stays. Do not start this without the owner's answer.

## 3. Rules for the changes

- No new inline `style=` and no new `<style>` block. The budgets are full (see Phase 4).
- No inline handlers: use `data-action` and the `Admin.page.register` action map (`pos.js:1908`).
- `scripts.blade.php` must stay in `@section('script')`.
- Run `node --test tests/js/*.test.mjs` and `php artisan test tests/Architecture --compact` after each change.

## 4. Browser check (closes the pending manual check)

The DOM stub cannot see layout, script order or scroll direction, so these need a person or a browser session. Check in Chrome at 100% zoom, light and dark.

| # | Width | Check | Pass when |
|---|---|---|---|
| 1 | 1920, 1366, 1280 | Header row | One row; no horizontal page scroll; badges inside the card |
| 2 | 1024, 992 | Header row | Wraps to two rows; nothing clipped |
| 3 | 768, 390 | Header and search | Customer on its own row; new customer button full width at 390 |
| 4 | Any | Cashier account with a 40-letter name | Name is cut with "…"; "قارئ نشط" stays inside |
| 5 | 1366 | Ribbon, right arrow | Shows `›`, moves back to "الكل" |
| 6 | 1366 | Ribbon, left arrow | Shows `‹`, moves forward |
| 7 | 1366 | Mouse wheel down on a fresh ribbon | Ribbon moves forward |
| 8 | 1366 | Drag the ribbon, release over a pill | Category does not change |
| 9 | Any | Open the page | List view is active; scrolling to the end loads more rows with no duplicates |
| 10 | Any | Browser console | No `Admin is not defined`, no errors |
| 11 | 1366 × 768 | Cart with 6 lines and trade-in open | Submit button visible without scrolling the page |

## 5. Done when

- UI-01 and UI-02 are confirmed or dismissed in the browser, and fixed if confirmed.
- The 11 checks pass and the result is written into `docs/ai/REMEDIATION_EXECUTION_REPORT.md`.
- JS tests and the architecture suite pass.
