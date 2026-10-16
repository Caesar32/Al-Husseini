<?php

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Frontend F4: the layout loads the Admin core once, before page scripts, and the navigation
// engine hands page modules their leave/boot lifecycle.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
});

test('the layout loads core.js once, before the page scripts container', function () {
    $html = $this->actingAs(User::where('email', 'admin@alhusseini.com')->firstOrFail())
        ->get(route('admin.dashboard'))->assertOk()->getContent();

    $core = asset('assets/js/admin/core.js');
    expect(substr_count($html, $core))->toBe(1)
        ->and(strpos($html, $core))->toBeLessThan(strpos($html, '<div id="page-scripts">'))
        ->and($html)->toContain('window.Admin.page.leave()')
        ->and($html)->toContain('window.Admin.page.boot(currentMain)')
        ->and(strpos($html, 'window.Admin.page.leave()'))->toBeLessThan(strpos($html, 'currentMain.innerHTML = newMain.innerHTML'));
});

test('the attendance page passes its routes and employees as escaped page config', function () {
    $employee = Employee::first();
    $employee->update(['full_name' => "O'Neil \"<b>x</b>\""]);

    $html = $this->actingAs(User::where('email', 'admin@alhusseini.com')->firstOrFail())
        ->get(route('admin.hr.attendance'))->assertOk()->getContent();

    preg_match("/data-page=\"hr.attendance\" data-config='([^']*)'/", $html, $m);
    expect($m)->toHaveCount(2);

    $config = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
    expect($config['routes']['punch'])->toBe(route('admin.hr.attendance.punch'))
        ->and(collect($config['employees'])->pluck('full_name'))->toContain("O'Neil \"<b>x</b>\"")
        ->and($m[1])->not->toContain('<b>');
});
