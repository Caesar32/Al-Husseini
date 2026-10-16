# Refactor & Bug-Fix Roadmap: Index and Progress Tracker

Base: `main` @ `4de75b5` (2026-10-16). Architect: Claude. Executor: Codex.

This roadmap continues `docs/ai/REMEDIATION_EXECUTION_REPORT.md`. It covers the code added after the remediation, which was never audited, and the open Phase 10 items (ARC-04, ARC-06, DB-03, DB-04, FE-08).

Each phase file is self-contained: rules, context, contracts, Codex prompts and acceptance criteria. Feed one phase file to Codex at a time, one prompt block at a time.

## Progress
| Phase | File | Theme | Key target files | Depends on | Status |
|---|---|---|---|---|---|
| 0 | [00_phase_baseline.md](docs/refactor/00_phase_baseline.md) | Clean tree, test baseline, HR edits decision | `app/Services/Hr/EmployeeService.php`, `resources/views/admin/hr/partials/employees/modal-form.blade.php` | none | [x] baseline done (HR decision pending owner) |
| 1 | [01_phase_security_and_correctness.md](docs/refactor/01_phase_security_and_correctness.md) | B1 owner token revocation, B2/B3 XSS, B4/B5 push job, B6 tests | `routes/api.php`, `bootstrap/app.php`, `app/Http/Middleware/EnsureOwnerTokenIsValid.php` (new), `resources/views/admin/purchases/create.blade.php`, `resources/views/admin/warranties/index.blade.php`, `app/Jobs/DispatchOwnerPushNotification.php` | 0 | [ ] |
| 2 | [02_phase_stock_movement_journal.md](docs/refactor/02_phase_stock_movement_journal.md) | Single stock writer + `stock_movements` | `app/Services/Catalog/StockLedgerService.php` (new), `PosOrderService.php`, `WarrantyService.php`, `PurchaseService.php`, `ImportStockCommand.php` | 1 | [x] `4671566`..`dc565ca`; dev MySQL `php artisan migrate` pending (owner) |
| 3 | [03_phase_shared_dashboard_metrics.md](docs/refactor/03_phase_shared_dashboard_metrics.md) | One metrics service; 7-day trend from 21 queries to 1 | `Admin/DashboardController.php`, `Api/V1/Owner/DashboardController.php`, `app/Services/Dashboard/DashboardMetricsService.php` (new) | 1 | [ ] |
| 4 | [04_phase_split_pos_order_service.md](docs/refactor/04_phase_split_pos_order_service.md) | Extract returns and debt collection; DI instead of `app()` | `PosOrderService.php`, `SalesReturnService.php` (new), `CreditCollectionService.php`, `StorePosInvoiceRequest.php`, `CreditCustomerController.php` | 2 | [ ] |
| 5 | [05_phase_hardcoded_urls.md](docs/refactor/05_phase_hardcoded_urls.md) | JS `/admin/...` URLs to `route()` | `admin-notifications.js`, `global-spotlight-search.js`, HR `scripts.blade.php` partials, `warranties/index.blade.php` | 1 | [ ] |
| 6 | [06_phase_data_model_fixes.md](docs/refactor/06_phase_data_model_fixes.md) | `scrapBatteries` / `technicianCommissions` become `hasMany`; drop 7 redundant indexes | `app/Models/Invoice.php`, 5 readers, new migration | 2, 4 | [ ] |
| 7 | [07_phase_final_verification.md](docs/refactor/07_phase_final_verification.md) | Suite, Pint, routes, diagnostics, smoke test, docs | none (verification only) | 0-6 | [ ] |

Phases 3 and 5 depend only on Phase 1, so they can run in parallel with Phases 2 and 4.

## Recorded values
| Key | Value |
|---|---|
| `PHASE0_SHA` | `0fa6996` |
| HR edits decision (Phase 0) | Pending owner (A/B/C). The two edited files stay uncommitted and are excluded from every refactor commit. |
| `N0` (baseline passed count) | 411 passed, 0 failed, 0 skipped (3020 assertions), at `4de75b5` plus the uncommitted HR edits |
| `N1` | Targeted only (owner protocol: full suite at Phases 0 and 7). Phase 1 added 14 tests (OwnerAuth 4, PurchaseCreateXss 1, Warranty 1, OwnerPush 4, EmployeeNationalId 4); expected full count 425; all targeted suites green |
| `N2` | Targeted only. Phase 2 added 17 tests (StockLedger 4, POS 1, Warranty 2, Purchases 1, Import 1, Diagnostics 1, Architecture 8 incl. 7 dataset cases); Sales, Warranty, Purchases, Admin, Catalog, Architecture all green |
| `N3` .. `N6` | |
| Admin dashboard query count before / after (Phase 3) | |
| Phase 6 index names confirmed (SQLite / MySQL) | |

## Findings during execution
- Phase 1 (B3): `resources/views/admin/warranties/index.blade.php` put its script in `@section('scripts')` nested inside `content`. The layout yields only `script` and `@stack('scripts')`, so the verify box and claim modal never ran. Fixed in `7af4719`.
- Same defect, not fixed (outside the roadmap's files): `resources/views/admin/scrap/index.blade.php:279` (`@section('scripts')` nested in `content`, page JS never rendered). Owner decision: fix it the same way.

- Phase 2: `ImportLegacyDataCommand.php:660` uses `updateOrCreate` with `'current_stock' => 0`, so re-running the legacy import resets the stock of existing products without a journal row. Not matched by the single-writer pattern (not `update([`). Owner decision.
- Phase 2: `stock_movements` has no history before the migration; the diagnostic only compares products that have movements.

## Global rules (repeated in every phase file)
- Never weaken, skip or delete tests. Never disable SQLite FK/CHECK enforcement. Every bug fix gets a regression test.
- Architecture: route `can:` -> FormRequest -> thin controller -> service interface -> Eloquent. No Policies, Events or Actions.
- Don't delete files without owner approval. Never use `git stash`. Run Pint only on touched files. Don't commit or push unless asked.
- Never run migrations or write data on dev or production MySQL.

## Out of scope (owner decisions; do not implement)
- Refund proration (D3), technician commissions (D4), automatic end of leave (D5), scrap treasury (D6) and locale (D7).
- Purchase-return permission, supplier catalog sync, replacement-warranty period, supplier credit limit and resale of returned serials.
- Unwired settings and role grants.
- The canonical low-stock definition. Phase 3 preserves both current definitions.
- A real FCM/APNs client: the push job stays a logging stub until credentials exist.
- Deleting the dead files and removing the Vite pipeline.
- Production data repair (D1).

## Running a step
New paths named in the phase files (services, interfaces, migrations, tests) are created by that phase; they don't exist yet. Commands are in Git Bash syntax.
```bash
# edits
codex exec -m <model> --config model_reasoning_effort="high" --sandbox workspace-write --full-auto --skip-git-repo-check "<one prompt block>" </dev/null 2>/dev/null
# read-only audit (Phase 7)
codex exec -m <model> --config model_reasoning_effort="high" --sandbox read-only --skip-git-repo-check "<prompt block>" </dev/null 2>/dev/null
```
After each step the orchestrator reviews the diff and runs the step's verification command. A phase is checked off only when every acceptance criterion in its file is met.
