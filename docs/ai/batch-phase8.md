# Batch: Phase 8 (HR / payroll integrity + HR reports backend)

Branch `batch/phase8-hr` (from remediation/2026-10 @ 3e4dc19), worktree-isolated. Plan reference: MASTER_REMEDIATION_PLAN.md Phase 8; decisions: REMEDIATION_EXECUTION_REPORT.md.

## Commits
- 9656978 HR attendance: keep holiday status on leave days; notify lateness once (BIZ-14)
- 4e4d3e3 Payroll: single consistency source, stored-value views, on-leave inclusion (BIZ-08, BIZ-15, FE-06, BIZ-09 partial, BIZ-22 partial)
- (this doc's commit) plus the HR reports commit: serve daily/monthly/range reports from the database (FE-03)

## Tests
- Before: 172 passed (1029 assertions).
- After: 186 passed (1124 assertions); FK and CHECK enforcement on (TestCase guard).
- New:
  - tests/Feature/Hr/PayrollIntegrityTest.php (8): overtime batch approve+disburse; register shows a consistent overtime batch as approvable (asserts on the exact button markup, not the JS selector); register flags a stale header; payroll show HTML/JSON; on_leave included; late check-in on leave keeps holiday with no penalty; single late notification; withheld deductions marked applied and immutable.
  - tests/Feature/Hr/HrReportsTest.php (6).
- No existing test was modified.

## Changes
- BIZ-08: `PayrollService::evaluateConsistency()` (interface method) is the single consistency definition.
  - It returns basic, allowances, overtime, deductions, carried_debt, expected_net, stored_net, zero_with_components, consistent, reasons.
  - `assertPayrollTotalsConsistent` uses it (always re-reading items).
  - `getPayrollIndexData` returns `payrollConsistency` per batch, and batches-table.blade.php renders it. The overtime-less inline formula is removed, and overtime is shown under allowances.
  - The guard semantics are unchanged (same header, net and zero-with-components rules).
- BIZ-15: new `resources/views/admin/hr/payroll_show.blade.php` with stored items and consistency reasons. The show JSON adds `consistency`. The register links to the detail page.
- FE-06: the breakdown table shows each employee's latest stored PayrollItem, with "—" and "not calculated yet" when none exists. The payslip fetches `admin.hr.payroll.show` JSON and renders the stored item, escaped. No client estimates remain in these two components.
- BIZ-09 (D5 partial): payroll generation includes `status in (active, on_leave)`.
- BIZ-14:
  - AttendanceObserver leaves `holiday`/`excused` untouched and zeroes late minutes for them. Hours on a leave day already count fully as overtime, so this also prevents the automatic late penalty.
  - AttendanceService does the same.
  - EmployeeLateNotification is sent only when `late_minutes` changes to a positive value.
- BIZ-22 (partial):
  - New migration `2026_10_01_080000_add_payroll_item_id_to_employee_deductions_table`: nullable FK to payroll_items, nullOnDelete, additive. Not run on dev MySQL.
  - Generation links withheld deductions. Disbursal marks exactly those (still approved) as `applied`.
  - DeductionService refuses status changes on applied rows, and DeductionController returns 422.
  - `payroll_item_id` is not mass assignable.
- FE-03:
  - `App\Services\Hr\HrReportService` and `App\Http\Controllers\Hr\HrReportController`.
  - Routes in a delimited block in the hr group, all `can:reports.hr`: `admin.hr.reports` (now controller-backed), `.daily`, `.monthly`, `.range`, `.employee`.
  - reports/scripts.blade.php fetches these endpoints with no `AlHusseiniHR` usage. Department and year options come from the DB.
  - Also fixed the monthly/range KPI crash caused by reading the nonexistent `summary.staffReport`.

## Behaviour changes to note
- An employee with no attendance row is reported as "not recorded" (`not_recorded`) instead of an unexcused absence. Only explicit `absent` rows count as absence.
- Late minutes on holiday/excused rows are 0, so no automatic late penalty is created for work on an approved leave day.
- Breakdown and payslip show stored payroll values. Employees not yet in any generated payroll show "—".

## Blocked / owner decisions (not changed)
- BIZ-19 commissions (D4).
- Automatic `on_leave` → `active` reversion at leave end (D5).
- BIZ-22 rest: late-penalty rate (basic/30) versus payroll day rate (basic/daysInMonth); the fixed 26-day absence norm; the zero-net guard for batches fully absorbed by debt; leave balances and overlap checks.
- Pre-existing gap, now observable: a deduction approved after its month's payroll was generated (or disbursed) is never withheld. It stays `approved` with a past date, outside every later payroll window. The policy (carry to next payroll, or require regeneration) is an owner decision.
- A deduction cancelled after generation but before disbursal stays linked. Disbursal then pays out the stored item, which still includes it. Same owner decision as above.

## Follow-ups for the orchestrator (outside this worker's allowed files)
- SystemDiagnosticService `payroll_math_integrity` compares header total_net with the item sum at 0.05 tolerance. It should call `PayrollService::evaluateConsistency()` (same rules as approve/disburse).
- Dashboard `loadHRDashboardStats` still reads `AlHusseiniHR` (Phase 10 / FE-05). public/assets/js/hr-store.js can be deleted once no view references it (now only the dashboard).
- The payroll page stats cards (payroll.blade.php `@php` totals from salary structures and the latest 20 deductions) are still estimates. Make them use the latest batch's consistency values.
- Pending migration on dev MySQL: 2026_10_01_080000 (plus 2026_10_01_000001 from the shared commit).
