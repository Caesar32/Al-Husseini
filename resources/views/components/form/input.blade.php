{{--
    <x-form.input name="email" type="email" label="البريد" required />
    Value: old($name, $value) unless stateful=false (per-row forms in a loop). Passwords never echo a value.
    Extra attributes go on the <input>. Slot "append" wraps the input in .input-group.
--}}
@props([
    'name', 'label' => null, 'type' => 'text', 'value' => null, 'id' => null,
    'required' => false, 'help' => null, 'stateful' => true, 'wrapperClass' => 'mb-3', 'labelClass' => 'form-label fw-semibold',
])
@php
    $id ??= \App\Support\FormIds::id($name);
    $errorKey = \App\Support\FormIds::errorKey($name);
    $hasError = $stateful && isset($errors) && $errors->has($errorKey);
    $describedBy = trim(($help ? $id.'-help ' : '').($hasError ? $id.'-error' : ''));
    $current = $type === 'password' ? null : ($stateful ? old($errorKey, $value) : $value);
@endphp
<x-form.field :id="$id" :label="$label" :required="$required" :help="$help" :error-key="$errorKey" :stateful="$stateful" :wrapper-class="$wrapperClass" :label-class="$labelClass">
    @isset($append)<div class="input-group">@endisset
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}"@if (! is_null($current)) value="{{ $current }}"@endif @if ($required)required @endif @if ($hasError)aria-invalid="true" @endif @if ($describedBy)aria-describedby="{{ $describedBy }}" @endif{{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}>
    @isset($append){{ $append }}</div>@endisset
</x-form.field>
