# Al-Husseini ERP/POS: project context

Read this first. Then read ONLY the `docs/ai/` files relevant to the task (index below). Do not redo completed audits or remediation.

## Before any work
1. Read this file. 2. Read the relevant `docs/ai/*` files only. 3. `git status`. 4. Read `docs/ai/REMEDIATION_EXECUTION_REPORT.md` (source of truth for what is done). 5. Continue from the current state.

## Stack
- PHP ^8.2 (8.2.12), Laravel 12.69, spatie/laravel-permission ^6.25, Pest 3.8. Dev DB MySQL, tests SQLite `:memory:` (FK + CHECK enforcement asserted in `tests/TestCase.php`; never disable it).
- UI: Blade + static Bootstrap 5 RTL admin theme in `public/assets` (Arabic, EGP). Vite/Tailwind exists but is unused. No SPA, no API, no queued jobs (cache/session/queue drivers = database).
- Domain: car batteries/oils/services, workshop, multi-branch ready (one branch today: MAIN).

## Modules (details in docs/ai)
Sales/POS/Credit, Catalog (products, customers, vehicles), Purchases/Suppliers, Warranty & Claims, Scrap batteries, HR (employees, attendance, leaves, deductions, payroll), Admin core (auth, users, roles, settings, dashboard, search, diagnostics).

## Architecture and business rules
- Flow: route (`can:` middleware) -> FormRequest/inline validation -> thin controller -> service (interface bound in `*ServiceProvider`) -> Eloquent. No Policies/Events/Jobs/Actions. Money/stock writes: `DB::transaction` + `lockForUpdate`.
- Shared mutable balances written directly by several services, with no journal: `Product.current_stock`, `Supplier.current_balance`, `Customer.current_credit_balance`. Find every writer before changing one.
- Money: 2-decimal values, compared in piasters (POS payments exactly == amount due; `finance.epsilon` for the rest). Invoice statuses live in `App\Enums\InvoiceStatus` (+ countable scope); `partially_refunded` counts net of `refunded_amount`.
- Numbers come from `DocumentNumberService` (invoices `INV-Ymd-000001`, claims `CLM-YYYYMM-n`, scrap batches). POS sales are idempotent via `idempotency_key` (scoped to the cashier).
- Returns are tracked per invoice line (`returned_quantity`). Supplier ledger is a running-balance chain (purchase posts full amount, then payment). Payroll consistency: `PayrollService::evaluateConsistency()` (includes overtime).
- `InvoiceObserver` / `PurchaseInvoiceObserver` are dead: never register them (double-posting). Invoices are created `withoutEvents`.
- Settings (`Setting::get/number`) are wired for working days/hours, overtime multiplier, warranty default months, invoice prefix. `vat_percentage`, `session_timeout_minutes`, `allow_negative_stock`, `scrap_prefix` are NOT wired (owner decision).

## Auth / permissions
- Guard `web`; `Gate::before` gives `super-admin` everything. Permissions are the catalogue in `PermissionRegistry` / `RolesAndPermissionsSeeder` (52). Roles: super-admin, branch-manager, accountant, cashier, workshop-supervisor.
- Backend: `can:<permission>` on every route + FormRequest `authorize()`. UI mirrors it (`@can` in sidebar, topbar, dashboard); never hide-only. `User::homeRouteName()` = landing page the user may open.
- Middleware: `EnsureUserIsActive`, `EnforceLockScreen`. Branch isolation: `BranchScope`/`BelongsToBranch` on 7 models (null branch or super-admin unrestricted; `BRANCH_ISOLATION=false` disables). Avatars live on the private disk. Manager override: only `finance.manager_override_hash|code`, fails closed.
- Env: `MANAGER_OVERRIDE_CODE_HASH` (required for overrides), `SEED_DEFAULT_PASSWORD`, `LOGIN_ACCOUNT_SWITCHER`, `LOGIN_SWITCHER_PASSWORD` (non-production only), `BRANCH_ISOLATION`, `FINANCE_EPSILON`.

## Commands
- Tests: `php artisan test --compact` (all), `... <path>` (focused). Last full run: 363 passed, 0 failed, 0 skipped (before the uncommitted POS payment work below).
- Dev: `php artisan migrate`, `php artisan db:seed`, `php artisan serve`; `npm run build` only for the unused Vite pipeline.
- Diagnostics: `php artisan system:diagnose [--audit|--simulate|--keep]` (`--keep` writes real data). `php artisan suppliers:rebuild-ledger-balances` is dry-run by default (`--apply` writes a JSON backup first; do not run on real data without owner approval).
- Style: Pint is configured but the codebase was never Pint-formatted; do not mass-reformat.

## Current remediation status
- Branch `remediation/2026-10` (from `main` 45b9ccc, tag `baseline-finance-2026-09-30`), tracks `origin/remediation/2026-10`; ~48 commits. Phases 0, 1, 3, 4, 5, 6+7, 8, 9 (branch isolation, derivable settings) are DONE and merged; security items SEC-01..14 resolved; navigation-engine fix and sidebar/dashboard authorization done; login account switcher restored.
- UNCOMMITTED (working tree): POS payment section: modes full/partial/remaining, live remaining, server validation (2-decimal, exact piaster over/under-payment checks, settlement `decimal:0,2`), `tests/Feature/Sales/PosPaymentValidationTest.php`. Browser-verified; 212 sales/admin tests passed; full suite not yet rerun; not committed.
- Pending on dev MySQL (owner action): `php artisan migrate` (2026_10_01_* migrations).
- Remaining work: Phase 10 cleanup (stock movement journal, DashboardController refactor, redundant indexes, `Invoice::scrapBattery` hasOne vs many, hard-coded `/admin/...` URLs in JS).

## Critical known issues / owner decisions (do not invent rules)
Blocked: refund proration of discount/scrap/tax; technician commissions are never created in production (payroll reads them); automatic end of `on_leave`; scrap sale has no cash/treasury record; locale switch only flips RTL/LTR; purchase-return route permission; supplier catalog sync merge vs replace; replacement-warranty period; supplier credit-limit enforcement; resale of a returned battery serial; role grants for `purchases.create`/`suppliers.*` (super-admin only); production data repair (`product_id 1` sales from the old POS). `/admin` and `/admin/warranties/verify` answer JSON to the nav engine's AJAX header (fall back to a full reload).

## Working rules (from the owner)
Never weaken or skip tests; no fake/mock data; ask before deleting files (candidates: `public/assets/js/sales-store.js`, `hr-store.js`, the two dead observers, `invoice-modal` partial, agent worktrees/branches `batch/*` in `.claude/worktrees`); do not push or commit unless asked; do not use `git stash` to "prove" tests (an interrupted stash once left work in the stash); append `Co-Authored-By` trailer to commits.

## docs/ai index (module docs date from the audit and may describe already-fixed gotchas; the execution report wins)
- `REMEDIATION_EXECUTION_REPORT.md`: what is done, blockers, DB/security status. Read first for status.
- `MASTER_REMEDIATION_PLAN.md`: phased plan with tasks/acceptance. `master-audit.md`: finding index.
- `architecture.md`, `security-audit.md`, `business-integrity.md`, `testing-audit.md`: audits by area.
- `PHASE_0_BASELINE.md`: baseline, commit boundaries, constraint-test problem.
- `routes.md` (96 routes, middleware), `database.md` (tables, relations, migration quirks).
- Per module: `sales.md`, `purchases.md`, `warranty-scrap.md`, `hr.md`, `admin-core.md`.
- Batch reports: `batch-phase3.md` (catalog), `batch-phase4.md` (POS/credit UI), `batch-phase5.md` (returns/revenue), `batch-phase67.md` (ledger/warranty/scrap), `batch-phase8.md` (HR/payroll).
- Other docs: `docs/DEPLOYMENT_GUIDE.md`, `docs/QA_TESTER_MANUAL.md`; `docs/*_ERRORS_REPORT.md` are historical. Root `PROJECT_CONTEXT.md` (2026-09-26, Arabic) is outdated.
