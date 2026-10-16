# M2: Repositories and providers

Files: `lib/data/repositories/*.dart` (7), `lib/providers/*.dart` (7), `lib/main.dart`.

## Current state
- Repositories cast `response['data'] as Map<String, dynamic>` directly. A malformed body throws a `TypeError` into the provider, which then shows `e.toString()`.
- `SalesRepository.getRecentReturns` returns `List<InvoiceSummary>` (wrong model, F-03). `fetchRecentReturns` is never called by the UI.
- No repository returns pagination (F-05). `SalesProvider.fetchRecentInvoices(page:)` replaces the list instead of appending.
- Providers each hand-roll `isLoading`/`error` pairs (Sales has three sets). Error strings come from `e.toString()`.
- `DashboardProvider` polls every 10 s through `Timer.periodic`. Background errors are swallowed with no "stale since" signal. Polling is not paused when the app is backgrounded, and not stopped on 401 (F-02).
- `AuthProvider` reads the token synchronously at construction and never validates it (`auth/me` is never called on startup). `refreshProfile` does `catch (_) {}`.
- The branch id lives in `DashboardProvider._selectedBranchId`, while `AuthStorage` has an unused `selected_branch_id` key. The server ignores `branch_id` on `inventory/alerts` and `credit/overview` (F-14).

## Target
- `AsyncState<T>` (sealed: `Idle`, `Loading(previous?)`, `Data(value, fetchedAt)`, `Failure(ApiFailure, previous?)`) in `lib/providers/async_state.dart`. Keeping `previous` lets the UI show stale data with a banner instead of blanking the screen.
- Repositories return typed results and use the `requireData*`/`readPagination` helpers from M0. Returns use `ReturnSummary` (M1-04).
- Paged providers (`SalesProvider` invoices and returns, `InventoryProvider`) expose `loadFirst()`, `loadMore()`, `hasMore`, and guard against concurrent loads.
- Session expiry: `AuthProvider` listens to `ApiService.onUnauthorized` → `status = unauthenticated` → the root navigator returns to `LoginScreen` (a single listener in `main.dart`/splash). `DashboardProvider.stopLivePolling()` runs on logout and expiry.
- Poll lifecycle: pause on `AppLifecycleState.paused`, resume plus an immediate fetch on `resumed`. Expose `lastSyncTime` and `isStale` (no success for more than 3 intervals).
- Startup: if a token exists, call `auth/me` once. On 401 go to login; on a network failure keep the cached user (offline-tolerant).
- Branch: one `BranchSelection` source of truth (persisted through `AuthStorage`); send `branch_id` only to endpoints that honour it (live, periods, recent invoices, returns, current shift, attendance).

## Cards
| Card | File | Change | Acceptance |
|---|---|---|---|
| M2-01 | `providers/async_state.dart` (new) | Sealed state | Unit test |
| M2-02 | `auth_provider.dart`, `main.dart` | Subscribe to `onUnauthorized`; validate the token through `me` at startup; no silent catch | Provider test with a fake repository: a 401 flips the state |
| M2-03 | `dashboard_provider.dart` | `AsyncState`; lifecycle-aware polling; stop on unauthenticated; `isStale` | Test with `fake_async`: poll cadence, pause/resume, stop on 401 |
| M2-04 | `sales_repository.dart`, `sales_provider.dart` | `Paged<InvoiceSummary>`, `Paged<ReturnSummary>`, `loadMore` | Provider test: append, `hasMore` false stops |
| M2-05 | `inventory_repository.dart`, `inventory_provider.dart` | Pagination, drop the client-side branch param | Provider test |
| M2-06 | `cash_drawer_*`, `staff_*`, `credit_*` | `AsyncState` migration (one card each) | Provider test each |
| M2-07 | `auth_storage.dart`, `dashboard_provider.dart` | Persisted branch selection | Test |

New dev dependency (needs approval): `fake_async` (test-only; part of the Flutter SDK's transitive set).

## Exit criteria
No provider exposes a raw `String? error` built from `e.toString()`. A 401 anywhere lands on the login screen within one event loop turn. Lists paginate.
