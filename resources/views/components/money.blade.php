{{-- Amount as "1,234.50 ج.م". Plain text unless attributes are given (then wrapped in a span). --}}
@props(['amount' => 0, 'currency' => 'ج.م', 'decimals' => 2])
@if ($attributes->isNotEmpty())<span {{ $attributes }}>{{ number_format((float) $amount, $decimals) }} {{ $currency }}</span>@else{{ number_format((float) $amount, $decimals) }} {{ $currency }}@endif
