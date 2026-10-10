<?php

use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->owner = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->token = $this->owner->createToken('Device', ['owner:monitor'])->plainTextToken;
    $this->headers = ['Authorization' => 'Bearer ' . $this->token];

    // Clear customers to have a controlled set
    Customer::query()->forceDelete();
});

test('credit overview returns total outstanding, top debtors and strictly hides national_id', function () {
    // 1. Debtor with national_id (MUST NEVER LEAK)
    $debtor1 = Customer::create([
        'name'                   => 'أحمد كمال المليجي',
        'phone'                  => '01011111111',
        'national_id'            => '29501011234567',
        'current_credit_balance' => 15000.00,
        'credit_limit'           => 20000.00,
        'is_active'              => true,
    ]);

    // 2. Debtor 2
    $debtor2 = Customer::create([
        'name'                   => 'محمود السعيد',
        'phone'                  => '01022222222',
        'national_id'            => '29802021234568',
        'current_credit_balance' => 8500.00,
        'credit_limit'           => 10000.00,
        'is_active'              => true,
    ]);

    // 3. Customer with ZERO debt (must not appear in top_debtors)
    $customerZero = Customer::create([
        'name'                   => 'علي سالم النقدي',
        'phone'                  => '01033333333',
        'national_id'            => '29903031234569',
        'current_credit_balance' => 0.00,
        'credit_limit'           => 5000.00,
        'is_active'              => true,
    ]);

    // Today payment collection of 2,500
    CreditLedgerEntry::create([
        'customer_id'    => $debtor1->id,
        'entry_type'     => 'payment_collection',
        'amount'         => 2500.00,
        'balance_before' => 17500.00,
        'balance_after'  => 15000.00,
        'collected_by'   => $this->owner->id,
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson(route('api.v1.owner.credit.overview'));

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'data'   => [
                'total_outstanding' => [
                    'raw' => 23500.00, // 15000 + 8500
                ],
                'collected_today'   => [
                    'raw' => 2500.00,
                ],
                'debtors_count'     => 2,
            ],
        ]);

    $topDebtors = $response->json('data.top_debtors');
    expect(count($topDebtors))->toBe(2)
        ->and($topDebtors[0]['id'])->toBe($debtor1->id) // Higher balance first (15000 > 8500)
        ->and($topDebtors[1]['id'])->toBe($debtor2->id);

    // SECURITY CHECK: national_id must NEVER be present anywhere in the entire response JSON
    $jsonString = $response->getContent();
    expect($jsonString)->not->toContain('national_id')
        ->and($jsonString)->not->toContain('29501011234567')
        ->and($jsonString)->not->toContain('29802021234568')
        ->and($jsonString)->not->toContain('29903031234569');
});
