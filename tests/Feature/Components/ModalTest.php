<?php

// Frontend F3: <x-modal> and <x-confirm-modal>.

test('a modal is labelled by its title and has a labelled close button', function () {
    $this->blade('<x-modal id="m" title="عنوان" icon="user-add-line" size="lg" header-class="bg-light"><div class="modal-body">ج</div></x-modal>')
        ->assertSee('class="modal fade" id="m" tabindex="-1" aria-labelledby="m-title" aria-hidden="true"', false)
        ->assertSee('<div class="modal-dialog modal-dialog-centered modal-lg">', false)
        ->assertSee('<div class="modal-header bg-light">', false)
        ->assertSee('id="m-title"', false)
        ->assertSee('ri-user-add-line', false)
        ->assertSee('data-bs-dismiss="modal" aria-label="إغلاق"', false)
        ->assertSee('<div class="modal-body">ج</div>', false);
});

test('a static modal ignores backdrop clicks and Esc; a custom header replaces the title', function () {
    $this->blade('<x-modal id="s" static close-white><x-slot:header><h5 id="s-title">خاص</h5></x-slot:header></x-modal>')
        ->assertSee('data-bs-backdrop="static" data-bs-keyboard="false"', false)
        ->assertSee('<h5 id="s-title">خاص</h5>', false)
        ->assertSee('btn-close btn-close-white', false)
        ->assertDontSee('modal-title fw-bold', false);
});

test('a confirm modal shows the message and a confirm button bound by data-confirm', function () {
    $this->blade('<x-confirm-modal id="c" title="حذف" message="هل أنت متأكد؟" confirm-label="حذف" />')
        ->assertSee('aria-labelledby="c-title"', false)
        ->assertSee('<p class="mb-0">هل أنت متأكد؟</p>', false)
        ->assertSee('class="btn btn-danger" data-confirm="c">حذف</button>', false);
});
