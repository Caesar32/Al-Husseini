<?php

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();

    $this->payload = fn (array $overrides = []) => array_merge([
        'branch_id' => $this->admin->branch_id,
        'department' => 'الورشة',
        'job_title' => 'فني بطاريات',
        'employee_code' => 'EMP-NID-'.fake()->unique()->numberBetween(1000, 9999),
        'full_name' => 'موظف بدون رقم قومي',
        'phone' => '010'.fake()->unique()->numerify('########'),
        'hire_date' => '2026-10-01',
        'shift_start_time' => '09:00',
        'shift_end_time' => '17:00',
        'grace_period_minutes' => 15,
        'basic_salary' => 5000,
    ], $overrides);
});

test('an employee can be created without a national id (B6)', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.hr.employees.store'), ($this->payload)(['employee_code' => 'EMP-NID-1']))
        ->assertSuccessful();

    expect(Employee::where('employee_code', 'EMP-NID-1')->value('national_id'))->toBeNull();
});

test('two employees without a national id do not collide on the unique rule (B6)', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.hr.employees.store'), ($this->payload)(['employee_code' => 'EMP-NID-2']))
        ->assertSuccessful();

    $this->actingAs($this->admin)
        ->postJson(route('admin.hr.employees.store'), ($this->payload)(['employee_code' => 'EMP-NID-3']))
        ->assertSuccessful();

    expect(Employee::whereIn('employee_code', ['EMP-NID-2', 'EMP-NID-3'])->whereNull('national_id')->count())->toBe(2);
});

test('a national id that is not 14 characters is rejected (B6)', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.hr.employees.store'), ($this->payload)(['national_id' => '123']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('national_id');
});

test('an existing national id can be cleared on update (B6)', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.hr.employees.store'), ($this->payload)([
            'employee_code' => 'EMP-NID-4',
            'national_id' => '29001011234567',
        ]))
        ->assertSuccessful();

    $employee = Employee::where('employee_code', 'EMP-NID-4')->firstOrFail();
    expect($employee->national_id)->toBe('29001011234567');

    $this->actingAs($this->admin)
        ->putJson(route('admin.hr.employees.update', $employee), ($this->payload)([
            'employee_code' => 'EMP-NID-4',
            'phone' => $employee->phone,
            'national_id' => null,
            'status' => 'active',
        ]))
        ->assertSuccessful();

    expect($employee->fresh()->national_id)->toBeNull();
});
