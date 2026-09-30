# Testing Audit

Snapshot 2026-09-30, working tree included. Finding references point to security-audit.md (SEC), business-integrity.md (BIZ/FE/DB) and architecture.md (ARC).

## Current state (verified by running it)
- `php artisan test --compact` gives 168 passed, 686 assertions, about 55 s, exit code 0.
- Framework: Pest 3 on Tests\TestCase for Feature and Unit (tests/Pest.php). Files mix Pest closures with PHPUnit `test_*` classes. There are 32 test files plus Pest.php and TestCase.php, and 31 of them use RefreshDatabase.
- Environment (phpunit.xml): SQLite `:memory:`, CACHE_STORE=array, SESSION_DRIVER=array, QUEUE_CONNECTION=sync, MAIL_MAILER=array. The dev database is MySQL, so the test database differs in dialect and constraints.
- No browser or JS tests exist. No Dusk. No factories exist for Sales, Purchases, Warranty or Scrap models (factories are HR plus User only), so sales tests build rows inline or through services.

## Suite map
- Admin:
  - Feature/Admin/{DashboardSalesFilter, GlobalSearch, ProfileAndSettings, RoleAndCashier, SystemDiagnostics}
  - Feature/Auth/AdminAuthTest (login, throttling, some 403s at L112-113)
- Sales:
  - Feature/Sales/FinanceRegressionTest (R-01..R-20: FIFO, refunds, partially_refunded, receipt duplication, date filter, serial search, statement pagination)
  - CreditAndInvoiceManagementTest (includes the HTTP return at L237)
  - SalesAndPurchasesControllersTest (403s at L39-51 for sales routes; supplier store as super-admin)
  - EndToEndSalesAndPurchasesScenarioTest (sectors 1-8: WAC, POS + scrap + warranty, credit, claims, settlement, scrap batch, overselling)
  - Unit/Sales/{Requests, Services}
- HR:
  - Feature/HrSubsystemTest, Feature/Hr/EmployeeLifecycleScenarioTest
  - Unit/Hr/{Services, Requests, Models, EdgeCases, Scale}

## False-positive risks (tests pass while production behaviour is wrong)
- TST-01 HIGH: constraint enforcement is off during tests (DB-01). Migration 2026_09_30_000001 leaves `foreign_keys=OFF` and `ignore_check_constraints=ON` on the shared `:memory:` connection, so no test can detect FK violations or invalid enum values after it runs. Every Sales, HR and Purchases test is affected.
- TST-02 HIGH: the UI path is untested (FE-01). Sales tests call PosOrderService directly or POST handcrafted payloads with real product IDs. The browser payload (product_id → 1, customer_id → null, invented serial) is never exercised, so a green suite says nothing about real sales.
- TST-03 HIGH: authorization tests do not cover the gaps. There are no 403 tests for suppliers.* or purchases.* (grep finds only suppliers.store as super-admin in SalesAndPurchasesControllersTest). None cover global search per permission, users.manage escalation, the avatar upload extension, or deactivated-user sessions. The gaps in SEC-02, SEC-04, SEC-05, SEC-06 and SEC-01 are therefore invisible.
- TST-04 MEDIUM: tests encode the backdoor. RoleAndCashierTest L98 uses '9999' and EndToEndSalesAndPurchasesScenarioTest L310 uses 'mgr_override_99' as the valid manager override. SystemDiagnosticService L463 depends on the same code. Removing SEC-03 breaks these by design, so they must be rewritten to use a configured hash.
- TST-05 MEDIUM: returns are tested once per invoice. FinanceRegressionTest (L150, L170, L231) tests a single return and never a second return on a partially_refunded invoice, so BIZ-03 and BIZ-04 pass unnoticed.
- TST-06 MEDIUM: payroll consistency tests skip overtime and debt. No HR test references debt, carried_debt or the consistency guard (grep over tests/Unit/Hr, tests/Feature/Hr and HrSubsystemTest returns 0 hits). No test renders batches-table.blade.php, so BIZ-08 is invisible, and so is the committed-HEAD regression that blocks overtime batches.
- TST-07 LOW: the SQLite and MySQL paths differ. Migrations 000001 and 000002 have MySQL-only branches (ENUM alter, index drop fallback) that no test executes.

## Business-critical flows without meaningful coverage
- Repeated or partial returns with discount, scrap or tax on the invoice (BIZ-03, BIZ-04).
- Revenue and stats with partially_refunded invoices, and a settlement where the debt sits on a partially_refunded invoice (BIZ-05).
- Purchase with partial payment at receipt: ledger chain versus current_balance (BIZ-06).
- Supplier payment against invoices, and the overpayment guard response code (BIZ-11).
- Warranty settlement repeated twice (BIZ-07). Claim on an already-claimed warranty (BIZ-13).
- Scrap batch sale persistence (BIZ-10; nothing to assert until a table exists).
- Payroll with overtime through approve and disburse, and debt carry and repayment across two months (BIZ-08, BIZ-22).
- Leave approval, then payroll the next month (BIZ-09). Late check-in during approved leave (BIZ-14).
- HTTP GET hr/payroll/{id} non-JSON (BIZ-15), and GET admin/roles/{role} (SEC-13).
- Concurrency: none of the lockForUpdate paths are tested under contention. Plain PHPUnit cannot easily do so, so accept the gap or use a MySQL integration job.

## High-value missing tests (write in the remediation phases; not now)
- Auth matrix: for each route in routes.md × each seeded role, assert 200/403 against the documented permission. This catches SEC-02 and any future unguarded route.
- Security:
  - The avatar upload rejects or neutralises a `.php` name and stores it outside public or under a server-chosen extension.
  - A deactivated user's next request is logged out.
  - A users.manage holder who is not super-admin cannot assign super-admin.
  - Global search omits sections the user cannot view.
- Manager override: fails when no hash is configured, accepts a configured hash, rejects '9999'.
- Returns: two sequential partial returns cannot exceed the sold quantity or the paid amount. A refund is prorated for discount and scrap.
- Supplier ledger invariant: after any purchase or payment, the last ledger balance_after equals supplier.current_balance, and Σ(purchase_invoice) − Σ(supplier_payment) − Σ(returns) ± Σ(adjustment) equals the balance.
- Customer credit invariant: Customer.current_credit_balance equals Σ open invoice remaining_amount after sale, return and settlement sequences.
- Warranty settlement is idempotent: a second settle is rejected.
- Payroll: a batch with overtime approves and disburses. The register view shows no "needs review" for a consistent batch with overtime.
- Frontend contract: a feature test that renders the POS view and asserts the product and customer IDs the page exposes are real DB IDs, after FE-01 is fixed. Also a JS-free check that the payload builder takes server data, via a view-render assertion.
- Migration: run the suite with FK enforcement asserted on (`PRAGMA foreign_keys` returns 1 inside a test) to guard DB-01 from regressing.

## Recommendations for the test harness
- Remove the PRAGMA side effects from migration 000001, and assert FK enforcement in TestCase::setUp.
- Add factories for Product, Customer, Invoice, Supplier and PurchaseInvoice so tests stop hand-building rows.
- Optionally add a CI job on MySQL 8 for the migration and enum paths (TST-07).
