<!doctype html>
@php
    $isRtl = true;
    if (session('locale') === 'en' || (request()->has('lang') && request('lang') === 'en')) {
        $isRtl = false;
    }
    $locale = $isRtl ? 'ar' : 'en';
    $dir = $isRtl ? 'rtl' : 'ltr';
@endphp
<html lang="{{ $locale }}" dir="{{ $dir }}" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable" data-theme="default" data-theme-colors="default" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <title>@yield('title', 'المصادقة') | {{ config('app.name', 'Al-Husseini') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="نظام إدارة مجموعة الحسيني | Al-Husseini Management System" name="description" />
    <meta content="Al-Husseini" name="author" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ asset('assets/images/alhusseini-icon.jpg') }}">

    @include('admin.layouts.partials.head-css')
</head>

<body>

    @yield('content')

    <!-- Vendor Scripts -->
    @include('admin.layouts.partials.vendor-scripts')

</body>

</html>
