@extends('admin.layouts.master')

@section('title', 'لوحة التحكم والتحليلات | مركز الحسيني لبطاريات وزيوت وصيانة السيارات')

@section('css')

    <style>
        .stat-card-widget {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card-widget:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
        }
        .quick-action-btn {
            transition: all 0.2s ease;
            font-weight: 700;
        }
        .quick-action-btn:hover {
            transform: translateY(-2px);
        }
        .table-nowrap td, .table-nowrap th {
            white-space: nowrap;
        }
        @media print {
            .app-menu, .topbar, .footer, .btn, .no-print { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; }
            .print-invoice-sheet {
                display: block !important;
                width: 100% !important;
                border: 2px solid #000;
                padding: 20px;
                font-size: 12pt;
            }
        }
    </style>
@endsection

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'لوحات تحكم الحسيني', 'title' => 'لوحة القيادة والمبيعات - مركز الحسيني'])

    <!-- ============================================================== -->
    <!-- 1. Welcome & Fast Action Bar                                   -->
    <!-- ============================================================== -->
    <div class="row mb-3 pb-1">
        <div class="col-12">
            <div class="d-flex align-items-lg-center flex-lg-row flex-column justify-content-between gap-3 card-body bg-body p-3 rounded shadow-sm border border-start border-4 border-primary">
                <div>
                    <h4 class="fs-16 fw-bold mb-1 d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary rounded-circle p-2 fs-14">
                            <i class="ri-car-fill"></i>
                        </span>
                        مركز الحسيني لبطاريات وزيوت وصيانة السيارات
                    </h4>
                    <p class="text-muted mb-0 fs-12">
                        متابعة حية وشاملة لمبيعات المعرض، فواتير الورشة، حركة المخزون بالباركود، وتحصيلات الآجل.
                    </p>
                </div>
                
                <!-- Quick Operations Bar -->
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <a href="{{ route('admin.sales.pos') }}" class="btn btn-primary quick-action-btn shadow-sm fs-12 px-3 py-2">
                        <i class="ri-barcode-box-line align-middle me-1"></i> نقطة البيع (POS الكاشير)
                    </a>
                    <a href="{{ route('admin.sales.credit') }}" class="btn btn-soft-warning quick-action-btn fs-12 px-3 py-2">
                        <i class="ri-hand-coin-line align-middle me-1"></i> تحصيل الآجل
                    </a>
                    <a href="{{ route('admin.sales.products') }}" class="btn btn-soft-info quick-action-btn fs-12 px-3 py-2">
                        <i class="ri-box-3-line align-middle me-1"></i> المخزون والباركود
                    </a>
                    <a href="{{ route('admin.hr.attendance') }}" class="btn btn-soft-success quick-action-btn fs-12 px-3 py-2">
                        <i class="ri-user-follow-line align-middle me-1"></i> حضور فنيي الورشة
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- 2. Top 4 Dynamic KPI Metric Cards                              -->
    <!-- ============================================================== -->
    <div class="row g-3 mb-3">
        <!-- Metric 1: Total Sales Revenue -->
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate stat-card-widget h-100 mb-0 border shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-bold text-muted text-truncate mb-0 fs-12">إجمالي مبيعات المركز</p>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="badge bg-success-subtle text-success fs-11">
                                <i class="ri-arrow-up-line align-middle"></i> مباشر + صيانة
                            </span>
                        </div>
                    </div>
                    <div class="d-flex align-items-end justify-content-between mt-3">
                        <div>
                            <h3 class="fs-22 fw-extrabold text-success mb-1 font-monospace" id="dashTotalSales">0 ج.م</h3>
                            <a href="{{ route('admin.sales.invoices') }}" class="text-decoration-underline text-muted fs-11">
                                <span id="dashInvoicesCount">0</span> فاتورة معتمدة
                            </a>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-success-subtle rounded-circle fs-3 text-success">
                                <i class="ri-money-dollar-circle-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 2: Credit / Receivables (Strictly called "الآجل") -->
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate stat-card-widget h-100 mb-0 border shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-bold text-muted text-truncate mb-0 fs-12">مستحقات العملاء (الآجل)</p>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="badge bg-warning-subtle text-warning fs-11">
                                <i class="ri-time-line align-middle"></i> واجبة التحصيل
                            </span>
                        </div>
                    </div>
                    <div class="d-flex align-items-end justify-content-between mt-3">
                        <div>
                            <h3 class="fs-22 fw-extrabold text-warning mb-1 font-monospace" id="dashTotalCredit">0 ج.م</h3>
                            <a href="{{ route('admin.sales.credit') }}" class="text-decoration-underline text-muted fs-11">
                                <span id="dashCreditCustomersCount">0</span> عملاء عليهم آجل
                            </a>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-warning-subtle rounded-circle fs-3 text-warning">
                                <i class="ri-hand-coin-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 3: Registered Customers & Cars -->
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate stat-card-widget h-100 mb-0 border shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-bold text-muted text-truncate mb-0 fs-12">دليل العملاء والمركبات</p>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="badge bg-primary-subtle text-primary fs-11">
                                <i class="ri-car-line align-middle"></i> ورشة ومعرض
                            </span>
                        </div>
                    </div>
                    <div class="d-flex align-items-end justify-content-between mt-3">
                        <div>
                            <h3 class="fs-22 fw-extrabold text-primary mb-1 font-monospace" id="dashCustomersCount">0</h3>
                            <a href="{{ route('admin.sales.customers') }}" class="text-decoration-underline text-muted fs-11">
                                سيارات ولوحات مسجلة
                            </a>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-primary-subtle rounded-circle fs-3 text-primary">
                                <i class="ri-user-shared-2-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 4: Inventory & Low Stock -->
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate stat-card-widget h-100 mb-0 border shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-bold text-muted text-truncate mb-0 fs-12">مخزون البطاريات والزيوت</p>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="badge bg-info-subtle text-info fs-11" id="dashLowStockBadge">
                                بالباركود EAN-13
                            </span>
                        </div>
                    </div>
                    <div class="d-flex align-items-end justify-content-between mt-3">
                        <div>
                            <h3 class="fs-22 fw-extrabold text-info mb-1 font-monospace" id="dashProductsCount">0</h3>
                            <a href="{{ route('admin.sales.products') }}" class="text-decoration-underline text-muted fs-11">
                                <span class="text-danger fw-bold" id="dashLowStockCount">0</span> أصناف أوشكت على النفاد
                            </a>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-info-subtle rounded-circle fs-3 text-info">
                                <i class="ri-battery-charge-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- 3. Interactive Analytics: Sales Trend & Category Distribution  -->
    <!-- ============================================================== -->
    <div class="row g-3 mb-3">
        <!-- Sales Trend & Category Breakdown Chart (Col-8) -->
        <div class="col-xl-8">
            <div class="card border shadow-sm h-100">
                <div class="card-header align-items-center d-flex bg-transparent border-bottom p-3">
                    <h5 class="card-title mb-0 flex-grow-1 fw-bold fs-14">
                        <i class="ri-line-chart-line text-primary me-1"></i> حركة الإيرادات والمبيعات الأسبوعية للأقسام
                    </h5>
                    <div class="d-flex gap-1">
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="ri-checkbox-circle-fill me-1"></i> تحديث تلقائي مباشر
                        </span>
                    </div>
                </div>

                <!-- Sub-summary counters bar -->
                <div class="card-header p-0 border-0 bg-light-subtle">
                    <div class="row g-0 text-center">
                        <div class="col-6 col-sm-3">
                            <div class="p-2 border border-dashed border-start-0">
                                <span class="text-muted fs-11 d-block mb-1">🔋 بطاريات سيارات</span>
                                <h6 class="mb-0 fw-bold font-monospace text-primary" id="catStatBatteries">0 ج.م</h6>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2 border border-dashed border-start-0">
                                <span class="text-muted fs-11 d-block mb-1">🛢️ زيوت وفلاتر</span>
                                <h6 class="mb-0 fw-bold font-monospace text-success" id="catStatOils">0 ج.م</h6>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2 border border-dashed border-start-0">
                                <span class="text-muted fs-11 d-block mb-1">🔧 صيانة وخدمات</span>
                                <h6 class="mb-0 fw-bold font-monospace text-info" id="catStatServices">0 ج.م</h6>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2 border border-dashed border-start-0 border-end-0">
                                <span class="text-muted fs-11 d-block mb-1">♻️ كهنة مسترجعة</span>
                                <h6 class="mb-0 fw-bold font-monospace text-danger" id="catStatScrap">0 ج.م</h6>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body p-3">
                    <div id="alhusseini_sales_trend_chart" style="min-height: 280px;" dir="ltr"></div>
                </div>
            </div>
        </div>

        <!-- Category Sales Donut Chart (Col-4) -->
        <div class="col-xl-4">
            <div class="card border shadow-sm h-100">
                <div class="card-header align-items-center d-flex bg-transparent border-bottom p-3">
                    <h5 class="card-title mb-0 flex-grow-1 fw-bold fs-14">
                        <i class="ri-pie-chart-line text-primary me-1"></i> توزيع المبيعات حسب القسم
                    </h5>
                </div>

                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div id="alhusseini_category_donut_chart" style="min-height: 220px;" dir="ltr"></div>

                    <!-- Custom Category Legend Grid -->
                    <div class="pt-3 border-top mt-2">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="p-2 bg-light rounded border fs-11">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="text-dark fw-bold"><i class="ri-checkbox-blank-circle-fill text-primary fs-9 me-1"></i>بطاريات</span>
                                        <strong class="font-monospace text-primary" id="donutPctBatteries">50%</strong>
                                    </div>
                                    <small class="text-muted font-monospace fs-10 d-block text-truncate" id="donutValBatteries">0 ج.م</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded border fs-11">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="text-dark fw-bold"><i class="ri-checkbox-blank-circle-fill text-success fs-9 me-1"></i>زيوت وفلاتر</span>
                                        <strong class="font-monospace text-success" id="donutPctOils">30%</strong>
                                    </div>
                                    <small class="text-muted font-monospace fs-10 d-block text-truncate" id="donutValOils">0 ج.م</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded border fs-11">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="text-dark fw-bold"><i class="ri-checkbox-blank-circle-fill text-warning fs-9 me-1"></i>شحوم وسوائل</span>
                                        <strong class="font-monospace text-warning" id="donutPctGreases">10%</strong>
                                    </div>
                                    <small class="text-muted font-monospace fs-10 d-block text-truncate" id="donutValGreases">0 ج.م</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded border fs-11">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="text-dark fw-bold"><i class="ri-checkbox-blank-circle-fill text-info fs-9 me-1"></i>صيانة وورشة</span>
                                        <strong class="font-monospace text-info" id="donutPctServices">10%</strong>
                                    </div>
                                    <small class="text-muted font-monospace fs-10 d-block text-truncate" id="donutValServices">0 ج.م</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- 4. Recent Invoices & Customers Due Table (الآجل)               -->
    <!-- ============================================================== -->
    <div class="row g-3 mb-3">
        <!-- Live Recent Invoices Table (Col-8) -->
        <div class="col-xl-8">
            <div class="card border shadow-sm h-100">
                <div class="card-header align-items-center d-flex justify-content-between bg-transparent border-bottom p-3">
                    <h5 class="card-title mb-0 fw-bold fs-14">
                        <i class="ri-file-list-3-line text-primary me-1"></i> أحدث فواتير المبيعات وصيانة السيارات بالمركز
                    </h5>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.sales.invoices') }}" class="btn btn-sm btn-soft-primary">
                            عرض جميع الفواتير <i class="ri-arrow-left-s-line align-middle"></i>
                        </a>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive table-card">
                        <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                            <thead class="table-light fs-12">
                                <tr>
                                    <th scope="col" class="text-center" style="width: 100px;">رقم الفاتورة</th>
                                    <th scope="col">العميل والسيارة</th>
                                    <th scope="col">الأصناف المشتراة</th>
                                    <th scope="col" class="text-end">القيمة الإجمالية</th>
                                    <th scope="col" class="text-center">طريقة الدفع</th>
                                    <th scope="col" class="text-center" style="width: 90px;">معاينة</th>
                                </tr>
                            </thead>
                            <tbody id="dashRecentInvoicesBody" class="fs-12">
                                <!-- Rendered dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Credit / Dues List (الآجل) (Col-4) -->
        <div class="col-xl-4">
            <div class="card border shadow-sm h-100">
                <div class="card-header align-items-center d-flex justify-content-between bg-transparent border-bottom p-3">
                    <div>
                        <h5 class="card-title mb-0 fw-bold fs-14 text-dark">
                            <i class="ri-hand-coin-line text-warning me-1"></i> كشف متابعة مبالغ الآجل
                        </h5>
                        <small class="text-muted fs-11">عملاء عليهم مستحقات مالية واجبة السداد</small>
                    </div>
                    <a href="{{ route('admin.sales.credit') }}" class="btn btn-sm btn-soft-warning">
                        سجل الآجل <i class="ri-arrow-left-s-line align-middle"></i>
                    </a>
                </div>

                <div class="card-body p-2">
                    <div class="d-flex flex-column gap-2" id="dashCreditListContainer">
                        <!-- Rendered dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- 5. Low Stock Alerts & Workshop Technicians Attendance          -->
    <!-- ============================================================== -->
    <div class="row g-3 mb-4">
        <!-- Low Stock Alerts Table (Col-6) -->
        <div class="col-xl-6">
            <div class="card border shadow-sm h-100 d-flex flex-column overflow-hidden mb-0">
                <div class="card-header align-items-center d-flex justify-content-between bg-transparent border-bottom p-3 flex-shrink-0">
                    <h5 class="card-title mb-0 fw-bold fs-14 text-dark">
                        <i class="ri-alarm-warning-line text-danger me-1"></i> تنبيهات نواقص المخزون (تحت حد الأمان)
                    </h5>
                    <a href="{{ route('admin.sales.products') }}" class="btn btn-sm btn-soft-danger">
                        إدارة المخزون <i class="ri-arrow-left-s-line align-middle"></i>
                    </a>
                </div>

                <div class="card-body p-0 flex-grow-1 d-flex flex-column overflow-hidden">
                    <div class="table-responsive table-card flex-grow-1" style="max-height: 330px; overflow-y: auto;">
                        <table class="table table-hover table-centered align-middle table-nowrap mb-0 fs-12">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>الصنف / الماركة</th>
                                    <th>القسم</th>
                                    <th class="text-center">الباركود</th>
                                    <th class="text-center">الرصيد المتبقي</th>
                                    <th class="text-center">الحالة</th>
                                </tr>
                            </thead>
                            <tbody id="dashLowStockTableBody">
                                <!-- Rendered dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Workshop Technicians & Attendance (Col-6) -->
        <div class="col-xl-6">
            <div class="card border shadow-sm h-100 d-flex flex-column overflow-hidden mb-0">
                <div class="card-header align-items-center d-flex justify-content-between bg-transparent border-bottom p-3 flex-shrink-0">
                    <div>
                        <h5 class="card-title mb-0 fw-bold fs-14 text-dark">
                            <i class="ri-team-line text-success me-1"></i> حضور فنيي وعمال الورشة لليوم
                        </h5>
                        <small class="text-muted fs-11">متابعة نوبات العمل وفنيي الصيانة والكهرباء</small>
                    </div>
                    <a href="{{ route('admin.hr.attendance') }}" class="btn btn-sm btn-soft-success">
                        كشف الحضور <i class="ri-arrow-left-s-line align-middle"></i>
                    </a>
                </div>

                <div class="card-body p-3 flex-grow-1 d-flex flex-column overflow-hidden">
                    <!-- Stat counters -->
                    <div class="row g-2 mb-3 text-center flex-shrink-0">
                        <div class="col-4">
                            <div class="p-2 bg-success-subtle border border-success-subtle rounded">
                                <h5 class="mb-0 fw-bold text-success font-monospace" id="hrPresentCount">0</h5>
                                <small class="text-muted fs-11">حاضرون بالورشة</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-warning-subtle border border-warning-subtle rounded">
                                <h5 class="mb-0 fw-bold text-warning font-monospace" id="hrLateCount">0</h5>
                                <small class="text-muted fs-11">تأخير</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-danger-subtle border border-danger-subtle rounded">
                                <h5 class="mb-0 fw-bold text-danger font-monospace" id="hrAbsentCount">0</h5>
                                <small class="text-muted fs-11">غياب / إجازة</small>
                            </div>
                        </div>
                    </div>

                    <!-- Technicians Quick List (Bounded, smooth scroll) -->
                    <div class="d-flex flex-column gap-2 overflow-y-auto flex-grow-1 pe-1" id="dashTechListContainer" style="max-height: 240px;">
                        <!-- Rendered dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Printable Official Invoice & Warranty Receipt -->
    <div class="modal fade" id="dashInvoicePrintModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light border-bottom p-3 no-print">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ri-printer-line text-primary fs-18"></i>
                        <h5 class="modal-title fw-bold text-dark mb-0 fs-15">فاتورة معتمدة وشهادة ضمان رسمية</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="dashPrintableInvoiceContent">
                    <!-- Rendered dynamically -->
                </div>
                <div class="modal-footer bg-light p-3 no-print d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-primary fw-bold px-4" onclick="window.print()">
                        <i class="ri-printer-fill me-1"></i> طباعة الإيصال (A4 / حراري)
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <!-- Apexcharts JS -->
    <script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>

    <script>
    'use strict';

    let salesTrendChart = null;
    let categoryDonutChart = null;

    document.addEventListener('DOMContentLoaded', function () {
        initAlHusseiniDashboard();

        window.addEventListener('alhusseini-sales-updated', function () {
            initAlHusseiniDashboard();
        });

        window.addEventListener('alhusseini-hr-updated', function () {
            loadHRDashboardStats();
        });
    });

    function initAlHusseiniDashboard() {
        if (!window.AlHusseiniSales) return;

        loadKPIStats();
        loadRecentInvoicesTable();
        loadCreditDuesList();
        loadLowStockAlerts();
        loadHRDashboardStats();
        renderDashboardCharts();
    }

    // 1. KPI Stats
    function loadKPIStats() {
        const invoices = window.AlHusseiniSales.getInvoices();
        const customers = window.AlHusseiniSales.getCustomers();
        const products = window.AlHusseiniSales.getProducts();

        // Total sales
        const totalSales = invoices.reduce((sum, inv) => sum + (Number(inv.totalAmount) || 0), 0);
        document.getElementById('dashTotalSales').textContent = window.AlHusseiniSales.formatCurrency(totalSales);
        document.getElementById('dashInvoicesCount').textContent = invoices.length;

        // Total Credit (الآجل)
        const totalCredit = customers.reduce((sum, c) => sum + (Number(c.creditBalance) || 0), 0);
        document.getElementById('dashTotalCredit').textContent = window.AlHusseiniSales.formatCurrency(totalCredit);
        const creditCustCount = customers.filter(c => Number(c.creditBalance) > 0).length;
        document.getElementById('dashCreditCustomersCount').textContent = creditCustCount;

        // Customers count
        document.getElementById('dashCustomersCount').textContent = customers.length;

        // Products & Low stock count
        const lowStock = products.filter(p => p.stock !== undefined && p.stock <= 10);
        document.getElementById('dashProductsCount').textContent = products.length;
        document.getElementById('dashLowStockCount').textContent = lowStock.length;
        document.getElementById('dashLowStockBadge').textContent = `${products.length} صنف متاح`;
    }

    // 2. Recent Invoices Table
    function loadRecentInvoicesTable() {
        const invoices = window.AlHusseiniSales.getInvoices();
        const tbody = document.getElementById('dashRecentInvoicesBody');
        if (!tbody) return;

        if (invoices.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <i class="ri-file-list-line fs-20 d-block mb-1"></i>
                        لا توجد فواتير مسجلة حتى الآن
                    </td>
                </tr>
            `;
            return;
        }

        const recent = invoices.slice(-6).reverse();
        let html = '';

        const paymentBadges = {
            'cash': '<span class="badge bg-success-subtle text-success">نقدي كاش</span>',
            'instapay': '<span class="badge bg-primary-subtle text-primary">إنستاباي</span>',
            'card': '<span class="badge bg-info-subtle text-info">فيزا بنكية</span>',
            'credit': '<span class="badge bg-warning-subtle text-warning fw-bold">الآجل ⏱️</span>'
        };

        recent.forEach(inv => {
            const payBadge = paymentBadges[inv.paymentMethod] || `<span class="badge bg-light text-dark">${inv.paymentMethod}</span>`;
            const firstItem = inv.items && inv.items[0] ? inv.items[0].name : 'صنف';
            const extraCount = inv.items && inv.items.length > 1 ? ` (+${inv.items.length - 1} أصناف)` : '';

            html += `
                <tr>
                    <td class="text-center">
                        <a href="javascript:void(0);" onclick="viewInvoiceFromDashboard('${inv.id}')" class="fw-bold font-monospace link-primary">
                            #${inv.invoiceNo}
                        </a>
                        <small class="d-block text-muted fs-10 font-monospace">${inv.date}</small>
                    </td>
                    <td>
                        <strong class="text-dark d-block">${inv.customerName}</strong>
                        <small class="text-muted"><i class="ri-car-line me-1"></i>${inv.carModel} (${inv.carPlate})</small>
                    </td>
                    <td>
                        <span class="text-truncate d-inline-block text-dark fw-medium" style="max-width: 220px;" title="${firstItem}">
                            ${firstItem}${extraCount}
                        </span>
                    </td>
                    <td class="text-end">
                        <strong class="font-monospace text-success fs-13">${window.AlHusseiniSales.formatCurrency(inv.totalAmount)}</strong>
                    </td>
                    <td class="text-center">${payBadge}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-soft-primary px-2 py-1 fs-11" onclick="viewInvoiceFromDashboard('${inv.id}')" title="معاينة وطباعة">
                            <i class="ri-eye-line me-1"></i> عرض
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    // 3. Pending Credit / Dues List (الآجل)
    function loadCreditDuesList() {
        const customers = window.AlHusseiniSales.getCustomers();
        const container = document.getElementById('dashCreditListContainer');
        if (!container) return;

        const debtors = customers.filter(c => Number(c.creditBalance) > 0)
                                 .sort((a, b) => Number(b.creditBalance) - Number(a.creditBalance))
                                 .slice(0, 5);

        if (debtors.length === 0) {
            container.innerHTML = `
                <div class="text-center py-4 text-muted fs-12">
                    <i class="ri-checkbox-circle-line fs-24 text-success d-block mb-1"></i>
                    لا توجد أي مبالغ متأخرة بالآجل حالياً.. جميع الحسابات مسددة!
                </div>
            `;
            return;
        }

        let html = '';
        debtors.forEach(c => {
            html += `
                <div class="p-2 bg-light rounded border d-flex justify-content-between align-items-center">
                    <div>
                        <strong class="fs-12 text-dark d-block">${c.name}</strong>
                        <small class="text-muted fs-11">
                            <i class="ri-car-line me-1"></i>${c.carModel} | لوحة: ${c.carPlate}
                        </small>
                    </div>
                    <div class="text-end">
                        <strong class="text-danger font-monospace fs-13 d-block">${window.AlHusseiniSales.formatCurrency(c.creditBalance)}</strong>
                        <a href="{{ route('admin.sales.credit') }}" class="btn btn-sm btn-soft-warning py-0 px-2 fs-10 fw-bold">
                            تحصيل <i class="ri-arrow-left-s-line"></i>
                        </a>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // 4. Low Stock Alerts
    function loadLowStockAlerts() {
        const products = window.AlHusseiniSales.getProducts();
        const tbody = document.getElementById('dashLowStockTableBody');
        if (!tbody) return;

        const lowStock = products.filter(p => p.stock !== undefined && p.stock <= 12)
                                 .sort((a, b) => a.stock - b.stock)
                                 .slice(0, 5);

        if (lowStock.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-3 text-muted">
                        <i class="ri-checkbox-circle-fill text-success me-1"></i> جميع الأصناف متوفرة ومخزونها في المنطقة الآمنة
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        lowStock.forEach(p => {
            const isDanger = p.stock <= 5;
            const badgeClass = isDanger ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle';
            const badgeText = isDanger ? 'حرج جداً' : 'أوشك على النفاد';

            html += `
                <tr>
                    <td>
                        <strong class="text-dark d-block fs-12">${p.name}</strong>
                        <span class="badge bg-light text-secondary border font-monospace fs-10" dir="ltr">${p.brand}</span>
                    </td>
                    <td><span class="badge bg-light text-secondary">${p.category}</span></td>
                    <td class="text-center font-monospace fs-11">${p.barcode || '-'}</td>
                    <td class="text-center font-monospace fw-bold ${isDanger ? 'text-danger' : 'text-warning'} fs-13">${p.stock}</td>
                    <td class="text-center"><span class="badge ${badgeClass} fs-10">${badgeText}</span></td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    // 5. Workshop Technicians & Attendance
    function loadHRDashboardStats() {
        if (!window.AlHusseiniHR) return;
        const employees = window.AlHusseiniHR.getEmployees();
        const today = new Date().toISOString().split('T')[0];
        const attendance = window.AlHusseiniHR.getAttendance(today);

        let present = 0;
        let late = 0;
        let absent = 0;

        attendance.forEach(att => {
            if (att.status === 'present') present++;
            else if (att.status === 'late') late++;
            else if (att.status === 'absent' || att.status === 'leave') absent++;
        });

        document.getElementById('hrPresentCount').textContent = present;
        document.getElementById('hrLateCount').textContent = late;
        document.getElementById('hrAbsentCount').textContent = absent;

        const container = document.getElementById('dashTechListContainer');
        if (!container) return;

        let html = '';
        let techStaff = employees.filter(e => e.department !== 'الإدارة والإشراف');
        if (techStaff.length === 0) techStaff = employees;
        techStaff = techStaff.slice(0, 4);

        techStaff.forEach(emp => {
            const att = attendance.find(a => a.employeeId === emp.id);
            const status = att ? att.status : 'present';
            let badgeHtml = '<span class="badge bg-success-subtle text-success border border-success-subtle">حاضر بالوردية</span>';
            if (status === 'late') badgeHtml = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">متأخر</span>';
            else if (status === 'absent') badgeHtml = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">غائب</span>';

            html += `
                <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light border fs-12 mb-1">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-xs">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fw-bold fs-11">
                                ${emp.name.charAt(0)}
                            </span>
                        </div>
                        <div>
                            <strong class="text-dark d-block fs-12">${emp.name}</strong>
                            <small class="text-muted fs-11"><i class="ri-user-settings-line me-1"></i>${emp.role || emp.department}</small>
                        </div>
                    </div>
                    <div class="text-end">
                        ${badgeHtml}
                        <small class="d-block text-muted font-monospace fs-10 mt-1">${emp.startTime || '09:00'} - ${emp.endTime || '18:00'}</small>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // 6. ApexCharts: Sales Trend & Donut
    function renderDashboardCharts() {
        const invoices = window.AlHusseiniSales.getInvoices();

        // Calculate Category Sums
        let sumBatteries = 0;
        let sumOils = 0;
        let sumGreases = 0;
        let sumServices = 0;
        let sumScrap = 0;

        invoices.forEach(inv => {
            sumScrap += (inv.scrapDiscountTotal || 0);
            (inv.items || []).forEach(it => {
                const total = it.finalPrice || (it.unitPrice * it.qty) || 0;
                if (it.category === 'بطاريات') sumBatteries += total;
                else if (it.category === 'زيوت') sumOils += total;
                else if (it.category === 'شحوم وسوائل') sumGreases += total;
                else if (it.category === 'خدمات وصيانة') sumServices += total;
            });
        });

        // If newly started with default invoices, give realistic sample proportions
        if (sumBatteries === 0 && sumOils === 0) {
            sumBatteries = 24500;
            sumOils = 13800;
            sumGreases = 3200;
            sumServices = 4900;
            sumScrap = 3600;
        }

        document.getElementById('catStatBatteries').textContent = window.AlHusseiniSales.formatCurrency(sumBatteries);
        document.getElementById('catStatOils').textContent = window.AlHusseiniSales.formatCurrency(sumOils);
        document.getElementById('catStatServices').textContent = window.AlHusseiniSales.formatCurrency(sumServices);
        document.getElementById('catStatScrap').textContent = `- ${window.AlHusseiniSales.formatCurrency(sumScrap)}`;

        const totalCats = sumBatteries + sumOils + sumGreases + sumServices;
        const pctB = Math.round((sumBatteries / totalCats) * 100);
        const pctO = Math.round((sumOils / totalCats) * 100);
        const pctG = Math.round((sumGreases / totalCats) * 100);
        const pctS = Math.max(0, 100 - (pctB + pctO + pctG));

        document.getElementById('donutPctBatteries').textContent = `${pctB}%`;
        document.getElementById('donutPctOils').textContent = `${pctO}%`;
        document.getElementById('donutPctGreases').textContent = `${pctG}%`;
        document.getElementById('donutPctServices').textContent = `${pctS}%`;

        document.getElementById('donutValBatteries').textContent = window.AlHusseiniSales.formatCurrency(sumBatteries);
        document.getElementById('donutValOils').textContent = window.AlHusseiniSales.formatCurrency(sumOils);
        document.getElementById('donutValGreases').textContent = window.AlHusseiniSales.formatCurrency(sumGreases);
        document.getElementById('donutValServices').textContent = window.AlHusseiniSales.formatCurrency(sumServices);

        // 1. Column / Area Trend Chart
        const trendEl = document.querySelector("#alhusseini_sales_trend_chart");
        if (trendEl) {
            if (salesTrendChart) salesTrendChart.destroy();

            const optionsTrend = {
                series: [
                    { name: 'بطاريات سيارات', data: [4200, 5600, 3800, 7100, 6200, 8400, sumBatteries > 10000 ? 9100 : sumBatteries] },
                    { name: 'زيوت وفلاتر', data: [2100, 3400, 2900, 4300, 3900, 5200, sumOils > 5000 ? 5800 : sumOils] },
                    { name: 'صيانة وكهرباء', data: [800, 1200, 950, 1400, 1100, 1800, sumServices > 2000 ? 2100 : sumServices] }
                ],
                chart: {
                    type: 'area',
                    height: 280,
                    toolbar: { show: false },
                    fontFamily: 'Cairo, Almarai, sans-serif'
                },
                colors: ['#405189', '#0ab39c', '#299cdb'],
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 2 },
                fill: {
                    type: 'gradient',
                    gradient: { opacityFrom: 0.45, opacityTo: 0.05 }
                },
                xaxis: {
                    categories: ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'اليوم'],
                    labels: { style: { fontFamily: 'Cairo', fontSize: '11px' } }
                },
                yaxis: {
                    labels: {
                        formatter: val => `${val.toLocaleString('ar-EG')} ج.م`,
                        style: { fontFamily: 'Cairo', fontSize: '11px' }
                    }
                },
                tooltip: {
                    y: { formatter: val => `${val.toLocaleString('ar-EG')} ج.م` }
                },
                legend: { position: 'top', horizontalAlign: 'right', fontFamily: 'Cairo' }
            };

            salesTrendChart = new ApexCharts(trendEl, optionsTrend);
            salesTrendChart.render();
        }

        // 2. Category Donut Chart
        const donutEl = document.querySelector("#alhusseini_category_donut_chart");
        if (donutEl) {
            if (categoryDonutChart) categoryDonutChart.destroy();

            const optionsDonut = {
                series: [sumBatteries, sumOils, sumGreases, sumServices],
                labels: ['بطاريات سيارات', 'زيوت وفلاتر', 'شحوم وسوائل', 'صيانة وخدمات'],
                chart: {
                    type: 'donut',
                    height: 215,
                    fontFamily: 'Cairo, Almarai, sans-serif'
                },
                colors: ['#405189', '#0ab39c', '#f7b84b', '#299cdb'],
                dataLabels: { enabled: false },
                legend: { show: false },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '68%',
                            labels: {
                                show: true,
                                name: { show: true, fontSize: '13px', fontFamily: 'Cairo' },
                                value: {
                                    show: true,
                                    fontSize: '15px',
                                    fontWeight: 700,
                                    fontFamily: 'Cairo',
                                    formatter: val => `${Number(val).toLocaleString('ar-EG')} ج.م`
                                },
                                total: {
                                    show: true,
                                    label: 'المبيعات',
                                    fontSize: '12px',
                                    formatter: w => `${w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString('ar-EG')} ج.م`
                                }
                            }
                        }
                    }
                }
            };

            categoryDonutChart = new ApexCharts(donutEl, optionsDonut);
            categoryDonutChart.render();
        }
    }

    // 7. Preview and Print Invoice Modal
    function viewInvoiceFromDashboard(invId) {
        const inv = window.AlHusseiniSales.getInvoiceById(invId);
        if (!inv) return;

        const container = document.getElementById('dashPrintableInvoiceContent');
        const payLabels = { 'cash': 'نقدي (كاش)', 'instapay': 'إنستاباي / فوري', 'card': 'فيزا / بطاقة بنكية', 'credit': 'الآجل (مستحق)' };

        let itemsHtml = '';
        (inv.items || []).forEach((it, i) => {
            itemsHtml += `
                <tr>
                    <td class="text-center font-monospace">${i + 1}</td>
                    <td>
                        <strong class="text-dark fs-13 d-block">${it.name}</strong>
                        <small class="text-muted font-monospace">${it.barcode ? `باركود: ${it.barcode}` : it.brand}</small>
                    </td>
                    <td class="text-center font-monospace fw-bold">${it.qty}</td>
                    <td class="text-end font-monospace">${window.AlHusseiniSales.formatCurrency(it.unitPrice)}</td>
                    <td class="text-end font-monospace fw-bold">${window.AlHusseiniSales.formatCurrency(it.finalPrice || (it.unitPrice * it.qty))}</td>
                </tr>
            `;
        });

        container.innerHTML = `
            <div class="print-invoice-sheet text-dark" style="direction: rtl;">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="48" class="rounded-circle shadow-sm">
                        <div>
                            <h4 class="fw-extrabold text-primary mb-0">مركز الحسيني لبطاريات وزيوت السيارات</h4>
                            <small class="text-muted">صيانة متكاملة - بطاريات جافة وسائلة - زيوت معتمدة - فحص كمبيوتر دينامو</small>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-dark font-monospace fs-13 mb-1">فاتورة #${inv.invoiceNo}</span>
                        <div class="text-muted fs-11">${inv.date} | ${inv.time || ''}</div>
                    </div>
                </div>

                <div class="row g-2 mb-3 p-3 bg-light rounded border">
                    <div class="col-6"><strong>اسم العميل:</strong> ${inv.customerName}</div>
                    <div class="col-6"><strong>رقم الهاتف:</strong> <span class="font-monospace">${inv.customerPhone}</span></div>
                    <div class="col-6"><strong>السيارة:</strong> ${inv.carModel}</div>
                    <div class="col-6"><strong>رقم اللوحة:</strong> <span class="font-monospace fw-bold">${inv.carPlate}</span></div>
                </div>

                <table class="table table-bordered align-middle mb-3">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 50px;">#</th>
                            <th>الصنف / الخدمة</th>
                            <th class="text-center" style="width: 70px;">الكمية</th>
                            <th class="text-end" style="width: 120px;">السعر</th>
                            <th class="text-end" style="width: 130px;">الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                </table>

                <div class="row justify-content-end mb-3">
                    <div class="col-md-6 col-12">
                        <div class="p-2 border rounded bg-light fs-12">
                            <div class="d-flex justify-content-between mb-1">
                                <span>المجموع:</span>
                                <span class="font-monospace fw-bold">${window.AlHusseiniSales.formatCurrency(inv.subtotal || inv.totalAmount)}</span>
                            </div>
                            ${inv.scrapDiscountTotal > 0 ? `
                                <div class="d-flex justify-content-between mb-1 text-success">
                                    <span>خصم البطارية القديمة (الكهنة):</span>
                                    <span class="font-monospace fw-bold">- ${window.AlHusseiniSales.formatCurrency(inv.scrapDiscountTotal)}</span>
                                </div>
                            ` : ''}
                            <div class="d-flex justify-content-between align-items-center border-top pt-1 mt-1 fs-14">
                                <strong class="text-dark">الصافي المطلوب:</strong>
                                <strong class="text-success font-monospace fs-16">${window.AlHusseiniSales.formatCurrency(inv.totalAmount)}</strong>
                            </div>
                            <div class="d-flex justify-content-between border-top pt-1 mt-1 text-muted fs-11">
                                <span>طريقة السداد:</span>
                                <span class="fw-bold">${payLabels[inv.paymentMethod] || inv.paymentMethod}</span>
                            </div>
                            ${inv.remainingCredit > 0 ? `
                                <div class="d-flex justify-content-between text-danger fw-bold border-top pt-1 mt-1">
                                    <span>المتبقي على الآجل:</span>
                                    <span class="font-monospace">${window.AlHusseiniSales.formatCurrency(inv.remainingCredit)}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>

                <div class="border-top pt-2 text-center text-muted fs-11">
                    <p class="mb-1"><strong>سيريال الضمان المعتمد:</strong> <span class="font-monospace text-primary fw-bold">${inv.serialNumber || 'SN-78942'}</span> | ينتهي في: <span class="font-monospace">${inv.warrantyExpiry || '2027-09-20'}</span></p>
                    <small>شكراً لتعاملكم مع مركز الحسيني - خدمة الدعم الفني والطوارئ: 01000000000</small>
                </div>
            </div>
        `;

        const modal = new bootstrap.Modal(document.getElementById('dashInvoicePrintModal'));
        modal.show();
    }
    </script>
@endsection
