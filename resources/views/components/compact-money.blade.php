@props(['amount' => 0])
@php
    $money = \App\Support\MoneyHelper::formatCompactCurrency((float) $amount);
@endphp
<span {{ $attributes->merge([
    'style' => 'cursor: help;',
    'data-bs-toggle' => 'tooltip',
    'data-bs-placement' => 'top',
    'title' => $money['exact'],
]) }}>{{ $money['compact'] }}</span>
