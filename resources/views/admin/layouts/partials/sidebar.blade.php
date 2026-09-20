<!-- ========== App Menu ========== -->
<div class="app-menu navbar-menu">
    <!-- LOGO -->
    <div class="navbar-brand-box">
        <!-- Dark Logo-->
        <a href="{{ route('admin.dashboard') }}" class="logo logo-dark">
            <span class="logo-sm">
                <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="30" class="rounded-circle shadow-sm">
            </span>
            <span class="logo-lg">
                <span class="d-inline-flex align-items-center gap-2">
                    <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="32" class="rounded-circle shadow-sm">
                    <span class="fw-bold fs-17 text-dark" style="letter-spacing: 0.5px;">الحسيني <span class="text-primary fs-13 fw-semibold">Al-Husseini</span></span>
                </span>
            </span>
        </a>
        <!-- Light Logo-->
        <a href="{{ route('admin.dashboard') }}" class="logo logo-light">
            <span class="logo-sm">
                <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="30" class="rounded-circle shadow-sm">
            </span>
            <span class="logo-lg">
                <span class="d-inline-flex align-items-center gap-2">
                    <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="32" class="rounded-circle shadow-sm">
                    <span class="fw-bold fs-17 text-white" style="letter-spacing: 0.5px;">الحسيني <span class="text-warning fs-13 fw-semibold">Al-Husseini</span></span>
                </span>
            </span>
        </a>
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover" id="vertical-hover">
            <i class="ri-record-circle-line"></i>
        </button>
    </div>

    <div id="scrollbar">
        <div class="container-fluid">

            <div id="two-column-menu"></div>
            <ul class="navbar-nav" id="navbar-nav">
                <li class="menu-title"><span>القائمة الرئيسية</span></li>

                <!-- Dashboards -->
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="#sidebarDashboards" data-bs-toggle="collapse" role="button" aria-expanded="{{ request()->routeIs('admin.dashboard') ? 'true' : 'false' }}" aria-controls="sidebarDashboards">
                        <i class="ri-dashboard-2-line"></i> <span>لوحات التحكم</span>
                    </a>
                    <div class="collapse menu-dropdown {{ request()->routeIs('admin.dashboard') ? 'show' : '' }}" id="sidebarDashboards">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item">
                                <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                                    الرئيسية والتجارة الإلكترونية
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.dashboard') }}" class="nav-link">
                                    تحليلات النظام
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.dashboard') }}" class="nav-link">
                                    المشاريع والمهام
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <!-- Starter Page -->
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.starter') ? 'active' : '' }}" href="{{ route('admin.starter') }}">
                        <i class="ri-pages-line"></i> <span>صفحة بداية (Starter)</span>
                        <span class="badge bg-success-subtle text-success fs-11">جديد</span>
                    </a>
                </li>

                <li class="menu-title"><span>إدارة المتجر والمبيعات</span></li>

                <!-- Ecommerce Menu -->
                <li class="nav-item">
                    <a class="nav-link menu-link" href="#sidebarEcommerce" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarEcommerce">
                        <i class="ri-shopping-bag-3-line"></i> <span>المتجر والمنتجات</span>
                    </a>
                    <div class="collapse menu-dropdown" id="sidebarEcommerce">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item">
                                <a href="{{ route('admin.dashboard') }}" class="nav-link">قائمة المنتجات</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.starter') }}" class="nav-link">إضافة منتج جديد</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.dashboard') }}" class="nav-link">الطلبات والمبيعات</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.dashboard') }}" class="nav-link">العملاء</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.dashboard') }}" class="nav-link">الفواتير والدفعات</a>
                            </li>
                        </ul>
                    </div>
                </li>

                <li class="menu-title"><span>إدارة النظام والحسابات</span></li>

                <!-- Authentication -->
                <li class="nav-item">
                    <a class="nav-link menu-link" href="#sidebarAuth" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarAuth">
                        <i class="ri-shield-user-line"></i> <span>المصادقة والحماية</span>
                    </a>
                    <div class="collapse menu-dropdown" id="sidebarAuth">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item">
                                <a href="{{ route('admin.login') }}" class="nav-link" target="_blank">تسجيل الدخول</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.register') }}" class="nav-link" target="_blank">إنشاء حساب جديد</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.error.404') }}" class="nav-link" target="_blank">صفحة خطأ 404</a>
                            </li>
                        </ul>
                    </div>
                </li>

                <!-- Settings & Tools -->
                <li class="nav-item">
                    <a class="nav-link menu-link" href="#sidebarSettings" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarSettings">
                        <i class="ri-settings-4-line"></i> <span>الإعدادات والخيارات</span>
                    </a>
                    <div class="collapse menu-dropdown" id="sidebarSettings">
                        <ul class="nav nav-sm flex-column">
                            <li class="nav-item">
                                <a href="javascript:void(0);" class="nav-link" data-bs-toggle="offcanvas" data-bs-target="#theme-settings-offcanvas">تخصيص الواجهة</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.starter') }}" class="nav-link">الملف الشخصي</a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.starter') }}" class="nav-link">إعدادات النظام العامة</a>
                            </li>
                        </ul>
                    </div>
                </li>

            </ul>
        </div>
        <!-- Sidebar -->
    </div>

    <div class="sidebar-background"></div>
</div>
<!-- Left Sidebar End -->
<!-- Vertical Overlay-->
<div class="vertical-overlay"></div>
