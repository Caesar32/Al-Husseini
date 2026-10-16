{{-- Bootstrap badge. soft: subtle background + colored text. --}}
@props(['variant' => 'secondary', 'soft' => false, 'pill' => false])
@php
    $classes = 'badge '.($soft ? "bg-{$variant}-subtle text-{$variant}" : "bg-{$variant}").($pill ? ' rounded-pill' : '');
@endphp
<span {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</span>
