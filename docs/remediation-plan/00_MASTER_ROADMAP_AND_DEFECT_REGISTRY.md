# 00. Master roadmap and defect registry

Date: 2026-11-10. Commit audited: `0254f47` (`main`). Scope: POS screen, oil by the liter, POS queries, test harness, CI and deploy.

Method: a read-only scan by Codex (gpt-5.6-terra, high) plus a separate manual read by Claude. Every row in the registry was checked against the file by at least one of the two; rows that need a real browser to confirm are marked **browser**. Nothing in the code was changed.

## 1. Important: what the brief asked for that is already done

Several items in the request describe the screen as it was before commits `2e87a5b` and `0254f47`. They need no work:

| Requested item | State today | Evidence |
|---|---|---|
| Remove the rigid `minmax(420px, ...)` | Not in the file any more. The only grid is `minmax(0, 1fr)`. | `resources/views/admin/sales/pos/partials/styles.blade.php:202` |
| Fluid header (customer, new customer button, vehicle, technician) | Done: wrapping flexbox with container queries at 900, 600 and 420px. | `styles.blade.php:32-52`, `:304-358` |
| Badges inside the card | Done: cashier name is cut at 130px, badges hide under a 600px panel. | `styles.blade.php:174-195`, `:321` |
| List view as default, infinite scroll | Done and tested. | `search-and-categories.blade.php:21`, `tests/js/pos.test.mjs:473-560` |
| Oil by the liter: decimal input, presets, liters plus packages, receipt line | Done and tested. | See `02_PHASE_2_BULK_OIL_DUAL_UNIT_ENGINE.md` |
| Indexes for barcode and phone | Already exist (unique). | `2026_09_21_160007_...:19-20`, `2026_09_21_160006_...:12` |
| N+1 in the POS bootstrap | None found. Relations are eager loaded, counts use `withCount` and a 300s cache. | `app/Http/Controllers/Admin/PosController.php:58-75`, `:311-326` |

## 2. Health index

**79 / 100.** This is a judgment, not a measurement. Start at 100, subtract 3 for each open High and 1 for each open Medium (5 High, 6 Medium). No Critical defect was found.

| Area | State | Basis |
|---|---|---|
| Application code (POS, oil, checkout) | Good | One High (vehicle ownership), two Medium UI items that need a browser to confirm |
| Tests | Good | 802/802 PHP and 79/79 JS pass (run 2026-11-10); one test asserts the wrong thing |
| CI and deploy | Weak | 3 High, 3 Medium: deploy does not wait for CI, has no lock, no health check, no rollback |

## 3. Unified defect registry

| ID | Sev. | Location | Root cause | Phase |
|---|---|---|---|---|
| INT-01 | High | `app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php:29`; used at `app/Services/Sales/PosOrderService.php:321`, `:420` | `customer_vehicle_id` is checked only for existence, not that it belongs to `customer_id`. A crafted request attaches another customer's vehicle to the invoice and the warranty. | 3 |
| CI-01 | High | `.github/workflows/ci.yml:73` | CI runs `php artisan test` only. The 79 JS tests (oil presets, combobox, list view, infinite scroll) never run in CI. | 4 |
| DEP-01 | High | `.github/workflows/deploy.yml:7` | A push to the merged branch `remediation/2026-10` deploys that branch to production. CI does not run for it. | 5 |
| DEP-02 | High | `.github/workflows/deploy.yml:3-7` | Deploy starts on `push` and does not wait for CI. A failing commit reaches production. | 5 |
| DEP-03 | High | `.github/workflows/deploy.yml:13-15` | No `concurrency` lock. Two pushes can run `git reset --hard` and `migrate` at the same time. | 5 |
| UI-01 | Medium, **browser** | `resources/views/admin/sales/pos/partials/search-and-categories.blade.php:28-29`, `:44-45`; comment at `public/assets/js/admin/pos/pos.js:306` | In RTL the first button sits on the right. It shows `‹`, moves the ribbon right (back) and is named `catScrollLeftBtn`. The icon points away from the movement. | 1 |
| UI-02 | Medium, **browser** | `public/assets/js/admin/pos/pos.js:262` | Wheel down adds to `scrollLeft`. In RTL the start position is the maximum, so wheel down does nothing on a fresh ribbon. The code comment says this was never checked in a browser. | 1 |
| TST-01 | Medium | `tests/Feature/Sales/PosScreenDataTest.php:31-35` | The test expects every active product in the first paint (the controller sends 36) and filters customers with `phone != '00000000000'`, which drops NULL-phone customers. The small seed hides both. | 4 |
| DEP-04 | Medium | `.github/workflows/deploy.yml:44-56` | No health check after `artisan up`, no recorded previous commit, no database backup before `migrate --force`. | 5 |
| DEP-05 | Medium | `.github/workflows/deploy.yml:44` | On any error the trap runs `php artisan up`. After a failed migration the site comes back on new code with a half-applied schema. | 5 |
| DEP-06 | Medium | `.github/workflows/deploy.yml:38` | `StrictHostKeyChecking=no`: the runner does not verify the server it sends commands to. | 5 |
| UI-03 | Low | `public/assets/js/admin/pos/pos.js:314-322` | Both arrows show whenever the ribbon overflows, also at the two ends where one of them does nothing. | 1 |
| UI-04 | Low | `header-bar.blade.php:25-33`, `cart-panel.blade.php:139-151` | The technician select exists twice (header and cart) and is kept in sync. It costs a row in the cart on short screens. Design choice for the owner. | 1 |
| PERF-01 | Low | `app/Http/Controllers/Admin/PosController.php:58-75` | Every active customer and vehicle is embedded in the page. Fine today; grows with the customer list. | 3 |
| PERF-02 | Low | `PosController.php:134`, `:138-141`, `:148` | Search uses `%term%` on four columns (cannot use an index), a `whereHas` for the category, and a count query per scrolled page. Fine at 7,340 products. | 3 |
| PERF-03 | Low | `tests/Feature/Seeders/` (untracked) | CI runs fewer tests than the local 802 because this folder is not in git. | 4 |

Not defects, recorded so they are not reported again:

- `allows_fraction` is not a column. It is derived in `PosController::mapPosProduct()` (`:252`) from `Product::allowsFraction()` and the `ProductUnit` enum. Do not add a column for it.
- The price per liter is stored (`retail_price` of a liter product), not computed at sale time from a package price. This is the decision recorded in `CLAUDE.md`.

## 4. Phase order

```
Phase 5a (DEP-01, DEP-02, DEP-03)  ── must go first: every later push deploys
        │
        ├── Phase 3 (INT-01)        ── independent, small, High
        ├── Phase 1 (UI-01..04)     ── needs a browser session
        └── Phase 4 (CI-01, TST-01) ── CI-01 before any further POS JS change
                 │
Phase 2 (oil)    │                  ── code done; owner actions only
                 ▼
        Phase 5b (DEP-04..06)       ── health check, rollback, host key
```

Why 5a first: with the pipeline as it is, each fix below is pushed to production without a test gate. DEP-01 and DEP-03 are one-line changes.

## 5. Decisions the owner has to make

| # | Question | Needed for |
|---|---|---|
| 1 | Keep the technician select in both places, or only in the header? | UI-04 |
| 2 | Has `products:convert-oils-to-liters` been run on production, and for which products? | Phase 2 |
| 3 | Is a short maintenance window on each deploy acceptable, or is real zero downtime wanted (needs release folders and a symlink on Hostinger)? | Phase 5 |
| 4 | May the deploy take a `mysqldump` before each migration (disk space on the host)? | DEP-04 |
| 5 | Should `tests/Feature/Seeders/` and the demo seeders be committed? | PERF-03 |
