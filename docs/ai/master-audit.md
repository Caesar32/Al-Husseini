# Master Technical Audit

Date 2026-09-30. Scope: the full repository at HEAD 45b9ccc plus the uncommitted working tree (finance/POS remediation in progress: 23 modified and 10 untracked files). This is the index of findings; evidence and remediation detail live in:
- architecture.md (ARC)
- security-audit.md (SEC)
- business-integrity.md (FE, BIZ, DB)
- testing-audit.md (TST)
- MASTER_REMEDIATION_PLAN.md (phases)

## Project health overview
- Backend core is structurally sound:
  - A thin-controller → service → model layering, with interfaces bound in providers.
  - Every monetary and stock mutation runs in DB::transaction with lockForUpdate.
  - 168 passing tests (686 assertions).
- The product is not end-to-end functional through its UI:
  - POS, products, customers and HR reports run on localStorage mock stores (FE-01, FE-02, FE-03). The POS sends product_id 1 and a null customer for every sale.
  - The backend is exercised mostly by tests and SystemDiagnosticService.
- Authorization depends solely on route middleware and has holes: suppliers and purchases are unguarded (SEC-02), the manager override has a backdoor (SEC-03), and the avatar upload can store a server-executable file (SEC-01).
- Financial integrity gaps remain:
  - Returns: repeatable, and not prorated.
  - partially_refunded invoices drop out of totals and FIFO.
  - The supplier ledger diverges from the supplier balance.
  - Warranty settlement is not idempotent.
  - The scrap sale is not recorded.
- The test suite overstates safety: a migration disables FK and CHECK enforcement for the test connection (DB-01, TST-01), and the UI and authorization gaps are untested (TST-02, TST-03).
- There is a parallel plan: FINANCE_MODULE_REMEDIATION_PLAN.md and BASELINE.md (repo root) cover the finance subset (33 issues from docs/FINANCE_ERRORS_REPORT.md). Much of it is already applied in the working tree. MASTER_REMEDIATION_PLAN.md supersedes it for sequencing, and its finance phases must reconcile with it.

## Confirmed issues (severity / module / reference)
- CRITICAL:
  - SEC-01 Profile: avatar upload keeps the client extension inside public/, so a PHP file can be stored and run (RCE).
  - FE-01 POS: mock catalog; product_id → 1, customer → null, invented serials, discount and tax fixed at 0.
  - FE-02 Products/Customers: no backend CRUD; the pages are localStorage-only.
- HIGH:
  - SEC-02 Suppliers/Purchases: resource routes lack `can:` checks.
  - SEC-03 POS/Finance: hard-coded override codes 9999 and mgr_override_99, plus a manager-password fallback active in the current config.
  - FE-03 HR reports: fully mock.
  - BIZ-03 Returns: unlimited repeat returns.
  - BIZ-04 Returns: refund ignores discount, scrap and tax.
  - BIZ-05 Sales/Credit: partially_refunded invoices are excluded wholesale from stats, dashboard, payments and FIFO.
  - BIZ-06 Purchases: ledger chain ≠ current_balance when paid at receipt.
  - BIZ-07 Warranty: supplier settlement can repeat (double stock or credit).
  - BIZ-08 Payroll: the register view omits overtime, blocking approval in the UI. The server fix is uncommitted.
  - DB-01 DB/Tests: the SQLite migration disables FK and CHECK enforcement.
  - ARC-01 umbrella for the FE split-brain (not counted separately).
- MEDIUM:
  - Security: SEC-04 (deactivated sessions live), SEC-05 (global search leaks PII), SEC-06 (users.manage can grant super-admin), SEC-07 (seeded shared weak password), SEC-08 (no branch isolation).
  - Frontend: FE-04 (credit payments tab mock), FE-06 (payslip estimate).
  - Business: BIZ-09 (on_leave never reverted), BIZ-10 (scrap sale not persisted), BIZ-11 (supplier payments not allocated), BIZ-12a/b (uniqid and count+1 numbering), BIZ-13 (claim gaps), BIZ-14 (observer overrides holiday), BIZ-15 (payroll_show view missing), BIZ-18 (purchase return unreachable), BIZ-19 (commissions never created).
  - Database: DB-02 (receipt migration can half-apply).
  - Architecture: ARC-02 (duplicated status rules), ARC-03 (dead observers), ARC-04 (no stock or balance journal), ARC-05 (route-only authorization).
- LOW:
  - Security: SEC-09 (settings arbitrary keys), SEC-10 (cosmetic lockscreen), SEC-11 (`?? 1` fallbacks), SEC-12 (6-character admin passwords), SEC-13 (roles show 500).
  - Frontend: FE-05 (dashboard mock dead code), FE-07 (locale direction only), FE-08 (global mock stores, hard-coded URLs).
  - Business: BIZ-16 (settings unused), BIZ-17, BIZ-20, BIZ-21, BIZ-22.
  - Database and architecture: DB-03, DB-04, ARC-06, ARC-07, ARC-08.
- Test risks (not defects in themselves): TST-01..03 HIGH, TST-04..06 MEDIUM, TST-07 LOW.

## Unverified
- Whether production runs with the documented nginx config (SEC-01 exploitability) and whether seeders ran in production (SEC-07).
- Whether a reverse proxy collapses the override and login rate-limit buckets (SEC-03).
- Client-side stored XSS through `innerHTML` in the POS, credit and dashboard renderers. Customer `$hidden` exposure in credit.blade `@json($customers)`.
- When `alhusseini-hr-updated` fires on the dashboard (FE-05).
- Whether production data already contains UI-created sales with product_id 1 and fake serials. This decides whether a data repair is needed.
- Debt edge cases when a draft payroll is regenerated after partial repayment (hr.md).
- The error surface of the uncaught DomainException in SupplierController::recordPayment (probably a 500).

## False positives (previous claims disproved or narrowed)
- "warranties.customer_id NOT NULL means walk-in sales cannot produce a warranty" (database.md): disproved in effect. A guest customer is attached (PosOrderService L281).
- "Credit page KPIs and rows fall back to localStorage" (sales.md): narrowed. They use the server data, and only the collected figure and payments tab are mock (FE-04).
- "Dashboard tables render mock data": not true today. The mock loaders exist but are never called (FE-05); the server-rendered widgets are authoritative.
- All ten listed leads were CONFIRMED. None was a false positive. The classification is in security-audit.md and business-integrity.md under "Previous leads".

## Cross-module dependencies relevant to remediation
- Product.current_stock is written by POS sale/return, purchase, purchase return, warranty replace and warranty settle. A movement journal (ARC-04), if introduced, must be threaded through all five.
- Supplier.current_balance and the supplier ledger are written by PurchaseService and WarrantyService. The ledger model fix (BIZ-06) must cover the warranty credit notes too.
- Customer credit (Customer.current_credit_balance, invoice remaining_amount, CreditLedgerEntry) is written by the sale, return and settlement paths in PosOrderService. BIZ-03/04/05 must be fixed together to keep one invariant.
- Invoice status semantics: the rule on partially_refunded is shared by PosOrderService, InvoicePayment scopes, InvoiceFilter and DashboardController. Centralise it (ARC-02) before changing it (BIZ-05).
- ManagerOverrideService is used by StorePosInvoiceRequest, PosOrderService, SystemDiagnosticService and tests. Removing the backdoor (SEC-03) requires updating diagnostics and tests in the same change.
- TechnicianCommission: payroll consumes it, but sales never produces it (BIZ-19). Changing either side affects the other.
- The mock stores (sales-store.js, hr-store.js) are loaded globally, so removing them touches POS, products, customers, credit, dashboard and HR reports.

## Blockers
- B1: FE-02 blocks FE-01. The POS cannot use real products and customers until backend CRUD exists or at least a read and create path.
- B2: DB-01 blocks trusting any test-based acceptance. Fix it first.
- B3: The uncommitted working tree (finance fixes plus the PayrollService overtime fix) must be committed or otherwise settled before phased work starts. Otherwise every phase diff mixes with unreviewed changes.
- B4: Business decisions are needed; see Open decisions in MASTER_REMEDIATION_PLAN.md. They cover the refund proration policy, commission rules, the override model, scrap sale accounting, the leave status model, locale scope, and the production data-repair scope.

## Remediation priorities, grouped by type (not ranked)
- Exposure of the host or the system: SEC-01.
- Controls that can be bypassed today: SEC-02, SEC-03, SEC-04, SEC-06.
- Data written wrongly by normal use: FE-01, FE-02, BIZ-03, BIZ-04, BIZ-06, BIZ-07.
- Data misreported: BIZ-05, FE-03, FE-04, BIZ-08.
- Missing records or features: BIZ-10, BIZ-18, BIZ-19, BIZ-15.
- Verification capability: DB-01, TST-01..06.
- Latent (multi-branch or scale): SEC-08, ARC-02, ARC-04, BIZ-12a/b.
- Hygiene: every LOW item.

## Recommended implementation order (dependency-driven; details in MASTER_REMEDIATION_PLAN.md)
1. Phase 0: settle the baseline (commit or review the working tree, tag it).
2. Phase 1: test-harness integrity (DB-01, factories, FK assertion).
3. Phase 2: security hardening (SEC-01..07, SEC-09..13) with an auth-matrix test.
4. Phase 3: product, customer and vehicle backend (FE-02).
5. Phase 4: POS and credit UI on real data (FE-01, FE-04), plus a data-impact assessment.
6. Phase 5: sales returns, revenue and credit integrity (BIZ-03/04/05/12a/17, ARC-02).
7. Phase 6: purchases and supplier ledger integrity (BIZ-06/11/18/20).
8. Phase 7: warranty and scrap integrity (BIZ-07/10/12b/13/21).
9. Phase 8: HR and payroll integrity (BIZ-08/09/14/15/19/22, FE-03/06).
10. Phase 9: branch isolation, settings wiring, locale (SEC-08, BIZ-16, FE-07).
11. Phase 10: debt cleanup (ARC-03/04/06/07, FE-05/08, DB-02/03/04).
- Phases 5, 6, 7 and 8 are mutually independent after Phase 2 and can run in parallel branches. Phase 5 must follow Phase 4 only for its UI tasks.
