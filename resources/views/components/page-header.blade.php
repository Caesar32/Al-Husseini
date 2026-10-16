{{--
    Page title row with breadcrumbs: <x-page-header title="..." :breadcrumbs="['الرئيسية' => route('admin.dashboard'), 'الصفحة' => null]" icon="pulse-line" />
    A breadcrumb with a null URL is the current page. Slot "actions" renders before the breadcrumbs.
--}}
@props(['title', 'breadcrumbs' => [], 'icon' => null, 'iconClass' => 'fs-20', 'boxClass' => ''])
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between {{ $boxClass }}">
            <h4 class="mb-sm-0 d-flex align-items-center gap-2">
                @if ($icon)<x-icon :name="$icon" :class="$iconClass" />@endif
                <span>{{ $title }}</span>
            </h4>
            {{ $actions ?? '' }}
            @if ($breadcrumbs)
                <nav class="page-title-right" aria-label="مسار التنقل">
                    <ol class="breadcrumb m-0">
                        @foreach ($breadcrumbs as $crumb => $url)
                            @if ($url)
                                <li class="breadcrumb-item"><a href="{{ $url }}">{{ $crumb }}</a></li>
                            @else
                                <li class="breadcrumb-item active" aria-current="page">{{ $crumb }}</li>
                            @endif
                        @endforeach
                    </ol>
                </nav>
            @endif
        </div>
    </div>
</div>
