{{-- <x-form.textarea name="notes" label="ملاحظات" rows="3" /> --}}
@props([
    'name', 'label' => null, 'value' => null, 'rows' => 3, 'id' => null,
    'required' => false, 'help' => null, 'stateful' => true, 'wrapperClass' => 'mb-3', 'labelClass' => 'form-label fw-semibold',
])
@php
    $id ??= \App\Support\FormIds::id($name);
    $errorKey = \App\Support\FormIds::errorKey($name);
    $hasError = $stateful && isset($errors) && $errors->has($errorKey);
    $describedBy = trim(($help ? $id.'-help ' : '').($hasError ? $id.'-error' : ''));
    $current = $stateful ? old($errorKey, $value) : $value;
@endphp
<x-form.field :id="$id" :label="$label" :required="$required" :help="$help" :error-key="$errorKey" :stateful="$stateful" :wrapper-class="$wrapperClass" :label-class="$labelClass">
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" @if ($required)required @endif @if ($hasError)aria-invalid="true" @endif @if ($describedBy)aria-describedby="{{ $describedBy }}" @endif{{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}>{{ $current }}</textarea>
</x-form.field>
