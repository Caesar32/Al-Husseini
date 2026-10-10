# HR Module (Employees, Attendance, Leaves, Deductions, Payroll, Notifications)

## Purpose
- Manages staff records and salary structures, daily attendance (manual punch / ZKTeco PIN), leave requests, financial penalties (deductions), monthly per-branch payroll batches with carried-over payroll debts, and in-app admin notifications.
- All UI text and exception messages are Arabic; currency is EGP (ج.م). Controllers are thin; logic lives in `app/Services/Hr/*`, bound to `app/Contracts/Hr/*` via `app/Providers/HrServiceProvider.php` (`$bindings` array; register/boot are empty).

## Key files
- Routes: `routes/web.php` group `admin.` + `auth` → `prefix('hr')->name('hr.')` (full names are `admin.hr.*`, URLs `/admin/hr/*`).
- Controllers: `app/Http/Controllers/Hr/{Employee,Attendance,Leave,Deduction,Payroll,Notification}Controller.php`.
- Services: `EmployeeService::{getPaginatedEmployees,getEmployeeStats,getFormData,createEmployee,updateEmployee,getEmployeeDetails,deleteEmployee}`; `AttendanceService::{getDailyAttendance,getDailyStats,getFormData,recordPunch,markAbsent,calculateLateness,calculateEarlyLeaveAndOvertime,notifyAdminsAboutLateness}`; `LeaveService::{getPaginatedLeaves,applyForLeave,updateLeaveStatus}`; `DeductionService::{getPaginatedDeductions,applyDeduction,updateDeductionStatus}`; `PayrollService::{getPayrollIndexData,generateMonthlyPayroll,assertPayrollTotalsConsistent(private),approvePayroll,disbursePayroll,getPayrollDetails}`; `NotificationService::{getUserNotifications,markNotificationAsRead,markAllNotificationsAsRead}`.
- FormRequests: `app/Http/Requests/Hr/{StoreEmployee,UpdateEmployee,RecordPunch,StoreLeave,UpdateLeaveStatus,StoreDeduction,UpdateDeductionStatus,GeneratePayroll}Request.php`.
- Observer: `app/Observers/AttendanceObserver.php::{saving,saved}` registered in `app/Providers/AppServiceProvider.php` (`Attendance::observe`).
- Notifications: `app/Notifications/{EmployeeLateNotification,LeaveRequestedNotification,PayrollGeneratedNotification}.php`.
- Models: `Employee` (SoftDeletes; `currentSalary` = hasOne SalaryStructure where `is_current`; scopes `active`, `technicians`), `SalaryStructure`, `Attendance`, `EmployeeLeave`, `EmployeeDeduction`, `DeductionRule`, `Payroll`, `PayrollItem`, `EmployeePayrollDebt::remainingAmount`, `TechnicianCommission`, `Department`→`JobTitle`→`Employee`, `Branch::employees`.
- Migrations: `2026_09_21_160003` (employees, salary_structures, employee_leaves), `..160004` (attendances, deduction_rules, employee_deductions), `..160005` (payrolls, payroll_items), `2026_09_29_150000_create_employee_payroll_debts_table`, `2026_09_29_150100_add_payroll_debt_columns_to_payroll_items` (debt_repayment, carried_debt).
- Views: `resources/views/admin/hr/{employees,attendance,payroll,reports}.blade.php` + `partials/{employees,attendance,payroll,reports}/*`; payroll register = `partials/payroll/batches-table.blade.php`; live breakdown = `breakdown-table.blade.php`; payslip = `modal-payslip.blade.php` rendered by JS in `partials/payroll/scripts.blade.php`.
- Diagnostics: `app/Services/Diagnostics/SystemDiagnosticService.php` check `payroll_math_integrity` (header `total_net` vs sum of item `net_salary`, tolerance 0.05).
- Tests (Pest): `tests/Feature/HrSubsystemTest.php`, `tests/Feature/Hr/EmployeeLifecycleScenarioTest.php`, `tests/Unit/Hr/{Services,Requests,Models,EdgeCases,Scale}/*`. No HR test references payroll debts, carried_debt or the consistency guard (grep for debt/carried in tests hits only Sales tests).

## Flow per action (route → permission → FormRequest → controller → service → models)
- GET `/hr/employees` `hr.employees` → `can:employees.view` → none → `EmployeeController::index` (JSON if `wantsJson`, else view `admin.hr.employees`) → `getPaginatedEmployees` (filters branch_id/status/search, Arabic letter normalization on name) + `getEmployeeStats` + `getFormData`.
- POST `/hr/employees` → `can:employees.create` → `StoreEmployeeRequest` (national_id size 14, unique code/phone/national_id/zkteco_pin, shift_end after shift_start, grace 0-60, basic_salary min 500) → `store` → `createEmployee` (DB transaction: Employee status active + SalaryStructure is_current, effective_from = hire_date).
- GET `/hr/employees/{employee}` → `can:employees.view` → `show` (always JSON) → `getEmployeeDetails` (last 30 attendances, 10 deductions, 5 leaves, 10 commissions).
- PUT `/hr/employees/{employee}` → `can:employees.edit` → `UpdateEmployeeRequest` (status in active/on_leave/terminated; shift times only `required`, no format/after rule) → `updateEmployee` (transaction; updates current SalaryStructure in place if `basic_salary` sent).
- DELETE `/hr/employees/{employee}` → `can:employees.delete` → `destroy` → `deleteEmployee` (soft delete; message says "archived").
- GET `/hr/attendance` `hr.attendance` → `can:attendance.view` → `AttendanceController::index` (date default today; branch_id/status filters) → `getDailyAttendance` + `getDailyStats`.
- POST `/hr/attendance/punch` → `can:attendance.manual_punch` → `RecordPunchRequest` (employee_id or pin, timestamp, punch_state in 0,1,check_in,check_out) → `recordManual` → `recordPunch(identifier, ts, state, 'manual')`.
- POST `/hr/attendance/mark-absent` → `can:attendance.manual_punch` → inline `$request->validate` → `markAbsent` (reason accepted but not stored).
- GET/POST `/hr/leaves`, POST `/hr/leaves/{leave}/status` → `can:leaves.manage` → `StoreLeaveRequest` (type annual/sick/emergency/unpaid, end >= start) / `UpdateLeaveStatusRequest` (approved/rejected, action_notes) → `LeaveController` (index returns JSON paginator) → `LeaveService`.
- GET/POST `/hr/deductions`, POST `/hr/deductions/{deduction}/status` → `can:deductions.manage` → `StoreDeductionRequest` (authorize re-checks `deductions.manage`; employee must be active; date <= today; amount min 1; reason 3-500) / `UpdateDeductionStatusRequest` (approved/cancelled) → `DeductionController` → `DeductionService`.
- GET `/hr/payroll` → `can:payroll.generate` → `PayrollController::index` → `getPayrollIndexData(branchId)` (paginated payrolls + active employees + last 20 deductions + latestPayroll).
- POST `/hr/payroll/generate` → `can:payroll.generate` → `GeneratePayrollRequest` (branch exists, year 2024-2035, month 1-12) → `generate` → `generateMonthlyPayroll`; exceptions → 422 JSON.
- GET `/hr/payroll/{payroll}` → `can:payroll.generate` → `show` → `getPayrollDetails`; non-JSON renders `admin.hr.payroll_show`.
- POST `/hr/payroll/{payroll}/approve` → `can:payroll.approve` → plain Request (`confirm_debt_review` boolean) → `approvePayroll(payroll, Auth::id(), confirm)`.
- POST `/hr/payroll/{payroll}/disburse` → `can:payroll.disburse` → `disbursePayroll`.
- GET `/hr/reports` → `can:reports.hr` → route closure returning `admin.hr.reports` (no controller/service).
- GET `/hr/notifications`, POST `/hr/notifications/{id}/read`, POST `/hr/notifications/read-all` → `can:notifications.view` → `NotificationController` → `NotificationService` (latest 20 + unread_count for Auth user).
- All FormRequests except `StoreDeductionRequest` have `authorize() { return true; }`; authorization relies on route `can:` middleware.

## Business rules
- Lateness/grace: late only if check_in > shift_start AND diff > `employees.grace_period_minutes` (default 15); then `late_minutes` = full diff (not diff minus grace) and status `late`; within grace → 0. Computed in both `AttendanceService::calculateLateness` and `AttendanceObserver::saving`.
- Punch rules: employee resolved by id OR zkteco_pin (numeric identifier matches either); one attendance row per employee/day (DB unique employee_id+work_date). Duplicate check-in < 5 min is silently ignored (debounce), >= 5 min throws; check-out requires prior check-in and must be after it; duplicate check-out < 5 min ignored, later check-out overwrites.
- On approved leave (or employee status on_leave) a check-in sets status `holiday`; hours worked that day become `overtime_hours` in full on check-out.
- Early leave / overtime: check-out before shift_end → `early_leave_minutes`, overtime 0; after shift_end, overtime counted only if >= 30 min (whole span, hours rounded 2dp). Overnight shifts: shift_end + 1 day when end < start (but `StoreEmployeeRequest` forbids end <= start on create).
- Absence (manual): `markAbsent` upserts status absent and zeros check-in/out, late, early-leave, overtime.
- Auto late penalty: `AttendanceObserver::saved` when `late_minutes >= 30` creates (firstOrCreate by attendance_id) an `EmployeeDeduction` of 0.25 x (basic/30), status `pending` — excluded from payroll until approved.
- Leaves: `days_count` = calendar days inclusive; created `pending`. Approval sets `employee.status = on_leave`. No leave balance/entitlement tracking, no overlap check, no reversal of on_leave on rejection or leave end (grep found no code resetting on_leave). Leave balances: none exist.
- Deductions: `applyDeduction` creates directly `approved` with approver = current user; status update allows approved/cancelled. DB enum also has `applied`, never set by code. `DeductionRule` (type lateness/absence/disciplinary/loan; calculation_method fixed_amount/hourly_rate_multiplier/day_wage_multiplier; multiplier_value) is only an optional FK label — no service uses calculation_method/multiplier.
- Payroll scope: one batch per branch/year/month (unique); includes only employees with `status = 'active'` in that branch having a current SalaryStructure.
- Payroll formula per employee (`generateMonthlyPayroll`): dayRate = basic / daysInMonth; hourlyRate = dayRate / 8; allowances = housing + transport + other; paid/unpaid leave days = approved leave overlap clipped to month (unpaid type → unpaid); presentDays = attendance rows with status != absent (includes late/holiday/excused); unexcused = max(0, 26 - (presentDays + paidLeaveDays)); absentDays = unexcused + unpaidLeaveDays; absenceCost = absentDays x dayRate; overtime = sum(overtime_hours) x hourlyRate x 1.5; commissions = sum of `approved` TechnicianCommission with created_at in month; approvedDeductions = sum of `approved` deductions with deduction_date in month.
- gross = basic + allowances + overtime + commissions; currentDeductions = absenceCost + approvedDeductions; available = gross - currentDeductions; debtRepayment = min(max(0, available), outstanding debt); carriedDebt = max(0, -available); net = max(0, available - debtRepayment); item `total_deduction` = currentDeductions + debtRepayment; item `total_allowance` = allowances + commissions; `total_overtime` stored separately; `late_minutes_total` informational only (lateness costs money only via deductions).
- Header totals: total_basic, total_allowances (excludes overtime), total_deductions, total_net = sums over items. No total_overtime column on payrolls.
- Statuses: `draft` → `approved` → `disbursed` (enum also has `reviewed`, unused). Regeneration allowed only for draft (approved/disbursed throw); regeneration deletes old items and the debts sourced from them, then rebuilds. There is no "return to draft" action despite the error message suggesting it (UNVERIFIED elsewhere; none in routes).
- Approval guards (`approvePayroll`): status must be draft; `assertPayrollTotalsConsistent`; if any item has carried_debt > 0 requires `confirm_debt_review=true`; approver id required (Auth fallback).
- Consistency guard (`assertPayrollTotalsConsistent`, commit 2468e9b + uncommitted fix adding overtime): recompute from items; expectedNet = basic + allowances + overtime - deductions + carriedDebt; throws if any header total differs from item sums by > 0.01, if item net sum differs from expectedNet by > 0.01, or if net sum ~ 0 while basic+allowances > 0 or deductions > 0. Used by approve and disburse.
- Disbursal (`disbursePayroll`): transaction + `lockForUpdate` on payroll, items and debts; must be approved; re-runs consistency guard; applies each item's `debt_repayment` FIFO (by id) to unsettled `EmployeePayrollDebt` rows, sets `settled_at` when fully paid; throws if repayment cannot be fully matched (> 0.01); sets status disbursed + `disbursed_at`.
- Debt carry-over: shortfall becomes a new `EmployeePayrollDebt` (original_amount, paid_amount 0) linked by `source_payroll_item_id`; recovered in later months only from positive available pay, and only marked paid at disbursal. Batches with debt are forced back to `draft` after generation.
- Commissions: read-only in HR; created by `database/seeders/SalesAndPosDataSeeder.php` and approved in `SystemDiagnosticService`; `InvoiceObserver` and `PosOrderService` import the model but no create call was found (UNVERIFIED where production commissions originate). Payroll never sets commission `payroll_id` or status `paid`.

## Side effects
- Transactions: createEmployee, updateEmployee, recordPunch, markAbsent, updateLeaveStatus, generateMonthlyPayroll, disbursePayroll. Not transactional: applyForLeave, applyDeduction, updateDeductionStatus, approvePayroll.
- Observer: Attendance `saving` recalculates lateness; `saved` may create a pending auto-deduction (fires inside recordPunch transaction).
- Notifications: all three use channel `database` only, `Queueable` trait but no `ShouldQueue` (sent synchronously, inside transactions). Payload keys: type, title, message, icon, color, url (+ context ids).
- `EmployeeLateNotification`: from `recordPunch` whenever saved `late_minutes > 0`; recipients role super-admin or branch-manager with `branch_id` null or equal to employee branch.
- `LeaveRequestedNotification`: from `applyForLeave`; all super-admin + branch-manager users (no branch filter).
- `PayrollGeneratedNotification`: from `generateMonthlyPayroll`; all super-admin users; message includes branch name and total_net.

## Gotchas
- Register view (`batches-table.blade.php`) computes expectedNet WITHOUT overtime while the service (working tree) includes it; any batch with overtime > 0 shows "needs review" and hides approve/disburse buttons. Register also displays the recomputed expected net, not stored `total_net`.
- The service overtime fix in `assertPayrollTotalsConsistent` is uncommitted (`git diff app/Services/Hr/PayrollService.php`); commit 2468e9b alone blocks every batch with overtime.
- Zero-net guard: a batch whose item nets all sum to 0 (e.g. single-employee branch fully in debt) can never be approved even with `confirm_debt_review`.
- Approved leave flips employee to `on_leave`, which excludes them from payroll generation (active-only) and is never reverted automatically.
- Observer `saving` runs after the service sets `holiday`, so a late check-in during approved leave is overwritten to status `late` (and can trigger a penalty draft).
- Late notification re-fires on check-out (late_minutes still > 0 on save), producing duplicates.
- Late penalty uses basic/30 while payroll dayRate uses basic/daysInMonth; absence uses a fixed 26-day norm regardless of month length or weekends.
- Commissions are counted by `created_at` month and never marked paid/linked; deductions never move to `applied`; re-running a later month cannot double count them only because of the date windows.
- Recalculation deletes debts sourced from old draft items, but debts partially paid by other batches are not specially handled (UNVERIFIED impact).
- `PayrollController::show` non-JSON renders `admin.hr.payroll_show`, which does not exist under `resources/views/admin/hr/` (would error).
- Payroll page breakdown/payslip (`breakdown-table.blade.php`, `modal-payslip`) is a live estimate: basic + allowances - approved deductions from only the latest 20 deductions system-wide; ignores absence, overtime, commissions, debts; not the stored PayrollItem.
- `UpdateEmployeeRequest` status can be set to terminated/on_leave manually; salary edits overwrite the current SalaryStructure (no history row / effective_to).
- Leave/deduction/payroll index endpoints return JSON only; routes use hard-coded `/admin/hr/...` URLs in JS.
- Diagnostics check only compares header total_net vs item net sum (0.05 tolerance), weaker than the approval guard.
