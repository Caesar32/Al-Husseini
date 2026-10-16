<?php

// Frontend F1: <x-money>, <x-alert>, <x-spinner>, <x-empty-state>.

test('money formats like number_format with the currency', function ($amount, string $expected) {
    $this->blade('<x-money :amount="$amount" />', ['amount' => $amount])->assertSee($expected, false);
})->with([
    [1234.5, '1,234.50 ج.م'],
    [0, '0.00 ج.م'],
    [-50, '-50.00 ج.م'],
    ['99.999', '100.00 ج.م'],
]);

test('money wraps in a span only when given attributes', function () {
    $this->blade('<x-money :amount="5" />')->assertDontSee('<span', false);
    $this->blade('<x-money :amount="5" class="font-monospace" />')->assertSee('<span class="font-monospace">5.00 ج.م</span>', false);
});

test('an alert has role alert, an optional icon and a labelled close button', function () {
    $this->blade('<x-alert variant="success" dismissible icon="checkbox-circle-line">تم</x-alert>')
        ->assertSee('class="alert alert-success alert-dismissible fade show" role="alert"', false)
        ->assertSee('ri-checkbox-circle-line me-1 align-middle', false)
        ->assertSee('data-bs-dismiss="alert" aria-label="إغلاق"', false);
    $this->blade('<x-alert variant="info">x</x-alert>')->assertDontSee('btn-close', false);
});

test('a spinner announces its label', function () {
    $this->blade('<x-spinner class="text-success d-none" id="s" />')
        ->assertSee('<span class="spinner-border spinner-border-sm text-success d-none" id="s" role="status"><span class="visually-hidden">جاري التحميل...</span></span>', false);
});

test('an empty state shows the message, an optional icon and action', function () {
    $this->blade('<x-empty-state message="لا يوجد" />')->assertSee('لا يوجد')->assertDontSee('<i ', false);
    $this->blade('<x-empty-state message="فارغ" icon="inbox-line"><x-slot:action><a href="/n">جديد</a></x-slot:action></x-empty-state>')
        ->assertSee('ri-inbox-line', false)
        ->assertSee('<a href="/n">جديد</a>', false);
});
