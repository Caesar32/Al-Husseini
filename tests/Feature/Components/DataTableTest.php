<?php

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Frontend F3: <x-data-table> and <x-filter-bar>, proven on the suppliers list.

test('a data table has a caption, column headers with scope and an empty row spanning every column', function () {
    $this->blade('<x-data-table caption="قائمة" :columns="[\'أ\', [\'label\' => \'ب\', \'class\' => \'text-center\']]" :is-empty="true" empty-message="لا شيء" />')
        ->assertSee('<caption class="visually-hidden">قائمة</caption>', false)
        ->assertSee('<th scope="col">أ</th>', false)
        ->assertSee('<th scope="col" class="text-center">ب</th>', false)
        ->assertSee('<td colspan="2" class="py-4">', false)
        ->assertSee('لا شيء');
});

test('a filter bar is a labelled search form with a reset link only while filtered', function () {
    $this->blade('<x-filter-bar action="/x" :keys="[\'search\']" label="بحث"><input name="search"></x-filter-bar>')
        ->assertSee('<form method="GET" action="/x" role="search" aria-label="بحث"', false)
        ->assertDontSee('إلغاء التصفية');
});

describe('suppliers list', function () {
    beforeEach(function () {
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    });

    test('renders suppliers, searches and shows the empty state', function () {
        Supplier::create(['name' => 'مسؤول', 'company_name' => 'شركة الاختبار الفريدة', 'phone' => '01055500777']);

        $this->actingAs($this->admin)->get(route('admin.suppliers.index'))
            ->assertOk()
            ->assertSee('<caption class="visually-hidden">قائمة الموردين المعتمدين</caption>', false)
            ->assertSee('شركة الاختبار الفريدة')
            ->assertSee('role="search"', false);

        $this->actingAs($this->admin)->get(route('admin.suppliers.index', ['search' => 'شركة الاختبار الفريدة']))
            ->assertSee('شركة الاختبار الفريدة');

        $this->actingAs($this->admin)->get(route('admin.suppliers.index', ['search' => 'لا-يوجد-مورد-بهذا-الاسم']))
            ->assertSee('لا يوجد موردون مسجلون حالياً.')
            ->assertSee('colspan="9"', false);
    });

    test('keeps the search in the pagination links', function () {
        foreach (range(1, 30) as $i) {
            Supplier::create(['name' => 'م', 'company_name' => "مورد صفحات {$i}", 'phone' => '0105550'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)]);
        }

        $html = $this->actingAs($this->admin)->get(route('admin.suppliers.index', ['search' => 'مورد صفحات']))->getContent();

        expect($html)->toContain('search='.rawurlencode('مورد صفحات').'&amp;page=2');
    });
});
