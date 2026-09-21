<?php

use App\Models\User;
use App\Models\Branch;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\InitialDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
});

test('guests are redirected to login when attempting to access admin routes', function () {
    $response = $this->get('/admin');
    $response->assertRedirect(route('admin.login'));

    $response = $this->get('/admin/hr/employees');
    $response->assertRedirect(route('admin.login'));

    $response = $this->get('/admin/hr/payroll');
    $response->assertRedirect(route('admin.login'));
});

test('login page is accessible to guests', function () {
    $response = $this->get(route('admin.login'));
    $response->assertStatus(200);
    $response->assertSee('تسجيل الدخول');
});

test('already authenticated admin is redirected from login to dashboard', function () {
    $admin = User::where('email', 'admin@alhusseini.com')->first();

    $response = $this->actingAs($admin)->get(route('admin.login'));
    $response->assertRedirect(route('admin.dashboard'));
});

test('admin can log in successfully with valid credentials', function () {
    $response = $this->post(route('admin.login.submit'), [
        'email' => 'admin@alhusseini.com',
        'password' => '12345678',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticated();
});

test('admin login fails with invalid password', function () {
    $response = $this->from(route('admin.login'))->post(route('admin.login.submit'), [
        'email' => 'admin@alhusseini.com',
        'password' => 'wrong-password',
    ]);

    $response->assertRedirect(route('admin.login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('inactive admin user is blocked from logging in', function () {
    $user = User::factory()->create([
        'email' => 'disabled@alhusseini.com',
        'password' => bcrypt('12345678'),
        'is_active' => false,
    ]);
    $user->assignRole('super-admin');

    $response = $this->from(route('admin.login'))->post(route('admin.login.submit'), [
        'email' => 'disabled@alhusseini.com',
        'password' => '12345678',
    ]);

    $response->assertRedirect(route('admin.login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('authenticated admin can log out and session is destroyed', function () {
    $admin = User::where('email', 'admin@alhusseini.com')->first();

    $response = $this->actingAs($admin)->post(route('admin.logout'));

    $response->assertRedirect(route('admin.login'));
    $this->assertGuest();
});

test('super admin has unrestricted access to all hr and system operations via Gate before', function () {
    $admin = User::where('email', 'admin@alhusseini.com')->first();

    $this->actingAs($admin);

    // التحقق من أن المشرف العام يستطيع الوصول لكل الشاشات المصونة
    $this->get(route('admin.dashboard'))->assertStatus(200);
    $this->get(route('admin.hr.employees'))->assertStatus(200);
    $this->get(route('admin.hr.attendance'))->assertStatus(200);
    $this->get(route('admin.hr.leaves.index'))->assertStatus(200);
    $this->get(route('admin.hr.deductions.index'))->assertStatus(200);
    $this->get(route('admin.hr.payroll'))->assertStatus(200);
    $this->get(route('admin.hr.reports'))->assertStatus(200);
    $this->get(route('admin.hr.notifications.index'))->assertStatus(200);
});

test('user without permissions is forbidden 403 from accessing restricted routes', function () {
    // مستخدم بدون أي صلاحيات
    $plainUser = User::factory()->create(['is_active' => true]);

    $this->actingAs($plainUser);

    // محاولة الوصول لشاشات الرواتب والإجازات المحمية
    $this->get(route('admin.hr.payroll'))->assertStatus(403);
    $this->get(route('admin.hr.leaves.index'))->assertStatus(403);
});
