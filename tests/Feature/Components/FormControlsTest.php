<?php

use App\Support\FormIds;
use Illuminate\Support\Facades\Route;

// Frontend F2: <x-form.field|input|select|textarea>.

test('field ids and error keys are derived from array names', function () {
    expect(FormIds::id('items[0][qty]'))->toBe('items-0-qty')
        ->and(FormIds::errorKey('items[0][qty]'))->toBe('items.0.qty')
        ->and(FormIds::errorKey('tags[]'))->toBe('tags');
});

test('an input is labelled, required-marked and carries passthrough attributes', function () {
    $this->withViewErrors([])
        ->blade('<x-form.input name="email" type="email" label="البريد" required placeholder="a@b.c" />')
        ->assertSee('<label for="email" class="form-label fw-semibold">البريد <span class="text-danger">*</span></label>', false)
        ->assertSee('type="email" name="email" id="email"', false)
        ->assertSee('required', false)
        ->assertSee('placeholder="a@b.c"', false)
        ->assertSee('class="form-control"', false)
        ->assertDontSee('aria-invalid', false);
});

test('an errored input is invalid and points at an existing error element', function () {
    $this->withViewErrors(['email' => 'البريد مطلوب'])
        ->blade('<x-form.input name="email" label="البريد" help="للدخول" />')
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('aria-describedby="email-help email-error"', false)
        ->assertSee('class="form-control is-invalid"', false)
        ->assertSee('<small id="email-help" class="text-muted">للدخول</small>', false)
        ->assertSee('<div id="email-error" class="invalid-feedback d-block">البريد مطلوب</div>', false);
});

test('old input wins over the value, except on stateless per-row forms and passwords', function () {
    Route::middleware('web')->get('/_old_probe', function () {
        session()->flashInput(['name' => 'قديم', 'password' => 'secret']);
        request()->setLaravelSession(session()->driver());

        return view()->file(__DIR__.'/fixtures/old-probe.blade.php')->render();
    });

    $html = $this->get('/_old_probe')->getContent();
    expect($html)->toContain('id="a" value="قديم"')
        ->and($html)->toContain('id="b" value="حالي"')
        ->and($html)->not->toContain('secret');
});

test('a select renders options, the placeholder and the selected value', function () {
    $this->withViewErrors([])
        ->blade('<x-form.select name="branch_id" label="الفرع" :options="[1 => \'رئيسي\', 2 => \'فرعي\']" selected="2" placeholder="بدون" />')
        ->assertSee('<option value="">بدون</option>', false)
        ->assertSee('<option value="2" selected>فرعي</option>', false)
        ->assertSee('<option value="1" >رئيسي</option>', false);
});

test('a textarea keeps its value and an input group wraps the append slot', function () {
    $this->withViewErrors([])
        ->blade('<x-form.textarea name="notes" label="ملاحظات" value="نص" />')
        ->assertSee('>نص</textarea>', false);

    $this->withViewErrors([])
        ->blade('<x-form.input name="password" type="password" label="كلمة المرور"><x-slot:append><button type="button">عرض</button></x-slot:append></x-form.input>')
        ->assertSeeInOrder(['<div class="input-group">', 'type="password"', '<button type="button">عرض</button>', '</div>'], false);
});
