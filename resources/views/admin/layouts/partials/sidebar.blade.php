<!-- ========== App Menu ========== -->
<div class="app-menu navbar-menu">
    <!-- LOGO -->
    <div class="navbar-brand-box">
        <!-- Dark Logo-->
        <a href="{{ route('admin.dashboard') }}" class="logo logo-dark">
            <span class="logo-sm">
                <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="28" class="rounded-3 shadow-sm" style="border: 1px solid rgba(212, 175, 55, 0.35);">
            </span>
            <span class="logo-lg">
                <span class="d-inline-flex align-items-center gap-2">
                    <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="32" class="rounded-3 shadow-sm" style="border: 1px solid rgba(212, 175, 55, 0.35);">
                    <span class="fw-bold fs-15 text-dark" style="letter-spacing: 0.3px;">الحسيني <span class="text-primary fs-12 fw-semibold">Al-Husseini</span></span>
                </span>
            </span>
        </a>
        <!-- Light Logo-->
        <a href="{{ route('admin.dashboard') }}" class="logo logo-light">
            <span class="logo-sm">
                <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="28" class="rounded-3 shadow-sm" style="border: 1px solid rgba(212, 175, 55, 0.35);">
            </span>
            <span class="logo-lg">
                <span class="d-inline-flex align-items-center gap-2">
                    <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="32" class="rounded-3 shadow-sm" style="border: 1px solid rgba(212, 175, 55, 0.35);">
                    <span class="fw-bold fs-15 text-white" style="letter-spacing: 0.3px;">الحسيني <span class="text-warning fs-12 fw-semibold">Al-Husseini</span></span>
                </span>
            </span>
        </a>
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover" id="vertical-hover">
            <i class="ri-record-circle-line"></i>
        </button>
    </div>

    <!-- Scrollable Sidebar Area -->
    <div id="scrollbar">
        <div class="container-fluid">

            <div id="two-column-menu"></div>
            <ul class="navbar-nav" id="navbar-nav">

                <!-- 1. الرئيسية والمؤشرات -->
                <li class="menu-title"><span>الرئيسية والمؤشرات</span></li>

                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                        <i class="ri-dashboard-2-line"></i> <span>لوحة التحكم والمؤشرات</span>
                    </a>
                </li>

                <!-- 2. المبيعات ونقاط البيع -->
                <li class="menu-title"><span>المبيعات ونقاط البيع</span></li>

                @can('pos.access')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ (request()->routeIs('admin.pos.*') || request()->routeIs('admin.sales.pos')) ? 'active' : '' }}" href="{{ route('admin.pos.index') }}">
                        <i class="ri-shopping-cart-2-line"></i> <span>كاشير نقطة البيع (POS)</span>
                        <span class="badge bg-success-subtle text-success fs-10 ms-auto fw-bold px-2 py-1">بيع فوري</span>
                    </a>
                </li>
                @endcan

                @can('invoices.view')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ (request()->routeIs('admin.invoices.*') || request()->routeIs('admin.sales.invoices')) ? 'active' : '' }}" href="{{ route('admin.invoices.index') }}">
                        <i class="ri-file-list-3-line"></i> <span>فواتير المبيعات الصادرة</span>
                    </a>
                </li>
                @endcan

                @can('credit.view')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ (request()->routeIs('admin.credit.*') || request()->routeIs('admin.sales.credit*')) ? 'active' : '' }}" href="{{ route('admin.credit.index') }}">
                        <i class="ri-hand-coin-line"></i> <span>حسابات ومديونيات الآجل</span>
                        <span class="badge bg-warning-subtle text-warning fs-10 ms-auto fw-bold px-2 py-1">تحصيل</span>
                    </a>
                </li>
                @endcan

                @can('customers.view')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.sales.customers') ? 'active' : '' }}" href="{{ route('admin.sales.customers') }}">
                        <i class="ri-user-shared-line"></i> <span>دليل العملاء والمركبات</span>
                    </a>
                </li>
                @endcan

                <!-- 3. المشتريات والتوريدات -->
                <li class="menu-title"><span>المشتريات والتوريدات</span></li>

                @can('purchases.view')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.purchases.*') ? 'active' : '' }}" href="{{ route('admin.purchases.index') }}">
                        <i class="ri-truck-line"></i> <span>فواتير التوريد والشراء</span>
                    </a>
                </li>
                @endcan

                @can('suppliers.view')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.suppliers.*') ? 'active' : '' }}" href="{{ route('admin.suppliers.index') }}">
                        <i class="ri-store-2-line"></i> <span>دليل الموردين والكتالوج</span>
                    </a>
                </li>
                @endcan

                <!-- 4. المخزون وتجارة الرصاص -->
                <li class="menu-title"><span>المخزون وتجارة الرصاص</span></li>

                @can('products.view')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.sales.products') ? 'active' : '' }}" href="{{ route('admin.sales.products') }}">
                        <i class="ri-battery-2-charge-line"></i> <span>دليل المنتجات والبطاريات</span>
                    </a>
                </li>
                @endcan

                @can('scrap.view')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.scrap.*') ? 'active' : '' }}" href="{{ route('admin.scrap.index') }}">
                        <i class="ri-recycle-line"></i> <span>مخزن الكهنة وتجارة الرصاص</span>
                        <span class="badge bg-danger-subtle text-danger fs-10 ms-auto fw-bold px-2 py-1">تخريد</span>
                    </a>
                </li>
                @endcan

                <!-- 5. الضمان وما بعد البيع -->
                <li class="menu-title"><span>الضمان وما بعد البيع</span></li>

                @can('warranties.view')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ (request()->routeIs('admin.warranties.index') || request()->routeIs('admin.warranties.claims*')) ? 'active' : '' }}" href="{{ route('admin.warranties.index') }}">
                        <i class="ri-shield-check-line"></i> <span>سجل الضمانات المعتمدة</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.warranties.verify') ? 'active' : '' }}" href="{{ route('admin.warranties.verify') }}">
                        <i class="ri-qr-scan-line"></i> <span>فحص الضمان والاستبدال السريع</span>
                        <span class="badge bg-info-subtle text-info fs-10 ms-auto fw-bold px-2 py-1">فحص</span>
                    </a>
                </li>
                @endcan

                <!-- 6. الموارد البشرية والورشة -->
                <li class="menu-title"><span>الموارد البشرية والورشة</span></li>

                @can('employees.view')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.hr.employees*') ? 'active' : '' }}" href="{{ route('admin.hr.employees') }}">
                        <i class="ri-user-star-line"></i> <span>دليل الفنيين والموظفين</span>
                    </a>
                </li>
                @endcan

                @can('attendance.view')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.hr.attendance*') ? 'active' : '' }}" href="{{ route('admin.hr.attendance') }}">
                        <i class="ri-fingerprint-2-line"></i> <span>حضور وانصراف الورشة</span>
                    </a>
                </li>
                @endcan

                @can('payroll.generate')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.hr.payroll*') ? 'active' : '' }}" href="{{ route('admin.hr.payroll') }}">
                        <i class="ri-money-dollar-circle-line"></i> <span>مسيرات الرواتب والعمولات</span>
                    </a>
                </li>
                @endcan

                @can('reports.hr')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.hr.reports*') ? 'active' : '' }}" href="{{ route('admin.hr.reports') }}">
                        <i class="ri-file-chart-line"></i> <span>تقارير الأداء والغياب</span>
                    </a>
                </li>
                @endcan

                <!-- 7. إدارة النظام والمنشأة -->
                <li class="menu-title"><span>إدارة النظام والمنشأة</span></li>

                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.profile*') ? 'active' : '' }}" href="{{ route('admin.profile') }}">
                        <i class="ri-user-settings-line"></i> <span>الملف الشخصي والحساب</span>
                    </a>
                </li>

                @can('roles.manage')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}" href="{{ route('admin.roles.index') }}">
                        <i class="ri-shield-keyhole-line"></i> <span>الأدوار والصلاحيات</span>
                    </a>
                </li>
                @endcan

                @can('users.manage')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                        <i class="ri-team-line"></i> <span>المستخدمين والحسابات</span>
                    </a>
                </li>
                @endcan

                @can('settings.manage')
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.settings*') ? 'active' : '' }}" href="{{ route('admin.settings') }}">
                        <i class="ri-settings-4-line"></i> <span>إعدادات النظام والمنشأة</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.diagnostics*') ? 'active' : '' }}" href="{{ route('admin.diagnostics.index') }}">
                        <i class="ri-pulse-line text-success"></i> <span>فحص وتشخيص النظام الحي</span>
                        <span class="badge bg-success-subtle text-success fs-10 ms-auto fw-bold px-2 py-1">Diagnostic</span>
                    </a>
                </li>
                @endcan

            </ul>
        </div>
        <!-- Container -->
    </div>
    <!-- Scrollbar -->

    <div class="sidebar-background"></div>
</div>
<!-- Left Sidebar End -->
<!-- Vertical Overlay-->
<div class="vertical-overlay"></div>
