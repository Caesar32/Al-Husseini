# Owner Mobile App: refactor roadmap (index)

Scope: the Flutter owner app at the repo root (`lib/`, `test/`, `android/`, `ios/`), package `alhusseini_app`.
It is a read-only client of the owner API in `routes/api.php` (`App\Http\Controllers\Api\V1\Owner\*`).

**Contract source of truth:** the Laravel controllers and `app/Http/Resources/Owner/*`, not
`docs/OWNER_MOBILE_API_PLAN.md`. The plan doc is the original design and has drifted. For example, it says
`phone_or_email` where the server reads `login`, `instapay_sales` where the server sends `bank_transfer_sales`,
and it omits `exact`, `summary` and `pagination`. When the two disagree, the server wins. The plan doc gets a
"superseded" note in M5.

## Baseline (2026-10-17, `main` @ ff1b2cf)

| Metric | Value |
|---|---|
| Flutter / Dart | 3.29.0 stable (`environment.sdk: ^3.7.0`) |
| `dart analyze lib` / `flutter analyze lib` | 0 issues (lint set: `flutter_lints` 5 defaults, no extra rules) |
| `flutter test` | 1 passed (`test/widget_test.dart`, a trivial `expect(true, isTrue)` smoke test) |
| Dart LOC in `lib/` | 5,444 in 46 files; largest: `login_screen.dart` 515, `live_pulse_tab.dart` 426, `invoice_detail_sheet.dart` 354, `recent_invoice.dart` 290 |
| Real test coverage | none (no model, repository, provider or widget tests) |
| Endpoints called | 12 of 12 constants defined; `sales/returns`, `auth/me`, `auth/device-token` are wired in repositories but never reached from the UI |

## Findings by severity (details in each phase file)

| ID | Sev | Finding | Phase |
|---|---|---|---|
| F-01 | P0 | `ScrapBatteryDetail.fromJson` casts `capacity_ah as num?`, but the column is `string(20)` (`'70Ah'`). Every invoice with a scrap battery fails to open its detail sheet with a `TypeError` | M1 |
| F-02 | P0 | A 401 response clears storage in `ApiService`, but `AuthProvider` stays `authenticated`: the user is not sent to login, and the 10 s live poll keeps sending unauthenticated requests. Server-side revocation (`OWNER_ACCESS_REVOKED`) hits this path | M0/M2 |
| F-03 | P1 | Returns feed parses `ReturnResource` as `InvoiceSummary`: `final_amount`, `payment_method`, `items_count` do not exist there, so amounts read 0; `refunded_amount`, `original_amount`, `returned_items` are dropped | M1 |
| F-04 | P1 | Invoice detail reads the deprecated `scrap_battery` (one item) instead of `scrap_batteries` (list); ignores `scrap_deduction_amount`, `refunded_amount`, item `returned_quantity`. The server keeps `scrap_battery` only until the app migrates | M1 |
| F-05 | P1 | Lists ignore `pagination` (`has_more`, `last_page`): invoices and inventory show page 1 only, with no load-more | M2 |
| F-06 | P1 | Token stored in plain `SharedPreferences` (90-day Sanctum token with full owner read access) | M4 |
| F-07 | P1 | The login screen lets anyone change the API base URL to any value, including `http://`; the bearer token is then sent there. Needs a debug-only gate or an https allow-list | M4 |
| F-08 | P2 | Numeric parsing is untyped (`(json['raw'] ?? 0.0).toDouble()` on `dynamic`, `json['x'] ?? 0` assigned to `int`); a string or a double from the server throws at runtime. No shared parse helpers | M1 |
| F-09 | P2 | No distinct handling for 429 (login throttle `owner-login`), 404, 5xx or non-JSON bodies; `catch (e)` produces messages like `حدث خطأ غير متوقع: FormatException…` | M0 |
| F-10 | P2 | Provider state is inconsistent: seven hand-rolled `isLoading`/`error` pairs; background poll errors are swallowed with no stale indicator; `refreshProfile` swallows all errors | M2 |
| F-11 | P2 | `credit_limit` is always present (`raw: 0` when unset), so `DebtorItem.creditLimit` is `0.0`, never `null`, and the UI may render a 0 limit | M1 |
| F-12 | P2 | `device_preview` is a runtime dependency (it ships in the release APK) | M5 |
| F-13 | P3 | Monolithic screens (`login_screen.dart` 515 lines, `live_pulse_tab.dart` 426) mix layout, dialogs and state access | M3 |
| F-14 | P3 | `branch_id` is sent to `inventory/alerts` (ignored server-side) and not to `credit/overview` (also ignored). Harmless today (one branch), but it should be documented, not implied | M2 |

## Phases

| Phase | File | Goal | Status |
|---|---|---|---|
| M0 | `M0_BASELINE_AND_NETWORKING.md` | Typed failures, a single 401 path, 429/5xx/non-JSON handling, injectable client | Done |
| M1 | `M1_MODELS_AND_SERIALIZATION.md` | Null-safe parse helpers; fix F-01, F-03, F-04, F-08, F-11; model unit tests from real payloads | Done |
| M2 | `M2_REPOSITORIES_AND_PROVIDERS.md` | Uniform `AsyncState`, pagination, poll lifecycle, session-expiry propagation | Done |
| M3 | `M3_PRESENTATION_REFACTOR.md` | Split monolithic screens into widgets with shared loading/error/empty states | Done |
| M4 | `M4_SECURITY_AND_PERSISTENCE.md` | Secure token storage, base-URL lock, offline/stale indicator, rate-limit UX | Not started |
| M5 | `M5_QUALITY_AND_TESTING.md` | Provider and widget tests, stricter lints, release APK build gate | Not started |

Order: M0 → M1 → M2 → (M3 ∥ M4) → M5. The F-01 fix (M1-01) is small and isolated; it can ship first as a hotfix.

## Quality gates (every card, every commit)

1. `flutter analyze` reports 0 issues (the baseline is 0; never add an `// ignore:` to pass).
2. `flutter test` passes, and the test count never goes down.
3. No change to `routes/api.php`, controllers or resources from a mobile card. A server contract change is its own card, needs owner approval, and runs `php artisan test --compact`.
4. No fake or mock data in `lib/` (test fixtures under `test/fixtures/` are copied from real resource output shapes).
5. User-facing strings stay Arabic. Money is shown from the server's `formatted`/`exact`/`compact` strings; `raw` is used only for logic.
6. One card = one concern = one commit, staged by explicit path, with the `Co-Authored-By` trailer.

## Mobile Card template (for delegation)

```
CARD <phase>-<nn>: <title>
FILE(S): <one file preferred; max 2>
CONTEXT: <the exact server JSON shape / current code excerpt, trimmed>
CHANGE: <precise behaviour to implement>
CONSTRAINTS: null-safe; no new packages unless listed; Arabic UI strings; match surrounding style
ACCEPTANCE: <test file + cases that must pass>; flutter analyze clean
OUT OF SCOPE: <what must not be touched>
OUTPUT: unified diff or full single-file replacement, nothing else
```

The orchestrator reviews each diff, applies it, runs the gates, and commits.

## Card log

Implementer: Codex `gpt-5.6-luna` at `high` effort, read-only sandbox (`luna-5.5` is not offered for this account). The orchestrator reviews, runs the gates and commits.

| Card | Result | Tests after |
|---|---|---|
| M1-01 | Done: scrap `capacity_ah` read as string; `scrap_batteries` list (legacy fallback); sheet renders each | 5 |
| M0-01, M0-04 | Done: sealed `ApiFailure` hierarchy (Arabic messages); `requireDataMap/List`, `requireBodyMap`, `readPagination` + `PaginationMeta` | 22 |
| M0-02, M0-03 | Done: `ApiService._send` maps 401/403/404/422/429/5xx/non-JSON/socket/TLS to `ApiFailure`; awaited `clearAll` + `onUnauthorized` stream on 401 | 35 |
| M1-02 | Done: `json_read.dart` tolerant readers + `Money` value class | 93 |
| M1-03, M1-04 | Done: sales models on readers; amounts are `Money` (server display strings); detail adds scrap deduction, refunded, item returned qty; new `ReturnSummary` | 98 |
| M1-05 | Done: auth, cash drawer, credit, inventory, live pulse, periods, staff models on readers; `creditLimit` null when raw ≤ 0 (F-11); adds `utilizationRate`, `depletionRatio`, `hasActiveShift` | 120 |
| M1-06 | Folded: `PaginationMeta` shipped in M0-04; `Paged<T>` moves to M2-04 | — |
| M2-01 | Done: sealed `AsyncState<T>` (idle/loading/data/failure, keeps previous value) + `toApiFailure`; `fake_async` dev dependency | 123 |
| M2-02 | Done: `AuthProvider` listens to `onUnauthorized` → unauthenticated + session-expired flag; app-level guard stops polling and routes to login with a SnackBar; splash validates the token through `auth/me` (offline keeps the cached user); `ApiService` decodes UTF-8 bytes (Laravel sends no charset) | 129 |
| M2-03 | Done: `DashboardProvider` on `AsyncState` (legacy getters kept as shims); poll pauses in background, resumes on foreground; background failures keep data + `lastPollFailure`; 401 stops polling; `isStale` | 137 |
| M2-04 | Done: `Paged<T>`; sales repository returns pages (returns use `ReturnSummary`, F-03 data side); `SalesProvider` load-more for invoices and returns with dedupe and `loadMoreError`; legacy getters kept | 148 |
| M2-05, M2-06 | Done: inventory paginated (`loadMoreAlerts`, no `branch_id`: F-14); cash drawer, staff, credit providers on `AsyncState`; all repositories use envelope helpers | 165 |
| M2-07 | Done: selected branch persisted through `AuthStorage` (cleared on logout/401) | 168 |
| M3-01, M3-02 | Done: `AsyncStateView`, `ErrorRetryCard`, `EmptyStateCard`, `StaleDataBanner`, `PagedListView` (pull-to-refresh, load-more, error footer) | 176 |
| M3-03 | Done: inventory (paged), credit, staff, cash drawer tabs render through `AsyncStateView`; drawer shows a no-open-shift notice; credit shows utilization rate | 181 |
| M3-04, M3-08 | Done: sales tab paginates; new Returns segment (`ReturnTile`; closes F-03 in the UI); detail failures show a SnackBar. File is 400 lines (over target; split in M3-09) | 182 |
| M3-05, M4-05 | Done: live pulse tab 426 → 128 lines, six widgets in `widgets/pulse/`; live and period sections through `AsyncStateView`; stale/offline banner. Review caught Codex swapping safe cash `exact`→`formatted` (restored) | 185 |
| M3-06 | Done: invoice sheet 354 → 95 lines, five sections in `widgets/invoice/`; new payments section; field-usage diff verified identical | 186 |
| M3-07, M4-02, M4-03 | Done: login 515 → 225 lines (`LoginForm`, `ServerSettingsSheet`); server setting only in debug builds, https-only when insecure is not allowed, URL change clears the session (F-07); 429 cooldown countdown on the login button. Residual: a custom http URL saved by an older build is still honoured (M4-06) | 190 |
| M3-09 | Done: tiles moved to `widgets/{sales,drawer,credit,staff}/`; screens now ≤ 230 lines except `cash_drawer_tab.dart` (264) | 190 |
