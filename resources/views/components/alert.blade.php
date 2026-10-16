{{-- Bootstrap alert with an optional leading icon (remix name) and close button. --}}
@props(['variant' => 'info', 'dismissible' => false, 'icon' => null])
<div {{ $attributes->merge(['class' => 'alert alert-'.$variant.($dismissible ? ' alert-dismissible fade show' : '')]) }} role="alert">
    @if ($icon)<x-icon :name="$icon" class="me-1 align-middle" />@endif
    {{ $slot }}
    @if ($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
    @endif
</div>
