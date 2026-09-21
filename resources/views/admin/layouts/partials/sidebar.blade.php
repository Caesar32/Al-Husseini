<!-- ========== App Menu ========== -->
<div class="app-menu navbar-menu">
    <!-- LOGO -->
    <div class="navbar-brand-box">
        <!-- Dark Logo-->
        <a href="{{ route('admin.dashboard') }}" class="logo logo-dark">
            <span class="logo-sm">
                <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="28" class="rounded-circle shadow-sm">
            </span>
            <span class="logo-lg">
                <span class="d-inline-flex align-items-center gap-2">
                    <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="30" class="rounded-circle shadow-sm">
                    <span class="fw-bold fs-15 text-dark" style="letter-spacing: 0.3px;">الحسيني <span class="text-primary fs-12 fw-semibold">Al-Husseini</span></span>
                </span>
            </span>
        </a>
        <!-- Light Logo-->
        <a href="{{ route('admin.dashboard') }}" class="logo logo-light">
            <span class="logo-sm">
                <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="28" class="rounded-circle shadow-sm">
            </span>
            <span class="logo-lg">
                <span class="d-inline-flex align-items-center gap-2">
                    <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="30" class="rounded-circle shadow-sm">
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

                <li class="menu-title"><span>الرئيسية</span></li>

                <!-- Dashboard -->
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                        <i class="ri-dashboard-2-line"></i> <span>لوحة التحكم الرئيسية</span>
                    </a>
                </li>

                <li class="menu-title"><span>المبيعات والفواتير</span></li>

                <!-- Point of Sale (POS) -->
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.sales.pos') ? 'active' : '' }}" href="{{ route('admin.sales.pos') }}">
                        <i class="ri-shopping-cart-2-line"></i> <span>نقطة البيع وفاتورة سريعة</span>
                    </a>
                </li>

                <!-- Invoices Register -->
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.sales.invoices') ? 'active' : '' }}" href="{{ route('admin.sales.invoices') }}">
                        <i class="ri-bill-line"></i> <span>فواتير المبيعات والضمان</span>
                    </a>
                </li>

                <!-- Credit & Dues (الآجل) -->
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.sales.credit') ? 'active' : '' }}" href="{{ route('admin.sales.credit') }}">
                        <i class="ri-hand-coin-line"></i> <span>حسابات الآجل والمستحقات</span>
                        <span class="badge bg-warning-subtle text-warning fs-10 ms-auto fw-bold px-2 py-1">الآجل</span>
                    </a>
                </li>

                <!-- Battery Inventory / Products -->
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.sales.products') ? 'active' : '' }}" href="{{ route('admin.sales.products') }}">
                        <i class="ri-battery-2-charge-line"></i> <span>بطاريات ومنتجات المركز</span>
                    </a>
                </li>

                <!-- Customers Directory -->
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.sales.customers') ? 'active' : '' }}" href="{{ route('admin.sales.customers') }}">
                        <i class="ri-user-shared-line"></i> <span>دليل وسجل العملاء والسيارات</span>
                    </a>
                </li>

                <li class="menu-title"><span>شؤون العاملين والورشة</span></li>

                <!-- Employees -->
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.hr.employees') ? 'active' : '' }}" href="{{ route('admin.hr.employees') }}">
                        <i class="ri-user-star-line"></i> <span>دليل البائعين والفنيين</span>
                    </a>
                </li>

                <!-- Attendance & Biometrics -->
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.hr.attendance') ? 'active' : '' }}" href="{{ route('admin.hr.attendance') }}">
                        <i class="ri-fingerprint-2-line"></i> <span>بصمة الحضور ومواعيد الورشة</span>
                    </a>
                </li>

                <!-- Payroll & Deductions -->
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.hr.payroll') ? 'active' : '' }}" href="{{ route('admin.hr.payroll') }}">
                        <i class="ri-money-dollar-circle-line"></i> <span>مسير الرواتب والخصومات</span>
                    </a>
                </li>

                <!-- Reports & Analytics -->
                <li class="nav-item">
                    <a class="nav-link menu-link {{ request()->routeIs('admin.hr.reports') ? 'active' : '' }}" href="{{ route('admin.hr.reports') }}">
                        <i class="ri-file-chart-line"></i> <span>تقارير الحضور والغياب والخصومات</span>
                    </a>
                </li>

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
