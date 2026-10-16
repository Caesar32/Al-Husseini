# Phase 7: Final verification

Status tracker: `REFACTOR_PLAN.md`. Requires Phases 0-6.

## Rules
- Shell commands in this file use Git Bash syntax, not PowerShell.
- Verification only: no feature work. If a check fails, fix it in the phase that caused it and record the fix there.
- Run Pint only on files changed since `PHASE0_SHA`. Never format the whole repo.
- Never use `git stash`. Don't commit or push unless the owner asks.

## Objective
Prove the roadmap changed structure without changing behaviour, apart from the documented fixes for B1-B6 and DB-04.

## Checks
| # | Check | Command / method | Pass condition |
|---|---|---|---|
| 1 | Full suite | `php artisan test --compact` | 0 failed, 0 skipped, passed > `N0` |
| 2 | Style on touched files (Git Bash) | `vendor/bin/pint --test $( (git diff --name-only <PHASE0_SHA> -- '*.php'; git ls-files --others --exclude-standard -- '*.php') | sort -u)` | exit 0 |
| 3 | Route authorization | `php artisan route:list --except-vendor --json` | every web route has `can:` except the documented guest/auth-only list (`docs/ai/routes.md`); every `api/v1/owner/*` route except login has `owner.valid` |
| 4 | Diagnostics | `php artisan system:diagnose --audit` | no new failures compared with `docs/refactor/baseline-diagnose.txt`; `stock_journal_consistency` passes |
| 5 | Single stock writer | `php artisan test --compact tests/Architecture` | passes |
| 6 | Migrations | on SQLite: `php artisan migrate:fresh --seed`, then roll back the new migrations and re-migrate | clean; `PRAGMA foreign_key_check` empty |
| 7 | XSS sinks | `grep -rn "{!!" resources/views` | no `json_encode` without HEX flags |
| 8 | Hard-coded URLs | ``grep -rnE "(fetch\(|action\s*=)\s*['\"\`]/admin/" public/assets/js/*.js resources/views`` | no hits |

## Codex prompt (read-only audit; run in `--sandbox read-only`)
```text
[TASK: Phase 7 - Audit]
ROLE: Strict Code Reviewer. Read-only.
ACTION: Verify refactor invariants; report violations only.
TARGET_FILE: files from `git diff <PHASE0_SHA> --stat` (committed + uncommitted) plus `git ls-files --others --exclude-standard`; only those files.
CHANGES_REQUIRED:
1. Report any test file where an assertion, test, or dataset was removed or weakened (compare against <PHASE0_SHA>).
2. Report any write to products.current_stock outside app/Services/Catalog/StockLedgerService.php (ignore the dead observers, Product $fillable/$casts, ImportLegacyDataCommand creation with 0).
3. Report any `app(...Service::class)` in app/Services or app/Http/Requests, excluding app/Services/Diagnostics/.
4. Report any DB::transaction-scoped dispatch of a ShouldQueue job lacking afterCommit.
5. Report any innerHTML assignment containing ${...} of server data without esc()/textContent in files changed since <PHASE0_SHA>.
OUTPUT_CONSTRAINT: Return ONLY a list `file:line - violation`, or `NONE`. Final line: STATUS: <one line>.
VERIFICATION: n/a (read-only).
```

## Manual smoke test (staging copy, browser)
- [ ] POS sale three ways: full payment, partial payment and credit. Check the invoice, the payments, the `stock_movements` row and the customer balance.
- [ ] A partial sales return: stock is back and a `sale_return` movement exists.
- [ ] A warranty replacement: `warranty_replacement` and `warranty_return` movements exist.
- [ ] A purchase plus a purchase return: the supplier ledger chain is intact and WAC is unchanged compared with before.
- [ ] Each surface, the admin dashboard and the owner app, shows the same KPIs as its own Phase 0 screenshot for the same data. The two surfaces differ from each other on purpose: "week", cash and low stock are defined differently.
- [ ] Owner app: log in, deactivate the user in the admin UI, and the next API call returns 401.
- [ ] Purchase create search and warranty verify do not run injected markup.
- [ ] The HR, payroll, notification and global search flows work. These are the pages whose URLs changed in Phase 5.

## Close-out
- [ ] Add a "Refactor roadmap" batch section to `docs/ai/REMEDIATION_EXECUTION_REPORT.md`: commits, test counts `N0`..`N6`, deviations and open owner decisions.
- [ ] Update the "Current remediation status" and "Commands" sections of `CLAUDE.md`.
- [ ] Mark every phase done in `REFACTOR_PLAN.md`.
