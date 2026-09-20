<!-- Google Fonts: Cairo & Almarai for Premium Eye-Friendly Arabic Typography -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Almarai:wght@400;700;800&display=swap" rel="stylesheet">

<!-- Layout config Js -->
<script src="{{ asset('assets/js/layout.js') }}"></script>

@yield('css')

@php
    $isRtl = true;
    if (session('locale') === 'en' || (request()->has('lang') && request('lang') === 'en')) {
        $isRtl = false;
    }
@endphp

@if($isRtl)
    <!-- Bootstrap Css RTL -->
    <link href="{{ asset('assets/css/bootstrap-rtl.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- Icons Css -->
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- App Css RTL -->
    <link href="{{ asset('assets/css/app-rtl.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- Custom Css RTL -->
    <link href="{{ asset('assets/css/custom-rtl.min.css') }}" rel="stylesheet" type="text/css" />
@else
    <!-- Bootstrap Css LTR -->
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- Icons Css -->
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- App Css LTR -->
    <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- Custom Css LTR -->
    <link href="{{ asset('assets/css/custom.min.css') }}" rel="stylesheet" type="text/css" />
@endif

<style>
    /* =======================================================
       Eye-Friendly, Bolder & Comfortable Arabic Typography
       ======================================================= */
    body, 
    h1, h2, h3, h4, h5, h6, 
    p, a, 
    button, input, select, textarea, label, 
    table, .table, 
    .navbar-nav, .menu-link, .menu-title, 
    .breadcrumb, .dropdown-item, .card-title, 
    .modal, .toast, .badge {
        font-family: 'Cairo', 'Almarai', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
    }

    body {
        font-weight: 500;
        line-height: 1.7;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        text-rendering: optimizeLegibility;
    }

    /* Headings bolder and clearer */
    h1, h2, h3, h4, h5, h6, .card-title, .fs-14, .fs-15, .fs-16, .fs-18, .fs-22 {
        font-weight: 700 !important;
        letter-spacing: -0.2px;
    }

    /* Sidebar Navigation Links */
    .navbar-nav .nav-link, .menu-link {
        font-weight: 600 !important;
        font-size: 0.94rem;
    }
    .menu-title {
        font-weight: 800 !important;
        font-size: 0.78rem;
        letter-spacing: 0.5px;
    }

    /* Stat Card numbers & Counters */
    .counter-value, .ff-secondary {
        font-weight: 800 !important;
        letter-spacing: -0.5px;
    }

    /* Tables text weight and readability */
    .table td, .table th {
        font-weight: 600;
        vertical-align: middle;
    }

    /* Buttons & Badges */
    .btn, .badge {
        font-weight: 700 !important;
    }

    /* Form labels & inputs */
    .form-label, .form-control, .form-select {
        font-weight: 600;
    }

    /* Muted text - increase weight slightly so it doesn't look washed out */
    .text-muted {
        font-weight: 500 !important;
        opacity: 0.9;
    }

    /* Safe-guard icon font families so icons never break */
    i[class*="ri-"], [class*="ri-"] {
        font-family: 'remixicon' !important;
    }
    i[class*="bx"], [class*="bx-"], .bx {
        font-family: 'boxicons' !important;
    }
    i[class*="mdi"], [class*="mdi-"] {
        font-family: 'Material Design Icons' !important;
    }
</style>
