<?php

use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Phase 5 (FE-08): admin JS gets its URLs from route(), never from literal /admin/... paths.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
});

test('HR and warranty pages embed route-generated URLs for their scripts', function (string $routeName, array $expected) {
    $html = $this->actingAs($this->admin)->get(route($routeName))->assertOk()->getContent();

    // @json escapes "/" as "\/".
    foreach ($expected as $url) {
        expect($html)->toContain(str_replace('/', '\/', $url));
    }
    expect($html)->not->toMatch('#(fetch\(|action\s*=)\s*[\'"`]/admin/#');
})->with([
    'attendance' => ['admin.hr.attendance', fn () => [route('admin.hr.attendance'), route('admin.hr.attendance.punch'), route('admin.hr.attendance.mark_absent'), route('admin.hr.deductions.store')]],
    'employees' => ['admin.hr.employees', fn () => [route('admin.hr.employees'), route('admin.hr.employees.show', ['employee' => '__ID__'])]],
    'payroll' => ['admin.hr.payroll', fn () => [route('admin.hr.payroll.generate'), route('admin.hr.payroll.show', ['payroll' => '__ID__']), route('admin.hr.payroll.approve', ['payroll' => '__ID__']), route('admin.hr.payroll.disburse', ['payroll' => '__ID__']), route('admin.hr.deductions.status', ['deduction' => '__ID__'])]],
    'warranties' => ['admin.warranties.index', fn () => [route('admin.warranties.claims.settle', ['claim' => '__ID__'])]],
]);
