{{--
    Bootstrap modal shell: dialog, content and header with a labelled close button.
    The default slot is everything after the header (modal-body/modal-footer, or a <form> wrapping them).
    Custom header content: <x-slot:header> ... <h5 id="{id}-title">...</h5> ... </x-slot:header>
--}}
@props([
    'id', 'title' => null, 'icon' => null, 'iconClass' => 'me-1', 'size' => null, 'centered' => true,
    'static' => false, 'headerClass' => '', 'contentClass' => 'border-0 shadow', 'closeWhite' => false,
])
@php
    $dialogClass = trim('modal-dialog'.($centered ? ' modal-dialog-centered' : '').($size ? ' modal-'.$size : ''));
@endphp
<div {{ $attributes->merge(['class' => 'modal fade']) }} id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}-title" aria-hidden="true"@if ($static) data-bs-backdrop="static" data-bs-keyboard="false"@endif>
    <div class="{{ $dialogClass }}">
        <div class="modal-content {{ $contentClass }}">
            <div class="modal-header {{ $headerClass }}">
                @isset($header)
                    {{ $header }}
                @else
                    <h5 class="modal-title fw-bold" id="{{ $id }}-title">@if ($icon)<x-icon :name="$icon" :class="$iconClass" /> @endif{{ $title }}</h5>
                @endisset
                <button type="button" class="btn-close{{ $closeWhite ? ' btn-close-white' : '' }}" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            {{ $slot }}
        </div>
    </div>
</div>
