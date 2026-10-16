<?php

use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Frontend F3: <x-stat-card>, <x-page-header>, proven on the diagnostics page.

test('a stat card shows label, value, icon and tint without inline styles', function () {
    $this->blade('<x-stat-card label="مؤشر" value="90%" value-id="v" icon="shield-check-line" variant="success" tint="success" />')
        ->assertSee('class="card card-animate border-0 shadow-sm overflow-hidden h-100 ah-stat ah-stat--success"', false)
        ->assertSee('<h3 class="mb-0 fw-bold text-success" id="v">90%</h3>', false)
        ->assertSee('bg-success-subtle text-success rounded-circle fs-24', false)
        ->assertSee('ri-shield-check-line', false)
        ->assertDontSee('style=', false);
});

test('a page header renders breadcrumbs with the current page marked', function () {
    $this->blade('<x-page-header title="عنوان" :breadcrumbs="[\'الرئيسية\' => \'/admin\', \'الحالية\' => null]" />')
        ->assertSee('<nav class="page-title-right" aria-label="مسار التنقل">', false)
        ->assertSee('<li class="breadcrumb-item"><a href="/admin">الرئيسية</a></li>', false)
        ->assertSee('<li class="breadcrumb-item active" aria-current="page">الحالية</li>', false);
});

test('the diagnostics page uses the components and keeps the ids its script updates', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->actingAs(User::where('email', 'admin@alhusseini.com')->firstOrFail())
        ->get(route('admin.diagnostics.index'))
        ->assertOk()
        ->assertSee('ah-stat--success', false)
        ->assertSee('id="statHealthScore"', false)
        ->assertSee('id="statPassedChecks"', false)
        ->assertSee('id="statDuration"', false)
        ->assertSee('id="simulationCard"', false)
        ->assertSee(asset('assets/css/components.css'), false);
});
