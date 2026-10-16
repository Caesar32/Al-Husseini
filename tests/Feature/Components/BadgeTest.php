<?php

use App\Enums\InvoiceStatus;
use App\Support\StatusBadges;

// Frontend F1: <x-badge>, <x-status-badge> and the StatusBadges map.

test('a badge renders solid, soft and pill variants', function () {
    $this->blade('<x-badge variant="danger">أ</x-badge>')->assertSee('<span class="badge bg-danger">أ</span>', false);
    $this->blade('<x-badge variant="success" soft pill class="fs-11">ب</x-badge>')
        ->assertSee('<span class="badge bg-success-subtle text-success rounded-pill fs-11">ب</span>', false);
});

test('every invoice status has an explicit badge', function (InvoiceStatus $status) {
    expect(StatusBadges::for('invoice', $status->value)['class'])->not->toBe('bg-info');
})->with(InvoiceStatus::cases());

test('invoice status badges keep the labels and classes of the old invoice page', function (string $status, string $class, string $label) {
    $this->blade('<x-status-badge type="invoice" :status="$status" class="fs-12 px-3 py-2" />', ['status' => $status])
        ->assertSee('<span class="badge '.$class.' fs-12 px-3 py-2">'.$label.'</span>', false);
})->with([
    ['paid', 'bg-success', 'مدفوعة بالكامل'],
    ['partially_paid', 'bg-warning text-dark', 'مدفوعة جزئياً (آجل)'],
    ['unpaid', 'bg-danger', 'آجل غير مسدد'],
    ['refunded', 'bg-secondary', 'مرتجع'],
    ['partially_refunded', 'bg-warning text-dark', 'مرتجع جزئي'],
    ['cancelled', 'bg-dark', 'ملغاة'],
]);

test('an unknown status falls back to the raw value on bg-info, escaped', function () {
    $this->blade('<x-status-badge type="invoice" status="<b>x</b>" />')
        ->assertSee('<span class="badge bg-info">&lt;b&gt;x&lt;/b&gt;</span>', false);
});
