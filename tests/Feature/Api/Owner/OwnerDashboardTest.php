<?php

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->owner = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->token = $this->owner->createToken('TestDevice', ['owner:monitor'])->plainTextToken;
    $this->headers = ['Authorization' => 'Bearer ' . $this->token];

    Cache::flush();
});

test('live dashboard returns structured pulse data in sub-50ms shape', function () {
    $response = $this->withHeaders($this->headers)
        ->getJson(route('api.v1.owner.dashboard.live'));

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ])
        ->assertJsonStructure([
            'status',
            'timestamp',
            'data' => [
                'safe_cash_now' => ['raw', 'formatted', 'exact', 'label'],
                'sales_today' => ['raw', 'formatted', 'exact', 'invoices_count', 'compared_to_yesterday'],
                'active_shift' => ['is_open', 'cashier_name', 'opened_at', 'duration_hours'],
                'workshop_attendance' => ['present_count', 'total_employees', 'attendance_rate'],
                'critical_alerts' => ['low_stock_count', 'returns_today_count', 'unsettled_credit_count'],
            ],
        ]);
});

test('live dashboard aggregates today payments and sales accurately', function () {
    $customer = Customer::create([
        'name'                   => 'عميل تجريبي',
        'phone'                  => '01011112222',
        'current_credit_balance' => 0,
        'credit_limit'           => 1000,
    ]);

    $invoice = Invoice::create([
        'invoice_number'         => 'INV-TEST-001',
        'branch_id'              => 1,
        'customer_id'            => $customer->id,
        'cashier_id'             => $this->owner->id,
        'subtotal'               => 1000.00,
        'final_amount'           => 1000.00,
        'paid_amount'            => 1000.00,
        'remaining_amount'       => 0.00,
        'payment_method'         => 'cash',
        'status'                 => 'paid',
    ]);

    InvoicePayment::create([
        'invoice_id'     => $invoice->id,
        'payment_method' => 'cash',
        'amount'         => 1000.00,
    ]);

    Cache::flush();

    $response = $this->withHeaders($this->headers)
        ->getJson(route('api.v1.owner.dashboard.live'));

    $response->assertOk();
    expect($response->json('data.sales_today.raw'))->toEqual(1000.00)
        ->and($response->json('data.sales_today.invoices_count'))->toBe(1)
        ->and($response->json('data.safe_cash_now.raw'))->toEqual(1000.00);
});

test('periods dashboard computes stats across periods', function () {
    foreach (['today', 'week', 'month', 'year', 'all'] as $period) {
        $response = $this->withHeaders($this->headers)
            ->getJson(route('api.v1.owner.dashboard.periods', ['period' => $period]));

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'period' => $period,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'period_label',
                    'revenue' => ['raw', 'compact', 'exact'],
                    'total_sales_count',
                    'credit_collected' => ['raw', 'compact', 'exact'],
                ],
            ]);
    }
});
