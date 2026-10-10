# Phase 0: Baseline Gate

Date 2026-09-30. This is an audit only: nothing in the repository was modified, reverted, staged or committed. The empirical checks ran from throwaway scripts in the session scratchpad against isolated SQLite `:memory:` databases (each script aborts unless the driver is sqlite `:memory:`) and against a `git archive` export of HEAD. The only reads against dev MySQL were `migrate:status` and SHOW/SELECT queries.
- Inputs read: master-audit.md, MASTER_REMEDIATION_PLAN.md, testing-audit.md, `git status`, `git diff`, and every untracked file listed below.
- CLAUDE.md does not exist (never written).

## 1. Git baseline
- Branch: `main`. HEAD: `45b9ccc` ("Display payroll register net from verified component totals", 2026-09-29 15:48 +0300). In sync with `origin/main` (git@github.com:Caesar32/Al-Husseini.git): nothing ahead or behind.
- Tags: none. BASELINE.md (untracked) claims a tag `baseline-finance-2026-09-30` was "created" (L3, L103). That claim is false; the tag does not exist.
- Stashes: none. Other branches: none.
- Working tree is dirty:
  - 23 tracked files modified (+1075 / −345 lines).
  - 11 untracked paths: 10 pre-existing plus docs/ai/ from this audit.
  - Git warns that app/Http/Controllers/Admin/DashboardController.php has CRLF line endings, which will be normalised to LF on the next git touch.
- Ignored but present (not part of the baseline; do not commit): .env, .env.production, id_rsa_deploy(.pub), database/database.sqlite (a stale Sep 21 file with none of the 2026_09_29/30 migrations applied), bootstrap/cache/*, storage/framework/views/*, public/build/, node_modules/, .claude/.
- Dev database (MySQL, per .env DB_CONNECTION=mysql):
  - `migrate:status` shows both untracked migrations 2026_09_30_000001 and 000002 as Ran (batch 1).
  - Live schema check: invoices.status is `enum('paid','partially_paid','unpaid','cancelled','refunded','partially_refunded')`. credit_ledger_entries.receipt_number has only `credit_ledger_entries_receipt_number_index` (non-unique).
  - The data is seed-sized: 9 invoices (8 paid, 1 unpaid).

## 2. Working-tree inventory
Legend: KEEP = intentional, coherent, required. REVIEW = needs an owner decision before it enters the baseline. REVERT = recommended to drop. "Loss risk" means the work is lost if the tree is reset, since none of it is committed or stashed.

### 2a. Finance / POS work (one coupled change set, the "FIN" set)
The tracked files in this set depend on the untracked ones: PosOrderService imports InvoiceFilter and ManagerOverrideService and reads config/finance.php. They must be committed together; committing only the tracked files would break the build.

| File | Change summary | Module | Finding / phase | Loss risk | Rec. |
|---|---|---|---|---|---|
| app/Services/Sales/PosOrderService.php | Filtering via InvoiceFilter; new getInvoiceStats; epsilon from config; discount+scrap ≤ gross guard; branch required (no `?? 1`); return flow splits credit/cash refund (negative InvoicePayment), sets partially_refunded, rejects cancelled; FIFO settlement writes per-invoice InvoicePayments; duplicate receipt check; aggregate-query cashier summary | Sales | Partial fixes of FINANCE_ERRORS_REPORT; BIZ-05 introduced (partially_refunded excluded wholesale); BIZ-03/04 still open; Phase 5 base | HIGH (286-line diff) | KEEP |
| app/Services/Sales/InvoiceFilter.php (untracked, 128 lines) | Single filter object: search incl. serial, status, branch, date range with order validation, stats exclusions | Sales | ARC-02 partial; Phase 5 | HIGH | KEEP |
| app/Services/Finance/ManagerOverrideService.php (untracked) | Override validation: config hash, then config code, then legacy codes `9999`/`mgr_override_99` always accepted, then a manager-password fallback when nothing is configured; IP rate limit | Finance | SEC-03. The backdoor predates this file: HEAD's StorePosInvoiceRequest already used `config('app.manager_override_code','9999')`. This file keeps it and adds `mgr_override_99` | HIGH | KEEP (fix in Phase 2) |
| config/finance.php (untracked) | epsilon, manager_override_code, manager_override_hash (null defaults). Its comment "No default fallback" contradicts ManagerOverrideService | Finance | SEC-03 | MEDIUM | KEEP |
| .env.example | Adds FINANCE_EPSILON, MANAGER_OVERRIDE_CODE, MANAGER_OVERRIDE_CODE_HASH (empty) | Config | SEC-03 | LOW | KEEP |
| app/Contracts/Sales/PosOrderServiceInterface.php | Adds getInvoiceStats() | Sales | Phase 5 | LOW | KEEP |
| app/Models/InvoicePayment.php | New scopes active() (excludes cancelled/refunded/partially_refunded) and cash() (method ≠ credit) | Sales | BIZ-05, ARC-02 | LOW | KEEP |
| app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php | Discount+scrap ≤ gross check; epsilon from config; override delegated to ManagerOverrideService | Sales | SEC-03 | MEDIUM | KEEP |
| app/Http/Controllers/Admin/SalesInvoiceController.php | index uses service filter and stats; catches the date-order ValidationException (422 or back); removes the duplicated stats query | Sales | ARC-02 | MEDIUM | KEEP |
| app/Http/Controllers/Admin/CreditCustomerController.php | statement(): paginated invoices (20, `invoices_page`) and ledgers (50, `ledgers_page`); JSON returns paginators | Credit | FIN pagination | MEDIUM | KEEP |
| app/Http/Controllers/Admin/DashboardController.php | Sales exclude refunded/partially_refunded; new cash revenue from InvoicePayment::active()->cash() by payment date; credit_collected from the ledger; 2-decimal formatting. CRLF file | Dashboard | BIZ-05 (wholesale exclusion), ARC-02 | MEDIUM | KEEP |
| resources/views/admin/dashboard/partials/kpi-cards.blade.php | KPI shows cash revenue as the main figure, invoice total and credit collected as secondary; label "إيرادات ومبيعات المركز" | Dashboard | goes with DashboardController | LOW | KEEP |
| resources/views/admin/dashboard/partials/scripts.blade.php | Period switcher updates revenue, sales and credit-collected; stops calling the mock loaders on sales-updated | Dashboard | FE-05 (mock loaders now dead) | LOW | KEEP |
| resources/views/admin/invoices/show.blade.php | Fixes wrong attribute names (serial_number → battery_serial_number, scrap_discount → scrap_deduction_amount, reference_number → transaction_reference, total_amount → final_amount, status keys); adds partially_refunded/cancelled badges | Sales | bug fixes | LOW | KEEP |
| resources/views/admin/credit/statement.blade.php | total_amount → final_amount; ledger entry_type keys aligned to the DB enum; pagination links | Credit | bug fixes | LOW | KEEP |
| resources/views/admin/sales/credit.blade.php | `window.serverCreditData` from the controller; KPIs and rows prefer server data; collected figure and payments tab still read the mock store | Credit | FE-04 (partial) | MEDIUM | KEEP |
| resources/views/admin/sales/invoices.blade.php | Keeps branch_id in the filter form; adds refunded/partially_refunded/cancelled status options | Sales | — | LOW | KEEP |
| database/migrations/2026_09_30_000001_add_partially_refunded_to_invoices_status.php (untracked) | Adds partially_refunded: MySQL `ALTER … ENUM`; SQLite edits sqlite_master and disables FK/CHECK enforcement (§7) | DB | DB-01, TST-01; Phase 1 | HIGH (already run on dev MySQL) | KEEP file, REVIEW body (Phase 1 rewrites the SQLite branch) |
| database/migrations/2026_09_30_000002_fix_receipt_number_unique.php (untracked) | Replaces the unique index on credit_ledger_entries.receipt_number with a plain index; errors swallowed | DB | DB-02 | HIGH (already run on dev MySQL) | KEEP |
| tests/Feature/Sales/FinanceRegressionTest.php (untracked, 279 lines, R-01..R-20) | Regression tests for the FIN set | Tests | TST-05 (single-return only) | HIGH | KEEP |
| tests/Feature/Sales/CreditAndInvoiceManagementTest.php | Replaces the non-existent `payment_status`/`invoice_type` keys with `status` | Tests | test fix | LOW | KEEP |
| tests/Feature/Sales/EndToEndSalesAndPurchasesScenarioTest.php | Same `payment_status` → `status` fix (sector 4) | Tests | test fix | LOW | KEEP |
| tests/Feature/Admin/RoleAndCashierTest.php | Adds `branch_id` to POS payloads (the service now requires a branch) | Tests | goes with the branch requirement | LOW | KEEP |
| tests/Feature/Sales/SalesAndPurchasesControllersTest.php | Same branch_id addition | Tests | same | LOW | KEEP |
| tests/Unit/Sales/Requests/SalesAndPurchasesRequestsTest.php | Adds branch_id / technician_id to request fixtures | Tests | same | LOW | KEEP |
| tests/Feature/Admin/DashboardSalesFilterTest.php | Label assertion updated to the new KPI text | Tests | goes with kpi-cards | LOW | KEEP |

### 2b. Payroll
| File | Change summary | Module | Finding / phase | Loss risk | Rec. |
|---|---|---|---|---|---|
| app/Services/Hr/PayrollService.php | assertPayrollTotalsConsistent adds total_overtime to the expected net (+1/−1 line). Without it, committed HEAD rejects every batch with overtime at approve/disburse | HR | BIZ-08 (server half); Phase 8 | HIGH (small but critical) | KEEP |

### 2c. Test weakening (masks a pre-existing failure)
| File | Change summary | Module | Finding / phase | Loss risk | Rec. |
|---|---|---|---|---|---|
| tests/Feature/Admin/SystemDiagnosticsTest.php | Drops `all_passed => true`; only the finance and search sectors must pass, with ≥7/8 overall; the CLI test changes from `system:diagnose` to `system:diagnose --audit`, so the simulation is no longer exercised via CLI. The comment calls payroll "may be flaky" | Diagnostics / HR | Masks a deterministic failure (see below) | LOW | REVIEW. Do not accept as-is into the baseline without a tracked issue |

- Evidence for 2c: runLiveSimulation (seeded with RolesAndPermissions, InitialData and SalesAndPosData, rollback=true) returns 7/8. The Payroll Lifecycle sector fails with "الصافي المتوقع: 6950 | المحسوب: 6800.00" (expected 6950, computed 6800).
- It fails identically on the working tree and on a `git archive HEAD` export, so the failure is pre-existing.
- Root cause (verified in the 2026-10-01 separation pass): the failure is calendar-dependent, and two real defects combine.
  - (a) SQLite month-window bug in PayrollService (app/Services/Hr/PayrollService.php L89 attendances and L110 deductions).
    - `whereBetween('work_date' / 'deduction_date', [Y-m-d, Y-m-d])` is used against `date`-cast columns.
    - Eloquent stores those as `Y-m-d 00:00:00` on SQLite (verified: `work_date` stored `2026-09-30 00:00:00`).
    - So every row dated the last day of the month is excluded, because `'2026-09-30 00:00:00' > '2026-09-30'` as strings.
    - The isolated run showed 25 counted attendance days instead of 26 (one absent day = 200) and the approved 50 late penalty excluded: 7000 − 200 = 6800.
    - MySQL DATE columns are not affected.
  - (b) The simulation itself (SystemDiagnosticService ~L640-650) creates attendance for days 1..25 minus today, plus today's punch. That yields 26 present days only when today ≥ 26, so on days 1-25 it also under-counts.
  - Net effect: the sector can pass only on days 26 to (last − 1) of a month. "Flaky" is literally true, but the weakening hides defect (a), a real SQLite date-boundary bug in payroll.
- Verified by snapshot run: HEAD's own strict SystemDiagnosticsTest fails at HEAD (2 failed, 152 passed on 2026-09-30). The earlier inference is now confirmed.

### 2d. Unrelated to the finance work
| File | Change summary | Module | Finding / phase | Loss risk | Rec. |
|---|---|---|---|---|---|
| resources/views/admin/layouts/partials/vendor-scripts.blade.php (+360, all additions) | New "Seamless Fullscreen Engine" on every page. In fullscreen it intercepts link clicks, GET-form submits, F5/Ctrl+R and back/forward; fetches pages and swaps `.main-content`; re-executes page scripts via indirect `eval` (~L417) after rewriting let/const; adds a top progress bar | Layout / UX | none. Related to ARC-01/FE-08 risk (script re-execution and double-binding, UNVERIFIED per page) | MEDIUM | REVIEW. Commit separately from FIN, or park on its own branch |

### 2e. Documentation (untracked)
| File | Change summary | Module | Finding / phase | Loss risk | Rec. |
|---|---|---|---|---|---|
| BASELINE.md | Finance baseline notes; contains the false tag claim | Docs | Phase 0 | LOW | REVIEW (correct the tag claim before committing) |
| FINANCE_MODULE_REMEDIATION_PLAN.md (1787 lines) | Earlier finance-only plan | Docs | Superseded for sequencing by MASTER_REMEDIATION_PLAN.md | LOW | KEEP (reference) |
| docs/FINANCE_ERRORS_REPORT.md, docs/POS_ERRORS_REPORT.md | Source issue reports (33 finance issues, POS issues) | Docs | inputs to FIN | LOW | KEEP |
| docs/ai/ (12 files) | This audit's documentation | Docs | all | MEDIUM | KEEP |

No file is recommended for REVERT. No incomplete or broken code was found: the suite is green and every untracked dependency of the modified files is present. Known remaining defects inside the FIN set (BIZ-03, BIZ-04, BIZ-05, SEC-03) are documented design gaps, not half-applied edits.

## 3. Changes that must be preserved
- The whole FIN set (§2a) as one unit, including the untracked InvoiceFilter.php, ManagerOverrideService.php, config/finance.php, both 2026_09_30 migrations and FinanceRegressionTest.php. Both migrations are recorded as Ran on dev MySQL, so losing the files would leave the dev database ahead of the code.
- PayrollService.php overtime line (§2b). Without it, payroll batches with overtime cannot be approved server-side.
- docs/ai/*, docs/FINANCE_ERRORS_REPORT.md, docs/POS_ERRORS_REPORT.md.
- Recommended safety step for the owner, not performed here: create a patch or branch backup of the dirty tree before any further work, e.g. commit to a `wip/baseline-2026-09-30` branch, or `git stash push --include-untracked` followed by an immediate `stash apply`.

## 4. Changes that need owner review
- R1: tests/Feature/Admin/SystemDiagnosticsTest.php weakening. Either accept it temporarily with a tracked issue ("payroll simulation expects 6950, gets 6800") or restore the strict assertion and let it fail until Phase 8 fixes the root cause. Accepting silently hides a real regression signal.
- R2: vendor-scripts.blade.php Seamless Fullscreen Engine. Decide whether it ships. If it ships, commit it separately. Its indirect eval of page scripts is a behaviour risk (double-bound handlers), not a verified defect.
- R3: BASELINE.md states a tag that does not exist. Correct it, or create the tag when the baseline commit is made.
- R4: migration 000001's SQLite branch (§7). It stays as-is for the baseline commit, but the owner should confirm that Phase 1 may edit it in place: it is untracked, has already run on dev MySQL, and the MySQL result would be unchanged.
- R5: behavioural choices inside FIN to acknowledge:
  - partially_refunded invoices are excluded wholesale from stats, dashboard and FIFO (BIZ-05).
  - The dashboard's headline figure now shows cash revenue by payment date instead of invoice totals.
  - POS now hard-fails when no branch can be resolved.
  - The legacy override codes are retained (SEC-03).
- R6: DashboardController.php CRLF line endings. Normalise them in the baseline commit to avoid noisy future diffs.

## 5. Unrelated changes
- Only vendor-scripts.blade.php (§2d) is unrelated to the finance/POS/payroll work. Everything else maps to FINANCE_ERRORS_REPORT, POS_ERRORS_REPORT or payroll BIZ-08.

## 6. Test baseline
- Command: `php artisan test` (composer `test` script = `config:clear` then `artisan test`). Environment from phpunit.xml: SQLite `:memory:`, array cache and session, sync queue.
- Result on the current working tree (2026-09-30): 168 passed, 686 assertions, 34.29 s, exit 0 (an earlier run in this session gave the same counts in 54.9 s).
- Caveats:
  - The green result is obtained with FK and CHECK enforcement disabled (§7).
  - SystemDiagnosticsTest has been relaxed (§2c).
  - HEAD's own suite was not executed. Its SystemDiagnosticsTest is inferred to fail.
  - The suite never exercises the browser POS payload (TST-02) or the SEC-02 routes (TST-03).

## 7. Constraint-test problem (DB-01 / TST-01), verified
- Where: database/migrations/2026_09_30_000001_add_partially_refunded_to_invoices_status.php `up()`, sqlite branch.
  - L20: `PRAGMA writable_schema=ON`, then a raw str_replace of `'refunded'` → `'refunded','partially_refunded'` in the invoices CREATE SQL stored in sqlite_master.
  - L26: `PRAGMA writable_schema=OFF`.
  - L30: `PRAGMA foreign_keys=OFF`.
  - L31: `PRAGMA ignore_check_constraints=ON`.
  - L32-33: the catch block runs `foreign_keys=off` and `ignore_check_constraints=ON` again, so the pragmas are applied even when the schema edit fails.
  - MySQL uses a real `ALTER TABLE … MODIFY status ENUM(...)` (no pragmas). pgsql and other drivers are a no-op.
- Empirical proof (scratch script, fresh sqlite `:memory:`):
  - Before migrating, `foreign_keys=1` and `ignore_check_constraints=0`. After an unrelated migration they are unchanged.
  - After all migrations they are `foreign_keys=0` and `ignore_check_constraints=1`.
  - Afterwards the database accepts an invoice_items row pointing to a non-existent invoice (FK violation) and `customers.tier='bogus'` (enum CHECK violation). The invoices CHECK does contain partially_refunded.
- Leak across the test connection: confirmed from framework source. For in-memory databases, vendor/laravel/framework/src/Illuminate/Foundation/Testing/RefreshDatabase.php migrates once per process (RefreshDatabaseState::$migrated, L83-90). It stores the migrated PDO in RefreshDatabaseState::$inMemoryConnections (L107, L141) and restores that same PDO for every later test (restoreInMemoryDatabase, L65-73). SQLite pragmas are per-connection, so the disabled state persists for every test in the run. 31 of 34 test files use RefreshDatabase.
- Scope: the leak is limited to the connection that ran the migration: the test process, or an `artisan migrate` process on a SQLite deployment. The sqlite_master edit itself is permanent in a file-based SQLite database. Dev MySQL is unaffected (verified schema in §1).
- Unknown until Phase 1: how many of the 168 tests fail once enforcement is restored. That is not measured here, because measuring it requires changing the migration or TestCase.

## 8. Exact prerequisites for Phase 1 (test-harness integrity)
- P1.1: the owner decides R1 to R6 (§4).
- P1.2: the FIN set, the PayrollService line and the docs are committed as the baseline on a branch, e.g. `chore/baseline-2026-09-30`, and tagged `baseline-2026-09-30`. vendor-scripts goes in a separate commit or is excluded per R2. `php artisan test` must be green on that commit, recorded as the reference count (currently 168/686).
- P1.3: owner confirmation (R4) that migration 000001 may be rewritten in place. Justification: dev MySQL has it recorded as Ran and the MySQL result is unchanged; no other environment is known to have run it (UNVERIFIED for production/staging; the owner must confirm).
- P1.4: a feature branch for Phase 1 created from the baseline tag.
- P1.5: acceptance that the Phase 1 fix will likely turn some currently green tests red. Each will be fixed or documented, with no assertion weakening.

## 9. Exact prerequisites for Phase 2 (security hardening)
- P2.1: Phase 1 merged. FK and CHECK enforcement is asserted in TestCase, and the suite is green with enforcement on.
- P2.2: decision D2, the manager override model (single configured hash or per-manager PIN with a recorded approver).
- P2.3: role grants confirmed for the newly enforced routes: which roles get suppliers.create/edit/delete and purchases.view/create (today accountant has purchases.view and suppliers.view, and nobody except super-admin has purchases.create).
- P2.4: deployment facts for SEC-01: the actual web-server config on production (does it execute `.php` under public/uploads?) and whether public/uploads/avatars exists there with user files that need migrating.
- P2.5: whether the seeders (InitialDataSeeder default passwords) were ever run in production (SEC-07), to decide on forced password resets.
- P2.6: plan how the test files that encode `9999` / `mgr_override_99` (RoleAndCashierTest L98, EndToEndSalesAndPurchasesScenarioTest L310) and SystemDiagnosticService (L463) switch to a configured hash in the same change set.

## Decision gate
PHASE 0 STATUS: BLOCKED

Unblock by completing P1.1 to P1.4:
- Owner decisions R1 to R6.
- A backed-up baseline commit on a branch, tagged, with the suite green.
- Confirmation that migration 000001 may be edited in place.

Nothing technical is broken, and nothing needs reverting. The block is that the baseline is uncommitted, includes one unrelated feature and one weakened test, and cannot be committed without owner approval.

## Commit Boundary Plan (executed 2026-10-01 on branch `remediation/2026-10`)
- Backup before any git write: `refs/backup/wip-2026-10-01` holds a stash commit of all tracked changes. The untracked files were archived to the session scratchpad.
- Tag `baseline-finance-2026-09-30` was created on 45b9ccc, which makes the BASELINE.md claim true (R3 resolved).
- Snapshot verification: `git archive HEAD` plus the selected files, full suite run on isolated SQLite.
  - HEAD: 2 failed / 152 passed.
  - HEAD+PAY: 2 / 152.
  - HEAD+FIN: 2 / 166.
  - HEAD+FIN untracked files only: 11 failed, which proves the FIN tracked and untracked files must be committed together.
  - HEAD+FIN+PAY: 2 / 166.
  - The 2 failures in every run are the strict SystemDiagnosticsTest cases (month-end root cause in §2c).

| # | Commit | Files | Purpose | Dependencies | Risk | Verification |
|---|---|---|---|---|---|---|
| 1 | 6a422d1 Finance/POS: unified invoice filter, FIFO settlement, partial refunds | §2a (26 files) | Preserve the FIN work as one unit | the untracked FIN files | MEDIUM (the migrations already ran on dev MySQL) | snapshot HEAD+FIN |
| 2 | f258584 Payroll: include overtime in totals consistency check | PayrollService.php | BIZ-08 server half | none | LOW | snapshot HEAD+PAY |
| 3 | 3f6428c UI: seamless fullscreen navigation (pre-existing, needs review) | vendor-scripts.blade.php | Isolate the unrelated feature | none (self-contained; no references elsewhere) | MEDIUM (indirect eval; review item) | full-tree suite (168 green) |
| 4 | docs commit | docs/ai/*, docs/*_REPORT.md, BASELINE.md, FINANCE_MODULE_REMEDIATION_PLAN.md | Audit trail | none | LOW | n/a |
| — | NOT committed | tests/Feature/Admin/SystemDiagnosticsTest.php (weakened) | Replaced in Phase 1 by root-cause fixes plus restored strict assertions; the original weakened version is kept in refs/backup/wip-2026-10-01 | Phase 1 | — | Phase 1 suite |

- Documentation corrections required:
  - config/finance.php says "No default fallback" and "will be hashed on first use". Both are false while ManagerOverrideService accepts legacy codes and compares the plain code with hash_equals. Fixed in the security batch (SEC-03).
  - docs/QA_TESTER_MANUAL.md L101 and L175 present `mgr_override_99` / `9999` as official test codes. Update this with SEC-03.
  - docs/FINANCE_ERRORS_REPORT.md and docs/POS_ERRORS_REPORT.md cite `PosOrderService.php:568`, but that code has moved to ManagerOverrideService. These are historical reports: annotate them, do not rewrite them.

BASELINE COMMIT PLAN: READY (executed)
