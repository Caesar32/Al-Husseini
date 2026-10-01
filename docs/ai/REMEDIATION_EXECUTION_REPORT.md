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

### Batch 5: Phase 4 POS and credit UI (IN PROGRESS)
- Agent af9b991e70525541a on branch batch/phase4-pos.
  - Committed: b15e343 (POS stock check per product), f4aa7f9 (POS screen on real data).
  - In progress: credit page payments endpoint and view (uncommitted in its worktree).
- Not merged. After merging, run the full suite.

## Stopping point (2026-10-01)
- Branch remediation/2026-10 at 6d0594a (plus this report commit). Full suite: 253 passed / 1548 assertions, 0 failed, 0 skipped, constraints enforced.
- Pending migrations on dev MySQL, not run (owner action: `php artisan migrate`):
  - 2026_10_01_000001
  - 050000
  - 060000-060002
  - Phase 8 (080000) once merged
- Next READY batch:
  1. Collect and review the Phase 8 and Phase 4 agent branches, merge each, then run the full suite. If an agent stopped early, resume it on its branch; its work is in its worktree under .claude/worktrees.
  2. Phase 9: SEC-08 branch isolation, plus settings wiring for keys whose rules are derivable.
  3. Phase 10 cleanup: remove the dead observers, sales-store.js/hr-store.js (after Phase 4/8), the dead SupplierController::create, and the redundant indexes.
  4. Final validation pass and an update of security-audit.md, business-integrity.md and master-audit.md statuses.
- BLOCKED on owner decisions:
  - D1: production data repair (`suppliers:rebuild-ledger-balances --apply`, UI-sale repair).
  - D3: refund proration.
  - D4: technician commissions.
  - D5 (partial): automatic leave-status reversion.
  - D6 (partial): scrap sale cash linkage.
  - D7: locale.
  - Purchase-return route permission (reuse purchases.create or add purchases.return).
  - Supplier catalog sync semantics (merge vs replace).
  - Replacement warranty period.
  - Supplier credit-limit enforcement.
  - Resale of a returned battery serial (unique index on warranties.serial_number).
  - Role grants for purchases.create / suppliers.* (currently super-admin only; adjustable in the Roles UI).
