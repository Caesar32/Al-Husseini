{{--
    Label + control + help + validation error for one form field.
    Ids: control "{id}", help "{id}-help", error "{id}-error" (the control's aria-describedby uses them).
    stateful=false skips @error (per-row forms rendered in a loop share one error bag).
--}}
@props(['id', 'label' => null, 'required' => false, 'help' => null, 'errorKey' => null, 'stateful' => true])
<div {{ $attributes->merge(['class' => 'mb-3']) }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label fw-semibold">{{ $label }}@if ($required) <span class="text-danger">*</span>@endif</label>
    @endif
    {{ $slot }}
    @if ($help)
        <small id="{{ $id }}-help" class="text-muted">{{ $help }}</small>
    @endif
    @if ($stateful && $errorKey && isset($errors))
        @error($errorKey)
            <div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    @endif
</div>
