{{--
    KPI card: <x-stat-card label="..." value="..." icon="shield-check-line" variant="success" tint="success" value-id="statX">
                  <x-slot:footer>...</x-slot:footer></x-stat-card>
    variant colors the value and the icon bubble; tint picks the background (.ah-stat--{tint}, components.css).
    Use the "value-slot" slot (<x-slot:value-slot>) instead of the prop when the value contains markup.
--}}
@props(['label', 'value' => null, 'valueId' => null, 'icon' => null, 'variant' => 'primary', 'iconVariant' => null, 'valueClass' => null, 'tint' => null])
@php
    $iconVariant ??= $variant;
    $valueClass ??= 'text-'.$variant;
@endphp
<div {{ $attributes->merge(['class' => 'card card-animate border-0 shadow-sm overflow-hidden h-100 ah-stat'.($tint ? ' ah-stat--'.$tint : '')]) }}>
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <p class="text-uppercase fw-semibold text-muted fs-12 mb-1">{{ $label }}</p>
                <h3 class="mb-0 fw-bold {{ $valueClass }}"@if ($valueId) id="{{ $valueId }}"@endif>@isset($valueSlot){{ $valueSlot }}@else{{ $value }}@endisset</h3>
            </div>
            @if ($icon)
                <div class="avatar-sm shrink-0">
                    <span class="avatar-title bg-{{ $iconVariant }}-subtle text-{{ $iconVariant }} rounded-circle fs-24">
                        <x-icon :name="$icon" />
                    </span>
                </div>
            @endif
        </div>
        {{ $footer ?? '' }}
    </div>
</div>
