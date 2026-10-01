<?php

use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The login page's account switcher: buttons that fill in the login of a known account.
// Config auth.login_switcher drives it; nothing is hard-coded in the view, and a password is
// never rendered in production.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    // Independent of the developer's .env
    config(['auth.login_switcher.enabled' => null, 'auth.login_switcher.password' => null]);
});

function loginPage($test): string
{
    return $test->get(route('admin.login'))->assertOk()->getContent();
}

test('the switcher lists the available accounts in a non-production environment', function () {
    $html = loginPage($this);

    expect($html)
        ->toContain('id="account-switcher"')
        ->toContain('data-login="admin@alhusseini.com"')
        ->toContain('data-login="cashier@alhusseini.com"')
        ->toContain('data-login="accountant@alhusseini.com"')
        ->toContain('المشرف العام')
        ->toContain('كاشير المبيعات')
        ->toContain('المحاسب المالي')
        ->not->toContain('data-password');
});

test('only existing, active accounts are offered', function () {
    User::where('email', 'cashier@alhusseini.com')->update(['is_active' => false]);
    User::where('email', 'accountant@alhusseini.com')->delete();

    $html = loginPage($this);

    expect($html)
        ->toContain('data-login="admin@alhusseini.com"')
        ->not->toContain('data-login="cashier@alhusseini.com"')
        ->not->toContain('data-login="accountant@alhusseini.com"');
});

test('a configured development password is rendered outside production only', function () {
    config(['auth.login_switcher.password' => 'dev-only-secret']);

    expect(loginPage($this))->toContain('data-password="dev-only-secret"');

    app()->instance('env', 'production');
    config(['auth.login_switcher.enabled' => true]); // explicitly forced on in production

    $production = loginPage($this);
    expect($production)
        ->toContain('data-login="admin@alhusseini.com"') // the switcher itself was explicitly enabled
        ->not->toContain('dev-only-secret')
        ->not->toContain('data-password');
});

test('production hides the switcher by default and never shows credentials', function () {
    app()->instance('env', 'production');
    config(['auth.login_switcher.password' => 'dev-only-secret']);

    expect(loginPage($this))
        ->not->toContain('id="account-switcher"')
        ->not->toContain('data-login')
        ->not->toContain('dev-only-secret')
        ->not->toContain('admin@alhusseini.com');
});

test('the switcher can be switched off explicitly', function () {
    config(['auth.login_switcher.enabled' => false]);

    expect(loginPage($this))->not->toContain('id="account-switcher"');
});

test('the login form itself is never prefilled', function () {
    config(['auth.login_switcher.password' => 'dev-only-secret']);

    expect(loginPage($this))
        ->not->toContain('value="admin@alhusseini.com"')
        ->not->toMatch('/id="password-input"[^>]*value=/');
});

// ── Normal login flow, unchanged ─────────────────────────────────────────────

test('accounts offered by the switcher can log in with the right password and land correctly', function (string $email, string $route) {
    User::where('email', $email)->update(['password' => bcrypt('Correct-Horse-9')]);

    $this->post(route('admin.login.submit'), ['login' => $email, 'password' => 'Correct-Horse-9'])
        ->assertRedirect(route($route));
    $this->assertAuthenticated();
})->with([
    ['admin@alhusseini.com', 'admin.dashboard'],      // admin
    ['cashier@alhusseini.com', 'admin.pos.index'],    // non-admin
    ['accountant@alhusseini.com', 'admin.pos.index'], // non-admin
]);

test('a wrong password is rejected for every offered account', function (string $email) {
    User::where('email', $email)->update(['password' => bcrypt('Correct-Horse-9')]);

    $this->from(route('admin.login'))
        ->post(route('admin.login.submit'), ['login' => $email, 'password' => 'wrong-password'])
        ->assertRedirect(route('admin.login'))
        ->assertSessionHasErrors();
    $this->assertGuest();
})->with(['admin@alhusseini.com', 'cashier@alhusseini.com', 'accountant@alhusseini.com']);

test('selecting an account does not authenticate anything: the password is still verified', function () {
    // Even when the dev password is rendered, only POST /admin/login with valid credentials logs in.
    config(['auth.login_switcher.password' => 'not-the-real-password']);
    User::where('email', 'admin@alhusseini.com')->update(['password' => bcrypt('Correct-Horse-9')]);

    $this->post(route('admin.login.submit'), ['login' => 'admin@alhusseini.com', 'password' => 'not-the-real-password'])
        ->assertSessionHasErrors();
    $this->assertGuest();
});
