{{--
    GET filter form for list pages. Fields go in the default slot; "actions" slot is optional.
    A reset link (to the unfiltered page) shows only while one of the $keys is filled.
--}}
@props(['action', 'method' => 'GET', 'keys' => [], 'resetLabel' => 'إلغاء التصفية', 'label' => 'تصفية النتائج'])
@php
    $filtered = collect($keys)->contains(fn ($key) => request()->filled($key));
@endphp
<form method="{{ $method }}" action="{{ $action }}" role="search" aria-label="{{ $label }}" {{ $attributes }}>
    {{ $slot }}
    {{ $actions ?? '' }}
    @if ($filtered)
        <a href="{{ $action }}" class="btn btn-sm btn-light">{{ $resetLabel }}</a>
    @endif
</form>
