<?php

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Legacy suppliers have no phone on record, which must not block editing the rest of them.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->legacy = Supplier::create(['name' => 'مورد قديم', 'company_name' => 'مورد قديم', 'phone' => null, 'credit_limit' => 0, 'is_active' => true]);

    $this->payload = fn (array $extra = []) => array_merge([
        'name' => 'مورد قديم',
        'company_name' => 'شركة المورد',
        'phone' => null,
        'credit_limit' => 1000,
    ], $extra);
});

test('a supplier with no phone can be edited without inventing one', function () {
    $this->actingAs($this->admin)
        ->putJson(route('admin.suppliers.update', $this->legacy), ($this->payload)())
        ->assertSessionHasNoErrors();

    expect($this->legacy->fresh())
        ->company_name->toBe('شركة المورد')
        ->phone->toBeNull()
        ->and((float) $this->legacy->fresh()->credit_limit)->toBe(1000.0);
});

test('several suppliers can stay without a phone', function () {
    $other = Supplier::create(['name' => 'مورد آخر', 'company_name' => 'مورد آخر', 'phone' => null, 'credit_limit' => 0, 'is_active' => true]);

    $this->actingAs($this->admin)
        ->putJson(route('admin.suppliers.update', $other), ($this->payload)(['name' => 'مورد آخر']))
        ->assertSessionHasNoErrors();

    expect($other->fresh()->phone)->toBeNull()
        ->and($this->legacy->fresh()->phone)->toBeNull();
});

test('a phone can be added later, and from then on it stays required', function () {
    $this->actingAs($this->admin)
        ->putJson(route('admin.suppliers.update', $this->legacy), ($this->payload)(['phone' => '01099990005']))
        ->assertSessionHasNoErrors();

    expect($this->legacy->fresh()->phone)->toBe('01099990005');

    $this->actingAs($this->admin)
        ->putJson(route('admin.suppliers.update', $this->legacy->fresh()), ($this->payload)())
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

test('a phone already used by another supplier is still refused', function () {
    Supplier::create(['name' => 'مورد له هاتف', 'company_name' => 'مورد له هاتف', 'phone' => '01099990006', 'credit_limit' => 0, 'is_active' => true]);

    $this->actingAs($this->admin)
        ->putJson(route('admin.suppliers.update', $this->legacy), ($this->payload)(['phone' => '01099990006']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

test('a new supplier still needs a phone', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.suppliers.store'), ['name' => 'مورد جديد', 'company_name' => 'مورد جديد', 'credit_limit' => 0])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

test('the edit form asks for a phone only when the supplier already has one', function () {
    $phoneInputIsRequired = fn () => (bool) preg_match(
        '/<input[^>]*name="phone"[^>]*\srequired\b[^>]*>/u',
        $this->actingAs($this->admin)->get(route('admin.suppliers.edit', $this->legacy->fresh()))->assertOk()->getContent()
    );

    expect($phoneInputIsRequired())->toBeFalse();

    $this->legacy->update(['phone' => '01099990007']);

    expect($phoneInputIsRequired())->toBeTrue();
});

test('the supplier pages open for a supplier with no phone', function () {
    $this->actingAs($this->admin)->get(route('admin.suppliers.show', $this->legacy))
        ->assertOk()
        ->assertSee('غير مسجل');

    $this->actingAs($this->admin)->get(route('admin.suppliers.index'))->assertOk();
});
