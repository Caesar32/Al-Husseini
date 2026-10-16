<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// Phase 6 (DB-03): the duplicate payroll unique index is gone; every unique index it duplicated stays.

test('the redundant payroll unique index is dropped and the covering unique index stays', function () {
    expect(Schema::hasIndex('payrolls', 'payrolls_branch_year_month_unique'))->toBeFalse()
        ->and(Schema::hasIndex('payrolls', 'payrolls_branch_id_year_month_unique'))->toBeTrue();
});

test('the unique indexes from the DB-03 audit table are all present', function (string $table, string $index) {
    expect(Schema::hasIndex($table, $index))->toBeTrue();
})->with([
    ['customers', 'customers_phone_unique'],
    ['products', 'products_sku_unique'],
    ['products', 'products_barcode_unique'],
    ['invoices', 'invoices_invoice_number_unique'],
    ['warranties', 'warranties_serial_number_unique'],
    ['purchase_invoices', 'purchase_invoices_invoice_number_unique'],
]);
