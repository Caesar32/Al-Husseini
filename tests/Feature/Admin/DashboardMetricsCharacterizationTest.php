<?php

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Category;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
 * Phase 3 characterization: pins every number both dashboards show today, before the
 * metrics move into DashboardMetricsService. Expected values are computed by hand from the
 * rows below. "Now" is Wednesday 2026-10-14 12:00.
 *   - Admin "week" = last 7 days incl. today (from Thu 2026-10-08).
 *   - Owner "week" = startOfWeek(); Fri 2026-10-09 is outside it for any week start.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $branchId = Branch::where('code', 'MAIN')->value('id');
    $category = fn (string $slug) => Category::firstOrCreate(['slug' => $slug], ['name' => $slug])->id;
    $at = fn (string $moment) => $this->travelTo(Carbon::parse($moment));

    $product = fn (string $sku, string $slug, bool $battery, int $stock, int $reorder, bool $active = true) => Product::forceCreate([
        'category_id' => $category($slug), 'sku' => $sku, 'name' => $sku, 'brand' => 'B', 'cost_price' => 10, 'retail_price' => 20,
        'is_battery' => $battery, 'current_stock' => $stock, 'reorder_threshold' => $reorder, 'is_active' => $active,
    ]);
    $this->b1 = $product('B1', 'batteries', true, 2, 5);          // low, active
    $this->o1 = $product('O1', 'oils', false, 50, 5);
    $this->s1 = $product('S1', 'services', false, 100, 0);
    $this->g1 = $product('G1', 'greases', false, 3, 3, false);    // low, inactive

    $at('2026-10-01 08:00');
    $this->c1 = Customer::forceCreate(['name' => 'C1', 'phone' => '01000000001', 'current_credit_balance' => 1500, 'is_active' => true]);
    $this->c2 = Customer::forceCreate(['name' => 'C2', 'phone' => '01000000002', 'current_credit_balance' => 0, 'is_active' => true]);
    $this->c3 = Customer::forceCreate(['name' => 'C3', 'phone' => '01000000003', 'current_credit_balance' => 250, 'is_active' => false]);
    CustomerVehicle::forceCreate(['customer_id' => $this->c1->id, 'plate_number' => 'ABC 123', 'car_brand' => 'X', 'car_model' => 'Y']);

    // [moment, status, final, refunded, scrap, lines [product, qty, price, returned], payment [method, amount] | null]
    $invoice = function (string $moment, string $status, float $final, float $refunded, float $scrap, array $lines, ?array $payment) use ($at, $branchId) {
        $at($moment);
        $inv = Invoice::withoutEvents(fn () => Invoice::forceCreate([
            'invoice_number' => 'INV-CHAR-'.(Invoice::count() + 1), 'branch_id' => $branchId, 'customer_id' => $this->c1->id,
            'cashier_id' => $this->admin->id, 'subtotal' => $final, 'final_amount' => $final, 'paid_amount' => $final,
            'refunded_amount' => $refunded, 'scrap_deduction_amount' => $scrap, 'status' => $status,
        ]));
        foreach ($lines as [$p, $qty, $price, $returned]) {
            InvoiceItem::forceCreate([
                'invoice_id' => $inv->id, 'product_id' => $p->id, 'quantity' => $qty, 'unit_price' => $price,
                'total_price' => $qty * $price, 'returned_quantity' => $returned,
            ]);
        }
        if ($payment) {
            InvoicePayment::forceCreate(['invoice_id' => $inv->id, 'payment_method' => $payment[0], 'amount' => $payment[1]]);
        }

        return $inv;
    };

    $this->i1 = $invoice('2026-10-14 10:00', 'paid', 1000, 0, 0, [[$this->b1, 1, 800, 0], [$this->o1, 2, 100, 0]], ['cash', 1000]);
    $this->i2 = $invoice('2026-10-13 10:00', 'partially_refunded', 600, 200, 0, [[$this->o1, 4, 100, 2], [$this->s1, 1, 200, 0]], ['cash', 600]);
    $this->i3 = $invoice('2026-10-09 10:00', 'paid', 300, 0, 40, [[$this->s1, 1, 300, 0]], ['card', 300]);
    $this->i4 = $invoice('2026-10-02 10:00', 'paid', 500, 0, 0, [[$this->g1, 1, 500, 0]], ['credit', 500]);
    $this->i5 = $invoice('2026-03-01 10:00', 'paid', 700, 0, 0, [[$this->b1, 1, 700, 0]], ['bank_transfer', 700]);
    $this->i6 = $invoice('2025-12-20 10:00', 'paid', 900, 0, 0, [[$this->b1, 1, 900, 0]], ['cash', 900]);
    $this->i7 = $invoice('2026-10-14 11:00', 'cancelled', 5000, 0, 0, [[$this->b1, 1, 5000, 0]], ['cash', 5000]);
    $this->i8 = $invoice('2026-10-13 11:00', 'refunded', 250, 250, 0, [[$this->o1, 1, 250, 1]], null);
    $at('2026-10-14 11:30');
    $this->i8->touch(); // refunded today

    $ledger = function (string $moment, string $type, float $amount) use ($at) {
        $at($moment);
        CreditLedgerEntry::forceCreate([
            'customer_id' => $this->c1->id, 'entry_type' => $type, 'amount' => $amount,
            'balance_before' => 0, 'balance_after' => 0, 'collected_by' => $this->admin->id,
        ]);
    };
    $ledger('2026-10-02 12:00', 'invoice_debt', 500);
    $ledger('2026-10-14 09:00', 'payment_collection', 100);
    $ledger('2026-10-09 09:00', 'payment_collection', 50);
    $ledger('2025-12-20 09:00', 'payment_collection', 30);

    $at('2026-10-14 12:00');
    [$e1, $e2, $e3] = Employee::orderBy('id')->take(3)->get()->all();
    $this->e1 = $e1;
    Attendance::forceCreate(['employee_id' => $e1->id, 'work_date' => '2026-10-14', 'status' => 'present', 'check_in' => '2026-10-14 09:00:00']);
    Attendance::forceCreate(['employee_id' => $e2->id, 'work_date' => '2026-10-14', 'status' => 'late', 'check_in' => '2026-10-14 09:40:00', 'check_out' => '2026-10-14 11:00:00', 'late_minutes' => 40]);
    Attendance::forceCreate(['employee_id' => $e3->id, 'work_date' => '2026-10-14', 'status' => 'absent']);
});

test('admin dashboard shows the pinned KPI values', function () {
    $view = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

    $view->assertViewHas('totalSales', 3800.0)
        ->assertViewHas('invoicesCount', 6)
        ->assertViewHas('selectedPeriodKey', 'all')
        ->assertViewHas('totalCredit', 1750.0)
        ->assertViewHas('creditCustomersCount', 2)
        ->assertViewHas('customersCount', 3)
        ->assertViewHas('vehiclesCount', 1)
        ->assertViewHas('productsCount', 4)
        ->assertViewHas('lowStockCount', 2)
        ->assertViewHas('catStatBatteries', 2400.0)
        ->assertViewHas('catStatOils', 400.0)
        ->assertViewHas('catStatServices', 500.0)
        ->assertViewHas('catStatGreases', 500.0)
        ->assertViewHas('catStatScrap', 40.0)
        ->assertViewHas('pctBatteries', 63.0)
        ->assertViewHas('pctOils', 11.0)
        ->assertViewHas('pctGreases', 13.0)
        ->assertViewHas('pctServices', 13.0)
        ->assertViewHas('trendCategories', ['الخميس', 'الجمعة', 'السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء'])
        ->assertViewHas('trendBatteries', [0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 800.0])
        ->assertViewHas('trendOils', [0.0, 0.0, 0.0, 0.0, 0.0, 200.0, 200.0])
        ->assertViewHas('trendServices', [0.0, 300.0, 0.0, 0.0, 0.0, 200.0, 0.0])
        ->assertViewHas('presentCount', 1)
        ->assertViewHas('lateCount', 1)
        ->assertViewHas('absentCount', 1);

    $data = $view->viewData('salesPeriods');
    $pick = fn (string $key) => array_intersect_key($data[$key], array_flip(['total', 'count', 'revenue', 'credit_collected']));
    expect($pick('today'))->toEqual(['total' => 1000.0, 'count' => 1, 'revenue' => 1000.0, 'credit_collected' => 100.0])
        ->and($pick('week'))->toEqual(['total' => 1700.0, 'count' => 3, 'revenue' => 1900.0, 'credit_collected' => 150.0])
        ->and($pick('month'))->toEqual(['total' => 2200.0, 'count' => 4, 'revenue' => 1900.0, 'credit_collected' => 150.0])
        ->and($pick('year'))->toEqual(['total' => 2900.0, 'count' => 5, 'revenue' => 2600.0, 'credit_collected' => 150.0])
        ->and($pick('all'))->toEqual(['total' => 3800.0, 'count' => 6, 'revenue' => 3500.0, 'credit_collected' => 180.0]);

    expect($view->viewData('recentInvoices')->pluck('id')->all())->toBe([$this->i6->id, $this->i5->id, $this->i4->id, $this->i3->id, $this->i2->id, $this->i1->id])
        ->and($view->viewData('debtors')->pluck('id')->all())->toBe([$this->c1->id, $this->c3->id])
        ->and($view->viewData('lowStockProducts')->pluck('id')->all())->toBe([$this->b1->id, $this->g1->id])
        ->and($view->viewData('workshopTechs')->pluck('id')->all())->toBe(Employee::where('status', 'active')->orderBy('id')->take(6)->pluck('id')->all());
});

test('admin dashboard period switch returns the same pinned period values as JSON', function () {
    $json = $this->actingAs($this->admin)
        ->getJson(route('admin.dashboard', ['sales_period' => 'week']))
        ->assertOk()
        ->json();

    expect($json['selected_period'])->toBe('week')
        ->and($json['data']['total'])->toEqual(1700)
        ->and($json['data']['count'])->toBe(3)
        ->and($json['data']['revenue'])->toEqual(1900)
        ->and($json['data']['credit_collected'])->toEqual(150);
});

test('owner live pulse shows the pinned values', function () {
    Sanctum::actingAs($this->admin, ['owner:monitor']);

    $data = $this->getJson(route('api.v1.owner.dashboard.live'))->assertOk()->json('data');

    expect($data['safe_cash_now']['raw'])->toEqual(1100)
        ->and($data['sales_today']['raw'])->toEqual(1000)
        ->and($data['sales_today']['invoices_count'])->toBe(1)
        ->and($data['sales_today']['compared_to_yesterday'])->toBe('+150%')
        ->and($data['active_shift']['is_open'])->toBeTrue()
        ->and($data['active_shift']['cashier_name'])->toBe($this->e1->full_name)
        ->and($data['active_shift']['opened_at'])->toBe('09:00 ص')
        ->and($data['active_shift']['duration_hours'])->toBe('3 ساعة و 0 دقيقة')
        ->and($data['workshop_attendance'])->toBe(['present_count' => 2, 'total_employees' => Employee::where('status', 'active')->count(), 'attendance_rate' => round(2 / Employee::where('status', 'active')->count() * 100).'%'])
        ->and($data['critical_alerts'])->toBe(['low_stock_count' => 1, 'returns_today_count' => 1, 'unsettled_credit_count' => 1]);
});

test('owner periods show the pinned values for month and week', function () {
    Sanctum::actingAs($this->admin, ['owner:monitor']);

    $month = $this->getJson(route('api.v1.owner.dashboard.periods', ['period' => 'month']))->assertOk()->json('data');
    expect($month['revenue']['raw'])->toEqual(2200)
        ->and($month['total_sales_count'])->toBe(4)
        ->and($month['credit_collected']['raw'])->toEqual(150);

    $week = $this->getJson(route('api.v1.owner.dashboard.periods', ['period' => 'week']))->assertOk()->json('data');
    expect($week['revenue']['raw'])->toEqual(1400)
        ->and($week['total_sales_count'])->toBe(2)
        ->and($week['credit_collected']['raw'])->toEqual(100);
});

test('the admin dashboard reads invoice lines in at most 6 queries instead of 26 (Phase 3)', function () {
    // Measured before the refactor: 26 queries touched invoice_items (4 category stats,
    // 21 for the 7-day x 3-category trend, 1 eager load of recent invoice items).
    $before = 26;

    DB::enableQueryLog();
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
    $after = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'invoice_items'))
        ->count();

    expect($after)->toBeLessThanOrEqual($before - 20, "invoice_items queries: before {$before}, after {$after}");
});
