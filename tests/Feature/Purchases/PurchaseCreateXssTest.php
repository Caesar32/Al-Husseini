<?php

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('purchase create page embeds supplier names without breaking out of the script tag (B2)', function () {
    Supplier::create([
        'name' => 'XSS Test Supplier',
        'company_name' => '</script><script>alert(1)</script>',
        'phone' => '01000000999',
    ]);
    $admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.purchases.create'))
        ->assertOk()
        ->assertDontSee('</script><script>alert(1)', false)
        ->assertSee('\u003C\/script\u003E\u003Cscript\u003Ealert(1)', false);
});
