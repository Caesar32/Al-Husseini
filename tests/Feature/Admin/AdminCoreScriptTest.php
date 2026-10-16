<?php

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
