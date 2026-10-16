# M1: Models and serialization

Files: `lib/data/models/*.dart` (8 files). Server shapes come from `app/Http/Resources/Owner/*` and the owner controllers.

## Contract audit (server → model)

| Endpoint | Server shape (actual) | Model | Status |
|---|---|---|---|
| `POST auth/login` | `data.{token, user{id,name,email,phone,role,avatar_url,branch{id,name}}}` | `AuthUser` | OK (phone/email may be null → `''`) |
| `GET auth/me` | `data.{…, branch{id,name,code}}` | `AuthUser` | OK; repository wired, UI never calls it |
| `GET dashboard/live` | `data.{safe_cash_now{raw,formatted,exact,label}, sales_today{…,invoices_count,compared_to_yesterday}, active_shift{is_open, cashier_name?, opened_at?, duration_hours?}, workshop_attendance, critical_alerts}` | `LivePulseData` | OK; nulls fall back to defaults |
| `GET dashboard/periods` | `data.{period, period_label, revenue{raw,compact,exact}, total_sales_count, credit_collected{…}}` | `PeriodMetrics`, `MoneyValue` | OK |
| `GET sales/recent-invoices` | `data[]` `InvoiceSummaryResource` + `pagination{current_page,last_page,per_page,total,has_more}` | `InvoiceSummary` | Fields OK; **pagination dropped** (M2) |
| `GET sales/invoices/{id}` | `InvoiceDetailResource`: `financials.{subtotal,discount_amount,scrap_deduction_amount,tax_amount,final_amount,paid_amount,remaining_amount,refunded_amount}{raw,formatted}`, `scrap_batteries[]{capacity_ah:string, scrap_value, lead_weight_kg, status}`, deprecated `scrap_battery`, `items[]{…returned_quantity}`, `payments[]{id,payment_method,amount,formatted,created_at}`, `cashier{}`, `technician{}`, `branch{}` | `InvoiceDetail` | **F-01** `capacity_ah as num?` → TypeError; **F-04** uses deprecated single scrap, drops `scrap_deduction_amount`/`refunded_amount`/`returned_quantity`/server `formatted` strings |
| `GET sales/returns` | `ReturnResource`: `{id, invoice_number, customer_name, customer_phone, cashier_name, status, time, updated_at, refunded_amount{}, original_amount{}, returned_items[]{product_name, returned_quantity, unit_price, total_refunded, formatted_total}}` + pagination | parsed as `InvoiceSummary` | **F-03 wrong model** |
| `GET cash-drawer/current-shift` | `data.{cashier_name, shift_status(open/no_active_shift), opened_at?, duration?, opening_balance, cash_sales, card_sales, bank_transfer_sales, credit_collections_cash, expenses_paid, expected_drawer_cash, audit_notes, formatted{…}}` | `CashDrawerShift` | OK |
| `GET inventory/alerts` | `data[]{id,name,sku,barcode,category_name,is_battery,current_stock,reorder_threshold,depletion_ratio,status,status_label,cost_price{},retail_price{}}` + pagination | `InventoryAlertItem` | OK; `depletion_ratio` recomputed client-side; pagination dropped |
| `GET staff/today-attendance` | top-level `summary{…}` + `data[]` (not inside `data`) | `StaffAttendanceSummary.fromJson(response)` | OK (the envelope is irregular: keep parsing from root) |
| `GET credit/overview` | `data.{total_outstanding{raw,compact,exact}, collected_today{…}, debtors_count, top_debtors[]{id,name,phone,current_credit_balance{raw,compact,exact}, credit_limit{raw,formatted}, utilization_rate?}}` | `CreditOverviewData` | **F-11** `credit_limit` never null (raw 0 = no limit); `utilization_rate` dropped |
| `POST auth/device-token` | body `{device_token, platform: ios|android, device_name?}` | repository | OK (plan doc says `fcm_token`, but the server reads `device_token`) |

## Target
- `lib/data/models/json_read.dart`: `readInt`, `readDouble`, `readString`, `readBool`, `readMap`, `readList` with a default, accepting `num`/numeric `String`/`null`. Every `fromJson` uses them (F-08).
- `Money` value class `{raw, display}` from `{raw, formatted|exact|compact}`; replace the hand-rolled reads in `recent_invoice.dart`, `credit_overview.dart`, `inventory_alert.dart`.
- `ScrapBatteryDetail.capacityAh` becomes `String` (display as-is); add `leadWeightKg`, `status`. `InvoiceDetail.scrapBatteries: List<…>` read from `scrap_batteries`, falling back to `scrap_battery`.
- New `ReturnSummary` + `ReturnedItem` models for `sales/returns`.
- `PaginationMeta {currentPage, lastPage, total, hasMore}` and `Paged<T>`.
- Unit-test fixtures in `test/fixtures/*.json`, taken from the resource `toArray` shapes above (structure only, not real customer data).

## Cards
| Card | File | Change | Acceptance |
|---|---|---|---|
| M1-01 (hotfix) | `recent_invoice.dart` | `capacityAh` → `String` via `toString()`; read `scrap_batteries` list (fallback `scrap_battery`); update the one usage in `invoice_detail_sheet.dart` | `test/data/invoice_detail_test.dart`: payload with `capacity_ah: "70Ah"` parses; two scraps → 2 items |
| M1-02 | `json_read.dart` (new) | Tolerant readers | Unit tests: int/double/numeric string/null/garbage |
| M1-03 | `recent_invoice.dart` | Use readers; add `scrapDeductionAmount`, `refundedAmount`, item `returnedQuantity`, keep server `formatted` strings | Fixture test round-trip |
| M1-04 | `return_summary.dart` (new) | `ReturnSummary`/`ReturnedItem` | Fixture test |
| M1-05 | `credit_overview.dart`, `inventory_alert.dart`, `live_pulse_data.dart`, `period_metrics.dart`, `cash_drawer_shift.dart`, `staff_attendance.dart`, `auth_user.dart` | Migrate to readers (one file per card); `creditLimit` null when `raw <= 0`; add `utilizationRate`, `depletionRatio` | One fixture test per model |
| M1-06 | `pagination.dart` (new) | `PaginationMeta`, `Paged<T>` | Unit test |

## Exit criteria
Every model has a fixture test. No `as num?`/`as int` on a raw JSON value outside `json_read.dart`. `scrap_battery` is no longer read, which unblocks removing the deprecated key server-side (a separate owner-approved backend card).
