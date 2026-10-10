<?php

use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The seamless navigation engine (vendor-scripts partial) re-runs scripts after swapping
// .main-content. It must re-run ONLY page-owned scripts; re-running the layout's own inline
// scripts (including the engine) registered a new copy of every global handler per
// navigation, which produced parallel fetches, racing swaps and blank/dimmed/stale content.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->actingAs(User::where('email', 'admin@alhusseini.com')->firstOrFail());
});

/** Markup of the #page-scripts container (up to its closing tag). */
function pageScriptsBlock(string $html): string
{
    $start = strpos($html, '<div id="page-scripts">');
    expect($start)->not->toBeFalse('layout must wrap page scripts in #page-scripts');
    $end = strpos($html, '</div>', $start);

    return substr($html, $start, $end - $start);
}

test('page scripts are inside #page-scripts and layout scripts are outside it', function (string $routeName) {
    $html = $this->get(route($routeName))->assertOk()->getContent();
    $block = pageScriptsBlock($html);

    // the engine, theme sync and CSRF keep-alive are layout-owned: never inside the container
    expect($block)
        ->not->toContain('seamlessNavigate')
        ->not->toContain('applyThemeMode')
        ->not->toContain('refreshCsrfToken');

    // ...but they are present on the page, after the container
    expect($html)->toContain('seamlessNavigate');
    expect(strpos($html, 'seamlessNavigate'))->toBeGreaterThan(strpos($html, '<div id="page-scripts">'));
})->with(['admin.hr.attendance', 'admin.dashboard', 'admin.pos.index']);

test('the page controller scripts of a page are inside the container', function () {
    $html = $this->get(route('admin.hr.attendance'))->assertOk()->getContent();

    expect(pageScriptsBlock($html))->toContain('punchDirect');
});

test('the engine only re-runs page-owned scripts and waits for libraries', function () {
    $html = $this->get(route('admin.profile'))->assertOk()->getContent();

    expect($html)
        ->toContain("doc.querySelectorAll('#page-scripts script')")
        ->not->toContain("body > script")                      // old selector that also matched layout scripts
        ->not->toContain("document.dispatchEvent(new Event('DOMContentLoaded'))") // re-fired every handler of every visited page
        ->toContain('await loadExternalScript(s.src)')         // inline code must wait for the library it needs
        ->toContain('isCurrent()');                            // last click wins; superseded navigations never swap
});
