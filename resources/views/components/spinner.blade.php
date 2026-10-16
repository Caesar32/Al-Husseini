{{-- Loading indicator announced to screen readers. --}}
@props(['size' => 'sm', 'label' => 'جاري التحميل...'])
<span {{ $attributes->merge(['class' => 'spinner-border'.($size ? ' spinner-border-'.$size : '')]) }} role="status"><span class="visually-hidden">{{ $label }}</span></span>
