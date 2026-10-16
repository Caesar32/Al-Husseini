<?php

use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Frontend F0: both admin layouts load the design tokens before the theme overrides use them.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
});

test('the main admin layout links the design tokens', function () {
    $this->actingAs(User::where('email', 'admin@alhusseini.com')->firstOrFail())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(asset('assets/css/tokens.css'), false)
        ->assertSee('--brand-sapphire: var(--ah-color-primary);', false);
});

test('the guest layout links the design tokens', function () {
    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee(asset('assets/css/tokens.css'), false);
});

test('the tokens file defines every token the theme references', function () {
    $tokens = file_get_contents(public_path('assets/css/tokens.css'));
    $theme = file_get_contents(resource_path('views/admin/layouts/partials/head-css.blade.php'));

    preg_match_all('/var\((--ah-[a-z0-9-]+)\)/', $theme, $used);
    foreach (array_unique($used[1]) as $token) {
        expect($tokens)->toContain($token.':');
    }
});
