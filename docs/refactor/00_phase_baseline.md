# Phase 0: Baseline gate

Status tracker: `REFACTOR_PLAN.md`. Base: `main` @ `4de75b5`. Next phase: `01_phase_security_and_correctness.md`.

## Rules
- Laravel 12, PHP 8.2, Pest. Tests run on SQLite `:memory:` with FK/CHECK enforced; never disable it.
- Never weaken, skip or delete tests. Never use `git stash`. Don't commit or push unless the owner asks.

## Objective
Record a reproducible starting point. No refactoring. Every later phase compares against the numbers recorded here.

## Context
- Since the last session started, the working tree holds owner edits that are not committed:
  - `app/Services/Hr/EmployeeService.php:77`: stray text inside a docblock (`/**test mode deo _id trset i omp _id`). It looks accidental.
  - `resources/views/admin/hr/partials/employees/modal-form.blade.php`:
    - makes `national_id` required in the UI (`required` attribute plus a red `*`);
    - the rest of the diff is formatter line-wrapping.
- The server rules make `national_id` optional (commit `7b95861`), and that includes `StoreEmployeeRequest` and `UpdateEmployeeRequest`. The UI and the server disagree.
- The last recorded full test run (363 passed) was before PR #1 (`5d78433`). No full run has happened since the four post-merge commits.

## Steps (orchestrator)
1. **Owner decision on the HR edits.** Pick one:
   - **A.** Keep `national_id` optional. Revert the `required`/`*` on `empNationalId`, remove the stray docblock text, and keep or drop the formatter wrapping.
   - **B.** Make `national_id` required. The server rules must change too. That reverses part of `7b95861` and needs owner sign-off.
   - **C.** The owner commits or discards the edits themselves.
2. Get to a clean tree. `CLAUDE.md` (status update), `REFACTOR_PLAN.md` and `docs/refactor/` are roadmap changes that are not yet committed. The owner commits them in a `docs:` commit, or explicitly allows them as the only exception. Apart from those, `git status --short` must be empty.
3. Run the full suite and record the result as `N0`.
4. Generate `docs/refactor/baseline-diagnose.txt` as an output of this phase: `php artisan system:diagnose --audit > docs/refactor/baseline-diagnose.txt`.
5. Record the commit SHA as `PHASE0_SHA` in `REFACTOR_PLAN.md`.

## Codex prompt (only if the owner picks option A)
```text
[TASK: Phase 0 - Step 1A]
ROLE: Strict Code Executor.
ACTION: Revert accidental HR edits to match server rules (national_id optional).
TARGET_FILE: `app/Services/Hr/EmployeeService.php:77`, `resources/views/admin/hr/partials/employees/modal-form.blade.php` (empNationalId block only)
CHANGES_REQUIRED:
1. EmployeeService.php:77: restore docblock opener to `/**` (remove trailing text). No other change.
2. modal-form.blade.php: on input#empNationalId remove `required`; on its label remove the `<span class="text-danger">*</span>`. Keep maxlength=14 and placeholder.
3. Do not touch any other line.
OUTPUT_CONSTRAINT: Return ONLY unified diff. No explanations. Final line: STATUS: <one line>.
VERIFICATION: Must pass `php artisan test --compact tests/Feature/Hr`.
```

## Acceptance criteria
- [ ] The owner has decided on the HR edits (A, B or C), and the decision is recorded in `REFACTOR_PLAN.md`.
- [ ] `git status --short` is empty, apart from any roadmap documents the owner explicitly allowed.
- [ ] `php artisan test --compact` shows 0 failed and 0 skipped. Record the passed count as `N0`.
- [ ] `php artisan route:list --except-vendor` succeeds.
- [ ] `docs/refactor/baseline-diagnose.txt` is saved.
- [ ] `PHASE0_SHA` is recorded.
