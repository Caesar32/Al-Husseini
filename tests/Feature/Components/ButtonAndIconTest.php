<?php

// Frontend F1: <x-icon> and <x-button>.

test('a decorative icon is hidden from assistive technology', function () {
    $this->blade('<x-icon name="printer-line" class="me-1" />')
        ->assertSee('<i class="ri-printer-line me-1" aria-hidden="true"></i>', false);
});

test('a meaningful icon gets an accessible name', function () {
    $this->blade('<x-icon name="alarm-warning-line" label="تنبيه" />')
        ->assertSee('role="img" aria-label="تنبيه"', false)
        ->assertDontSee('aria-hidden', false);
});

test('a button renders variant, size, icon and passes attributes through', function () {
    $this->blade('<x-button variant="soft-secondary" size="sm" icon="printer-line" class="ms-2" data-action="print">طباعة</x-button>')
        ->assertSee('<button type="button" class="btn btn-soft-secondary btn-sm ms-2" data-action="print"', false)
        ->assertSeeInOrder(['<i class="ri-printer-line me-1" aria-hidden="true"></i>', 'طباعة', '</button>'], false);
});

test('a button with href renders a link', function () {
    $this->blade('<x-button href="/x" variant="light">رجوع</x-button>')
        ->assertSee('<a href="/x" class="btn btn-light">', false)
        ->assertDontSee('<button', false);
});

test('a loading button is disabled, busy and shows a spinner instead of its icon', function () {
    $this->blade('<x-button icon="save-line" :loading="true">حفظ</x-button>')
        ->assertSee('disabled aria-busy="true"', false)
        ->assertSee('spinner-border spinner-border-sm', false)
        ->assertDontSee('ri-save-line', false);
});

test('an icon-only button can carry an accessible name', function () {
    $this->blade('<x-button variant="light" icon="close-line" icon-class="" aria-label="إغلاق" />')
        ->assertSee('aria-label="إغلاق"', false);
});
