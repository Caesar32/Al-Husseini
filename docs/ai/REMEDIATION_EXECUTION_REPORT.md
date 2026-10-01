# Remediation Execution Report

Branch `remediation/2026-10` (from `main` 45b9ccc). The plan is in MASTER_REMEDIATION_PLAN.md, findings are in master-audit.md, and the baseline is in PHASE_0_BASELINE.md. This file records execution; it does not repeat those documents.

## Business decisions (classification per orchestration rules)
- A = safe default exists in current behaviour; B = derivable from documented rules or data invariants; C = owner decision needed.
- Only tasks marked C are blocked; everything else proceeds.

| ID | Decision | Class | Evidence / resolution |
|---|---|---|---|
| D1 | Does production hold UI-created data (repair scope)? | C | Unknown externally. Repair commands are built dry-run only; executing them is BLOCKED |
| D2 | Manager override model | B | FINANCE_MODULE_REMEDIATION_PLAN.md B-07 decided "single hash, constant-time, no 9999 default". Implemented as: configured hash or code only, legacy codes removed, fail closed. Per-manager approver recording (option B) is not decided, so it is not built |
| D3 | Refund valuation (proration of discount/scrap/tax) | C | Only the invariant is enforced (cumulative refunds ≤ amount received, returned qty ≤ sold qty). Proration is BLOCKED |
| D4 | Technician commission rules | C | BIZ-19 is BLOCKED |
| D5 | Leave semantics | B, partial | Payroll already computes paid and unpaid leave days, so employees on leave are meant to be paid: on_leave employees are included in payroll. Automatic status reversion at leave end is C (BLOCKED) |
| D6 | Scrap sale accounting | B, partial | The sale form already collects buyer, method and amount, so they are persisted (scrap_sales). Linking to a cash or treasury ledger is C (none exists) |
| D7 | Locale: translations or remove the switch | C | FE-07 BLOCKED |
| D8 | Commit the working tree | resolved | Phase 0 commits (see PHASE_0_BASELINE.md) |
| P-grants | Role grants for newly enforced supplier/purchase permissions | A | The existing permission catalogue is enforced as defined; grants can be changed without code via the Roles UI. Documented, not invented |

## Dependency graph (status at batch start)
- Phase 0 baseline → COMPLETE (commits 6a422d1, f258584, 3f6428c, a4471ac).
- Phase 1 test integrity → COMPLETE (f959795). It gates everything below.
- Shared document sequences → COMPLETE (3e4dc19). Needed by BIZ-12a (Phase 5) and BIZ-12b (Phase 7).
- Security (SEC-01..07, 09..13) → READY. Owner: orchestrator (touches shared files: routes, bootstrap, auth controllers).
- Phase 3 catalog backend (FE-02) → READY (independent module). It blocks Phase 4.
- Phase 5 sales/returns/credit (BIZ-03, 05, 12a, 17 partial; ARC-02; POS idempotency backend) → READY. BIZ-04 proration is BLOCKED (D3).
- Phase 6 + 7 purchases ledger, warranty, scrap (BIZ-06, 07, 10, 11, 12b, 13, 20, 21) → READY, as one owner because both write Supplier.current_balance and the supplier ledger. BIZ-18 route exposure is BLOCKED (no return permission exists in the catalogue).
- Phase 8 HR/payroll (BIZ-08, 09 partial, 14, 15, 22 partial; FE-03, FE-06) → READY. BIZ-19 is BLOCKED (D4).
- Phase 4 POS/credit UI on real data (FE-01, FE-04) → BLOCKED on Phase 3 (and on Phase 5 for the idempotency key).
- Phase 9 branch isolation and settings (SEC-08, BIZ-16) → BLOCKED on Phases 3-8 (touches every controller). Locale is BLOCKED (D7).
- Phase 10 cleanup (ARC-03/04/06, FE-05/08, DB-02/03/04) → BLOCKED on Phases 3, 4 and 8.
- Diagnostics updates (SystemDiagnosticService checks for new invariants) → serialized at integration (a single owner avoids conflicts).

## Batch log
(appended per batch below)

### Batch 0: baseline (COMPLETE)
- Commits: 6a422d1 (Finance/POS), f258584 (payroll overtime), 3f6428c (fullscreen UI, isolated, needs review), a4471ac (docs). Backup ref `refs/backup/wip-2026-10-01`; tag `baseline-finance-2026-09-30` on 45b9ccc.

### Batch 1: test integrity gate (COMPLETE)
- f959795.
  - SQLite migration 000001 no longer disables FK or CHECK enforcement.
  - TestCase asserts enforcement on every test.
  - Payroll and dashboard month-end/today date bugs fixed.
  - Diagnostics payroll simulation made calendar-independent.
  - The strict SystemDiagnosticsTest is restored.
- Tests: 169 / 1020, up from 168 / 686 with constraints off.

### Batch 2: shared infrastructure + security (COMPLETE except SEC-08)
- 3e4dc19: DocumentNumberService and the document_sequences table.
- 504860d: SEC-02 (supplier/purchase permissions) and SEC-13 (dead routes).
- 21446d3: SEC-03 (override backdoor removed, fail closed; deployments must set MANAGER_OVERRIDE_CODE_HASH).
- 2e80d1d: SEC-01, reclassified HIGH. The php RCE path was a false positive (Laravel blocks php extensions); the remaining issue was that other extensions were stored in the public web root. Avatars are now stored privately.
- 73ec0e5: SEC-04, 05, 06, 07, 09, 10 and 12, plus the new finding SEC-14 (the login form prefilled admin credentials).
- ba1b5d9: POS idempotency keys scoped to the cashier.
- 6d0594a: diagnostics customer/supplier ledger chain checks fixed.

### Batch 3: parallel module work (merged: Phases 3, 5, 6+7; Phase 8 running)
- bce8faf: Phase 3 merged (catalog backend, FE-02). Report: batch-phase3.md.
- 4ade658: Phase 5 merged (returns, revenue, credit, invoice numbering, POS idempotency backend). Report: batch-phase5.md.
  - Accepted deviation: settlement amounts that cannot be allocated to invoices are kept as opening-balance collections. Seeded opening balances have no invoices; this is existing behaviour (A).
- 0503eb4: Phases 6+7 merged (supplier ledger posting and repair command, payment allocation, warranty settlement state machine, claim fields, scrap_sales). Report: batch-phase67.md.
- Phase 8 (HR/payroll/reports): agent still running on branch batch/phase8-hr. Not merged.
- Phase 4 (POS/credit UI on real data): agent started on branch batch/phase4-pos from 0503eb4. Not merged.

### Batch 4: Phase 8 merge and follow-ups (COMPLETE)
- 414bda9: Phase 8 merged (payroll consistency single source, payroll_show view, stored-value payslip, on_leave included in payroll, attendance observer/notification fixes, deductions marked applied, HR reports from the DB). Report: batch-phase8.md.
- 1ec18f5: diagnostics payroll check uses PayrollService::evaluateConsistency().
- 37e2530: dead mock-store loaders removed from the dashboard script (FE-05). The unused invoice-modal partial was NOT deleted (file deletion needs owner approval).
- ca3b08c: settings wired where the seeded value equals current behaviour (monthly_working_days, daily_working_hours, overtime_rate_multiplier, warranty_months_default, invoice_prefix); Setting::get no longer caches defaults.
  - Not wired, owner decision: vat_percentage (14 vs 0 today), session_timeout_minutes (120 vs 1440 today), allow_negative_stock (unsigned stock column on MySQL), scrap_prefix.
- ff3df99: migration 000002 made idempotent with a working down() (DB-02). A full rollback and re-migrate of all 10 remediation migrations on isolated SQLite passes (integrity_check ok, foreign_key_check clean).
- Tests: 275 passed / 1675 assertions.

### Batch 5: Phase 4 merge, mock stores, Phase 9 (COMPLETE)
- 1cc1a4d: Phase 4 merged (POS and credit screens on server data, collections endpoint, stock check per product across lines, one battery unit per line). Report: batch-phase4.md.
- 4c239d6: sales-store.js and hr-store.js are no longer loaded on any page. A test renders 11 admin pages and asserts none references them. The two .js files remain on disk (deletion needs approval).
- 86077c4: SEC-08 branch isolation (BranchScope + BelongsToBranch on 7 models; WithinUserBranch rule on 5 requests; BRANCH_ISOLATION flag).
- c29f94e: last employee-id-1 fallback removed from POS scrap recording.

# Execution Summary
- Branch `remediation/2026-10`, 43 commits since the baseline tag (176 files, +14555 / -3179) before this report.
- Batches executed: 0 baseline, 1 test integrity, 2 shared infrastructure and security, 3 parallel modules (Phases 3, 5, 6+7), 4 Phase 8 and follow-ups, 5 Phase 4 and Phase 9. Four module phases ran as parallel agents in isolated worktrees and were merged one at a time with the full suite run after each merge.
- Phases COMPLETE: 0, 1, 3, 4, 5, 6+7, 8. Phase 9 is complete for branch isolation (SEC-08) and the settings that can be wired without changing behaviour.
- Phases PARTIAL: 9 (settings that need an owner decision, locale), 10 (cleanup, see Remaining Work).
- BLOCKED parts: see Remaining Blockers.

# Batch Results (tests before -> after)
| Batch | Tests passed | Failed | Notes |
|---|---|---|---|
| HEAD 45b9ccc (strict tests) | 152 | 2 | the two failures were the month-end payroll simulation; pre-existing |
| Baseline working tree (constraints silently off) | 168 | 0 | weakened diagnostics test, constraints disabled by migration |
| 1 test integrity | 169 | 0 | constraints enforced; month-end date bugs fixed; strict test restored |
| 2 security | 198 | 0 | |
| 3 merges (3, 5, 6+7) | 250 | 0 | |
| 4 Phase 8 and follow-ups | 275 | 0 | |
| 5 Phase 4, mock stores, branch isolation | 310 (1889 assertions) | 0 | 0 skipped |
- No test was skipped. The only test assertions removed since the baseline are the dashboard label rename, the replacement of the legacy override codes by a configured secret, and the invalid `payment_status`/`invoice_type` columns (all documented in their commits).
- Failures found and resolved on the way: payroll last-day-of-month window (SQLite), simulation only passing on days 26 and later, ExampleTest 302 (UserFactory missing `is_active`), HrReportsTest 404 (fixture in a different branch), migration 000002 not rolling back on SQLite.

# Remaining Blockers (owner decisions; none can be inferred from the code)
| Decision | Affects | Why it can't be inferred |
|---|---|---|
| D1 production data repair | `suppliers:rebuild-ledger-balances --apply`, repair of UI-created sales (product_id 1, fake serials), UI-era returned quantities | depends on what production holds; the command is dry-run by default and writes a JSON backup first |
| D3 refund proration | BIZ-04 | policy for spreading invoice discount, scrap deduction and tax over returned lines |
| D4 technician commissions | BIZ-19, payroll gross | no rule for rate, trigger or approval exists in code or docs |
| D5 leave end | automatic return to active status | whether `on_leave` should be derived or reverted is a policy |
| D6 scrap sale | cash/treasury link | no treasury concept exists |
| D7 locale | FE-07 | translate, or remove the switch |
| Purchase returns | route permission | reuse `purchases.create` or add `purchases.return` |
| Supplier catalog sync | merge or replace | `sync()` would delete entries not in the request |
| Replacement warranty | period | full new period (current) or remaining |
| Supplier credit limit | enforcement | block, warn, or ignore |
| Returned battery serial | resale | unique index on `warranties.serial_number` includes voided rows |
| Settings not wired | vat_percentage (14 vs 0 today), session_timeout_minutes (120 vs 1440 today), allow_negative_stock (unsigned stock on MySQL), scrap_prefix | wiring changes behaviour |
| Payroll rules | late-penalty rate, zero-net guard when debt absorbs the batch, leave balances (the 26-day norm is now a setting) | policy |
| Role grants | `purchases.create`, `suppliers.create/edit` are super-admin only | which roles should hold them |

# Security Status
- RESOLVED (committed and tested): SEC-01 avatar storage (reclassified HIGH; the PHP-extension RCE claim was a false positive, because Laravel blocks those extensions), SEC-02, SEC-03, SEC-04, SEC-05, SEC-06, SEC-07, SEC-08, SEC-09, SEC-10, SEC-11, SEC-12, SEC-13, SEC-14 (new: login form prefilled admin credentials), POS idempotency keys scoped per cashier.
- Route verification: of 112 routes, the 17 without `can:` are exactly the documented auth-only or guest routes (login, register, logout, root redirect, lockscreen, profile and avatar, global search which is now permission-filtered, starter, 404, lang, CSRF).
- UNRESOLVED / residual risk:
  - Operators must set `MANAGER_OVERRIDE_CODE_HASH`; without it every override is refused (fail closed).
  - Client-side stored XSS through `innerHTML` was only partly reviewed; the rewritten POS, credit, products and customers screens escape server data, the remaining views were not audited.
  - Production web server configuration and whether seeders ran in production are unknown. The deploy guide now denies script and html extensions under `/uploads`.
  - Suppliers, customers and products have no branch column, so they are shared across branches by design.
  - Any staff member can fetch another user's avatar image (staff photos); low sensitivity.
  - Pint style is not enforced and the codebase was never Pint-formatted (about 100 files flagged, including untouched baseline files); no repo-wide reformat was done, to keep the diff reviewable.

# Database Status
- New migrations, NOT yet run on dev MySQL (owner action: `php artisan migrate`): 2026_10_01_000001 (document_sequences), 050000 (return tracking, refunded amount, idempotency key on invoices), 060000 (purchase item returned quantity), 060001 (claim notes), 060002 (scrap_sales), 080000 (deduction payroll item link).
- Rewritten, already applied on dev MySQL, no rerun needed: 2026_09_30_000001 (SQLite branch only; the MySQL result is unchanged) and 2026_09_30_000002 (idempotent; MySQL already in the target state).
- Verified on isolated SQLite with seeded data: a full rollback and re-migrate of all ten remediation migrations, `integrity_check` ok, `foreign_key_check` clean. Foreign keys and CHECK constraints are enforced during tests, and a TestCase assertion fails if anything disables them.
- Data risks: invoices created through the old POS screen may carry product_id 1 and generated serials; ledger repair and per-line returned quantities for past returns are D1-blocked.

# Final Test Status
- 310 passed, 1889 assertions, 0 failed, 0 skipped.
- Known failures: none. The two HEAD failures (month-end payroll simulation) are fixed at the root cause.
- Not covered by automated tests: real browser flows (scanner focus, modals, an actual checkout) and MySQL-specific migration paths. A manual test sale on a staging copy is needed before release.

# Remaining Work (concrete)
1. Owner: run `php artisan migrate`, set `MANAGER_OVERRIDE_CODE_HASH`, review and merge `remediation/2026-10`, run a manual POS test sale.
2. Needs your approval before deletion: public/assets/js/sales-store.js, public/assets/js/hr-store.js, app/Observers/InvoiceObserver.php, app/Observers/PurchaseInvoiceObserver.php, resources/views/admin/dashboard/partials/invoice-modal.blade.php, the dead SupplierController::create method, and the five agent worktrees and branches (`batch/*`, `worktree-agent-*`, under .claude/worktrees).
3. ARC-04 stock movement journal (stock changes from five writers); ARC-06 move DashboardController aggregation into a service and batch the 7-day trend query; DB-03 redundant indexes; DB-04 `Invoice::scrapBattery` / `technicianCommission` hasOne vs many; FE-08 hard-coded `/admin/...` URLs in JS; the unused Vite/Tailwind pipeline.
4. Owner decisions listed above, each unblocking its dependent task.
