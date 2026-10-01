<?php

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Sidebar / topbar / dashboard shortcuts must only offer destinations the user may open,
// while the backend keeps answering 403 for everything else.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $branchId = Branch::where('code', 'MAIN')->value('id');
    $make = function (string $role) use ($branchId): User {
        $user = User::factory()->create(['branch_id' => $branchId]);
        $user->assignRole($role);

        return $user;
    };

    $this->users = [
        'super-admin'           => User::where('email', 'admin@alhusseini.com')->firstOrFail(),
        'accountant'            => User::where('email', 'accountant@alhusseini.com')->firstOrFail(),
        'cashier'               => User::where('email', 'cashier@alhusseini.com')->firstOrFail(),
        'branch-manager'        => $make('branch-manager'),
        'workshop-supervisor'   => $make('workshop-supervisor'),
        'no-permissions'        => User::factory()->create(['branch_id' => $branchId]),
    ];
});

/** Sidebar markup (the nav list) of a page every authenticated user can open. */
function sidebarOf($test, User $user): string
{
    $html = $test->actingAs($user)->get(route('admin.profile'))->assertOk()->getContent();
    $start = strpos($html, 'id="navbar-nav"');
    expect($start)->not->toBeFalse();

    return substr($html, $start, strpos($html, '</ul>', $start) - $start);
}

function sidebarHrefs(string $sidebar): array
{
    preg_match_all('/href="([^"]+)"/', $sidebar, $m);

    return array_values(array_unique($m[1]));
}

/** Permissions required by the route that serves $href (from its can: middleware). */
function permissionsFor(string $href): array
{
    $path = parse_url($href, PHP_URL_PATH);
    $route = app('router')->getRoutes()->match(Request::create($path, 'GET'));

    return collect($route->gatherMiddleware())
        ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'can:'))
        ->map(fn ($m) => substr($m, 4))
        ->values()->all();
}

test('super-admin sees every module in the sidebar', function () {
    $hrefs = sidebarHrefs(sidebarOf($this, $this->users['super-admin']));

    foreach ([
        'admin.dashboard', 'admin.pos.index', 'admin.invoices.index', 'admin.credit.index', 'admin.sales.customers',
        'admin.purchases.index', 'admin.suppliers.index', 'admin.sales.products', 'admin.scrap.index',
        'admin.warranties.index', 'admin.warranties.verify', 'admin.hr.employees', 'admin.hr.attendance',
        'admin.hr.payroll', 'admin.hr.reports', 'admin.profile', 'admin.roles.index', 'admin.users.index',
        'admin.settings', 'admin.diagnostics.index',
    ] as $name) {
        expect($hrefs)->toContain(route($name));
    }
});

test('every sidebar link a user sees is a page that user may open', function (string $key) {
    $user = $this->users[$key];
    $hrefs = array_filter(sidebarHrefs(sidebarOf($this, $user)), fn ($h) => str_contains($h, '/admin'));

    foreach ($hrefs as $href) {
        foreach (permissionsFor($href) as $permission) {
            expect($user->can($permission))->toBeTrue("{$key} sees {$href} but lacks {$permission}");
        }
        expect($this->actingAs($user)->get($href)->status())->not->toBe(403, "{$key} got 403 from sidebar link {$href}");
    }
})->with(['super-admin', 'accountant', 'cashier', 'branch-manager', 'workshop-supervisor', 'no-permissions']);

test('a user lacking a permission does not see its link and still gets 403 directly', function (string $key) {
    $user = $this->users[$key];
    $visible = sidebarHrefs(sidebarOf($this, $user));

    $guardedGetRoutes = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
        return in_array('GET', $route->methods(), true)
            && str_starts_with((string) $route->getName(), 'admin.')
            && !str_contains($route->uri(), '{')
            && collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'can:'));
    });

    expect($guardedGetRoutes->count())->toBeGreaterThan(25);

    foreach ($guardedGetRoutes as $route) {
        $permissions = collect($route->gatherMiddleware())
            ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'can:'))->map(fn ($m) => substr($m, 4));
        $allowed = $permissions->every(fn ($p) => $user->can($p));
        $url = url($route->uri());

        if (!$allowed) {
            expect($visible)->not->toContain($url, "{$key} sees forbidden link {$url}");
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }
})->with(['accountant', 'cashier', 'branch-manager', 'workshop-supervisor', 'no-permissions']);

test('section headings only appear when the user can open something in that section', function (string $key) {
    $sidebar = sidebarOf($this, $this->users[$key]);

    // every heading must be followed by at least one link before the next heading
    $parts = preg_split('/<li class="menu-title">/', $sidebar);
    array_shift($parts); // markup before the first heading
    foreach ($parts as $part) {
        $title = trim(strip_tags(strstr($part, '</li>', true)));
        expect(substr_count($part, 'menu-link'))->toBeGreaterThan(0, "{$key}: empty section '{$title}'");
    }
})->with(['super-admin', 'accountant', 'cashier', 'branch-manager', 'workshop-supervisor', 'no-permissions']);

test('cashier sees POS, inventory and warranty sections but not purchasing, HR or admin sections', function () {
    $sidebar = sidebarOf($this, $this->users['cashier']);
    preg_match_all('/<li class="menu-title"><span>(.*?)<\/span>/', $sidebar, $m);

    expect($m[1])
        ->toContain('المبيعات ونقاط البيع')
        ->toContain('المخزون وتجارة الرصاص')
        ->toContain('الضمان وما بعد البيع')
        ->not->toContain('المشتريات والتوريدات')
        ->not->toContain('الموارد البشرية والورشة')
        ->not->toContain('الرئيسية والمؤشرات');

    expect(sidebarHrefs($sidebar))
        ->not->toContain(route('admin.settings'))
        ->not->toContain(route('admin.roles.index'))
        ->not->toContain(route('admin.users.index'))
        ->not->toContain(route('admin.suppliers.index'));
});

test('a user without permissions only gets the profile entry', function () {
    $hrefs = array_filter(sidebarHrefs(sidebarOf($this, $this->users['no-permissions'])), fn ($h) => str_contains($h, '/admin'));

    expect(array_values($hrefs))->toBe([route('admin.profile')]);
});

test('login, root and the logo land on a page the user can open', function (string $key, string $expectedRoute) {
    $user = $this->users[$key];

    expect($user->homeRouteName())->toBe($expectedRoute);

    $this->actingAs($user)->get('/')->assertRedirect(route($expectedRoute));
    $this->get(route($expectedRoute))->assertOk();
    $this->get(route('admin.login'))->assertRedirect(route($expectedRoute));
})->with([
    ['super-admin', 'admin.dashboard'],
    ['cashier', 'admin.pos.index'],
    ['accountant', 'admin.pos.index'],
    ['branch-manager', 'admin.pos.index'],
    ['workshop-supervisor', 'admin.sales.products'],
    ['no-permissions', 'admin.profile'],
]);

test('the login form sends a user without dashboard access to a page they can open', function () {
    $user = $this->users['workshop-supervisor'];
    $user->update(['password' => bcrypt('Sup3rvisor-pass')]);

    $this->post(route('admin.login.submit'), ['email' => $user->email, 'password' => 'Sup3rvisor-pass'])
        ->assertRedirect(route('admin.sales.products'));
});

test('topbar shows settings and notifications only to users who may use them', function () {
    $admin = $this->actingAs($this->users['super-admin'])->get(route('admin.profile'))->getContent();
    expect($admin)->toContain('notificationDropdown')->toContain(route('admin.settings'));

    $cashier = $this->actingAs($this->users['cashier'])->get(route('admin.profile'))->getContent();
    expect($cashier)->not->toContain('notificationDropdown')->not->toContain(route('admin.settings'));
});

// ── Dashboard shortcuts / recent actions ─────────────────────────────────────

function dashboardUser(array $permissions): User
{
    $role = Role::create(['name' => 'dash-' . implode('-', $permissions ?: ['only']), 'guard_name' => 'web']);
    $role->givePermissionTo(array_merge(['dashboard.view'], $permissions));
    $user = User::factory()->create(['branch_id' => Branch::where('code', 'MAIN')->value('id')]);
    $user->assignRole($role);

    return $user;
}

test('dashboard without module permissions offers no module links', function () {
    $html = $this->actingAs(dashboardUser([]))->get(route('admin.dashboard'))->assertOk()->getContent();

    foreach (['admin.sales.credit', 'admin.sales.customers', 'admin.sales.products', 'admin.sales.invoices',
        'admin.sales.pos', 'admin.pos.index', 'admin.hr.attendance'] as $name) {
        expect($html)->not->toContain(route($name));
    }
    expect($html)->not->toContain('/admin/credit/')->not->toContain('/admin/invoices/');
});

test('dashboard shortcuts appear exactly for the permissions the user holds', function () {
    $html = $this->actingAs(dashboardUser(['credit.view', 'attendance.view']))->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)
        ->toContain(route('admin.sales.credit'))
        ->toContain(route('admin.hr.attendance'))
        ->not->toContain(route('admin.sales.products'))
        ->not->toContain(route('admin.sales.customers'))
        ->not->toContain(route('admin.sales.invoices'))
        ->not->toContain(route('admin.sales.pos'));
});

test('super-admin dashboard keeps all shortcuts', function () {
    $html = $this->actingAs($this->users['super-admin'])->get(route('admin.dashboard'))->assertOk()->getContent();

    foreach (['admin.sales.credit', 'admin.sales.customers', 'admin.sales.products', 'admin.sales.invoices',
        'admin.sales.pos', 'admin.hr.attendance'] as $name) {
        expect($html)->toContain(route($name));
    }
});
