# Database Reference (migrations + Eloquent models)

Source: all 29 files in `database/migrations/` and all 32 files in `app/Models/` (as of 2026-09-30, including uncommitted working-tree changes).
Conventions below: `FK x -> table (onDelete)`; "dec(10,2)" = decimal(10,2); `id` + `timestamps` exist unless stated otherwise.
- Default DB (.env.example): `DB_CONNECTION=sqlite`; tests (phpunit.xml): sqlite `:memory:`. Later migrations have MySQL-specific branches (see "Later alterations").
- No model has `boot`/`booted` hooks, observers, or class constants. Only accessor: `JobTitle::getTitleNameAttribute`. Traits: SoftDeletes on Branch, Customer, Employee, Product, Supplier. HasFactory on Attendance, Branch, Department, Employee, EmployeeDeduction, EmployeeLeave, EmployeePayrollDebt, JobTitle, Payroll, PayrollItem, SalaryStructure, User (factories exist for all except EmployeePayrollDebt).

## 1. Auth / Users & permissions

### users — `App\Models\User` (extends Authenticatable)
- Created 0001_01_01_000000: name, email (unique), email_verified_at nullable, password, remember_token, timestamps.
- 2026_09_21_160001 adds: `branch_id` FK -> branches (nullOnDelete), nullable, after id; `is_active` bool default true.
- 2026_09_21_200001 adds: `phone` string(25) nullable, `avatar` string nullable.
- No soft deletes.
- Traits: HasFactory, Notifiable, HasRoles (spatie/laravel-permission).
- $fillable: branch_id, name, email, phone, password, avatar, is_active. $hidden: password, remember_token.
- Casts: email_verified_at datetime, password hashed, is_active boolean.
- Relations: branch() -> belongsTo -> Branch (branch_id). No inverse relations to Employee, Invoice (cashier), ledgers, etc. are defined on User.

### password_reset_tokens — no model
- email string PK, token, created_at nullable. No id/updated_at.

### sessions — no model
- id string PK, user_id nullable indexed (no FK constraint), ip_address(45), user_agent text, payload longText, last_activity int indexed.

### Spatie permission tables (2026_09_21_153245) — models from package (Spatie\Permission\Models\Role/Permission), none in app/Models
- Names from config/permission.php: permissions, roles, model_has_permissions, model_has_roles, role_has_permissions. `teams` = false; `model_morph_key` = model_id; no `permission.testing` key in config, so no team column on roles.
- permissions / roles: bigIncrements id, name, guard_name, timestamps; unique (name, guard_name).
- model_has_permissions: permission_id FK -> permissions (cascade), model_type, model_id; PK (permission_id, model_id, model_type); index (model_id, model_type).
- model_has_roles: same shape with role_id FK -> roles (cascade).
- role_has_permissions: permission_id + role_id, both FK cascade; PK (permission_id, role_id).
- Migration clears the permission cache at the end of up().

### notifications — no model (Laravel DatabaseNotification via Notifiable)
- uuid id PK, type, morphs notifiable (notifiable_type, notifiable_id + index), data text, read_at nullable.

## 2. Organisation / HR

### branches — `Branch`
- name string(150) unique; code string(50) unique; phone(30) nullable; address(255) nullable; is_active bool default true, indexed; soft deletes.
- Casts: is_active boolean.
- Relations: users() hasMany User; employees() hasMany Employee; invoices() hasMany Invoice; payrolls() hasMany Payroll; scrapBatteries() hasMany ScrapBatteriesInventory (branch_id). No relation for purchase_invoices / warranty_claims although both have branch_id.

### departments — `Department`
- name(100) unique; code(50) unique. No soft deletes.
- Relations: jobTitles() hasMany JobTitle.

### job_titles — `JobTitle`
- department_id FK -> departments (cascadeOnDelete); title(100); min_salary / max_salary dec(10,2) default 0; unique (department_id, title).
- Casts: min/max_salary decimal:2. `$appends = ['title_name']`; accessor getTitleNameAttribute returns `title ?? ''`.
- Relations: department() belongsTo Department; employees() hasMany Employee.

### employees — `Employee` (SoftDeletes)
- branch_id FK -> branches (restrict); job_title_id FK -> job_titles (restrict); user_id FK -> users nullable, UNIQUE (nullOnDelete).
- employee_code(30) unique; full_name(150); national_id(20) unique; phone(20) unique; hire_date date.
- shift_start_time time default 09:00:00; shift_end_time time default 17:00:00; grace_period_minutes usmallint default 15; zkteco_pin(50) nullable unique.
- status enum [active, on_leave, terminated] default active, indexed.
- Extra indexes (2026_09_23_000001): full_name; (branch_id, status); (branch_id, full_name).
- Gotcha: unique columns (employee_code, national_id, phone, zkteco_pin, user_id) remain occupied by soft-deleted rows.
- Casts: hire_date date, grace_period_minutes integer (shift times are NOT cast; strings).
- Relations: branch() belongsTo Branch; jobTitle() belongsTo JobTitle; user() belongsTo User; salaryStructures() hasMany SalaryStructure; currentSalary() hasOne SalaryStructure where is_current=true; attendances() hasMany Attendance; deductions() hasMany EmployeeDeduction; payrollDebts() hasMany EmployeePayrollDebt; leaves() hasMany EmployeeLeave; technicianInvoices() hasMany Invoice (technician_id); commissions() hasMany TechnicianCommission.
- Scopes: active() status='active'; technicians() whereHas jobTitle title LIKE '%فني%' OR '%كهربائي%' (orWhere inside the whereHas closure, so it is grouped within the subquery).

### salary_structures — `SalaryStructure`
- employee_id FK -> employees (cascade); basic_salary dec(10,2) required; housing_allowance / transport_allowance / other_allowances dec(10,2) default 0; effective_from date; effective_to date nullable; is_current bool default true indexed; index (employee_id, is_current).
- No DB constraint enforcing a single is_current row per employee.
- Casts: salary fields decimal:2, effective_from/to date, is_current boolean. Relations: employee() belongsTo Employee.

### employee_leaves — `EmployeeLeave`
- employee_id FK (cascade); leave_type enum [annual, sick, emergency, unpaid] default annual; start_date, end_date date; days_count usmallint default 1; reason text nullable.
- status enum [pending, approved, rejected] default pending indexed; actioned_by FK -> users nullable (nullOnDelete); action_notes text nullable.
- Indexes: (employee_id, start_date, status); (start_date, end_date, status) added 2026_09_23.
- Casts: start/end_date date, days_count integer. Relations: employee() belongsTo Employee; actionedByUser() belongsTo User (actioned_by).

### attendances — `Attendance`
- employee_id FK (cascade); work_date date; check_in / check_out datetime nullable; late_minutes, early_leave_minutes usmallint default 0; overtime_hours dec(4,2) default 0 (max 99.99).
- status enum [present, absent, late, excused, holiday] default present; source string(30) default 'zkteco'.
- Unique (employee_id, work_date); index (work_date, status).
- Casts: work_date date, check_in/out datetime, minutes integer, overtime_hours decimal:2.
- Relations: employee() belongsTo Employee; deductions() hasMany EmployeeDeduction (attendance_id).
- Scopes: lateToday() work_date=today & late_minutes>0; absentToday() status absent; presentToday() status present (note: 'late' status rows are not counted by presentToday).

### deduction_rules — `DeductionRule`
- name(100); type enum [lateness, absence, disciplinary, loan] indexed; calculation_method enum [fixed_amount, hourly_rate_multiplier, day_wage_multiplier] (no default); multiplier_value dec(6,2); description text nullable.
- Casts: multiplier_value decimal:2. Relations: deductions() hasMany EmployeeDeduction.

### employee_deductions — `EmployeeDeduction`
- employee_id FK (cascade); deduction_rule_id FK nullable (nullOnDelete); attendance_id FK nullable (nullOnDelete); deduction_date date; amount dec(10,2); reason text (required).
- approved_by FK -> users nullable (nullOnDelete); status enum [pending, approved, applied, cancelled] default pending indexed; index (employee_id, status, deduction_date).
- Casts: deduction_date date, amount decimal:2.
- Relations: employee(), deductionRule(), attendance() belongsTo; approvedByUser() belongsTo User (approved_by).

### payrolls — `Payroll`
- branch_id FK (restrict); year usmallint; month utinyint; total_basic / total_allowances / total_deductions / total_net dec(12,2) default 0.
- status enum [draft, reviewed, approved, disbursed] default draft indexed; approved_by FK -> users nullable (nullOnDelete); disbursed_at datetime nullable.
- Unique (branch_id, year, month) created in 160005 (default name `payrolls_branch_id_year_month_unique`).
- 2026_09_22_215442: checks `hasIndex('payrolls', 'payrolls_branch_year_month_unique')` — a different name from the one 160005 created, so the check is always false and a SECOND identical unique index `payrolls_branch_year_month_unique` is added (redundant, harmless). down() drops only the named one.
- Casts: year/month integer, totals decimal:2, disbursed_at datetime.
- Relations: branch() belongsTo; approvedBy() belongsTo User (approved_by); items() hasMany PayrollItem; commissions() hasMany TechnicianCommission.

### payroll_items — `PayrollItem`
- payroll_id FK (cascade); employee_id FK (restrict); basic_salary dec(10,2); total_allowance / total_deduction / total_overtime dec(10,2) default 0; net_salary dec(10,2) required; absent_days utinyint default 0; late_minutes_total usmallint default 0; unique (payroll_id, employee_id).
- 2026_09_29_150100 adds: debt_repayment dec(15,2) default 0 (after total_deduction), carried_debt dec(15,2) default 0 (after debt_repayment). Precision differs from other columns (15,2 vs 10,2).
- Casts: all money decimal:2, absent_days/late_minutes_total integer. Relations: payroll() belongsTo; employee() belongsTo.
- No hasMany back to employee_payroll_debts (source_payroll_item_id) is defined.

### employee_payroll_debts — `EmployeePayrollDebt` (2026_09_29_150000)
- employee_id FK (cascade); source_payroll_item_id FK -> payroll_items nullable (nullOnDelete); original_amount dec(15,2); paid_amount dec(15,2) default 0; settled_at timestamp nullable; index (employee_id, settled_at).
- Casts: amounts decimal:2, settled_at datetime.
- Relations: employee() belongsTo; sourcePayrollItem() belongsTo PayrollItem (source_payroll_item_id).
- Helper method (not accessor): remainingAmount(): float = max(0, original - paid).

### technician_commissions — `TechnicianCommission` (created in invoices migration 160008)
- employee_id FK (cascade); invoice_id FK (cascade); commission_amount dec(10,2) default 0; status enum [pending, approved, paid] default pending indexed; payroll_id FK nullable (nullOnDelete); unique (employee_id, invoice_id).
- Casts: commission_amount decimal:2. Relations: employee(), invoice(), payroll() belongsTo.

## 3. Sales / POS

### customers — `Customer` (SoftDeletes)
- name(150); phone(30) unique + separate index; national_id(30) nullable; credit_limit dec(10,2) default 5000.00; current_credit_balance dec(10,2) default 0; tier enum [standard, vip, fleet] default standard; is_active bool default true.
- Indexes added 2026_09_23: name; national_id; (is_active, name).
- Model `$attributes` mirrors DB defaults (credit_limit 5000, balance 0, tier standard, is_active true).
- Casts: credit_limit, current_credit_balance decimal:2; is_active boolean.
- Relations: vehicles() hasMany CustomerVehicle; invoices() hasMany Invoice; creditLedgers() hasMany CreditLedgerEntry; warranties() hasMany Warranty; warrantyClaims() hasMany WarrantyClaim (customer_id added 2026_09_23_100004).
- Helper: getLatestCreditLedgers(int $limit = 20) = creditLedgers ordered by id desc (method, not accessor despite `get` prefix; no `Attribute` suffix).
- Scopes: inDebt() balance>0; exceededLimit() balance > credit_limit AND credit_limit > 0.

### customer_vehicles — `CustomerVehicle`
- customer_id FK (cascade); plate_number(50) indexed; car_brand(50), car_model(50) required; model_year usmallint nullable; chassis_number(100) nullable; last_odometer uint nullable; notes text nullable; unique (customer_id, plate_number) — same plate may exist for different customers.
- Indexes 2026_09_23: chassis_number; (car_brand, car_model).
- Casts: model_year, last_odometer integer. Relations: customer() belongsTo; invoices() hasMany Invoice; warranties() hasMany Warranty.

### categories — `Category`
- name(100) unique; slug(100) unique. Relations: products() hasMany Product.

### products — `Product` (SoftDeletes)
- category_id FK (restrict); sku(50) unique + index; barcode(100) nullable unique + index; name(200); brand(100) required; capacity_ah string(20) nullable (string, e.g. "70Ah" — not numeric); voltage(20) default '12V'.
- terminal_type enum [regular, reverse, side] default regular; warranty_months usmallint default 12; cost_price, retail_price dec(10,2) required; wholesale_price nullable.
- current_stock uint default 0 (unsigned: MySQL rejects negative stock; SQLite does not enforce unsigned — UNVERIFIED how app guards this); reorder_threshold uint default 5; is_battery bool default true; is_active bool default true indexed.
- Indexes 2026_09_23: name; brand; (category_id, is_active); (is_battery, is_active, brand).
- Casts: warranty_months/current_stock/reorder_threshold integer; prices decimal:2; is_battery/is_active boolean.
- Relations: category() belongsTo; invoiceItems() hasMany InvoiceItem; purchaseInvoiceItems() hasMany PurchaseInvoiceItem; suppliers() belongsToMany Supplier via `supplier_products` using SupplierProduct pivot, withPivot [id, supplier_sku, last_purchase_price, min_order_qty, lead_time_days, is_primary_supplier] (not `notes`), withTimestamps; primarySupplier() = suppliers() wherePivot is_primary_supplier=true (BelongsToMany, may return many); warrantyClaims() hasMany WarrantyClaim (replacement_product_id).
- Scopes: batteriesOnly(); active(); lowStock() current_stock <= reorder_threshold.

### invoices — `Invoice`
- invoice_number(50) unique + index; branch_id FK (restrict); customer_id FK nullable (nullOnDelete); customer_vehicle_id FK nullable (nullOnDelete); technician_id FK -> employees nullable (nullOnDelete); cashier_id FK -> users (restrict).
- subtotal dec(10,2) required; discount_amount, scrap_deduction_amount, tax_amount dec(10,2) default 0; final_amount, paid_amount dec(10,2) required (no default); remaining_amount default 0.
- payment_method enum [cash, card, bank_transfer, credit, split] default cash.
- status enum originally [paid, partially_paid, unpaid, cancelled, refunded] default paid indexed; `partially_refunded` added by 2026_09_30_000001 (see Later alterations). Code writing it: PosOrderService (return flow).
- notes text nullable; index (branch_id, created_at); 2026_09_23 adds (customer_id, created_at), (technician_id, created_at).
- No soft deletes.
- Casts: all money fields decimal:2 (status/payment_method not cast).
- Relations: branch(), customer(), customerVehicle() belongsTo; technician() belongsTo Employee (technician_id); cashier() belongsTo User (cashier_id); items() hasMany InvoiceItem; scrapBattery() hasOne ScrapBatteriesInventory; creditEntries() hasMany CreditLedgerEntry; technicianCommission() hasOne TechnicianCommission; payments() hasMany InvoicePayment.
- Mismatch: scrapBattery() and technicianCommission() are hasOne, but the DB allows many (scrap invoice_id not unique; commission unique is (employee_id, invoice_id), not invoice_id alone).
- No relation to warranty_claims.replacement_invoice_id on Invoice.

### invoice_items — `InvoiceItem`
- invoice_id FK (cascade); product_id FK (restrict); quantity uint default 1; unit_price, total_price dec(10,2); battery_serial_number(100) nullable indexed (not unique); warranty_duration_months usmallint default 12.
- Index 2026_09_23: (product_id, created_at).
- Casts: prices decimal:2; quantity, warranty_duration_months integer.
- Relations: invoice(), product() belongsTo; warranty() hasOne Warranty (invoice_item_id).

### invoice_payments — `InvoicePayment` (2026_09_23_100003)
- invoice_id FK (cascade); payment_method enum [cash, card, bank_transfer, credit] indexed, no default (no 'split' — split invoices are stored as several rows); amount dec(10,2); transaction_reference(100) nullable indexed; notes string(255) nullable; index (invoice_id, payment_method).
- Casts via `$casts` property: amount decimal:2.
- Relations: invoice() belongsTo Invoice.
- Scopes (new, uncommitted): active() = whereHas invoice status NOT IN [cancelled, refunded, partially_refunded] — gotcha: payments of partially refunded invoices are excluded entirely, not reduced; cash() = payment_method != 'credit' (i.e. includes card and bank_transfer, not only cash — name is misleading).

### credit_ledger_entries — `CreditLedgerEntry`
- customer_id FK (cascade); invoice_id FK nullable (nullOnDelete); entry_type enum [invoice_debt, payment_collection, credit_adjustment, refund] indexed; amount, balance_before, balance_after dec(10,2) required.
- collected_by FK -> users (restrict, required); receipt_number(50) nullable — originally UNIQUE, changed to a plain index by 2026_09_30_000002; notes text nullable; index (customer_id, created_at).
- Casts: amount/balance_before/balance_after decimal:2.
- Relations: customer(), invoice() belongsTo; collectedByUser() belongsTo User (collected_by).

## 4. Warranty / Scrap

### warranties — `Warranty`
- invoice_item_id FK (cascade); customer_id FK required (restrict); customer_vehicle_id FK nullable (nullOnDelete); serial_number(100) unique + index; start_date date; end_date date indexed.
- status enum [active, expired, claimed, voided] default active indexed; notes text nullable. No soft deletes.
- Indexes 2026_09_23: (customer_id, status); (status, end_date).
- Gotcha: invoices.customer_id is nullable, but warranties.customer_id is required — a walk-in (no customer) invoice cannot produce a warranty row.
- Casts: start_date, end_date date.
- Relations: invoiceItem(), customer(), customerVehicle() belongsTo; claims() hasMany WarrantyClaim.

### warranty_claims — `WarrantyClaim`
- Created 160009: warranty_id FK (restrict); technician_id FK -> employees (restrict, required); claim_date date; battery_voltage_tested dec(4,2) required; cca_tested dec(6,1) nullable; issue_description text; decision enum [pending, recharged, repaired, replaced, rejected] default pending indexed; replacement_invoice_id FK -> invoices nullable (nullOnDelete).
- 2026_09_23_100004 adds: claim_number(50) nullable unique; customer_id FK nullable (restrict); branch_id FK nullable (restrict); defective_battery_serial(100) nullable indexed; replacement_product_id FK -> products nullable (restrict); replacement_battery_serial(100) nullable indexed; supplier_id FK nullable (nullOnDelete); supplier_resolution enum [pending, sent_to_supplier, settled_replacement, settled_credit_note, rejected] default pending indexed; received_by_user_id, settled_by_user_id FK -> users nullable (nullOnDelete); received_at, resolved_at timestamp nullable; index (customer_id, supplier_resolution) `idx_warranty_claim_cust_res`.
- Casts: claim_date date, battery_voltage_tested decimal:2, cca_tested decimal:1, received_at/resolved_at datetime.
- Relations: warranty(), customer(), branch(), supplier() belongsTo; technician() belongsTo Employee (technician_id); replacementInvoice() belongsTo Invoice (replacement_invoice_id); replacementProduct() belongsTo Product (replacement_product_id); receivedByUser() / settledByUser() belongsTo User (received_by_user_id / settled_by_user_id).
- $fillable covers every column. Note: restrictOnDelete FKs to customers/branches/products coexist with SoftDeletes on those models (soft delete is fine; force delete blocked).

### scrap_batteries_inventory — `ScrapBatteriesInventory` ($table set explicitly)
- branch_id FK (restrict); invoice_id FK nullable (nullOnDelete); capacity_ah string(20) default '70Ah'; scrap_value dec(10,2); lead_weight_kg dec(6,2) nullable.
- status enum [in_stock, sold_to_factory, recycled] default in_stock indexed; batch_number(50) nullable indexed; received_by FK -> EMPLOYEES (restrict, required); index (branch_id, status).
- Gotcha: `received_by` here references employees, whereas purchase_invoices.received_by references users.
- Casts: scrap_value, lead_weight_kg decimal:2.
- Relations: branch(), invoice() belongsTo; receivedByEmployee() belongsTo Employee (received_by); receivedBy() alias returning receivedByEmployee().

### scrap_pricing_tiers — `ScrapPricingTier` (2026_09_23_100002)
- capacity_min_ah, capacity_max_ah uint; tier_name(100); default_scrap_price dec(10,2); is_active bool default true indexed; index (capacity_min_ah, capacity_max_ah, is_active) `idx_scrap_tier_lookup`.
- No DB constraint against overlapping ranges; findPriceForCapacity() returns `first()` without ordering.
- Casts ($casts property): min/max integer, price decimal:2, is_active boolean.
- Scope: active(). Static helpers: findPriceForCapacity(int): ?self (min <= cap <= max); getPriceForCapacity(int): ?float.

## 5. Purchases / Suppliers

### suppliers — `Supplier` (SoftDeletes)
- name(150); company_name(150) indexed; phone(30) unique; alt_phone(30), email(100), tax_number(50), commercial_register(50), address(255) nullable; credit_limit dec(12,2) default 0; current_balance dec(12,2) default 0 (amount owed TO supplier); is_active bool default true indexed.
- Indexes 2026_09_23: name; tax_number; commercial_register.
- Casts: credit_limit, current_balance decimal:2; is_active boolean.
- Relations: purchaseInvoices() hasMany; ledgerEntries() hasMany SupplierLedgerEntry; products() belongsToMany Product via supplier_products (SupplierProduct pivot, same withPivot list as Product::suppliers, withTimestamps); warrantyClaims() hasMany WarrantyClaim.
- Scopes: active(); withBalanceDue() current_balance > 0.

### purchase_invoices — `PurchaseInvoice`
- invoice_number(50) unique + index (globally unique, not per supplier); supplier_id FK (restrict); branch_id FK (restrict); received_by FK -> users (restrict); invoice_date date.
- subtotal, final_amount dec(12,2) required; tax_amount, discount_amount, paid_amount, remaining_amount dec(12,2) default 0; payment_status enum [paid, partially_paid, unpaid] default unpaid indexed; notes; index (supplier_id, invoice_date).
- Casts: invoice_date date; money decimal:2.
- Relations: supplier(), branch() belongsTo; receivedByUser() belongsTo User (received_by); receivedBy() alias; items() hasMany PurchaseInvoiceItem; ledgerEntries() hasMany SupplierLedgerEntry.

### purchase_invoice_items — `PurchaseInvoiceItem`
- purchase_invoice_id FK (cascade); product_id FK (restrict); quantity uint (no default); unit_cost_price dec(10,2); total_cost_price dec(12,2); batch_number(50) nullable; production_date date nullable.
- Casts: quantity integer, costs decimal:2, production_date date. Relations: purchaseInvoice(), product() belongsTo.

### supplier_ledger_entries — `SupplierLedgerEntry`
- supplier_id FK (cascade); purchase_invoice_id FK nullable (nullOnDelete); entry_type enum [purchase_invoice, supplier_payment, purchase_return, adjustment] indexed; amount, balance_before, balance_after dec(12,2).
- payment_method enum [cash, bank_transfer, cheque] default cash (no 'card'); cheque_number(50) nullable; paid_by FK -> users (restrict, required); receipt_number(50) nullable, no index/unique; notes; index (supplier_id, created_at).
- Casts: amounts decimal:2. Relations: supplier(), purchaseInvoice() belongsTo; paidByUser() belongsTo User (paid_by); paidBy() alias.

### supplier_products — `SupplierProduct` (extends Pivot; 2026_09_23_100001)
- supplier_id FK (cascade); product_id FK (cascade); supplier_sku(100) nullable indexed; last_purchase_price dec(10,2) required; min_order_qty uint default 1; lead_time_days usmallint default 1; is_primary_supplier bool default false indexed; notes text nullable.
- Unique (supplier_id, product_id) `uk_supplier_product`; indexes (product_id, is_primary_supplier), (supplier_id, supplier_sku).
- Model: $table 'supplier_products', `$incrementing = true`; $casts: price decimal:2, qty/lead integer, is_primary_supplier boolean.
- Relations: supplier(), product() belongsTo.
- No DB constraint limiting one primary supplier per product. FK cascade on products never fires on soft delete.

## 6. System / framework tables

### settings — `Setting` (2026_09_21_200002)
- key string unique; value text nullable; group string(50) default 'general' indexed. No casts (values stored as strings; bools written as '1'/'0').
- Static API: get($key, $default) — Cache::rememberForever("setting.{key}"); gotcha: when the key is missing a non-null $default is itself cached forever, so later get() calls with a different default return the first one until set() is called. set($key, $value, $group='general') — updateOrCreate, forgets "setting.{key}" and "settings.group.{group}" (only the NEW group; if a key moves groups, the old group cache stays stale). getGroup($group) — cached forever key=>value array. setMany(array, group).

### cache, cache_locks — no model
- cache: key PK, value mediumText, expiration int indexed. cache_locks: key PK, owner, expiration indexed.

### jobs, job_batches, failed_jobs — no model (Laravel defaults)
- jobs: id, queue indexed, payload, attempts utinyint, reserved_at/available_at/created_at uint.
- job_batches: id string PK, name, total/pending/failed_jobs int, failed_job_ids, options nullable, cancelled_at/created_at/finished_at int.
- failed_jobs: id, uuid unique, connection, queue, payload, exception, failed_at useCurrent.

## 7. Later alterations of earlier tables (chronological)
- 2026_09_21_160001: adds users.branch_id (FK nullOnDelete) + users.is_active.
- 2026_09_21_200001: adds users.phone, users.avatar.
- 2026_09_22_215442: redundant second unique index on payrolls (branch_id, year, month) — see payrolls.
- 2026_09_23_000001: search indexes only (employees, customers, customer_vehicles, products, invoices, invoice_items, warranties, suppliers, employee_leaves); all explicitly named; no column changes.
- 2026_09_23_100004: adds 12 columns + FKs to warranty_claims (see above).
- 2026_09_29_150100: adds payroll_items.debt_repayment, carried_debt.
- 2026_09_30_000001_add_partially_refunded_to_invoices_status (untracked, new):
  - MySQL: `ALTER TABLE invoices MODIFY COLUMN status ENUM('paid','partially_paid','unpaid','cancelled','refunded','partially_refunded') NOT NULL DEFAULT 'paid'`.
  - SQLite: Laravel stores enum as varchar + CHECK constraint. Migration sets `PRAGMA writable_schema=ON`, reads invoices CREATE SQL from sqlite_master, and if it contains `'refunded'` but not `'partially_refunded'` does a raw str_replace of `'refunded'` -> `'refunded','partially_refunded'` and writes it back into sqlite_master, then `writable_schema=OFF`.
  - SQLite side effect (gotcha): afterwards it runs `PRAGMA foreign_keys=OFF` and `PRAGMA ignore_check_constraints=ON` on the CURRENT connection (also in the catch path). Code comments say the edited CHECK only applies to new connections, so for the rest of that connection — e.g. the whole `:memory:` test connection during RefreshDatabase — FK enforcement and ALL CHECK constraints (every enum) are disabled. Tests therefore cannot catch invalid enum values or FK violations after this migration runs. Whether the schema cookie/version is bumped after the sqlite_master edit is UNVERIFIED (not done explicitly).
  - Other drivers (e.g. pgsql): no-op, so `partially_refunded` would violate the CHECK constraint there.
  - down(): MySQL only, reverts the ENUM only if zero rows have status partially_refunded; SQLite/others: no-op.
- 2026_09_30_000002_fix_receipt_number_unique (untracked, new):
  - Purpose: credit_ledger_entries.receipt_number no longer unique (multiple entries may share one receipt).
  - MySQL: tries `dropUnique(['receipt_number'])` (index `credit_ledger_entries_receipt_number_unique`); on failure runs raw `ALTER TABLE ... DROP INDEX credit_ledger_entries_receipt_number_unique`, swallowing errors; then adds plain index (`credit_ledger_entries_receipt_number_index`).
  - Non-MySQL (SQLite etc.): tries dropUnique in try/catch (errors swallowed), then adds plain index. The inline comment says "sqlite will ignore"; whether the unique index is actually dropped on SQLite is UNVERIFIED (Laravel normally creates it as a separate unique index, so the drop should succeed).
  - down(): MySQL only — re-adds unique only if no duplicate non-null receipt_numbers exist; SQLite: no-op.
  - Note: if the MySQL raw fallback also fails, up() still adds the plain index and the unique remains — silent partial success.

## 8. Model vs migration mismatches / gotchas summary
- Invoice::scrapBattery() and Invoice::technicianCommission() are hasOne while DB permits many rows per invoice.
- Product::primarySupplier() is BelongsToMany (collection), not a single model; no DB rule enforcing one primary.
- `withPivot` lists on Product::suppliers / Supplier::products omit `notes` (column exists and is fillable on SupplierProduct).
- received_by means Employee on scrap_batteries_inventory but User on purchase_invoices.
- warranties.customer_id NOT NULL vs invoices.customer_id nullable.
- InvoicePayment::scopeCash() includes card & bank_transfer (everything except credit); scopeActive() drops all payments of partially_refunded invoices.
- Status/enum columns are not cast anywhere; enum lists live only in migrations (no PHP enums/constants).
- `unique()->index()` on customers.phone, products.sku/barcode, invoices.invoice_number, warranties.serial_number, purchase_invoices.invoice_number creates two indexes each (redundant).
- Soft-deleted Customer/Employee/Product/Supplier rows still hold their unique values (phone, sku, national_id, etc.).
- No inverse relations on User (employee, invoices as cashier, ledger entries); no Branch relations for purchase invoices / warranty claims; no Invoice relation for replacement warranty claims; no PayrollItem relation to debts.
- All model $fillable lists match their migration columns (no fillable column missing from the schema found).
