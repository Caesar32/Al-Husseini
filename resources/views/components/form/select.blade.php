{{--
    <x-form.select name="branch_id" label="الفرع" :options="$branches->pluck('name', 'id')" placeholder="بدون فرع" :selected="$u->branch_id" />
    Pass <option>s in the slot instead of :options for custom option markup.
--}}
@props([
    'name', 'label' => null, 'options' => [], 'selected' => null, 'placeholder' => null, 'id' => null,
    'required' => false, 'help' => null, 'stateful' => true, 'wrapperClass' => 'mb-3', 'labelClass' => 'form-label fw-semibold',
])
@php
    $id ??= \App\Support\FormIds::id($name);
    $errorKey = \App\Support\FormIds::errorKey($name);
    $hasError = $stateful && isset($errors) && $errors->has($errorKey);
    $describedBy = trim(($help ? $id.'-help ' : '').($hasError ? $id.'-error' : ''));
    $current = $stateful ? old($errorKey, $selected) : $selected;
@endphp
<x-form.field :id="$id" :label="$label" :required="$required" :help="$help" :error-key="$errorKey" :stateful="$stateful" :wrapper-class="$wrapperClass" :label-class="$labelClass">
    <select name="{{ $name }}" id="{{ $id }}" @if ($required)required @endif @if ($hasError)aria-invalid="true" @endif @if ($describedBy)aria-describedby="{{ $describedBy }}" @endif{{ $attributes->class(['form-select', 'is-invalid' => $hasError]) }}>
        @if (! is_null($placeholder))<option value="">{{ $placeholder }}</option>@endif
        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        @endif
    </select>
</x-form.field>
