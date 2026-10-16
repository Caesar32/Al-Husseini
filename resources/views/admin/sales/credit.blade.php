@extends('admin.layouts.master')

@section('title', 'حسابات الآجل والمستحقات المالية | مركز الحسيني لبطاريات السيارات')

@section('css')
<style>
    .credit-stat-card {
        border-radius: 12px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .credit-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.06);
    }
    .quick-pay-preset {
        font-weight: 700;
        border-radius: 8px;
        padding: 6px 12px;
        cursor: pointer;
    }
    .quick-pay-preset:hover {
        background: var(--vz-success-bg-subtle, rgba(10, 179, 156, 0.1));
        border-color: var(--vz-success);
        color: var(--vz-success);
    }

    /* ============================================================== */
    /* Dark Mode Overrides for Credit & Receivables Page              */
    /* ============================================================== */
    [data-bs-theme="dark"] .credit-stat-card {
        background-color: #212529 !important;
        border: 1px solid #32383e !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important;
    }
    [data-bs-theme="dark"] .credit-stat-card:hover {
        box-shadow: 0 10px 24px rgba(0,0,0,0.35) !important;
    }
    [data-bs-theme="dark"] .table-light,
    [data-bs-theme="dark"] .table-light th {
        background-color: #212529 !important;
        color: #ced4da !important;
        border-color: #32383e !important;
    }
    [data-bs-theme="dark"] .text-dark {
        color: #f8f9fa !important;
    }
    [data-bs-theme="dark"] .bg-light {
        background-color: #212529 !important;
        border-color: #32383e !important;
    }
    [data-bs-theme="dark"] .quick-pay-preset {
        background-color: #1e2226;
        color: #3cd188;
        border: 1px solid #198754;
    }
    [data-bs-theme="dark"] .quick-pay-preset:hover {
        background-color: #198754;
        color: #ffffff;
    }
    [data-bs-theme="dark"] .modal-content {
        background-color: #212529 !important;
        border-color: #383f45 !important;
        color: #ced4da !important;
    }
    [data-bs-theme="dark"] .modal-footer.bg-light {
        background-color: #1a1d21 !important;
        border-top-color: #32383e !important;
    }
    [data-bs-theme="dark"] .modal-content .form-control,
    [data-bs-theme="dark"] .modal-content .form-select {
        background-color: #1e2226 !important;
        border-color: #383f45 !important;
        color: #f8f9fa !important;
    }

    @media print {
        .app-menu, .topbar, .footer, .btn, .no-print, .credit-controls { display: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; }
        .print-credit-header { display: block !important; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .table { border: 1px solid #000 !important; }
        .table th, .table td { border: 1px solid #ccc !important; }
    }
    .print-credit-header { display: none; }
</style>
@endsection

@section('content')
@php
    $pageConfig = [
        'data' => [
            'totalOutstanding' => (float) number_format($totalOutstanding, 2, '.', ''),
            'customersCount' => (int) $customersCount,
            'exceededLimitCount' => (int) $exceededLimitCount,
            'collectedThisMonth' => (float) number_format($collectedThisMonth, 2, '.', ''),
            'collectionsCount' => (int) $collectionsCount,
            'totalCustomers' => (int) $totalCustomers,
            'customers' => $customers,
        ],
        'routes' => [
            'settle' => route('admin.credit.settle'),
            'payments' => route('admin.credit.payments'),
            'statement' => route('admin.credit.statement', ['customer' => '__ID__']),
        ],
    ];
@endphp
<div data-page="sales.credit" data-config='@json($pageConfig)'>
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'المبيعات والمستحقات', 'title' => 'حسابات الآجل والمستحقات المالية'])

    <!-- Official Print Header (Visible only when printing) -->
    <div class="print-credit-header text-center">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="text-start">
                <h5 class="fw-bold mb-0">مركز الحسيني لبطاريات وزيوت وصيانة السيارات</h5>
                <p class="text-muted fs-12 mb-0">كشف حسابات مديونيات وفواتير الآجل للعملاء والورش</p>
            </div>
            <div class="text-end">
                <p class="text-muted fs-11 mb-0">تاريخ التقرير: <span id="printCreditDate"></span></p>
                <p class="text-muted fs-11 mb-0">يعتمد: الإدارة المالية</p>
            </div>
        </div>
    </div>

    <!-- Friendly Top Guidance Alert -->
    <div class="row mb-3 no-print">
        <div class="col-12">
            <div class="alert alert-warning bg-warning-subtle border-0 shadow-sm d-flex flex-wrap align-items-center justify-content-between p-3 rounded-3 mb-0">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <div class="avatar-sm me-3 flex-shrink-0">
                        <span class="avatar-title bg-warning text-dark rounded-circle fs-20 shadow-sm fw-bold">
                            <i class="ri-hand-coin-line"></i>
                        </span>
                    </div>
                    <div>
                        <h5 class="alert-heading fw-bold mb-1 fs-15 text-warning-emphasis">إدارة حسابات "الآجل" ومستحقات بطاريات السيارات بالخارج</h5>
                        <p class="mb-0 fs-13 text-muted">
                            هنا يمكنك متابعة كل المبالغ المتبقية على العملاء وأصحاب الورش وسيارات النقل، وتسجيل تحصيل أي دفعة بنقرة واحدة لتحديث الرصيد فورياً.
                        </p>
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-warning text-dark fw-bold btn-sm me-1" data-action="credit.export">
                        <i class="ri-file-excel-2-line me-1"></i> تصدير إكسيل (CSV)
                    </button>
                    <button type="button" class="btn btn-warning text-dark fw-bold btn-sm" data-action="print">
                        <i class="ri-printer-line me-1"></i> طباعة كشف الآجل
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Summary Metric Cards -->
    <div class="row mb-3">
        <!-- 1. Total Outstanding Credit -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate credit-stat-card border-start border-danger border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">إجمالي مبالغ الآجل المستحقة</p>
                            <h3 class="fs-24 fw-extrabold text-danger mb-1 font-monospace" id="kpiTotalOutstanding" style="cursor: help;" data-bs-toggle="tooltip" data-bs-placement="top" title="0.00 ج.م">0 ج.م</h3>
                            <small class="text-muted fs-11">مستحقات على عملاء وورش المركز</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-20 shadow-sm">
                                <i class="ri-money-dollar-circle-fill"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Indebted Customers Count -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate credit-stat-card border-start border-warning border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">عدد العملاء المدينين بالآجل</p>
                            <h3 class="fs-24 fw-extrabold text-warning mb-1 font-monospace" id="kpiCreditCustomersCount">0 عميل</h3>
                            <small class="text-muted fs-11">لديهم فواتير متبقية لم تسدد</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-20 shadow-sm">
                                <i class="ri-user-unfollow-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Collected This Month -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate credit-stat-card border-start border-success border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">المحصل من الآجل هذا الشهر</p>
                            <h3 class="fs-24 fw-extrabold text-success mb-1 font-monospace" id="kpiCollectedThisMonth" style="cursor: help;" data-bs-toggle="tooltip" data-bs-placement="top" title="0.00 ج.م">0 ج.م</h3>
                            <small class="text-muted fs-11">تم توريدها لحساب المركز</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-20 shadow-sm">
                                <i class="ri-hand-coin-fill"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Total Customers -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate credit-stat-card border-start border-primary border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">نسبة عملاء الآجل للمركز</p>
                            <h3 class="fs-24 fw-extrabold text-primary mb-1" id="kpiCreditRatio">0%</h3>
                            <small class="text-muted fs-11" id="kpiTotalCustomersText">من إجمالي العملاء</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20 shadow-sm">
                                <i class="ri-pie-chart-2-fill"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Credit Table with Tabs: [العملاء المدينين بالآجل] & [سجل سندات التحصيل] -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom p-0">
                    <ul class="nav nav-tabs nav-tabs-custom card-header-tabs border-bottom-0" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active fw-bold fs-14 py-3" data-bs-toggle="tab" href="#tab-credit-customers" role="tab">
                                <i class="ri-user-shared-line me-1 text-danger"></i> كشف حسابات العملاء المدينين بالآجل
                                <span class="badge bg-danger-subtle text-danger ms-1" id="badgeCreditCustCount">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-bold fs-14 py-3" data-bs-toggle="tab" href="#tab-credit-payments" role="tab">
                                <i class="ri-history-line me-1 text-success"></i> سجل سندات ودفعات التحصيل الأخيرة
                                <span class="badge bg-success-subtle text-success ms-1" id="badgeCreditPayCount">0</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-0">
                    <div class="tab-content">
                        <!-- TAB 1: INDEBTED CUSTOMERS TABLE -->
                        <div class="tab-pane active p-3" id="tab-credit-customers" role="tabpanel">
                            <!-- Filters bar -->
                            <div class="row g-2 align-items-center mb-3 no-print">
                                <div class="col-md-5 col-12">
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="ri-search-line text-muted"></i></span>
                                        <input type="text" class="form-control" id="searchCreditInput" placeholder="ابحث باسم العميل أو رقم التليفون أو رقم اللوحة..." data-action="credit.render">
                                    </div>
                                </div>
                                <div class="col-md-4 col-12">
                                    <select class="form-select" id="filterCustType" data-action="credit.render">
                                        <option value="all">جميع تصنيفات العملاء</option>
                                        <option value="standard">عادي</option>
                                        <option value="vip">مميز (VIP)</option>
                                        <option value="fleet">أساطيل وشركات</option>
                                    </select>
                                </div>
                                <div class="col-md-3 col-12 text-md-end">
                                    <span class="badge bg-light text-dark border fs-12 p-2">
                                        المعروض: <span class="fw-bold" id="visibleCreditCount">0</span> عميل
                                    </span>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle table-nowrap mb-0" id="tableCreditCustomers">
                                    <thead class="table-light">
                                        <tr class="text-muted fs-12 text-uppercase">
                                            <th>العميل</th>
                                            <th>رقم الهاتف</th>
                                            <th>نوع السيارة واللوحة</th>
                                            <th>تصنيف العميل</th>
                                            <th>إجمالي المسحوبات</th>
                                            <th>المتبقي على حساب الآجل</th>
                                            <th>حالة المديونية</th>
                                            <th class="text-center no-print" style="min-width: 180px;">إجراء التحصيل السريع</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyCreditCustomers">
                                        <!-- Rendered dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 2: CREDIT PAYMENTS HISTORY -->
                        <div class="tab-pane p-3" id="tab-credit-payments" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle table-nowrap mb-0">
                                    <thead class="table-light">
                                        <tr class="text-muted fs-12 text-uppercase">
                                            <th>رقم إيصال التحصيل</th>
                                            <th>اسم العميل</th>
                                            <th>تاريخ السداد</th>
                                            <th>المبلغ المحصل (ج.م)</th>
                                            <th>المستلم بالمركز</th>
                                            <th>ملاحظات السداد</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyCreditPayments">
                                        <!-- Rendered dynamically -->
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-center gap-2 mt-3 no-print" id="paymentsPager"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Quick Record Payment for Credit (تسجيل تحصيل من الآجل) -->
    <div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white p-3">
                    <h5 class="modal-title fw-bold text-white fs-15">
                        <i class="ri-hand-coin-line me-1"></i> تسجيل سند تحصيل دفعة من الآجل
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="recordPaymentForm" data-action="credit.submit-payment">
                    <input type="hidden" id="payCustId">
                    <div class="modal-body p-4">
                        <!-- Customer Brief Card -->
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="fw-bold text-dark fs-14 mb-0" id="payCustName">-</h6>
                                <span class="badge bg-danger text-white fs-12 font-monospace" id="payCustBalanceBadge">0 ج.م</span>
                            </div>
                            <p class="text-muted fs-12 mb-0" id="payCustCarInfo">-</p>
                        </div>

                        <!-- Amount Presets -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark fs-13 mb-1">
                                المبلغ المراد تحصيله (اختر سريعاً أو اكتب):
                            </label>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <button type="button" class="btn btn-outline-success btn-sm quick-pay-preset" data-action="credit.pay-amount" data-amount="500">500 ج.م</button>
                                <button type="button" class="btn btn-outline-success btn-sm quick-pay-preset" data-action="credit.pay-amount" data-amount="1000">1,000 ج.م</button>
                                <button type="button" class="btn btn-outline-success btn-sm quick-pay-preset" data-action="credit.pay-amount" data-amount="2000">2,000 ج.م</button>
                                <button type="button" class="btn btn-outline-primary btn-sm quick-pay-preset" data-action="credit.pay-full">سداد كامل المتبقي</button>
                            </div>
                            <div class="input-group">
                                <input type="number" class="form-control form-control-lg fw-bold fs-16 border-success text-center" id="payAmountInput" required min="0.01" step="0.01" value="500">
                                <span class="input-group-text bg-success-subtle text-success fw-bold">جنيه مصري (EGP)</span>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark fs-13">طريقة استلام الدفعة:</label>
                            <select class="form-select" id="payMethodSelect">
                                <option value="cash">نقداً (كاش بالخزينة)</option>
                                <option value="bank_transfer">تحويل بنكي / إنستاباي / محفظة</option>
                                <option value="card">بطاقة بنكية (فيزا)</option>
                            </select>
                        </div>

                        <!-- Receipt number (optional) & receiver (always the signed-in user) -->
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold text-dark fs-13">رقم سند القبض (اختياري):</label>
                                <input type="text" class="form-control font-monospace" id="payReceiptInput" maxlength="50">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold text-dark fs-13">المستلم:</label>
                                <input type="text" class="form-control" value="{{ auth()->user()->name }}" readonly>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mb-0">
                            <label class="form-label fw-semibold text-muted fs-12">ملاحظات التحصيل (اختياري):</label>
                            <input type="text" class="form-control form-control-sm" id="payNotesInput" placeholder="مثلاً: دفعة من حساب بطارية الشاحنة">
                        </div>
                    </div>

                    <div class="modal-footer bg-light p-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-success fw-bold px-4">
                            <i class="ri-check-line align-middle me-1"></i> حفظ سند القبض وتحديث الرصيد
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Customer Statement of Account (كشف حساب فواتير العميل) -->
    <div class="modal fade" id="customerStatementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white p-3">
                    <h5 class="modal-title fw-bold text-white fs-15" id="statementModalTitle">كشف حساب فواتير وسداد العميل</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="statementModalBody">
                    <!-- Populated dynamically -->
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-primary" data-action="print">
                        <i class="ri-printer-line me-1"></i> طباعة كشف الحساب
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="{{ asset('assets/js/admin/sales/credit.js') }}"></script>
@endsection
