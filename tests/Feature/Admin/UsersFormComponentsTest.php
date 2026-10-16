<?php

use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Frontend F2: the users page forms use <x-form.*>; per-user edit modals are stateless.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
});

test('every create and edit field has a label bound to its control', function () {
    $html = $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()->getContent();

    foreach (['add_user_name', 'add_user_email', 'add_user_phone', 'add_user_branch', 'add_user_role', 'add_user_password'] as $id) {
        expect($html)->toContain('<label for="'.$id.'"')->toContain('id="'.$id.'"');
    }
    foreach (User::pluck('id') as $userId) {
        expect($html)->toContain('<label for="edit_email_'.$userId.'"')->toContain('id="edit_email_'.$userId.'"');
    }
});

test('a failed create shows its errors in the create form only', function () {
    $html = $this->actingAs($this->admin)
        ->from(route('admin.users.index'))
        ->followingRedirects()
        ->post(route('admin.users.store'), ['name' => 'س', 'email' => 'not-an-email', 'role' => 'cashier', 'password' => 'secret123'])
        ->getContent();

    expect($html)->toContain('id="add_user_email-error"')
        ->toContain('aria-describedby="add_user_email-error"')
        ->not->toContain('edit_email_'.$this->admin->id.'-error')
        ->not->toContain('value="not-an-email" required aria-invalid="true" aria-describedby="edit_email');
});
