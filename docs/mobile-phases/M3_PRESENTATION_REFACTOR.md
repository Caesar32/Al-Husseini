# M3: Presentation refactor

Files: `lib/presentation/screens/**`, `lib/presentation/widgets/**`. Depends on M2 (`AsyncState`).

## Current state (largest first)
| File | Lines | Problems |
|---|---|---|
| `screens/login_screen.dart` | 515 | Form, animated background, the base-URL settings dialog (lines ~380-500) and the dev URL presets in one widget |
| `screens/tabs/live_pulse_tab.dart` | 426 | KPI grid, shift card, attendance, alerts, period selector in one `build` |
| `widgets/invoice_detail_sheet.dart` | 354 | Header, financials, items, scrap, payments inline; formats raw doubles itself (`'${scrapValue} ج.م'`) instead of using server strings |
| `screens/tabs/cash_drawer_tab.dart` | 240 | |
| `screens/tabs/sales_stream_tab.dart` | 234 | Search, branch selector, list, no load-more |
| `screens/tabs/inventory_alerts_tab.dart` | 200 | |
| `screens/tabs/credit_receivables_tab.dart` | 193 | |
| `screens/main_surveillance_layout.dart` | 183 | Tab shell, drawer, logout navigation |

Each tab repeats its own loading spinner, error box with retry, and empty state.

## Target
- Shared state widgets in `widgets/state/`: `AsyncStateView<T>` (loading / error with retry / empty / data, plus a stale banner when `previous != null`), `ErrorRetryCard`, `EmptyStateCard`, `StaleDataBanner`.
- `PagedListView<T>` with load-more on scroll end (M2 paged providers).
- Decomposition (one card per extraction, behaviour-identical):
  - Login: `LoginForm`, `ServerSettingsSheet` (moved; gated in M4), `LoginBackground`.
  - Live pulse: `SafeCashCard`, `SalesTodayCard`, `ActiveShiftCard`, `AttendancePulseCard`, `CriticalAlertsRow`, `PeriodMetricsSection`.
  - Invoice sheet: `InvoiceHeaderSection`, `InvoiceFinancialsSection` (adds scrap deduction and refunded), `InvoiceItemsSection` (returned qty badge), `ScrapBatteriesSection` (list), `InvoicePaymentsSection`.
  - Sales tab: add a Returns segment backed by `ReturnSummary`, so the owner sees today's returns (the plan's "return audit"; the dashboard already counts them).
- Rule: widgets render server `formatted`/`exact` strings; no money formatting in widgets.

## Cards
M3-01 shared state widgets → M3-02 `PagedListView` → M3-03..M3-07 one extraction per file above → M3-08 Returns segment. Each extraction card must pass analyze, keep the existing widget tests green, and add a golden-free widget test (finds key texts for a fixture state).

## Exit criteria
No screen file over ~250 lines. Every tab renders through `AsyncStateView`. The invoice sheet shows all `financials` the server sends.
