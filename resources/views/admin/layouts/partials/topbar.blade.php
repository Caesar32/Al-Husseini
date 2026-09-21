<header id="page-topbar">
    <div class="layout-width">
        <div class="navbar-header">
            <div class="d-flex">
                <!-- LOGO -->
                <div class="navbar-brand-box horizontal-logo">
                    <a href="{{ route('admin.dashboard') }}" class="logo logo-dark">
                        <span class="logo-sm">
                            <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="28" class="rounded-circle shadow-sm">
                        </span>
                        <span class="logo-lg">
                            <span class="d-inline-flex align-items-center gap-2">
                                <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="28" class="rounded-circle shadow-sm">
                                <span class="fw-bold fs-17 text-dark">الحسيني <span class="text-primary fs-13">Al-Husseini</span></span>
                            </span>
                        </span>
                    </a>

                    <a href="{{ route('admin.dashboard') }}" class="logo logo-light">
                        <span class="logo-sm">
                            <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="28" class="rounded-circle shadow-sm">
                        </span>
                        <span class="logo-lg">
                            <span class="d-inline-flex align-items-center gap-2">
                                <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="28" class="rounded-circle shadow-sm">
                                <span class="fw-bold fs-17 text-white">الحسيني <span class="text-warning fs-13">Al-Husseini</span></span>
                            </span>
                        </span>
                    </a>
                </div>

                <button type="button" class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger material-shadow-none" id="topnav-hamburger-icon">
                    <span class="hamburger-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>

                <!-- App Search-->
                <form class="app-search d-none d-md-block">
                    <div class="position-relative">
                        <input type="text" class="form-control" placeholder="بحث..." autocomplete="off" id="search-options" value="">
                        <span class="mdi mdi-magnify search-widget-icon"></span>
                        <span class="mdi mdi-close-circle search-widget-icon search-widget-icon-close d-none" id="search-close-options"></span>
                        <div class="dropdown-menu dropdown-menu-lg" id="search-dropdown"></div>
                    </div>
                </form>
            </div>

            <div class="d-flex align-items-center">

                <div class="dropdown d-md-none topbar-head-dropdown header-item">
                    <button type="button" class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle" id="page-header-search-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-search fs-22"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0" aria-labelledby="page-header-search-dropdown">
                        <form class="p-3">
                            <div class="form-group m-0">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Search ..." aria-label="Recipient's username">
                                    <button class="btn btn-primary" type="submit"><i class="mdi mdi-magnify"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Language Dropdown -->
                <div class="dropdown ms-1 topbar-head-dropdown header-item">
                    @php
                        $currentLocale = session('locale', 'ar');
                        if (request()->has('lang') && in_array(request('lang'), ['ar', 'en'])) {
                            $currentLocale = request('lang');
                        }
                    @endphp
                    <button type="button" class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <img id="header-lang-img" src="{{ asset('assets/images/flags/' . ($currentLocale === 'en' ? 'us.svg' : 'ae.svg')) }}" alt="Header Language" height="20" class="rounded">
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a href="{{ route('switch-lang', 'ar') }}" class="dropdown-item notify-item language {{ $currentLocale === 'ar' ? 'active' : '' }}" data-lang="ar" title="العربية">
                            <img src="{{ asset('assets/images/flags/ae.svg') }}" alt="user-image" class="me-2 rounded" height="18">
                            <span class="align-middle">العربية</span>
                        </a>
                        <a href="{{ route('switch-lang', 'en') }}" class="dropdown-item notify-item language py-2 {{ $currentLocale === 'en' ? 'active' : '' }}" data-lang="en" title="English">
                            <img src="{{ asset('assets/images/flags/us.svg') }}" alt="user-image" class="me-2 rounded" height="18">
                            <span class="align-middle">English</span>
                        </a>
                    </div>
                </div>

                <!-- Fullscreen -->
                <div class="ms-1 header-item d-none d-sm-flex">
                    <button type="button" class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle" data-toggle="fullscreen">
                        <i class='bx bx-fullscreen fs-22'></i>
                    </button>
                </div>

                <!-- Light / Dark Mode -->
                <div class="ms-1 header-item d-none d-sm-flex">
                    <button type="button" class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle light-dark-mode">
                        <i class='bx bx-moon fs-22'></i>
                    </button>
                </div>

                <!-- Notifications Dropdown -->
                <div class="dropdown topbar-head-dropdown ms-1 header-item" id="notificationDropdown">
                    <button type="button" class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle" id="page-header-notifications-dropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-haspopup="true" aria-expanded="false">
                        <i class='bx bx-bell fs-22'></i>
                        <span class="position-absolute topbar-badge fs-10 translate-middle badge rounded-pill bg-danger" id="topbar-notification-badge">0<span class="visually-hidden">unread messages</span></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0" aria-labelledby="page-header-notifications-dropdown">

                        <div class="dropdown-head bg-primary bg-pattern rounded-top">
                            <div class="p-3">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="m-0 fs-16 fw-semibold text-white"> التنبيهات الإدارية </h6>
                                    </div>
                                    <div class="col-auto dropdown-tabs">
                                        <span class="badge bg-light text-body fs-13" id="topbar-notification-count">0 جديد</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-content position-relative" id="notificationItemsTabContent">
                            <div class="tab-pane fade show active py-2 ps-2" id="all-noti-tab" role="tabpanel">
                                <div data-simplebar style="max-height: 300px;" class="pe-2" id="topbar-notification-list">
                                    <!-- Populated dynamically by HR Store -->
                                </div>
                                <div class="text-center py-2 border-top">
                                    <button type="button" class="btn btn-sm btn-link text-primary" onclick="if(window.AlHusseiniHR) window.AlHusseiniHR.markAllAsRead();">
                                        <i class="ri-check-double-line align-middle me-1"></i> تحديد الكل كمقروء
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Profile Dropdown -->
                <div class="dropdown ms-sm-3 header-item topbar-user">
                    <button type="button" class="btn material-shadow-none" id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <img class="rounded-circle header-profile-user" src="{{ asset('assets/images/users/avatar-1.jpg') }}" alt="Header Avatar">
                            <span class="text-start ms-xl-2">
                                <span class="d-none d-xl-inline-block ms-1 fw-bold user-name-text">المشرف العام</span>
                                <span class="d-none d-xl-block ms-1 fs-12 user-name-sub-text">إدارة نظام الحسيني (Al-Husseini)</span>
                            </span>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">مرحباً بك!</h6>
                        <a class="dropdown-item" href="javascript:void(0);"><i class="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i> <span class="align-middle">الملف الشخصي</span></a>
                        <a class="dropdown-item" href="javascript:void(0);"><i class="mdi mdi-cog-outline text-muted fs-16 align-middle me-1"></i> <span class="align-middle">الإعدادات</span></a>
                        <a class="dropdown-item" href="javascript:void(0);"><i class="mdi mdi-lock text-muted fs-16 align-middle me-1"></i> <span class="align-middle">قفل الشاشة</span></a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="{{ route('admin.login') }}"><i class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i> <span class="align-middle">تسجيل الخروج</span></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
