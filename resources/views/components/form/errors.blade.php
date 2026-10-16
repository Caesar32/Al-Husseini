{{-- Validation summary for classic (non-AJAX) forms. --}}
@props(['bag' => 'default', 'title' => null])
@if (isset($errors) && $errors->getBag($bag)->any())
    <x-alert variant="danger" icon="error-warning-line" {{ $attributes }}>
        @if ($title)<strong class="d-block mb-1">{{ $title }}</strong>@endif
        <ul class="mb-0">
            @foreach ($errors->getBag($bag)->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
