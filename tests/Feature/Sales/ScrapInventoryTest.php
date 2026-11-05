<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\ScrapBatteriesInventory;
use App\Models\ScrapPricingTier;
use App\Models\ScrapSale;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ScrapPricingTiersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->seed(ScrapPricingTiersSeeder::class);

    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->branch = Branch::where('code', 'MAIN')->firstOrFail();
    $this->technician = Employee::factory()->create(['branch_id' => $this->branch->id]);
    $this->actingAs($this->admin);
});

/** @return list<int> Battery ids, oldest first. */
function scrapStock(int $count, array $overrides = []): array
{
    $ids = [];
    for ($i = 0; $i < $count; $i++) {
        $ids[] = ScrapBatteriesInventory::create(array_merge([
            'branch_id' => test()->branch->id,
            'capacity_ah' => '70Ah',
            'scrap_value' => 100,
            'lead_weight_kg' => 11.9,
            'status' => 'in_stock',
            'received_by' => test()->technician->id,
        ], $overrides))->id;
    }

    return $ids;
}

function scrapSale(array $fields): Illuminate\Testing\TestResponse
{
    return test()->post(route('admin.scrap.sell_batch'), array_merge([
        'buyer_name' => 'مصنع الشرق',
        'buyer_phone' => '01000000000',
        'total_amount' => 5000,
        'payment_method' => 'bank_transfer',
        'notes' => 'سيارة 123',
    ], $fields));
}

test('the page script loads after the core script that defines Admin', function () {
    $html = $this->get(route('admin.scrap.index'))->assertOk()->getContent();

    expect(strpos($html, 'assets/js/admin/scrap.js'))->toBeGreaterThan(strpos($html, 'assets/js/admin/core.js'));
});

test('the sell button works from the stock on hand, not from ticked rows', function (int $stock, bool $disabled) {
    scrapStock($stock);

    $html = $this->get(route('admin.scrap.index'))->assertOk()->getContent();
    preg_match('/<button[^>]*id="openSellModalBtn"[^>]*>/', $html, $button);

    expect(str_contains($button[0], ' disabled'))->toBe($disabled)
        ->and($button[0])->toContain('data-stock-units="'.$stock.'"');
})->with([
    'with stock' => [3, false],
    'empty stock' => [0, true],
]);

test('selling ticked batteries moves only those out of stock with a batch number', function () {
    [$first, $second, $kept] = scrapStock(3);

    scrapSale(['selection_mode' => 'selected', 'scrap_battery_ids' => [$first, $second]])
        ->assertRedirect(route('admin.scrap.index'))
        ->assertSessionHasNoErrors();

    $sale = ScrapSale::firstOrFail();
    expect($sale->batch_number)->toStartWith('SCRAP-BATCH-')
        ->and($sale->batteries_count)->toBe(2)
        ->and($sale->payment_method)->toBe('bank_transfer')
        ->and((float) $sale->total_amount)->toBe(5000.0)
        ->and((float) $sale->cost_value)->toBe(200.0)
        ->and((float) $sale->gross_profit)->toBe(4800.0);

    foreach ([$first, $second] as $id) {
        $battery = ScrapBatteriesInventory::findOrFail($id);
        expect($battery->status)->toBe('sold_to_factory')
            ->and($battery->batch_number)->toBe($sale->batch_number)
            ->and($battery->scrap_sale_id)->toBe($sale->id);
    }
    expect(ScrapBatteriesInventory::findOrFail($kept)->status)->toBe('in_stock');
});

test('callers that send only the ids keep working', function () {
    [$id] = scrapStock(2);

    scrapSale(['scrap_battery_ids' => [$id, $id]])->assertSessionHasNoErrors();

    expect(ScrapSale::firstOrFail()->batteries_count)->toBe(1);
});

test('selling the whole stock reaches batteries beyond the first table page', function () {
    scrapStock(40);
    $sold = scrapStock(1, ['status' => 'sold_to_factory', 'batch_number' => 'OLD']);

    scrapSale(['selection_mode' => 'all'])->assertSessionHasNoErrors();

    expect(ScrapSale::firstOrFail()->batteries_count)->toBe(40)
        ->and(ScrapBatteriesInventory::where('status', 'in_stock')->count())->toBe(0)
        ->and(ScrapBatteriesInventory::findOrFail($sold[0])->batch_number)->toBe('OLD');
});

test('selling a count takes the oldest batteries first', function () {
    $ids = scrapStock(20);

    scrapSale(['selection_mode' => 'count', 'batch_count' => 17])->assertSessionHasNoErrors();

    expect(ScrapBatteriesInventory::where('status', 'sold_to_factory')->orderBy('id')->pluck('id')->all())
        ->toBe(array_slice($ids, 0, 17))
        ->and(ScrapSale::firstOrFail()->batteries_count)->toBe(17);
});

test('a count above the stock changes nothing and explains why', function () {
    scrapStock(3);

    scrapSale(['selection_mode' => 'count', 'batch_count' => 4])->assertSessionHasErrors('scrap_error');

    expect(ScrapSale::count())->toBe(0)
        ->and(ScrapBatteriesInventory::where('status', 'in_stock')->count())->toBe(3);
});

test('invalid sale requests are rejected', function (array $fields, string $errorField) {
    scrapStock(2);

    scrapSale($fields)->assertSessionHasErrors($errorField);

    expect(ScrapSale::count())->toBe(0);
})->with([
    'selected without ids' => [['selection_mode' => 'selected'], 'scrap_battery_ids'],
    'no mode and no ids' => [[], 'scrap_battery_ids'],
    'count without a number' => [['selection_mode' => 'count'], 'batch_count'],
    'count of zero' => [['selection_mode' => 'count', 'batch_count' => 0], 'batch_count'],
    'unknown mode' => [['selection_mode' => 'everything'], 'selection_mode'],
    'zero amount' => [['selection_mode' => 'all', 'total_amount' => 0], 'total_amount'],
]);

test('an empty stock cannot be sold as a whole', function () {
    scrapSale(['selection_mode' => 'all'])->assertSessionHasErrors('scrap_error');

    expect(ScrapSale::count())->toBe(0);
});

test('a battery already sold cannot be sold again', function () {
    $sold = scrapStock(1, ['status' => 'sold_to_factory']);

    scrapSale(['selection_mode' => 'selected', 'scrap_battery_ids' => $sold])->assertSessionHasErrors('scrap_battery_ids');
});

test('a whole-stock sale by a branch user leaves other branches alone', function () {
    $other = Branch::factory()->create();
    scrapStock(2);
    $foreign = scrapStock(3, ['branch_id' => $other->id]);

    $user = User::factory()->create(['branch_id' => $this->branch->id]);
    $user->givePermissionTo(['scrap.view', 'scrap.transfer']);

    $this->actingAs($user);
    scrapSale(['selection_mode' => 'all'])->assertSessionHasNoErrors();

    expect(ScrapSale::firstOrFail()->batteries_count)->toBe(2)
        ->and(ScrapBatteriesInventory::withoutGlobalScopes()->whereIn('id', $foreign)->where('status', 'in_stock')->count())->toBe(3);
});

test('a user without the transfer permission sees no sale form and cannot sell', function () {
    scrapStock(1);
    $viewer = User::factory()->create(['branch_id' => $this->branch->id]);
    $viewer->givePermissionTo('scrap.view');

    $this->actingAs($viewer);
    $this->get(route('admin.scrap.index'))->assertOk()
        ->assertDontSee('id="openSellModalBtn"', false)
        ->assertDontSee('id="sellBatchForm"', false)
        ->assertDontSee('حفظ الأسعار الجديدة');

    scrapSale(['selection_mode' => 'all'])->assertForbidden();
});

test('the list opens on stock on hand and the filter can show everything', function () {
    scrapStock(1, ['capacity_ah' => '55Ah']);
    scrapStock(1, ['capacity_ah' => '180Ah', 'status' => 'sold_to_factory', 'batch_number' => 'B-OLD']);

    $this->get(route('admin.scrap.index'))->assertDontSee('B-OLD');
    $this->get(route('admin.scrap.index', ['status' => '']))->assertSee('B-OLD');
    $this->get(route('admin.scrap.index', ['status' => 'sold_to_factory']))->assertSee('B-OLD');
});

test('row numbers continue across pages', function () {
    scrapStock(16);

    $html = $this->get(route('admin.scrap.index', ['page' => 2]))->assertOk()->getContent();

    expect($html)->toMatch('#<td>16</td>#');
});

test('the metrics count only the stock on hand of the branch', function () {
    scrapStock(2, ['capacity_ah' => '100Ah', 'scrap_value' => 150.25, 'lead_weight_kg' => 17]);
    scrapStock(1, ['capacity_ah' => '70Ah', 'scrap_value' => 100, 'lead_weight_kg' => 11.9]);
    scrapStock(5, ['status' => 'sold_to_factory']);
    scrapStock(5, ['branch_id' => Branch::factory()->create()->id]);

    $user = User::factory()->create(['branch_id' => $this->branch->id]);
    $user->givePermissionTo('scrap.view');

    $metrics = $this->actingAs($user)->getJson(route('admin.scrap.index'))->assertOk()->json('metrics');

    expect($metrics['total_units'])->toBe(3)
        ->and((float) $metrics['total_scrap_value'])->toBe(400.5)
        ->and((float) $metrics['total_lead_weight_kg'])->toBe(45.9)
        ->and((float) $metrics['total_metric_tons'])->toBe(0.046)
        ->and(array_column($metrics['capacity_breakdown'], 'capacity'))->toBe(['70Ah', '100Ah'])
        ->and(array_column($metrics['capacity_breakdown'], 'count'))->toBe([1, 2]);
});

test('saving the pricing table updates the price the cashier gets', function () {
    $tier = ScrapPricingTier::orderBy('capacity_min_ah')->firstOrFail();

    $this->put(route('admin.scrap.update_tiers'), [
        'tiers' => [['id' => $tier->id, 'default_scrap_price' => 655.5]],
    ])->assertRedirect(route('admin.scrap.index'))->assertSessionHasNoErrors();

    $found = ScrapPricingTier::findPriceForCapacity((int) $tier->capacity_min_ah);
    expect((float) ($found?->default_scrap_price ?? $found))->toBe(655.5)
        ->and((bool) $tier->fresh()->is_active)->toBe((bool) $tier->is_active);
});

test('the pricing table accepts any amount and only managers can save it', function () {
    $tier = ScrapPricingTier::firstOrFail();

    $html = $this->get(route('admin.scrap.index'))->assertOk()->getContent();
    expect($html)->toContain('step="0.01" min="0" name="tiers[0][default_scrap_price]"');

    $this->put(route('admin.scrap.update_tiers'), ['tiers' => [['id' => $tier->id, 'default_scrap_price' => -1]]])
        ->assertSessionHasErrors('tiers.0.default_scrap_price');

    $viewer = User::factory()->create(['branch_id' => $this->branch->id]);
    $viewer->givePermissionTo('scrap.view');
    $this->actingAs($viewer)
        ->put(route('admin.scrap.update_tiers'), ['tiers' => [['id' => $tier->id, 'default_scrap_price' => 1]]])
        ->assertForbidden();
});
