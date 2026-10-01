<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->cashier = User::where('email', 'cashier@alhusseini.com')->firstOrFail();
});

// ── SEC-04: deactivated users ────────────────────────────────────────────────

test('a user deactivated after login is logged out on the next request', function () {
    $this->actingAs($this->cashier)->get(route('admin.pos.index'))->assertOk();

    $this->cashier->update(['is_active' => false]);

    $this->get(route('admin.pos.index'))->assertRedirect(route('admin.login'));
    $this->assertGuest();
});

test('deactivating a user removes their stored sessions', function () {
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert([
        'id' => 'sess-cashier', 'user_id' => $this->cashier->id, 'ip_address' => '127.0.0.1',
        'user_agent' => 'test', 'payload' => '', 'last_activity' => time(),
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.toggle-status', $this->cashier))
        ->assertSessionHasNoErrors();

    expect($this->cashier->fresh()->is_active)->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $this->cashier->id)->exists())->toBeFalse();
});

// ── SEC-10: lock screen ──────────────────────────────────────────────────────

test('a locked session can only reach the lock screen, unlock, logout and keep-alive', function () {
    $this->actingAs($this->admin)->withSession(['lockscreen_locked' => true]);

    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.lockscreen'));
    $this->getJson(route('admin.global_search', ['q' => 'test']))->assertStatus(423);
    $this->get(route('admin.lockscreen'))->assertOk();
    $this->get(route('refresh_csrf'))->assertOk();

    $this->post(route('admin.lockscreen.unlock'), ['password' => '12345678'])->assertRedirect();
    $this->get(route('admin.dashboard'))->assertOk();
});

// ── SEC-05: global search ────────────────────────────────────────────────────

test('global search only returns sections the user is allowed to view', function () {
    // Seeded employees include "محمود"; cashier lacks employees.view and suppliers.view.
    $cashierResult = $this->actingAs($this->cashier)
        ->getJson(route('admin.global_search', ['q' => 'محمود']))
        ->assertOk()
        ->json('data.sections');

    expect(array_keys($cashierResult))->not->toContain('employees')
        ->and(array_keys($cashierResult))->not->toContain('suppliers')
        ->and(array_keys($cashierResult))->not->toContain('purchase_invoices');

    $adminResult = $this->actingAs($this->admin)
        ->getJson(route('admin.global_search', ['q' => 'محمود']))
        ->json('data.sections');

    expect(array_keys($adminResult))->toContain('employees');
});

// ── SEC-06 / SEC-12: user and role management escalation ─────────────────────

function delegatedUserManager(): User
{
    $role = Role::create(['name' => 'user-admin', 'guard_name' => 'web']);
    $role->givePermissionTo(['users.manage', 'roles.manage']);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);

    return $user;
}

test('a non super-admin with users.manage cannot grant or touch super-admin accounts', function () {
    $manager = delegatedUserManager();
    $this->actingAs($manager);

    $this->post(route('admin.users.store'), [
        'name' => 'x', 'email' => 'new@x.test', 'role' => 'super-admin', 'password' => 'long-enough-1',
    ])->assertSessionHasErrors('user_error');
    expect(User::where('email', 'new@x.test')->exists())->toBeFalse();

    $this->post(route('admin.users.role', $this->cashier), ['role' => 'super-admin'])
        ->assertSessionHasErrors('user_error');
    expect($this->cashier->fresh()->hasRole('super-admin'))->toBeFalse();

    $originalHash = $this->admin->password;
    $this->post(route('admin.users.reset-password', $this->admin), ['password' => 'attacker-pass-1'])
        ->assertSessionHasErrors('user_error');
    expect($this->admin->fresh()->password)->toBe($originalHash);

    $this->post(route('admin.users.toggle-status', $this->admin))->assertSessionHasErrors('user_error');
    expect($this->admin->fresh()->is_active)->toBeTrue();

    // Ordinary accounts can still be managed.
    $this->post(route('admin.users.role', $this->cashier), ['role' => 'accountant'])
        ->assertSessionHasNoErrors();
    expect($this->cashier->fresh()->hasRole('accountant'))->toBeTrue();
});

test('a non super-admin with roles.manage cannot add privilege-management permissions to a role', function () {
    $manager = delegatedUserManager();

    $this->actingAs($manager)
        ->put(route('admin.roles.update', Role::findByName('cashier')), [
            'permissions' => ['pos.access', 'users.manage'],
        ])
        ->assertSessionHasErrors('role_error');

    expect(Role::findByName('cashier')->hasPermissionTo('users.manage'))->toBeFalse();
});

test('admin-set passwords require at least 8 characters', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.reset-password', $this->cashier), ['password' => 'short7x'])
        ->assertSessionHasErrors('password');
});

// ── SEC-07 / SEC-14: seeded credentials ──────────────────────────────────────

test('re-running the initial seeder does not reset passwords or reactivate accounts', function () {
    $this->cashier->update(['password' => Hash::make('changed-by-owner'), 'is_active' => false]);

    $this->seed(InitialDataSeeder::class);

    $cashier = $this->cashier->fresh();
    expect(Hash::check('changed-by-owner', $cashier->password))->toBeTrue()
        ->and($cashier->is_active)->toBeFalse();
});

test('the login page never prefills the form or hard-codes credentials', function () {
    // Independent of any LOGIN_SWITCHER_PASSWORD in the developer's .env (see LoginAccountSwitcherTest).
    config(['auth.login_switcher.password' => null]);

    $this->get(route('admin.login'))
        ->assertOk()
        ->assertDontSee('12345678')
        ->assertDontSee("fillLogin('admin@alhusseini.com'", false)
        ->assertDontSee('value="admin@alhusseini.com"', false);
});

// ── SEC-09: settings allow-list ──────────────────────────────────────────────

test('settings update ignores keys outside the managed list', function () {
    $this->actingAs($this->admin)->post(route('admin.settings.update'), [
        'company_name' => 'الحسيني',
        'company_phone' => '0100',
        'currency' => 'EGP',
        'injected_key' => 'x',
    ])->assertSessionHasNoErrors();

    expect(Setting::where('key', 'company_name')->value('value'))->toBe('الحسيني')
        ->and(Setting::where('key', 'injected_key')->exists())->toBeFalse();
});
