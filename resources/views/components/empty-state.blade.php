{{-- "Nothing here" message for lists and tables; optional icon and action slot. --}}
@props(['message', 'icon' => null])
<div {{ $attributes->merge(['class' => 'text-center text-muted']) }}>
    @if ($icon)<x-icon :name="$icon" class="d-block fs-1 mb-2" />@endif
    {{ $message }}
    @isset($action)<div class="mt-3">{{ $action }}</div>@endisset
</div>
