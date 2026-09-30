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
                    <button type="button" class="btn btn-outline-warning text-dark fw-bold btn-sm me-1" onclick="exportCreditCSV()">
                        <i class="ri-file-excel-2-line me-1"></i> تصدير إكسيل (CSV)
                    </button>
                    <button type="button" class="btn btn-warning text-dark fw-bold btn-sm" onclick="window.print()">
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
                            <h3 class="fs-24 fw-extrabold text-danger mb-1 font-monospace" id="kpiTotalOutstanding">0 ج.م</h3>
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
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">المحصل من الآجل مؤخراً</p>
                            <h3 class="fs-24 fw-extrabold text-success mb-1 font-monospace" id="kpiCollectedThisMonth">0 ج.م</h3>
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
                                        <input type="text" class="form-control" id="searchCreditInput" placeholder="ابحث باسم العميل أو رقم التليفون أو رقم اللوحة..." oninput="renderCreditTable()">
                                    </div>
                                </div>
                                <div class="col-md-4 col-12">
                                    <select class="form-select" id="filterCustType" onchange="renderCreditTable()">
                                        <option value="all">جميع أنواع العملاء</option>
                                        <option value="ملاكي">سيارات ملاكي خاصة</option>
                                        <option value="ورش وشركات">ورش صيانة وشركات</option>
                                        <option value="تاكسي وأوبر">تاكسي وأوبر</option>
                                        <option value="نقل وتريلات">نقل وتريلات</option>
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
                                            <th>طريقة الدفع</th>
                                            <th>المستلم بالمركز</th>
                                            <th>ملاحظات السداد</th>
                                            <th class="no-print">الحالة</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyCreditPayments">
                                        <!-- Rendered dynamically -->
                                    </tbody>
                                </table>
                            </div>
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

                <form id="recordPaymentForm" onsubmit="submitCreditPayment(event)">
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
                                <button type="button" class="btn btn-outline-success btn-sm quick-pay-preset" onclick="setPayAmount(500)">500 ج.م</button>
                                <button type="button" class="btn btn-outline-success btn-sm quick-pay-preset" onclick="setPayAmount(1000)">1,000 ج.م</button>
                                <button type="button" class="btn btn-outline-success btn-sm quick-pay-preset" onclick="setPayAmount(2000)">2,000 ج.م</button>
                                <button type="button" class="btn btn-outline-primary btn-sm quick-pay-preset" onclick="setPayFullAmount()">سداد كامل المتبقي</button>
                            </div>
                            <div class="input-group">
                                <input type="number" class="form-control form-control-lg fw-bold fs-16 border-success text-center" id="payAmountInput" required min="50" step="50" value="500">
                                <span class="input-group-text bg-success-subtle text-success fw-bold">جنيه مصري (EGP)</span>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark fs-13">طريقة استلام الدفعة:</label>
                            <select class="form-select" id="payMethodSelect">
                                <option value="cash">نقداً (كاش بالخزينة)</option>
                                <option value="instapay">تحويل إنستاباي (InstaPay)</option>
                                <option value="vodafone_cash">فودافون كاش / محفظة</option>
                                <option value="bank">إيداع / تحويل بنكي</option>
                            </select>
                        </div>

                        <!-- Receiver -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark fs-13">المستلم بالإدارة:</label>
                            <select class="form-select" id="payReceiverSelect">
                                <option value="الحاج محمود الحسيني">الحاج محمود الحسيني (المدير العام)</option>
                                <option value="إبراهيم حسن (كبير البائعين)">إبراهيم حسن (مسؤول الصالة)</option>
                                <option value="الخزينة الرئيسية">الخزينة الرئيسية للمركز</option>
                            </select>
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
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i class="ri-printer-line me-1"></i> طباعة كشف الحساب
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
<script>
'use strict';

// Server-backed credit data (FIN-M06: no longer localStorage-only)
window.serverCreditData = {
    totalOutstanding: {{ number_format($totalOutstanding, 2, '.', '') }},
    customersCount: {{ $customersCount }},
    exceededLimitCount: {{ $exceededLimitCount }},
    customers: @json($customers)
};

let currentTargetCustomer = null;

document.addEventListener('DOMContentLoaded', function () {
    const today = new Date();
    const el = document.getElementById('printCreditDate');
    if (el) el.textContent = today.toLocaleDateString('ar-EG', { dateStyle: 'full' });

    renderCreditDashboard();

    window.addEventListener('alhusseini-sales-updated', function () {
        renderCreditDashboard();
    });
});

function renderCreditDashboard() {
    // Prefer server data; fallback to localStorage mock if not available
    let summary = null;
    if (window.serverCreditData && Array.isArray(window.serverCreditData.customers)) {
        const custs = window.serverCreditData.customers;
        // Hydrate minimal summary from server
        summary = {
            totalCreditOutstanding: window.serverCreditData.totalOutstanding,
            creditCustomersCount: window.serverCreditData.customersCount,
            totalCollectedThisMonth: 0, // server does not track monthly collected here; keep 0
            totalCustomers: custs.length, // approximate; full totalCustomers would require extra query
            creditCustomers: custs.map(c => ({
                id: c.id,
                name: c.name,
                phone: c.phone,
                carModel: (c.vehicles && c.vehicles[0]) ? (c.vehicles[0].car_brand + ' ' + c.vehicles[0].car_model) : '',
                carPlate: (c.vehicles && c.vehicles[0]) ? c.vehicles[0].plate_number : '',
                type: c.tier === 'fleet' ? 'ورش وشركات' : (c.tier === 'vip' ? 'تاكسي وأوبر' : 'ملاكي'),
                creditBalance: parseFloat(c.current_credit_balance),
                totalPurchases: 0,
            })),
            // For payments tab, keep localStorage if available, else empty
            getCreditPayments: () => window.AlHusseiniSales ? window.AlHusseiniSales.getCreditPayments() : []
        };
        // Update KPIs from server
        const totOutEl = document.getElementById('kpiTotalOutstanding');
        if (totOutEl) totOutEl.textContent = new Intl.NumberFormat('ar-EG', {style:'currency', currency:'EGP'}).format(summary.totalCreditOutstanding);
        const custCountEl = document.getElementById('kpiCreditCustomersCount');
        if (custCountEl) custCountEl.textContent = `${summary.creditCustomersCount} عميل`;
        // Keep collected as 0 or from localStorage if available
        const collectedEl = document.getElementById('kpiCollectedThisMonth');
        if (collectedEl && window.AlHusseiniSales) {
            try { collectedEl.textContent = window.AlHusseiniSales.formatCurrency(window.AlHusseiniSales.getCreditSummary().totalCollectedThisMonth); } catch(e) {}
        }
        const ratioEl = document.getElementById('kpiCreditRatio');
        if (ratioEl) {
            const ratio = summary.creditCustomersCount > 0 ? 100 : 0;
            ratioEl.textContent = `${ratio}%`;
        }
        const totalCustEl = document.getElementById('kpiTotalCustomersText');
        if (totalCustEl) totalCustEl.textContent = `من إجمالي ${summary.creditCustomersCount} عميل مدين`;
        const badgeCust = document.getElementById('badgeCreditCustCount');
        if (badgeCust) badgeCust.textContent = summary.creditCustomersCount;
        const badgePay = document.getElementById('badgeCreditPayCount');
        if (badgePay && window.AlHusseiniSales) { try { badgePay.textContent = window.AlHusseiniSales.getCreditPayments().length; } catch(e) {} }

        // Render tables with server data
        renderCreditTableServer(summary);
        renderPaymentsTable();
        return;
    }

    if (!window.AlHusseiniSales) return;

    const fallbackSummary = window.AlHusseiniSales.getCreditSummary();

    // 1. Update KPIs
    document.getElementById('kpiTotalOutstanding').textContent = window.AlHusseiniSales.formatCurrency(fallbackSummary.totalCreditOutstanding);
    document.getElementById('kpiCreditCustomersCount').textContent = `${fallbackSummary.creditCustomersCount} عميل`;
    document.getElementById('kpiCollectedThisMonth').textContent = window.AlHusseiniSales.formatCurrency(fallbackSummary.totalCollectedThisMonth);

    const ratio = fallbackSummary.totalCustomers > 0 ? Math.round((fallbackSummary.creditCustomersCount / fallbackSummary.totalCustomers) * 100) : 0;
    document.getElementById('kpiCreditRatio').textContent = `${ratio}%`;
    document.getElementById('kpiTotalCustomersText').textContent = `من إجمالي ${fallbackSummary.totalCustomers} عميل مسجل`;

    document.getElementById('badgeCreditCustCount').textContent = fallbackSummary.creditCustomersCount;
    document.getElementById('badgeCreditPayCount').textContent = window.AlHusseiniSales.getCreditPayments().length;

    renderCreditTable();
    renderPaymentsTable();
}

function renderCreditTableServer(serverSummary) {
    const searchVal = (document.getElementById('searchCreditInput')?.value || '').trim().toLowerCase();
    const typeVal = document.getElementById('filterCustType')?.value || 'all';
    const tbody = document.getElementById('tbodyCreditCustomers');
    if (!tbody) return;
    const filtered = serverSummary.creditCustomers.filter(c => {
        if (typeVal !== 'all' && c.type !== typeVal) return false;
        if (searchVal) {
            const matchName = c.name.toLowerCase().includes(searchVal);
            const matchPhone = c.phone.toLowerCase().includes(searchVal);
            const matchCar = (c.carModel||'').toLowerCase().includes(searchVal);
            const matchPlate = (c.carPlate||'').toLowerCase().includes(searchVal);
            if (!matchName && !matchPhone && !matchCar && !matchPlate) return false;
        }
        return true;
    });
    const visibleEl = document.getElementById('visibleCreditCount');
    if (visibleEl) visibleEl.textContent = filtered.length;
    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted fs-13"><i class="ri-checkbox-circle-line fs-24 text-success d-block mb-1"></i>لا توجد مديونيات آجلة مطابقة لبحثك! كل الحسابات خالصة.</td></tr>`;
        return;
    }
    let html = '';
    filtered.forEach(c => {
        const balance = Number(c.creditBalance) || 0;
        const totalPurchases = Number(c.totalPurchases) || 0;
        html += `
            <tr>
                <td><div class="d-flex align-items-center"><div class="avatar-xs me-2"><span class="avatar-title rounded-circle bg-warning-subtle text-warning fw-bold fs-13">${c.name.charAt(0)}</span></div><div><h6 class="fs-13 mb-0 fw-bold text-dark">${c.name}</h6><small class="text-muted font-monospace">${c.id}</small></div></div></td>
                <td><span class="font-monospace fw-semibold text-dark fs-12">${c.phone}</span></td>
                <td><span class="fw-bold fs-12 text-dark d-block">${c.carModel}</span><span class="badge bg-light text-secondary border font-monospace fs-11">${c.carPlate}</span></td>
                <td><span class="badge bg-primary-subtle text-primary fs-11">${c.type}</span></td>
                <td><span class="font-monospace text-muted fs-12">${new Intl.NumberFormat('ar-EG', {style:'currency', currency:'EGP'}).format(totalPurchases)}</span></td>
                <td><span class="badge bg-danger text-white fs-13 font-monospace px-2 py-1 shadow-sm">${new Intl.NumberFormat('ar-EG', {style:'currency', currency:'EGP'}).format(balance)}</span></td>
                <td><span class="badge bg-warning-subtle text-warning fs-11 fw-bold"><i class="ri-time-line me-1"></i>آجل مستحق السداد</span></td>
                <td class="text-center no-print"><div class="d-inline-flex gap-1"><button type="button" class="btn btn-sm btn-success fw-bold" onclick="openPaymentModal('${c.id}')"><i class="ri-hand-coin-line me-1"></i> تحصيل دفعة</button><button type="button" class="btn btn-sm btn-soft-primary" onclick="openStatementModal('${c.id}')" title="كشف الفواتير"><i class="ri-file-text-line"></i> الفواتير</button></div></td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function renderCreditTable() {
    if (!window.AlHusseiniSales) return;

    const summary = window.AlHusseiniSales.getCreditSummary();
    const searchVal = (document.getElementById('searchCreditInput')?.value || '').trim().toLowerCase();
    const typeVal = document.getElementById('filterCustType')?.value || 'all';

    const tbody = document.getElementById('tbodyCreditCustomers');
    if (!tbody) return;

    const filtered = summary.creditCustomers.filter(c => {
        if (typeVal !== 'all' && c.type !== typeVal) return false;
        if (searchVal) {
            const matchName = c.name.toLowerCase().includes(searchVal);
            const matchPhone = c.phone.toLowerCase().includes(searchVal);
            const matchCar = c.carModel.toLowerCase().includes(searchVal);
            const matchPlate = c.carPlate.toLowerCase().includes(searchVal);
            if (!matchName && !matchPhone && !matchCar && !matchPlate) return false;
        }
        return true;
    });

    document.getElementById('visibleCreditCount').textContent = filtered.length;

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted fs-13"><i class="ri-checkbox-circle-line fs-24 text-success d-block mb-1"></i>لا توجد مديونيات آجلة مطابقة لبحثك! كل الحسابات خالصة.</td></tr>`;
        return;
    }

    let html = '';
    filtered.forEach(c => {
        const balance = Number(c.creditBalance) || 0;
        const totalPurchases = Number(c.totalPurchases) || 0;

        html += `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-xs me-2">
                            <span class="avatar-title rounded-circle bg-warning-subtle text-warning fw-bold fs-13">
                                ${c.name.charAt(0)}
                            </span>
                        </div>
                        <div>
                            <h6 class="fs-13 mb-0 fw-bold text-dark">${c.name}</h6>
                            <small class="text-muted font-monospace">${c.id}</small>
                        </div>
                    </div>
                </td>
                <td><span class="font-monospace fw-semibold text-dark fs-12">${c.phone}</span></td>
                <td>
                    <span class="fw-bold fs-12 text-dark d-block">${c.carModel}</span>
                    <span class="badge bg-light text-secondary border font-monospace fs-11">${c.carPlate}</span>
                </td>
                <td><span class="badge bg-primary-subtle text-primary fs-11">${c.type}</span></td>
                <td><span class="font-monospace text-muted fs-12">${window.AlHusseiniSales.formatCurrency(totalPurchases)}</span></td>
                <td>
                    <span class="badge bg-danger text-white fs-13 font-monospace px-2 py-1 shadow-sm">
                        ${window.AlHusseiniSales.formatCurrency(balance)}
                    </span>
                </td>
                <td>
                    <span class="badge bg-warning-subtle text-warning fs-11 fw-bold">
                        <i class="ri-time-line me-1"></i>آجل مستحق السداد
                    </span>
                </td>
                <td class="text-center no-print">
                    <div class="d-inline-flex gap-1">
                        <button type="button" class="btn btn-sm btn-success fw-bold" onclick="openPaymentModal('${c.id}')">
                            <i class="ri-hand-coin-line me-1"></i> تحصيل دفعة
                        </button>
                        <button type="button" class="btn btn-sm btn-soft-primary" onclick="openStatementModal('${c.id}')" title="كشف الفواتير">
                            <i class="ri-file-text-line"></i> الفواتير
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function renderPaymentsTable() {
    if (!window.AlHusseiniSales) return;
    const payments = window.AlHusseiniSales.getCreditPayments();
    const tbody = document.getElementById('tbodyCreditPayments');
    if (!tbody) return;

    if (payments.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted fs-13">لا توجد سندات تحصيل مسجلة بعد</td></tr>`;
        return;
    }

    const payLabels = { 'cash': 'نقداً بالخزينة', 'instapay': 'تحويل إنستاباي', 'vodafone_cash': 'فودافون كاش', 'bank': 'إيداع بنكي' };

    let html = '';
    payments.forEach(p => {
        html += `
            <tr>
                <td><span class="badge bg-light text-dark border font-monospace fs-12 fw-bold">${p.receiptNo || p.id}</span></td>
                <td><strong class="text-dark fs-13">${p.customerName}</strong></td>
                <td><span class="text-muted fs-12">${p.date} (${p.time || ''})</span></td>
                <td><span class="fw-bold text-success font-monospace fs-13">+ ${window.AlHusseiniSales.formatCurrency(p.amount)}</span></td>
                <td><span class="badge bg-success-subtle text-success fs-11">${payLabels[p.method] || p.method}</span></td>
                <td><span class="text-dark fs-12">${p.receivedBy || 'الحاج محمود الحسيني'}</span></td>
                <td><span class="text-muted fs-12">${p.notes || '-'}</span></td>
                <td class="no-print"><span class="badge bg-success text-white fs-10"><i class="ri-check-line me-1"></i>تم التوريد</span></td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function openPaymentModal(custId) {
    let cust = null;
    // Try server data first
    if (window.serverCreditData && Array.isArray(window.serverCreditData.customers)) {
        const c = window.serverCreditData.customers.find(x => String(x.id) === String(custId));
        if (c) {
            cust = {
                id: c.id,
                name: c.name,
                phone: c.phone,
                carModel: (c.vehicles && c.vehicles[0]) ? (c.vehicles[0].car_brand + ' ' + c.vehicles[0].car_model) : '',
                carPlate: (c.vehicles && c.vehicles[0]) ? c.vehicles[0].plate_number : '',
                creditBalance: parseFloat(c.current_credit_balance),
            };
        }
    }
    if (!cust && window.AlHusseiniSales) cust = window.AlHusseiniSales.getCustomerById(custId);
    if (!cust) return;
    currentTargetCustomer = cust;

    document.getElementById('payCustId').value = cust.id;
    document.getElementById('payCustName').textContent = cust.name;
    document.getElementById('payCustCarInfo').textContent = `${cust.carModel} — لوحة: ${cust.carPlate} | هاتف: ${cust.phone}`;
    const fmt = window.AlHusseiniSales ? window.AlHusseiniSales.formatCurrency : (v => new Intl.NumberFormat('ar-EG', {style:'currency', currency:'EGP'}).format(v));
    document.getElementById('payCustBalanceBadge').textContent = `الآجل المستحق: ${fmt(cust.creditBalance)}`;

    // Default payment amount
    const defaultAmount = Math.min(cust.creditBalance, 500);
    document.getElementById('payAmountInput').value = defaultAmount;

    const modal = new bootstrap.Modal(document.getElementById('recordPaymentModal'));
    modal.show();
}

function setPayAmount(amount) {
    if (!currentTargetCustomer) return;
    const finalAmt = Math.min(currentTargetCustomer.creditBalance, amount);
    document.getElementById('payAmountInput').value = finalAmt;
}

function setPayFullAmount() {
    if (!currentTargetCustomer) return;
    document.getElementById('payAmountInput').value = currentTargetCustomer.creditBalance;
}

function submitCreditPayment(e) {
    e.preventDefault();
    const custId = document.getElementById('payCustId').value;
    const amount = Number(document.getElementById('payAmountInput').value);
    const method = document.getElementById('payMethodSelect').value;
    const receivedBy = document.getElementById('payReceiverSelect').value;
    const notes = document.getElementById('payNotesInput').value.trim();

    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جاري التسجيل...';
    }

    const numericCustomerId = !isNaN(parseInt(custId)) ? parseInt(custId) : null;

    // Send payment to backend
    fetch("{{ route('admin.credit.settle') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': (typeof window.getCsrfToken === 'function' ? window.getCsrfToken() : '{{ csrf_token() }}')
        },
        body: JSON.stringify({
            customer_id: numericCustomerId,
            amount: amount,
            payment_method: method === 'instapay' ? 'bank_transfer' : method,
            notes: notes
        })
    })
    .then(async res => {
        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'فشل تسجيل التحصيل من الخادم.');
        }
        return data;
    })
    .then(data => {
        // Sync local mock storage if available
        if (window.AlHusseiniSales && window.AlHusseiniSales.recordCreditPayment) {
            window.AlHusseiniSales.recordCreditPayment({
                customerId: custId,
                amount: amount,
                method: method,
                receivedBy: receivedBy,
                notes: notes
            });
        }

        const modalEl = document.getElementById('recordPaymentModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        Swal.fire({
            icon: 'success',
            title: 'تم تسجيل سند التحصيل بنجاح!',
            html: `
                <div class="p-2 mb-2 bg-light rounded text-center">
                    <strong class="text-success font-monospace fs-16">${data.message}</strong>
                    ${data.receipt_number ? `<div class="text-muted fs-12 mt-1">رقم سند القبض: <span class="font-monospace fw-bold">${data.receipt_number}</span></div>` : ''}
                    <div class="text-dark fs-13 mt-1">الرصيد المتبقي الجديد: <strong class="font-monospace text-danger">${Number(data.new_balance || 0).toLocaleString('ar-EG')} ج.م</strong></div>
                </div>
            `,
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#198754'
        }).then(() => {
            renderCreditDashboard();
        });
    })
    .catch(err => {
        console.warn('API Credit Settlement fallback:', err);
        // Fallback to local if offline or mock-only customer
        if (window.AlHusseiniSales && window.AlHusseiniSales.recordCreditPayment) {
            const result = window.AlHusseiniSales.recordCreditPayment({
                customerId: custId,
                amount: amount,
                method: method,
                receivedBy: receivedBy,
                notes: notes
            });

            if (result.success) {
                const modalEl = document.getElementById('recordPaymentModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                Swal.fire({
                    icon: 'success',
                    title: 'تم تسجيل سند التحصيل بنجاح!',
                    html: `تم تحصيل مبلغ <strong>${amount} ج.م</strong> وتحديث الرصيد فورياً.`,
                    confirmButtonText: 'ممتاز',
                    confirmButtonColor: '#198754'
                });

                renderCreditDashboard();
                return;
            }
        }
        Swal.fire('خطأ في التحصيل', err.message || 'حدث خطأ أثناء السداد', 'error');
    })
    .finally(() => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });
}

function openStatementModal(custId) {
    // Try server data first
    let cust = null;
    let isServer = false;
    if (window.serverCreditData && Array.isArray(window.serverCreditData.customers)) {
        const c = window.serverCreditData.customers.find(x => String(x.id) === String(custId));
        if (c) {
            cust = {
                id: c.id,
                name: c.name,
                phone: c.phone,
                carModel: (c.vehicles && c.vehicles[0]) ? (c.vehicles[0].car_brand + ' ' + c.vehicles[0].car_model) : '',
                carPlate: (c.vehicles && c.vehicles[0]) ? c.vehicles[0].plate_number : '',
                creditBalance: parseFloat(c.current_credit_balance),
            };
            isServer = true;
        }
    }
    if (!cust && window.AlHusseiniSales) cust = window.AlHusseiniSales.getCustomerById(custId);
    if (!cust) return;

    const modalEl = document.getElementById('customerStatementModal');
    const modalBody = document.getElementById('statementModalBody');
    document.getElementById('statementModalTitle').textContent = `كشف حساب آجل: ${cust.name}`;

    if (isServer && !isNaN(parseInt(custId))) {
        // Fetch real statement from server
        modalBody.innerHTML = `<div class="text-center py-4"><span class="spinner-border text-primary"></span><p class="text-muted fs-13 mt-2">جاري تحميل كشف الحساب من الخادم...</p></div>`;
        new bootstrap.Modal(modalEl).show();
        fetch(`/admin/credit/${custId}/statement`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': (typeof window.getCsrfToken === 'function' ? window.getCsrfToken() : document.querySelector('meta[name="csrf-token"]')?.content || '') }
        })
        .then(r => r.ok ? r.json() : Promise.reject('fail'))
        .then(data => {
            const invs = data.invoices?.data || data.invoices || [];
            const leds = data.credit_ledgers?.data || data.credit_ledgers || [];
            // Invoices may be paginated object or array
            const invArray = Array.isArray(invs) ? invs : (invs.data || []);
            const ledArray = Array.isArray(leds) ? leds : (leds.data || []);
            modalBody.innerHTML = `
                <div class="p-3 bg-light rounded border mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div><h5 class="fw-bold mb-1 text-dark">${cust.name}</h5><p class="text-muted mb-0 fs-12">${cust.carModel} - لوحة: <strong>${cust.carPlate}</strong> | هاتف: ${cust.phone}</p></div>
                        <div class="text-end"><span class="fs-12 text-muted d-block">الرصيد المتبقي على الآجل:</span><h4 class="text-danger fw-extrabold mb-0 font-monospace">${new Intl.NumberFormat('ar-EG', {style:'currency', currency:'EGP'}).format(cust.creditBalance)}</h4><a href="/admin/credit/${custId}/statement" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i class="ri-file-list-3-line me-1"></i> كشف الحساب التفصيلي المعتمد</a></div>
                    </div>
                </div>
                <h6 class="fw-bold fs-13 text-dark mb-2"><i class="ri-bill-line me-1 text-primary"></i> فواتير المبيعات الصادرة للعميل:</h6>
                <div class="table-responsive mb-3"><table class="table table-sm table-bordered fs-12 mb-0"><thead class="table-light"><tr><th>رقم الفاتورة</th><th>التاريخ</th><th>إجمالي الفاتورة</th><th>المدفوع</th><th>المتبقي بالآجل</th><th>الحالة</th></tr></thead><tbody>
                    ${invArray.length ? invArray.map(inv => `
                        <tr><td class="font-monospace fw-bold">${inv.invoice_number}</td><td>${(inv.created_at||'').substring(0,10)}</td><td class="font-monospace">${Number(inv.final_amount).toLocaleString('ar-EG')} ج.م</td><td class="font-monospace text-success">${Number(inv.paid_amount).toLocaleString('ar-EG')} ج.م</td><td class="font-monospace text-danger fw-bold">${Number(inv.remaining_amount).toLocaleString('ar-EG')} ج.م</td><td><span class="badge ${Number(inv.remaining_amount)>0?'bg-warning-subtle text-warning':'bg-success-subtle text-success'}">${inv.status}</span></td></tr>
                    `).join('') : `<tr><td colspan="6" class="text-center py-2 text-muted">لا توجد فواتير آجل مفتوحة</td></tr>`}
                </tbody></table></div>
                <h6 class="fw-bold fs-13 text-dark mb-2"><i class="ri-history-line me-1 text-success"></i> سندات ودفعات التحصيل المسددة:</h6>
                <div class="table-responsive"><table class="table table-sm table-bordered fs-12 mb-0"><thead class="table-light"><tr><th>رقم الإيصال</th><th>التاريخ</th><th>المبلغ المحصل</th><th>طريقة السداد</th></tr></thead><tbody>
                    ${ledArray.length ? ledArray.map(l => `
                        <tr><td class="font-monospace">${l.receipt_number||'-'}</td><td>${(l.created_at||'').substring(0,10)}</td><td class="text-success font-monospace fw-bold">+ ${Number(l.amount).toLocaleString('ar-EG')} ج.م</td><td>${l.entry_type}</td></tr>
                    `).join('') : `<tr><td colspan="4" class="text-center py-2 text-muted">لا توجد سندات سابقة</td></tr>`}
                </tbody></table></div>
            `;
        })
        .catch(() => {
            modalBody.innerHTML = `<div class="text-center py-4 text-muted">تعذر تحميل كشف الحساب. <a href="/admin/credit/${custId}/statement" target="_blank">فتح الكشف الكامل</a></div>`;
        });
        return;
    }

    const invoices = window.AlHusseiniSales.getInvoices().filter(i => String(i.customerId) === String(custId));
    const payments = window.AlHusseiniSales.getCreditPayments().filter(p => String(p.customerId) === String(custId));

    document.getElementById('statementModalTitle').textContent = `كشف حساب آجل: ${cust.name}`;

    modalBody.innerHTML = `
        <div class="p-3 bg-light rounded border mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1 text-dark">${cust.name}</h5>
                    <p class="text-muted mb-0 fs-12">${cust.carModel} - لوحة: <strong>${cust.carPlate}</strong> | هاتف: ${cust.phone}</p>
                </div>
                <div class="text-end">
                    <span class="fs-12 text-muted d-block">الرصيد المتبقي على الآجل:</span>
                    <h4 class="text-danger fw-extrabold mb-0 font-monospace">${window.AlHusseiniSales.formatCurrency(cust.creditBalance)}</h4>
                    ${!isNaN(parseInt(custId)) ? `<a href="/admin/credit/${custId}/statement" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i class="ri-file-list-3-line me-1"></i> كشف الحساب التفصيلي المعتمد</a>` : ''}
                </div>
            </div>
        </div>

        <h6 class="fw-bold fs-13 text-dark mb-2"><i class="ri-bill-line me-1 text-primary"></i> فواتير المبيعات الصادرة للعميل:</h6>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered fs-12 mb-0">
                <thead class="table-light">
                    <tr><th>رقم الفاتورة</th><th>التاريخ</th><th>البطارية</th><th>إجمالي الفاتورة</th><th>المدفوع</th><th>المتبقي بالآجل</th><th>الحالة</th></tr>
                </thead>
                <tbody>
                    ${invoices.map(inv => `
                        <tr>
                            <td class="font-monospace fw-bold">${inv.invoiceNo}</td>
                            <td>${inv.date}</td>
                            <td>${inv.items[0]?.name || 'بطارية'}</td>
                            <td class="font-monospace">${inv.totalAmount} ج.م</td>
                            <td class="font-monospace text-success">${inv.paidAmount} ج.م</td>
                            <td class="font-monospace text-danger fw-bold">${inv.remainingCredit} ج.م</td>
                            <td><span class="badge ${inv.remainingCredit > 0 ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success'}">${inv.remainingCredit > 0 ? 'آجل معلق' : 'مسدد بالكامل'}</span></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>

        <h6 class="fw-bold fs-13 text-dark mb-2"><i class="ri-history-line me-1 text-success"></i> سندات ودفعات التحصيل المسددة:</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered fs-12 mb-0">
                <thead class="table-light">
                    <tr><th>رقم الإيصال</th><th>التاريخ</th><th>المبلغ المحصل</th><th>طريقة السداد</th><th>المستلم</th></tr>
                </thead>
                <tbody>
                    ${payments.length > 0 ? payments.map(p => `
                        <tr>
                            <td class="font-monospace">${p.receiptNo || p.id}</td>
                            <td>${p.date}</td>
                            <td class="text-success font-monospace fw-bold">+ ${p.amount} ج.م</td>
                            <td>${p.method}</td>
                            <td>${p.receivedBy}</td>
                        </tr>
                    `).join('') : `<tr><td colspan="5" class="text-center py-2 text-muted">لا توجد سندات سابقة</td></tr>`}
                </tbody>
            </table>
        </div>
    `;

    const modal = new bootstrap.Modal(document.getElementById('customerStatementModal'));
    modal.show();
}

function exportCreditCSV() {
    if (!window.AlHusseiniSales) return;
    const summary = window.AlHusseiniSales.getCreditSummary();
    const filename = `كشف_حسابات_الآجل_مركز_الحسيني_${new Date().toISOString().split('T')[0]}`;

    const headers = ['كود العميل', 'اسم العميل', 'رقم الهاتف', 'السيارة', 'رقم اللوحة', 'تصنيف العميل', 'إجمالي المشتريات (ج.م)', 'رصيد الآجل المتبقي (ج.م)'];
    const rows = summary.creditCustomers.map(c => [
        c.id,
        c.name,
        c.phone,
        c.carModel,
        c.carPlate,
        c.type,
        c.totalPurchases,
        c.creditBalance
    ]);

    window.AlHusseiniSales.exportToCSV(filename, headers, rows);
}
</script>
@endsection
