{{--
    Bootstrap switch. An unchecked checkbox posts nothing; pass unchecked-value="0" only when the
    controller expects the field (controllers using $request->has() would read the hidden field as "on").
--}}
@props([
    'name', 'label', 'checked' => false, 'value' => '1', 'uncheckedValue' => null, 'id' => null,
    'help' => null, 'wrapperClass' => 'form-check form-switch form-switch-md',
])
@php
    $id ??= \App\Support\FormIds::id($name);
@endphp
<div class="{{ $wrapperClass }}">
    @if (! is_null($uncheckedValue))<input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}">@endif
    <input type="checkbox" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" role="switch"@if ($checked) checked @endif @if ($help)aria-describedby="{{ $id }}-help" @endif{{ $attributes->class(['form-check-input']) }}>
    <label class="form-check-label fw-semibold" for="{{ $id }}">{{ $label }}</label>
    @if ($help)<div id="{{ $id }}-help" class="text-muted fs-11">{{ $help }}</div>@endif
</div>
