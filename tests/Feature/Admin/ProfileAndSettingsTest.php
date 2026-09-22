<?php

use App\Models\User;
use App\Models\Setting;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->admin = User::where('email', 'admin@alhusseini.com')->first();
    $this->actingAs($this->admin);
});

test('admin can view profile page with account details', function () {
    $response = $this->get(route('admin.profile'));

    $response->assertStatus(200);
    $response->assertSee($this->admin->name);
    $response->assertSee($this->admin->email);
});

test('admin can update personal information', function () {
    $response = $this->put(route('admin.profile.info'), [
        'name' => 'أحمد الحسيني المحدث',
        'email' => 'admin_updated@alhusseini.com',
        'phone' => '01099998888',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status');

    $this->assertDatabaseHas('users', [
        'id' => $this->admin->id,
        'name' => 'أحمد الحسيني المحدث',
        'email' => 'admin_updated@alhusseini.com',
        'phone' => '01099998888',
    ]);
});

test('admin can change password with correct current password', function () {
    $response = $this->put(route('admin.profile.password'), [
        'current_password' => '12345678',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status');

    $this->assertTrue(Hash::check('newpassword123', $this->admin->fresh()->password));
});

test('admin cannot change password with incorrect current password', function () {
    $response = $this->put(route('admin.profile.password'), [
        'current_password' => 'wrongpassword',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('current_password');
    $this->assertFalse(Hash::check('newpassword123', $this->admin->fresh()->password));
});

test('admin can view system settings page', function () {
    $response = $this->get(route('admin.settings'));

    $response->assertStatus(200);
    $response->assertSee('إعدادات النظام والمنشأة');
    $response->assertSee('مجموعة الحسيني لتجارة وتوزيع بطاريات السيارات');
});

test('admin can update settings values and they are persisted', function () {
    $response = $this->post(route('admin.settings.update'), [
        'company_name' => 'شركة الحسيني للبطاريات الحديثة',
        'company_phone' => '0409999999',
        'currency' => 'ج.م',
        'default_grace_period' => '20',
        'vat_percentage' => '14',
        'monthly_working_days' => '26',
        'daily_working_hours' => '8',
        'allow_negative_stock' => '1',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status');

    expect(Setting::get('company_name'))->toBe('شركة الحسيني للبطاريات الحديثة')
        ->and(Setting::get('default_grace_period'))->toBe('20')
        ->and(Setting::get('allow_negative_stock'))->toBe('1');
});

test('admin can view lockscreen page', function () {
    $response = $this->get(route('admin.lockscreen'));

    $response->assertStatus(200);
    $response->assertSee('الشاشة مقفلة');
    $response->assertSee($this->admin->name);
    $this->assertTrue(session('lockscreen_locked', false));
});

test('admin can unlock screen with correct password', function () {
    session(['lockscreen_locked' => true]);

    $response = $this->post(route('admin.lockscreen.unlock'), [
        'password' => '12345678',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertFalse(session()->has('lockscreen_locked'));
});

test('admin cannot unlock screen with incorrect password', function () {
    session(['lockscreen_locked' => true]);

    $response = $this->post(route('admin.lockscreen.unlock'), [
        'password' => 'wrongpass',
    ]);

    $response->assertSessionHasErrors('password');
});
