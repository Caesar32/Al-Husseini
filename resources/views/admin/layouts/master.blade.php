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
    <title>@yield('title', 'لوحة التحكم') | {{ config('app.name', 'Al-Husseini') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="نظام إدارة مجموعة الحسيني | Al-Husseini Management System" name="description" />
    <meta content="Al-Husseini" name="author" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="asset-url" content="{{ asset('') }}">

    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ asset('assets/images/alhusseini-icon.jpg') }}">

    @include('admin.layouts.partials.head-css')
</head>

<body>

    <!-- Begin page -->
    <div id="layout-wrapper">

        <!-- Header / Topbar -->
        @include('admin.layouts.partials.topbar')

        <!-- Sidebar / Navigation -->
        @include('admin.layouts.partials.sidebar')

        <!-- ============================================================== -->
        <!-- Start right Content here -->
        <!-- ============================================================== -->
        <div class="main-content">

            <div class="page-content">
                <div class="container-fluid">
                    @yield('content')
                </div>
                <!-- container-fluid -->
            </div>
            <!-- End Page-content -->

            <!-- Footer -->
            @include('admin.layouts.partials.footer')

        </div>
        <!-- end main content-->

    </div>
    <!-- END layout-wrapper -->

    <!-- removeNotificationModal -->
    <div id="removeNotificationModal" class="modal fade zoomIn" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="NotificationModalbtn-close"></button>
                </div>
                <div class="modal-body">
                    <div class="mt-2 text-center">
                        <lord-icon src="https://cdn.lordicon.com/gsqxdxog.json" trigger="loop" colors="primary:#f7b84b,secondary:#f06548" style="width:100px;height:100px"></lord-icon>
                        <div class="mt-4 pt-2 fs-15 mx-4 mx-sm-5">
                            <h4>هل أنت متأكد؟</h4>
                            <p class="text-muted mx-4 mb-0">هل تريد حقاً حذف هذا التنبيه؟</p>
                        </div>
                    </div>
                    <div class="d-flex gap-2 justify-content-center mt-4 mb-2">
                        <button type="button" class="btn w-sm btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="button" class="btn w-sm btn-danger" id="delete-notification">نعم، احذفه!</button>
                    </div>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->

    <!-- Theme Customizer & Preloader -->
    @include('admin.layouts.partials.customizer')

    <!-- Vendor Scripts -->
    @include('admin.layouts.partials.vendor-scripts')

</body>

</html>
