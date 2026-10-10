<?php

use App\Services\Support\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('issues sequential numbers per key', function () {
    $service = app(DocumentNumberService::class);

    $numbers = DB::transaction(fn () => [
        $service->nextFormatted('INV-20261001'),
        $service->nextFormatted('INV-20261001'),
        $service->nextFormatted('INV-20261002'),
        $service->nextFormatted('INV-20261001'),
    ]);

    expect($numbers)->toBe([
        'INV-20261001-000001',
        'INV-20261001-000002',
        'INV-20261002-000001',
        'INV-20261001-000003',
    ]);
});

test('numbers issued in a rolled back transaction are not consumed', function () {
    $service = app(DocumentNumberService::class);

    try {
        DB::transaction(function () use ($service) {
            $service->next('CLM-202610');
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(DB::transaction(fn () => $service->next('CLM-202610')))->toBe(1);
});

test('refuses to run outside a transaction', function () {
    // RefreshDatabase wraps each test in a transaction; leave it to simulate a bare call.
    $level = DB::transactionLevel();
    for ($i = 0; $i < $level; $i++) {
        DB::rollBack();
    }

    try {
        expect(fn () => app(DocumentNumberService::class)->next('X'))->toThrow(LogicException::class);
    } finally {
        for ($i = 0; $i < $level; $i++) {
            DB::beginTransaction();
        }
    }
});
