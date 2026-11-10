# Phase 5. CI/CD and deployment

Registry IDs: DEP-01 to DEP-06. Files: `.github/workflows/deploy.yml`, `.github/workflows/ci.yml`.

## 1. What the pipeline does today

| Step | Behaviour | Where |
|---|---|---|
| Trigger | Every push to `main` **or** `remediation/2026-10` | `deploy.yml:3-7` |
| CI | Separate workflow on the same push; deploy does not wait for it | `ci.yml:3-7` |
| Remote | `artisan down`, `git fetch` + `git reset --hard`, `composer install --no-dev`, `migrate --force`, clear and rebuild caches, `artisan up` | `deploy.yml:44-56` |
| On error | `trap 'php artisan up' ERR` | `deploy.yml:44` |
| After | Prints a success line. No HTTP check. | `deploy.yml:57` |

About the title "zero downtime": the current design is an in-place update behind maintenance mode, so each deploy has a short outage by design. Real zero downtime on Hostinger needs release folders and a symlink switch, and still cannot cover a migration that breaks the old code. That is an owner decision (registry question 3). The plan below makes the existing design safe first.

## 2. Phase 5a: three changes before any other push (High)

**Status 2026-11-10: applied in the working tree, not committed, not yet run on GitHub.** The form applied differs from the sketch below in one way: the steps were not moved. `deploy.yml` is now a reusable workflow (`on: workflow_call`), and the `deploy` job in `ci.yml` calls it with `needs: tests`, the `main`-push condition, `concurrency: production-deploy` (no cancel) and `secrets: inherit`. Two more changes came with it: the CI-level `cancel-in-progress` now applies to pull requests only, and the server fetches `github.sha` (the tested commit) instead of the branch tip. The first real push must be watched: check that the deploy job starts after the tests and that `git fetch` of a commit id works on the server.

### DEP-01. Remove the old branch from the trigger

```diff
--- .github/workflows/deploy.yml
@@ -5,3 +5,2 @@
       - main
-      - remediation/2026-10
```

### DEP-02. Deploy only after the tests pass

Simplest safe form: make deploy a job of the CI workflow, so it needs the test job and uses the same commit.

```diff
--- .github/workflows/ci.yml
@@ -74,1 +74,8 @@
         run: php artisan test
+
+  deploy:
+    needs: tests
+    if: github.event_name == 'push' && github.ref == 'refs/heads/main'
+    concurrency: production-deploy
+    runs-on: ubuntu-latest
+    steps: # moved unchanged from deploy.yml
```

Then delete the `push` trigger from `deploy.yml` (keep the file only if a manual `workflow_dispatch` deploy is wanted). Two details:

- `ci.yml:9-11` cancels a running workflow when a newer push arrives. With deploy inside it, a cancel could stop a deploy halfway. Set `cancel-in-progress: false` for pushes to `main`, or keep the cancel rule on pull requests only.
- A `workflow_run` trigger is the other option. It needs an explicit `conclusion == 'success'` check and must deploy `github.event.workflow_run.head_sha`, not the branch tip. The job form above avoids both traps.

### DEP-03. One deploy at a time

Covered by `concurrency: production-deploy` in the diff above. Do not set `cancel-in-progress` on it.

## 3. Phase 5b: health check, rollback, host key (Medium)

### DEP-04 and DEP-05. Record the old commit, back up, check, roll back

Replace the remote script body (`deploy.yml:43-57`) with this shape:

```bash
set -e
cd "$TARGET"
PREV=$(git rev-parse HEAD)
rollback() {
  echo "Deploy failed, restoring $PREV"
  git reset --hard "$PREV"
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
  php artisan package:discover --ansi
  php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
  php artisan up
  exit 1
}
trap rollback ERR
php artisan down || true
# optional, owner decision 4: mysqldump to a dated file outside the web root, keep the last 5
git fetch ... && git reset --hard FETCH_HEAD
composer install ...
php artisan package:discover --ansi
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
curl --fail --silent --retry 5 --retry-delay 2 https://alhusseini.shop/up
```

Notes:

- `cd` now comes before the trap, so a wrong path cannot run `artisan up` somewhere else.
- The rollback restores **code** only. A migration that already ran stays applied. That is why the guard in section 4 requires migrations that the previous code can live with.
- Health check target: `/up` is Laravel's health route and is enabled (`bootstrap/app.php:12`). `/admin/pos` needs a login, so an anonymous request answers with a redirect, not 200; it cannot be the automated check. A second anonymous check can be `curl --fail https://alhusseini.shop/login` (confirm the exact login URL from `routes/web.php`). The POS page itself is checked by hand, see section 6.
- `/up` behaviour during maintenance mode and on the Hostinger setup was not tested in this audit. Try the curl line once by hand on the server before relying on it.

### DEP-06. Verify the server's host key

```diff
--- .github/workflows/deploy.yml
@@ -36,3 +36,4 @@
           chmod 600 ~/.ssh/id_rsa
+          echo "$SSH_KNOWN_HOSTS" > ~/.ssh/known_hosts
-          ssh -o StrictHostKeyChecking=no \
+          ssh -o StrictHostKeyChecking=yes \
```

Needs a new repository secret `SSH_KNOWN_HOSTS` (output of `ssh-keyscan -p <port> <host>`, compared with the key shown in the Hostinger panel). Owner action.

## 4. Before each push to `main`

| # | Check | How |
|---|---|---|
| 1 | Quality gate | Phase 4, section 6 |
| 2 | Only intended files staged | `git status`, `git diff --cached --stat`; stage explicit paths, never `git add -A` or a folder |
| 3 | Migration is safe for the old code | New columns nullable or with a default; no column dropped or renamed in the same release as the code that stops using it; `down()` exists |
| 4 | Migration runs on MySQL, not only SQLite | `php artisan migrate --pretend` on the dev MySQL. The `2026_10_01_*` and `2026_10_16_*` migrations are still pending there. |
| 5 | No data command hidden in a migration | `suppliers:rebuild-ledger-balances --apply`, `products:convert-oils-to-liters`, `app:import-legacy-data` run only by hand with the owner's approval |
| 6 | No secret in the diff | `.env`, `MANAGER_OVERRIDE_CODE_HASH`, keys |
| 7 | Shop is not mid-sale | Push outside opening hours while deploys still use maintenance mode |
| 8 | Owner asked for the push | Standing rule: no commit or push without a request |

## 5. Commit standard

Format already used in the history: `type(scope): summary` in the imperative, lower case.

| Type | Use |
|---|---|
| `fix` | Defect from the registry; put the ID in the body (`Refs: INT-01`) |
| `feat` | New behaviour |
| `test` | Tests only |
| `ci` | Workflow files |
| `docs` | Documentation |
| `refactor` | No behaviour change |

One registry ID per commit where possible. Each commit keeps the suite green. End every message with the `Co-Authored-By` trailer. Suggested order of commits: `ci` (DEP-01, DEP-02, DEP-03, CI-01), `fix(pos)` INT-01 with its tests, `test(pos)` TST-01, `fix(pos)` UI-01 and UI-02, `ci` DEP-04 to DEP-06.

## 6. After each deploy

| # | Check | Pass when |
|---|---|---|
| 1 | Workflow log | Ends with the success line; the curl step passed |
| 2 | `https://alhusseini.shop/up` | 200 |
| 3 | Log in as a cashier, open `/admin/pos` | Page loads, catalog paints, console has no error |
| 4 | Scan or type one barcode | Product is found |
| 5 | `php artisan migrate:status` on the server | No pending migration |
| 6 | `storage/logs/laravel.log` | No new error since the deploy |
| 7 | Owner app | Dashboard still loads from `/api` |

## 7. Rollback playbook

| Situation | Action |
|---|---|
| Deploy script failed | The trap restores the previous commit and brings the site up. Read the log, fix forward. |
| Deploy passed, screen is broken, no migration in the release | `git revert <sha>` on `main` and push (through the gate). The pipeline deploys the revert. |
| Deploy passed, a migration is part of the problem | Do not run `migrate:rollback` blindly: it can drop data written since the deploy. Prefer a new forward migration. Roll back only an additive migration whose columns are still empty, and only with the owner's approval. |
| Data is damaged | Site into maintenance mode, restore the dump taken before the migration, re-enter the sales made since from the printed receipts. This needs the backup from owner decision 4; without it there is no recovery path. |
| Pipeline itself is broken | On the server: `git reset --hard <PREV>`, `composer install --no-dev`, rebuild caches, `php artisan up`. |

Never use `git push --force` on `main`: the server follows the branch with `git reset --hard`.

## 8. Done when

- A commit with a failing test does not reach production (prove it once on a throwaway branch and pull request, not on `main`).
- Two quick pushes produce two deploys in sequence.
- A forced failure after `git reset` (tested on a staging copy if one exists, otherwise reviewed line by line with the owner) ends with the previous commit live.
- The health check step is green on a normal deploy.
