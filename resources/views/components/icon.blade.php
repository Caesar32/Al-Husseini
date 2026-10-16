{{-- Remix icon. Decorative by default; pass label="..." when the icon alone carries meaning. --}}
@props(['name', 'label' => null])
@if ($label)
    <i {{ $attributes->merge(['class' => 'ri-'.$name]) }} role="img" aria-label="{{ $label }}"></i>
@else
    <i {{ $attributes->merge(['class' => 'ri-'.$name]) }} aria-hidden="true"></i>
@endif
