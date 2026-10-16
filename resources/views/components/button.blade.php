{{--
    Bootstrap button. Renders <a> when href is set.
    variant: primary, soft-secondary, outline-danger, ... (the part after "btn-").
    An icon-only button must receive aria-label.
--}}
@props([
    'variant' => 'primary',
    'size' => null,
    'icon' => null,
    'iconClass' => 'me-1',
    'type' => 'button',
    'href' => null,
    'loading' => false,
])
@php
    $classes = trim('btn btn-'.$variant.($size ? ' btn-'.$size : ''));
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" :class="$iconClass" />@endif{{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} @if ($loading) disabled aria-busy="true" @endif>
        @if ($loading)<span class="spinner-border spinner-border-sm {{ $iconClass }}" aria-hidden="true"></span>@elseif ($icon)<x-icon :name="$icon" :class="$iconClass" />@endif{{ $slot }}
    </button>
@endif
