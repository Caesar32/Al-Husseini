<!-- Early Synchronous Theme Loader (Zero White Flash / FOUC) -->
<script>
    (function () {
        var savedTheme = localStorage.getItem('data-bs-theme') || sessionStorage.getItem('data-bs-theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
            try { sessionStorage.setItem('data-bs-theme', savedTheme); } catch(e){}
        }
    })();
</script>

<!-- Google Fonts: Cairo, Almarai & Outfit (for crisp Arabic typography & tabular numerals) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Almarai:wght@400;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Layout config Js -->
<script src="{{ asset('assets/js/layout.js') }}"></script>

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
@else
    <!-- Bootstrap Css LTR -->
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- Icons Css -->
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- App Css LTR -->
    <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css" />
@endif

<!-- SweetAlert2 Css -->
<link href="{{ asset('assets/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />

<style>
    /* =======================================================
       Global CSS Variables & Brand Palette from Assets
       ======================================================= */
    :root {
        /* Typography Variables (Propagate into Velzon & Bootstrap components) */
        --vz-body-font-family: 'Cairo', 'Almarai', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        --vz-font-sans-serif: 'Cairo', 'Almarai', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        --vz-font-family-secondary: 'Cairo', 'Almarai', sans-serif !important;
        --bs-body-font-family: 'Cairo', 'Almarai', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        --bs-font-sans-serif: 'Cairo', 'Almarai', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        --vz-btn-font-family: 'Cairo', 'Almarai', sans-serif !important;
        --vz-font-monospace: 'Outfit', 'Poppins', 'Segoe UI', Consolas, monospace !important;
        --bs-font-monospace: 'Outfit', 'Poppins', 'Segoe UI', Consolas, monospace !important;

        /* Brand Colors Matching Al-Husseini Royal Emblem */
        --brand-sapphire: #1a4480;
        --brand-sapphire-dark: #123363;
        --brand-sapphire-light: #2563eb;
        --brand-gold: #c59b27;
        --brand-gold-accent: #d4af37;
        --brand-gold-light: #fdf5d7;
        --brand-navy-dark: #0c141f;

        /* Base Border Radii for Modern Sleek Finish (8px default) */
        --vz-border-radius: 0.5rem;
        --vz-border-radius-sm: 0.375rem;
        --vz-border-radius-lg: 0.75rem;
        --vz-border-radius-xl: 0.875rem;
        --vz-border-radius-2xl: 1rem;
        --vz-border-radius-pill: 50rem;
    }

    /* =======================================================
       Eye-Friendly, Bolder & Comfortable Arabic Typography
       ======================================================= */
    html, body, 
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
        line-height: 1.68;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        text-rendering: optimizeLegibility;
    }

    /* Headings bolder, clearer, and premium */
    h1, h2, h3, h4, h5, h6, .card-title, .fs-14, .fs-15, .fs-16, .fs-18, .fs-22 {
        font-weight: 700 !important;
        letter-spacing: -0.2px;
    }

    /* Tabular Numeral Styling for Prices, Invoices, and Codes */
    .font-monospace, .font-mono, [class*="font-monospace"] {
        font-family: var(--vz-font-monospace) !important;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.2px;
    }

    /* Stat Card numbers & Counters */
    .counter-value, .ff-secondary {
        font-family: 'Cairo', 'Outfit', sans-serif !important;
        font-weight: 800 !important;
        letter-spacing: -0.5px;
    }

    /* Tables text weight and readability */
    .table td, .table th {
        font-weight: 600;
        vertical-align: middle;
    }

    /* Form labels & inputs */
    .form-label, .form-control, .form-select {
        font-weight: 600;
    }

    /* Muted text - high readability */
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
    .navbar-menu .navbar-nav .nav-link[data-bs-toggle="collapse"]:after {
        font-family: 'Material Design Icons' !important;
    }

    /* =======================================================
       State-of-the-Art Button Design System
       ======================================================= */
    .btn:not(.header-item):not(.vertical-menu-btn):not(.btn-vertical-sm-hover):not(.btn-topbar) {
        font-family: var(--vz-btn-font-family);
        font-weight: 700 !important;
        border-radius: 8px;
        padding: 0.48rem 1.05rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        letter-spacing: -0.1px;
        transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1) !important;
        box-shadow: none;
    }

    .btn:not(.header-item):not(.vertical-menu-btn):not(.btn-vertical-sm-hover):not(.btn-topbar):hover:not(:disabled) {
        transform: translateY(-1.5px);
    }

    .btn:not(.header-item):not(.vertical-menu-btn):not(.btn-vertical-sm-hover):not(.btn-topbar):active:not(:disabled) {
        transform: translateY(0.5px);
    }

    .btn-sm:not(.header-item):not(.vertical-menu-btn):not(.btn-vertical-sm-hover):not(.btn-topbar) {
        padding: 0.32rem 0.75rem;
        font-size: 0.78rem;
        border-radius: 7px;
        gap: 0.3rem;
    }

    .btn-lg {
        padding: 0.7rem 1.5rem;
        font-size: 0.98rem;
        border-radius: 10px;
        gap: 0.5rem;
    }

    .btn.rounded-pill {
        border-radius: 50rem !important;
        padding-left: 1.25rem;
        padding-right: 1.25rem;
    }

    .btn-icon {
        width: 36px;
        height: 36px;
        padding: 0 !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        flex-shrink: 0;
    }

    .btn-icon.btn-sm {
        width: 28px;
        height: 28px;
        border-radius: 6px;
    }

    .btn-icon.btn-lg {
        width: 44px;
        height: 44px;
        border-radius: 10px;
    }

    /* Primary Brand Button (Royal Sapphire Blue) */
    .btn-primary {
        background: linear-gradient(135deg, #1f4f96, #163b70) !important;
        border-color: #163b70 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(22, 59, 112, 0.25);
    }
    .btn-primary:hover:not(:disabled) {
        background: linear-gradient(135deg, #2663bc, #1a4480) !important;
        border-color: #1a4480 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 14px rgba(22, 59, 112, 0.38) !important;
    }

    /* Warning Brand Button (Royal Amber Gold) */
    .btn-warning {
        background: linear-gradient(135deg, #d4af37, #b8860b) !important;
        border-color: #b8860b !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(184, 134, 11, 0.25);
    }
    .btn-warning:hover:not(:disabled) {
        background: linear-gradient(135deg, #e5c158, #c59b27) !important;
        border-color: #c59b27 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 14px rgba(184, 134, 11, 0.38) !important;
    }

    /* Success Button (Emerald Green) */
    .btn-success {
        background: linear-gradient(135deg, #0ab39c, #089481) !important;
        border-color: #089481 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(10, 179, 156, 0.25);
    }
    .btn-success:hover:not(:disabled) {
        background: linear-gradient(135deg, #0ec9b0, #0ab39c) !important;
        border-color: #0ab39c !important;
        color: #ffffff !important;
        box-shadow: 0 4px 14px rgba(10, 179, 156, 0.38) !important;
    }

    /* Danger Button (Crimson) */
    .btn-danger {
        background: linear-gradient(135deg, #f06548, #d84d31) !important;
        border-color: #d84d31 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(240, 101, 72, 0.25);
    }
    .btn-danger:hover:not(:disabled) {
        background: linear-gradient(135deg, #f37d64, #f06548) !important;
        border-color: #f06548 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 14px rgba(240, 101, 72, 0.38) !important;
    }

    /* Info Button (Sky Blue) */
    .btn-info {
        background: linear-gradient(135deg, #299cdb, #1e83bc) !important;
        border-color: #1e83bc !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(41, 156, 219, 0.25);
    }
    .btn-info:hover:not(:disabled) {
        background: linear-gradient(135deg, #3aafe9, #299cdb) !important;
        border-color: #299cdb !important;
        color: #ffffff !important;
        box-shadow: 0 4px 14px rgba(41, 156, 219, 0.38) !important;
    }

    /* Soft Buttons (Refined Subtle Tints with matching borders) */
    .btn-soft-primary {
        background-color: rgba(26, 68, 128, 0.08) !important;
        color: #1a4480 !important;
        border: 1px solid rgba(26, 68, 128, 0.16) !important;
    }
    .btn-soft-primary:hover:not(:disabled) {
        background-color: #1a4480 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(26, 68, 128, 0.25) !important;
    }

    .btn-soft-warning {
        background-color: rgba(212, 175, 55, 0.1) !important;
        color: #b38c1b !important;
        border: 1px solid rgba(212, 175, 55, 0.25) !important;
    }
    .btn-soft-warning:hover:not(:disabled) {
        background-color: #c59b27 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(197, 155, 39, 0.3) !important;
    }

    .btn-soft-success {
        background-color: rgba(10, 179, 156, 0.08) !important;
        color: #0ab39c !important;
        border: 1px solid rgba(10, 179, 156, 0.2) !important;
    }
    .btn-soft-success:hover:not(:disabled) {
        background-color: #0ab39c !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(10, 179, 156, 0.25) !important;
    }

    .btn-soft-danger {
        background-color: rgba(240, 101, 72, 0.08) !important;
        color: #f06548 !important;
        border: 1px solid rgba(240, 101, 72, 0.2) !important;
    }
    .btn-soft-danger:hover:not(:disabled) {
        background-color: #f06548 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(240, 101, 72, 0.25) !important;
    }

    .btn-soft-info {
        background-color: rgba(41, 156, 219, 0.08) !important;
        color: #299cdb !important;
        border: 1px solid rgba(41, 156, 219, 0.2) !important;
    }
    .btn-soft-info:hover:not(:disabled) {
        background-color: #299cdb !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(41, 156, 219, 0.25) !important;
    }

    /* Form Controls & Inputs */
    .form-control, .form-select, .input-group-text {
        border-radius: 8px;
        font-size: 0.86rem;
        font-weight: 500;
        border: 1px solid rgba(var(--vz-dark-rgb), 0.13);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--brand-sapphire);
        box-shadow: 0 0 0 3px rgba(26, 68, 128, 0.15);
    }

    /* Badges */
    .badge {
        font-weight: 700 !important;
        border-radius: 6px;
        padding: 0.38em 0.7em;
        letter-spacing: 0.1px;
    }
    .badge.rounded-pill {
        border-radius: 50rem !important;
        padding: 0.38em 0.85em;
    }

    /* Global Card Polish */
    .card {
        border-radius: 12px;
        border: 1px solid rgba(var(--vz-dark-rgb), 0.08);
        box-shadow: 0 2px 12px rgba(18, 38, 63, 0.04);
    }
    .card-header {
        border-bottom: 1px solid rgba(var(--vz-dark-rgb), 0.06);
    }

    /* Modern Global Scrollbars */
    ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    ::-webkit-scrollbar-track {
        background: transparent;
    }
    ::-webkit-scrollbar-thumb {
        background: rgba(var(--vz-dark-rgb), 0.18);
        border-radius: 6px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: rgba(var(--vz-dark-rgb), 0.28);
    }
    [data-bs-theme="dark"] ::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.18);
    }
    [data-bs-theme="dark"] ::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.28);
    }

    /* Clean & Comfortable Sidebar Typography (Preserves Velzon Layout & SimpleBar) */
    :not([data-sidebar-size="sm"]) .navbar-nav .nav-link,
    :not([data-sidebar-size="sm"]) .menu-link {
        font-weight: 500;
        font-size: 0.86rem;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    :not([data-sidebar-size="sm"]) .navbar-nav .nav-link.active,
    :not([data-sidebar-size="sm"]) .menu-link.active {
        font-weight: 700;
        background: rgba(255, 255, 255, 0.08);
    }

    :not([data-sidebar-size="sm"]) .menu-title {
        font-weight: 700;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* =======================================================
       Small Sidebar (sm) Layout Architecture & Sticky Footer
       ======================================================= */
    @media (min-width: 768px) {
        html:is([data-layout=vertical],[data-layout=semibox])[data-sidebar-size=sm],
        html[data-sidebar-size=sm],
        body[data-sidebar-size=sm],
        :is([data-layout=vertical],[data-layout=semibox])[data-sidebar-size=sm],
        :is([data-layout=vertical],[data-layout=semibox])[data-sidebar-size=sm] body,
        :is([data-layout=vertical],[data-layout=semibox])[data-sidebar-size=sm] #layout-wrapper,
        :is([data-layout=vertical],[data-layout=semibox])[data-sidebar-size=sm] .main-content,
        :is([data-layout=vertical],[data-layout=semibox])[data-layout-style=detached][data-sidebar-size=sm] #layout-wrapper,
        :is([data-layout=vertical],[data-layout=semibox])[data-layout-style=detached][data-sidebar-size=sm] .main-content,
        [data-layout-width=boxed][data-sidebar-size=sm-hover][data-layout=vertical] #layout-wrapper,
        [data-layout-width=boxed][data-sidebar-size=sm][data-layout=vertical] #layout-wrapper {
            min-height: 100vh !important;
            height: auto !important;
        }
    }

    /* Fixed full-height sidebar in collapsed mode: prevents dark background from cutting off */
    :is([data-layout=vertical],[data-layout=semibox])[data-sidebar-size=sm] .navbar-menu {
        position: fixed !important;
        top: 0 !important;
        bottom: 0 !important;
        height: 100vh !important;
        min-height: 100vh !important;
        max-height: 100vh !important;
        z-index: 1002 !important;
        background: var(--vz-vertical-menu-bg) !important;
        border-left: 1px solid var(--vz-vertical-menu-border) !important;
        padding-top: 70px !important;
    }

    #layout-wrapper {
        min-height: 100vh !important;
        display: flex !important;
        flex-direction: column !important;
    }

    .main-content {
        min-height: 100vh !important;
        display: flex !important;
        flex-direction: column !important;
        position: relative !important;
    }

    .page-content {
        flex: 1 0 auto !important;
    }

    /* Modern Flexbox Sticky Footer: sticks to bottom of viewport on short pages */
    .footer {
        margin-top: auto !important;
        position: relative !important;
        bottom: 0 !important;
        right: auto !important;
        left: auto !important;
        width: 100% !important;
        flex-shrink: 0 !important;
        z-index: 10 !important;
    }

    /* =======================================================
       Comprehensive Dark Mode Enhancements & Adaptations
       ======================================================= */
    [data-bs-theme="dark"] {
        --vz-body-bg: #121820;
        --vz-card-bg: #18202b;
        --vz-light: #1f2a38;
        --vz-border-color: #283546;
        --vz-heading-color: #f1f5f9;
        --vz-body-color: #cbd5e1;

        /* Topbar & Header Dark Tokens */
        --vz-header-bg: #161e29;
        --vz-header-border: #283546;
        --vz-header-item-color: #cbd5e1;
        --vz-header-item-bg: rgba(255, 255, 255, 0.05);
        --vz-header-item-sub-color: #94a3b8;
        --vz-topbar-user-bg: transparent;
        --vz-topbar-search-bg: #1f2a38;
        --vz-topbar-search-color: #f1f5f9;
    }

    /* Topbar Deep Midnight Styling (Eradicates Velzon's unwanted purple-blue / slate tint) */
    [data-bs-theme="dark"] #page-topbar,
    [data-topbar="dark"] #page-topbar,
    [data-bs-theme="dark"][data-topbar="dark"] #page-topbar,
    html[data-bs-theme="dark"] #page-topbar,
    html[data-topbar="dark"] #page-topbar {
        background-color: #161e29 !important;
        background: #161e29 !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
        box-shadow: 0 2px 14px rgba(0, 0, 0, 0.35) !important;
    }

    /* Seamless user dropdown in dark topbar - removes jarring dark rectangular box */
    [data-bs-theme="dark"] .topbar-user,
    [data-topbar="dark"] .topbar-user,
    [data-bs-theme="dark"] #page-header-user-dropdown,
    [data-topbar="dark"] #page-header-user-dropdown,
    html[data-bs-theme="dark"] .topbar-user,
    html[data-topbar="dark"] .topbar-user {
        background-color: transparent !important;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
    }

    [data-bs-theme="dark"] #page-header-user-dropdown:hover,
    [data-topbar="dark"] #page-header-user-dropdown:hover {
        background-color: rgba(255, 255, 255, 0.06) !important;
        border-radius: 8px !important;
    }

    [data-bs-theme="dark"] .user-name-text,
    [data-topbar="dark"] .user-name-text {
        color: #f1f5f9 !important;
    }

    [data-bs-theme="dark"] .user-name-sub-text,
    [data-topbar="dark"] .user-name-sub-text {
        color: #94a3b8 !important;
    }

    /* =======================================================
       Header Hamburger & Sidebar Open/Close Toggle Button Reset
       ======================================================= */
    .header-item.vertical-menu-btn,
    .header-item.topnav-hamburger,
    #topnav-hamburger-icon {
        height: 70px !important;
        background-color: transparent !important;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 0 1.15rem !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        transform: none !important;
        cursor: pointer !important;
        transition: background-color 0.2s ease !important;
    }

    .header-item.vertical-menu-btn:hover,
    .header-item.topnav-hamburger:hover,
    #topnav-hamburger-icon:hover {
        background-color: rgba(255, 255, 255, 0.05) !important;
        transform: none !important;
    }

    [data-bs-theme="light"] .header-item.vertical-menu-btn:hover,
    [data-bs-theme="light"] #topnav-hamburger-icon:hover,
    html:not([data-bs-theme="dark"]) .header-item.vertical-menu-btn:hover,
    html:not([data-bs-theme="dark"]) #topnav-hamburger-icon:hover {
        background-color: rgba(0, 0, 0, 0.04) !important;
    }

    /* Hamburger icon container */
    .hamburger-icon {
        width: 20px !important;
        height: 14px !important;
        position: relative !important;
        cursor: pointer !important;
        display: inline-block !important;
    }

    /* Hamburger bars base */
    .hamburger-icon span {
        position: absolute !important;
        border-radius: 2px !important;
        transition: 0.3s cubic-bezier(0.8, 0.5, 0.2, 1.4) !important;
        width: 100% !important;
        height: 2px !important;
        display: block !important;
        right: 0 !important;
        background-color: #495057 !important;
    }

    /* Dark mode hamburger bars */
    [data-bs-theme="dark"] .hamburger-icon span,
    [data-topbar="dark"] .hamburger-icon span,
    html[data-bs-theme="dark"] .hamburger-icon span,
    html[data-topbar="dark"] .hamburger-icon span {
        background-color: #cbd5e1 !important;
    }

    /* Hover effect on bars when closed */
    .vertical-menu-btn:hover .hamburger-icon:not(.open) span:nth-child(1) {
        top: -1px !important;
    }
    .vertical-menu-btn:hover .hamburger-icon:not(.open) span:nth-child(3) {
        bottom: -1px !important;
    }

    /* Hamburger open transformation (Arrow state) */
    .hamburger-icon.open {
        transform: rotate(90deg) !important;
    }
    .hamburger-icon.open span:nth-child(1) {
        right: 1px !important;
        top: 5px !important;
        width: 20px !important;
        transform: rotate(-90deg) !important;
        transition-delay: 150ms !important;
    }
    .hamburger-icon.open span:nth-child(2) {
        right: 3px !important;
        top: 13px !important;
        width: 10px !important;
        transform: rotate(-45deg) !important;
        transition-delay: 50ms !important;
    }
    .hamburger-icon.open span:nth-child(3) {
        right: 9px !important;
        top: 13px !important;
        width: 10px !important;
        transform: rotate(45deg) !important;
        transition-delay: 0.1s !important;
    }

    /* Topbar buttons & utility icons */
    [data-bs-theme="dark"] .header-item .btn-topbar,
    [data-topbar="dark"] .header-item .btn-topbar {
        color: #cbd5e1 !important;
        background: transparent !important;
    }

    [data-bs-theme="dark"] .header-item .btn-topbar:hover,
    [data-topbar="dark"] .header-item .btn-topbar:hover {
        color: #ffffff !important;
        background-color: rgba(255, 255, 255, 0.08) !important;
    }

    /* Sidebar pin / hover button (#vertical-hover) */
    .navbar-menu .btn-vertical-sm-hover,
    #vertical-hover {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        transform: none !important;
        border-radius: 0 !important;
        color: #94a3b8 !important;
    }
    #vertical-hover:hover {
        color: #ffffff !important;
        transform: none !important;
    }

    /* Unified navbar-brand-box in small sidebar mode */
    [data-bs-theme="dark"]:is([data-layout=vertical],[data-layout=semibox])[data-sidebar-size=sm] .navbar-brand-box,
    [data-topbar="dark"]:is([data-layout=vertical],[data-layout=semibox])[data-sidebar-size=sm] .navbar-brand-box,
    html[data-bs-theme="dark"]:is([data-layout=vertical],[data-layout=semibox])[data-sidebar-size=sm] .navbar-brand-box {
        background-color: #161e29 !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    }

    /* Search bar inside topbar in dark mode */
    [data-bs-theme="dark"] .app-search .form-control,
    [data-topbar="dark"] .app-search .form-control {
        background-color: #1f2a38 !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
        color: #f1f5f9 !important;
    }

    [data-bs-theme="dark"] .app-search .form-control::placeholder,
    [data-topbar="dark"] .app-search .form-control::placeholder {
        color: #64748b !important;
    }

    [data-bs-theme="dark"] .app-search .search-widget-icon,
    [data-topbar="dark"] .app-search .search-widget-icon {
        color: #94a3b8 !important;
    }

    /* User profile avatar in dark mode */
    [data-bs-theme="dark"] .header-profile-user,
    [data-topbar="dark"] .header-profile-user {
        border: 2px solid rgba(212, 175, 55, 0.6) !important;
    }

    /* Headings, text-dark & contrast fixes */
    [data-bs-theme="dark"] .text-dark:not(.btn-warning):not(.badge.bg-warning):not(.bg-warning *):not(.alert-warning *):not(.keep-dark) {
        color: var(--vz-heading-color, #f1f5f9) !important;
    }

    [data-bs-theme="dark"] .bg-light:not(.keep-light) {
        background-color: var(--vz-light, #1f2a38) !important;
        color: var(--vz-body-color, #cbd5e1) !important;
    }

    [data-bs-theme="dark"] .bg-white:not(.keep-white):not(.print-page):not(.print-invoice-sheet) {
        background-color: var(--vz-card-bg, #18202b) !important;
        color: var(--vz-body-color, #cbd5e1) !important;
    }

    /* Table styling in Dark Mode */
    [data-bs-theme="dark"] .table-light,
    [data-bs-theme="dark"] .table-light > th,
    [data-bs-theme="dark"] .table-light > td,
    [data-bs-theme="dark"] thead.table-light th {
        background-color: var(--vz-light, #242930) !important;
        color: var(--vz-heading-color, #f1f5f9) !important;
        border-color: var(--vz-border-color, #2f353d) !important;
    }

    [data-bs-theme="dark"] .table {
        --bs-table-color: var(--vz-body-color, #cbd5e1);
        --bs-table-border-color: var(--vz-border-color, #2f353d);
    }

    [data-bs-theme="dark"] .table-hover > tbody > tr:hover > * {
        background-color: rgba(255, 255, 255, 0.03) !important;
        color: var(--vz-heading-color, #f1f5f9);
    }

    /* Badges in Dark Mode */
    [data-bs-theme="dark"] .badge.bg-light.text-dark,
    [data-bs-theme="dark"] .badge.bg-light {
        background-color: rgba(255, 255, 255, 0.09) !important;
        color: #e2e8f0 !important;
        border-color: rgba(255, 255, 255, 0.14) !important;
    }

    /* Card & Modal refinement */
    [data-bs-theme="dark"] .card {
        background-color: var(--vz-card-bg, #1e2227);
        border-color: var(--vz-border-color, #2f353d);
    }

    [data-bs-theme="dark"] .card-header,
    [data-bs-theme="dark"] .card-footer {
        border-color: var(--vz-border-color, #2f353d) !important;
    }

    /* Buttons in Dark Mode */
    [data-bs-theme="dark"] .btn-soft-primary {
        background-color: rgba(37, 99, 235, 0.16) !important;
        color: #60a5fa !important;
        border: 1px solid rgba(37, 99, 235, 0.3) !important;
    }
    [data-bs-theme="dark"] .btn-soft-primary:hover:not(:disabled) {
        background-color: #2563eb !important;
        color: #ffffff !important;
    }
    [data-bs-theme="dark"] .btn-soft-warning {
        background-color: rgba(212, 175, 55, 0.16) !important;
        color: #fbbf24 !important;
        border: 1px solid rgba(212, 175, 55, 0.3) !important;
    }
    [data-bs-theme="dark"] .btn-soft-warning:hover:not(:disabled) {
        background-color: #c59b27 !important;
        color: #ffffff !important;
    }
    [data-bs-theme="dark"] .btn-soft-success {
        background-color: rgba(10, 179, 156, 0.16) !important;
        color: #34d399 !important;
        border: 1px solid rgba(10, 179, 156, 0.3) !important;
    }
    [data-bs-theme="dark"] .btn-soft-success:hover:not(:disabled) {
        background-color: #0ab39c !important;
        color: #ffffff !important;
    }
    [data-bs-theme="dark"] .btn-soft-danger {
        background-color: rgba(240, 101, 72, 0.16) !important;
        color: #f87171 !important;
        border: 1px solid rgba(240, 101, 72, 0.3) !important;
    }
    [data-bs-theme="dark"] .btn-soft-danger:hover:not(:disabled) {
        background-color: #f06548 !important;
        color: #ffffff !important;
    }
    [data-bs-theme="dark"] .btn-soft-info {
        background-color: rgba(41, 156, 219, 0.16) !important;
        color: #38bdf8 !important;
        border: 1px solid rgba(41, 156, 219, 0.3) !important;
    }
    [data-bs-theme="dark"] .btn-soft-info:hover:not(:disabled) {
        background-color: #299cdb !important;
        color: #ffffff !important;
    }
    [data-bs-theme="dark"] .btn-light {
        background-color: #242930 !important;
        border-color: #2f353d !important;
        color: #cbd5e1 !important;
    }

    [data-bs-theme="dark"] .modal-content {
        background-color: var(--vz-card-bg, #1e2227) !important;
        border-color: var(--vz-border-color, #2f353d) !important;
        color: var(--vz-body-color, #cbd5e1) !important;
    }

    [data-bs-theme="dark"] .modal-header,
    [data-bs-theme="dark"] .modal-footer {
        border-color: var(--vz-border-color, #2f353d) !important;
    }

    [data-bs-theme="dark"] .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    /* Form controls & inputs */
    [data-bs-theme="dark"] .form-control,
    [data-bs-theme="dark"] .form-select {
        background-color: #242930 !important;
        border-color: #2f353d !important;
        color: #cbd5e1 !important;
    }

    [data-bs-theme="dark"] .form-control:focus,
    [data-bs-theme="dark"] .form-select:focus {
        background-color: #2a3038 !important;
        border-color: #405189 !important;
        color: #ffffff !important;
        box-shadow: 0 0 0 0.2rem rgba(64, 81, 137, 0.25);
    }

    [data-bs-theme="dark"] .form-control::placeholder {
        color: #6c757d !important;
    }

    [data-bs-theme="dark"] .input-group-text {
        background-color: #242930 !important;
        border-color: #2f353d !important;
        color: #cbd5e1 !important;
    }

    /* Dropdowns */
    [data-bs-theme="dark"] .dropdown-menu {
        background-color: #1e2227 !important;
        border-color: #2f353d !important;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5) !important;
    }

    [data-bs-theme="dark"] .dropdown-item {
        color: #cbd5e1 !important;
    }

    [data-bs-theme="dark"] .dropdown-item:hover,
    [data-bs-theme="dark"] .dropdown-item:focus {
        background-color: rgba(255, 255, 255, 0.06) !important;
        color: #ffffff !important;
    }

    [data-bs-theme="dark"] .dropdown-divider {
        border-top-color: #2f353d !important;
    }

    /* SweetAlert2 in Dark Mode */
    [data-bs-theme="dark"] .swal2-popup {
        background: #1e2227 !important;
        color: #f1f5f9 !important;
        border: 1px solid #2f353d !important;
    }

    [data-bs-theme="dark"] .swal2-title,
    [data-bs-theme="dark"] .swal2-html-container {
        color: #f1f5f9 !important;
    }

    /* Filter Tabs & Custom Components (HR, POS, Warranties) */
    [data-bs-theme="dark"] .filter-tab-pill {
        background: #1e2227 !important;
        color: #cbd5e1 !important;
        border-color: #2f353d !important;
    }

    [data-bs-theme="dark"] .filter-tab-pill:hover {
        background: rgba(255, 255, 255, 0.08) !important;
        color: #ffffff !important;
    }

    [data-bs-theme="dark"] .filter-tab-pill.active {
        background: #2563eb !important;
        color: #ffffff !important;
        border-color: #2563eb !important;
    }

    /* Ensure Print Styles are ALWAYS white with dark ink, even if user is in dark mode */
    @media print {
        [data-bs-theme="dark"] body,
        body {
            background-color: #ffffff !important;
            color: #000000 !important;
        }
        [data-bs-theme="dark"] .table,
        [data-bs-theme="dark"] .table-light,
        [data-bs-theme="dark"] .card {
            background-color: #ffffff !important;
            color: #000000 !important;
            border-color: #000000 !important;
        }
        [data-bs-theme="dark"] .text-dark,
        .text-dark {
            color: #000000 !important;
        }
    }
</style>

@yield('css')

