<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest is redirected to admin login', function () {
    $response = $this->get('/admin');
    $response->assertRedirect(route('admin.login'));
});

test('authenticated super admin can access dashboard', function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $response = $this->actingAs($admin)->get('/admin');
    $response->assertStatus(200);
});
