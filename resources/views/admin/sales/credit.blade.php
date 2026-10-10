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
                                        <input type="text" class="form-control" id="searchCreditInput" placeholder="ابحث باسم العميل أو رقم التليفون أو رقم اللوحة..." oninput="renderCreditTable()">
                                    </div>
                                </div>
                                <div class="col-md-4 col-12">
                                    <select class="form-select" id="filterCustType" onchange="renderCreditTable()">
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

// Server data only (no browser mock store).
const CREDIT_DATA = {
    totalOutstanding: {{ number_format($totalOutstanding, 2, '.', '') }},
    customersCount: {{ (int) $customersCount }},
    exceededLimitCount: {{ (int) $exceededLimitCount }},
    collectedThisMonth: {{ number_format($collectedThisMonth, 2, '.', '') }},
    collectionsCount: {{ (int) $collectionsCount }},
    totalCustomers: {{ (int) $totalCustomers }},
    customers: @json($customers),
};
const CREDIT_ROUTES = {
    settle: @json(route('admin.credit.settle')),
    payments: @json(route('admin.credit.payments')),
    statement: @json(route('admin.credit.statement', ['customer' => '__ID__'])),
};
const TIER_LABELS = { standard: 'عادي', vip: 'مميز (VIP)', fleet: 'أساطيل وشركات' };

let currentTargetCustomer = null;
let paymentsPage = 1;

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

function fmt(amount) {
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(Number(amount) || 0) + ' ج.م';
}

/** Compact form for page-header KPI totals only (e.g. 26781945.84 -> "26.78 مليون ج.م");
 *  mirrors App\Support\MoneyHelper::formatCompactCurrency(). Table/row amounts keep using fmt(). */
function fmtCompact(amount) {
    const n = Number(amount) || 0;
    if (n >= 1e6) return (n / 1e6).toFixed(2) + ' مليون ج.م';
    if (n >= 1e3) return (n / 1e3).toFixed(1) + ' ألف ج.م';
    return Math.round(n).toLocaleString('ar-EG') + ' ج.م';
}

/** Initializes (once) or live-updates a Bootstrap tooltip's text without losing its instance. */
function initOrUpdateTooltip(el, title) {
    if (!el || typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
    el.setAttribute('title', title);
    el.setAttribute('data-bs-original-title', title);
    const existing = bootstrap.Tooltip.getInstance(el);
    if (existing) {
        existing.setContent({ '.tooltip-inner': title });
    } else {
        new bootstrap.Tooltip(el);
    }
}

function setKpiCompact(elId, amount) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.textContent = fmtCompact(amount);
    initOrUpdateTooltip(el, fmt(amount));
}

function csrf() {
    return typeof window.getCsrfToken === 'function'
        ? window.getCsrfToken()
        : (document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}');
}

function statementUrl(id) {
    return CREDIT_ROUTES.statement.replace('__ID__', encodeURIComponent(id));
}

function findCreditCustomer(id) {
    return CREDIT_DATA.customers.find(c => c.id === Number(id)) || null;
}

document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('printCreditDate');
    if (el) el.textContent = new Date().toLocaleDateString('ar-EG', { dateStyle: 'full' });

    renderCreditDashboard();
    loadPayments(1);
});

function renderCreditDashboard() {
    setKpiCompact('kpiTotalOutstanding', CREDIT_DATA.totalOutstanding);
    document.getElementById('kpiCreditCustomersCount').textContent = `${CREDIT_DATA.customersCount} عميل`;
    setKpiCompact('kpiCollectedThisMonth', CREDIT_DATA.collectedThisMonth);

    const ratio = CREDIT_DATA.totalCustomers > 0
        ? Math.round((CREDIT_DATA.customersCount / CREDIT_DATA.totalCustomers) * 100)
        : 0;
    document.getElementById('kpiCreditRatio').textContent = `${ratio}%`;
    document.getElementById('kpiTotalCustomersText').textContent = `من إجمالي ${CREDIT_DATA.totalCustomers} عميل مسجل`;

    document.getElementById('badgeCreditCustCount').textContent = CREDIT_DATA.customersCount;
    document.getElementById('badgeCreditPayCount').textContent = CREDIT_DATA.collectionsCount;

    renderCreditTable();
}

function renderCreditTable() {
    const searchVal = (document.getElementById('searchCreditInput')?.value || '').trim().toLowerCase();
    const tierVal = document.getElementById('filterCustType')?.value || 'all';
    const tbody = document.getElementById('tbodyCreditCustomers');
    if (!tbody) return;

    const filtered = CREDIT_DATA.customers.filter(c => {
        if (tierVal !== 'all' && c.tier !== tierVal) return false;
        if (!searchVal) return true;
        return [c.name, c.phone, c.vehicle?.car, c.vehicle?.plate_number]
            .some(v => v && String(v).toLowerCase().includes(searchVal));
    });

    document.getElementById('visibleCreditCount').textContent = filtered.length;

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted fs-13"><i class="ri-checkbox-circle-line fs-24 text-success d-block mb-1"></i>لا توجد مديونيات آجلة مطابقة لبحثك! كل الحسابات خالصة.</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map(c => {
        const exceeded = c.credit_limit > 0 && c.credit_balance > c.credit_limit;
        return `
            <tr>
                <td><div class="d-flex align-items-center"><div class="avatar-xs me-2"><span class="avatar-title rounded-circle bg-warning-subtle text-warning fw-bold fs-13">${esc(c.name.charAt(0))}</span></div><div><h6 class="fs-13 mb-0 fw-bold text-dark">${esc(c.name)}</h6><small class="text-muted font-monospace">#${c.id}</small></div></div></td>
                <td><span class="font-monospace fw-semibold text-dark fs-12">${esc(c.phone)}</span></td>
                <td>${c.vehicle ? `<span class="fw-bold fs-12 text-dark d-block">${esc(c.vehicle.car)}</span><span class="badge bg-light text-secondary border font-monospace fs-11">${esc(c.vehicle.plate_number)}</span>` : '<span class="text-muted fs-12">-</span>'}</td>
                <td><span class="badge bg-primary-subtle text-primary fs-11">${esc(TIER_LABELS[c.tier] || c.tier)}</span></td>
                <td><span class="font-monospace text-muted fs-12">${esc(fmt(c.purchases_total))}</span></td>
                <td><span class="badge bg-danger text-white fs-13 font-monospace px-2 py-1 shadow-sm">${esc(fmt(c.credit_balance))}</span></td>
                <td>${exceeded
                    ? '<span class="badge bg-danger-subtle text-danger fs-11 fw-bold"><i class="ri-error-warning-line me-1"></i>تجاوز سقف الائتمان</span>'
                    : '<span class="badge bg-warning-subtle text-warning fs-11 fw-bold"><i class="ri-time-line me-1"></i>آجل مستحق السداد</span>'}</td>
                <td class="text-center no-print"><div class="d-inline-flex gap-1">
                    <button type="button" class="btn btn-sm btn-success fw-bold" onclick="openPaymentModal(${c.id})"><i class="ri-hand-coin-line me-1"></i> تحصيل دفعة</button>
                    <button type="button" class="btn btn-sm btn-soft-primary" onclick="openStatementModal(${c.id})" title="كشف الفواتير"><i class="ri-file-text-line"></i> الفواتير</button>
                </div></td>
            </tr>
        `;
    }).join('');
}

async function loadPayments(page) {
    const tbody = document.getElementById('tbodyCreditPayments');
    const pager = document.getElementById('paymentsPager');
    if (!tbody) return;

    try {
        const url = new URL(CREDIT_ROUTES.payments, window.location.origin);
        url.searchParams.set('payments_page', page);
        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error();
        const data = await res.json();
        paymentsPage = data.meta.current_page;

        CREDIT_DATA.collectedThisMonth = data.collected_this_month;
        CREDIT_DATA.collectionsCount = data.meta.total;
        setKpiCompact('kpiCollectedThisMonth', data.collected_this_month);
        document.getElementById('badgeCreditPayCount').textContent = data.meta.total;

        if (data.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted fs-13">لا توجد سندات تحصيل مسجلة بعد.</td></tr>';
        } else {
            tbody.innerHTML = data.data.map(p => `
                <tr>
                    <td><span class="font-monospace fw-bold text-dark fs-12">${esc(p.receipt_number || '-')}</span></td>
                    <td>${p.customer ? `<a href="${esc(statementUrl(p.customer.id))}" target="_blank" class="fw-semibold">${esc(p.customer.name)}</a>` : '-'}</td>
                    <td><span class="font-monospace fs-12">${esc((p.created_at || '').substring(0, 16))}</span></td>
                    <td><span class="fw-bold text-success font-monospace fs-13">+ ${esc(fmt(p.amount))}</span></td>
                    <td><span class="fs-12">${esc(p.collected_by || '-')}</span></td>
                    <td><span class="text-muted fs-12">${esc(p.notes || '')}</span></td>
                </tr>
            `).join('');
        }

        if (pager) {
            pager.innerHTML = data.meta.last_page > 1 ? `
                <button type="button" class="btn btn-sm btn-light" ${paymentsPage <= 1 ? 'disabled' : ''} onclick="loadPayments(${paymentsPage - 1})">السابق</button>
                <span class="align-self-center fs-12 text-muted">صفحة ${paymentsPage} من ${data.meta.last_page}</span>
                <button type="button" class="btn btn-sm btn-light" ${paymentsPage >= data.meta.last_page ? 'disabled' : ''} onclick="loadPayments(${paymentsPage + 1})">التالي</button>
            ` : '';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger fs-13">تعذر تحميل سجل التحصيلات من الخادم.</td></tr>';
    }
}

function openPaymentModal(custId) {
    const cust = findCreditCustomer(custId);
    if (!cust) return;
    currentTargetCustomer = cust;

    document.getElementById('payCustId').value = cust.id;
    document.getElementById('payCustName').textContent = cust.name;
    document.getElementById('payCustCarInfo').textContent = cust.vehicle
        ? `${cust.vehicle.car} — لوحة: ${cust.vehicle.plate_number} | هاتف: ${cust.phone}`
        : `هاتف: ${cust.phone}`;
    document.getElementById('payCustBalanceBadge').textContent = `الآجل المستحق: ${fmt(cust.credit_balance)}`;
    document.getElementById('payAmountInput').value = Math.min(cust.credit_balance, 500);
    document.getElementById('payAmountInput').max = cust.credit_balance;
    document.getElementById('payReceiptInput').value = '';
    document.getElementById('payNotesInput').value = '';

    new bootstrap.Modal(document.getElementById('recordPaymentModal')).show();
}

function setPayAmount(amount) {
    if (!currentTargetCustomer) return;
    document.getElementById('payAmountInput').value = Math.min(currentTargetCustomer.credit_balance, amount);
}

function setPayFullAmount() {
    if (!currentTargetCustomer) return;
    document.getElementById('payAmountInput').value = currentTargetCustomer.credit_balance;
}

async function submitCreditPayment(e) {
    e.preventDefault();
    const custId = Number(document.getElementById('payCustId').value);
    const cust = findCreditCustomer(custId);
    if (!cust) {
        Swal.fire('خطأ في التحصيل', 'العميل غير موجود. أعد تحميل الصفحة.', 'error');
        return;
    }

    const body = {
        customer_id: cust.id,
        amount: Number(document.getElementById('payAmountInput').value),
        payment_method: document.getElementById('payMethodSelect').value,
        notes: document.getElementById('payNotesInput').value.trim() || null,
    };
    const receipt = document.getElementById('payReceiptInput').value.trim();
    if (receipt) body.receipt_number = receipt;

    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جاري التسجيل...';
    }

    try {
        const res = await fetch(CREDIT_ROUTES.settle, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(body)
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) {
            throw new Error(data.errors ? Object.values(data.errors).flat().join(' - ') : (data.message || 'فشل تسجيل التحصيل من الخادم.'));
        }

        // Reflect the server result (authoritative new balance).
        const newBalance = Number(data.new_balance || 0);
        CREDIT_DATA.totalOutstanding = Math.max(0, CREDIT_DATA.totalOutstanding - (cust.credit_balance - newBalance));
        cust.credit_balance = newBalance;
        if (newBalance <= 0) {
            CREDIT_DATA.customers = CREDIT_DATA.customers.filter(c => c.id !== cust.id);
            CREDIT_DATA.customersCount = CREDIT_DATA.customers.length;
        }

        bootstrap.Modal.getInstance(document.getElementById('recordPaymentModal'))?.hide();
        renderCreditDashboard();
        loadPayments(1);

        Swal.fire({
            icon: 'success',
            title: 'تم تسجيل سند التحصيل بنجاح!',
            html: `
                <div class="p-2 mb-2 bg-light rounded text-center">
                    <strong class="text-success fs-15">${esc(data.message || '')}</strong>
                    ${data.receipt_number ? `<div class="text-muted fs-12 mt-1">رقم سند القبض: <span class="font-monospace fw-bold">${esc(data.receipt_number)}</span></div>` : ''}
                    <div class="text-dark fs-13 mt-1">الرصيد المتبقي الجديد: <strong class="font-monospace text-danger">${esc(fmt(newBalance))}</strong></div>
                </div>
            `,
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#198754'
        });
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'خطأ في التحصيل', text: err.message || 'حدث خطأ أثناء السداد', confirmButtonText: 'موافق' });
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
        }
    }
}

async function openStatementModal(custId) {
    const cust = findCreditCustomer(custId);
    if (!cust) return;

    const modalEl = document.getElementById('customerStatementModal');
    const modalBody = document.getElementById('statementModalBody');
    document.getElementById('statementModalTitle').textContent = `كشف حساب آجل: ${cust.name}`;
    modalBody.innerHTML = '<div class="text-center py-4"><span class="spinner-border text-primary"></span><p class="text-muted fs-13 mt-2">جاري تحميل كشف الحساب من الخادم...</p></div>';
    new bootstrap.Modal(modalEl).show();

    const fullUrl = statementUrl(cust.id);
    try {
        const res = await fetch(fullUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error();
        const data = await res.json();
        const invoices = Array.isArray(data.invoices) ? data.invoices : (data.invoices?.data || []);
        const ledgers = Array.isArray(data.credit_ledgers) ? data.credit_ledgers : (data.credit_ledgers?.data || []);
        const vehicleText = cust.vehicle ? `${cust.vehicle.car} - لوحة: ${cust.vehicle.plate_number} | ` : '';

        modalBody.innerHTML = `
            <div class="p-3 bg-light rounded border mb-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div><h5 class="fw-bold mb-1 text-dark">${esc(cust.name)}</h5><p class="text-muted mb-0 fs-12">${esc(vehicleText)}هاتف: ${esc(cust.phone)}</p></div>
                    <div class="text-end"><span class="fs-12 text-muted d-block">الرصيد المتبقي على الآجل:</span><h4 class="text-danger fw-extrabold mb-0 font-monospace">${esc(fmt(cust.credit_balance))}</h4>
                        <a href="${esc(fullUrl)}" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i class="ri-file-list-3-line me-1"></i> كشف الحساب التفصيلي المعتمد</a></div>
                </div>
            </div>
            <h6 class="fw-bold fs-13 text-dark mb-2"><i class="ri-bill-line me-1 text-primary"></i> فواتير المبيعات المفتوحة للعميل:</h6>
            <div class="table-responsive mb-3"><table class="table table-sm table-bordered fs-12 mb-0"><thead class="table-light"><tr><th>رقم الفاتورة</th><th>التاريخ</th><th>إجمالي الفاتورة</th><th>المدفوع</th><th>المتبقي بالآجل</th><th>الحالة</th></tr></thead><tbody>
                ${invoices.length ? invoices.map(inv => `
                    <tr><td class="font-monospace fw-bold">${esc(inv.invoice_number)}</td><td>${esc((inv.created_at || '').substring(0, 10))}</td><td class="font-monospace">${esc(fmt(inv.final_amount))}</td><td class="font-monospace text-success">${esc(fmt(inv.paid_amount))}</td><td class="font-monospace text-danger fw-bold">${esc(fmt(inv.remaining_amount))}</td><td><span class="badge ${Number(inv.remaining_amount) > 0 ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success'}">${esc(inv.status)}</span></td></tr>
                `).join('') : '<tr><td colspan="6" class="text-center py-2 text-muted">لا توجد فواتير آجل مفتوحة</td></tr>'}
            </tbody></table></div>
            <h6 class="fw-bold fs-13 text-dark mb-2"><i class="ri-history-line me-1 text-success"></i> حركات حساب الآجل:</h6>
            <div class="table-responsive"><table class="table table-sm table-bordered fs-12 mb-0"><thead class="table-light"><tr><th>رقم الإيصال</th><th>التاريخ</th><th>المبلغ</th><th>نوع الحركة</th></tr></thead><tbody>
                ${ledgers.length ? ledgers.map(l => `
                    <tr><td class="font-monospace">${esc(l.receipt_number || '-')}</td><td>${esc((l.created_at || '').substring(0, 10))}</td><td class="font-monospace fw-bold">${esc(fmt(l.amount))}</td><td>${esc(l.entry_type)}</td></tr>
                `).join('') : '<tr><td colspan="4" class="text-center py-2 text-muted">لا توجد حركات سابقة</td></tr>'}
            </tbody></table></div>
        `;
    } catch (e) {
        modalBody.innerHTML = `<div class="text-center py-4 text-muted">تعذر تحميل كشف الحساب. <a href="${esc(fullUrl)}" target="_blank">فتح الكشف الكامل</a></div>`;
    }
}

function exportCreditCSV() {
    const headers = ['كود العميل', 'اسم العميل', 'رقم الهاتف', 'السيارة', 'رقم اللوحة', 'تصنيف العميل', 'إجمالي المشتريات (ج.م)', 'رصيد الآجل المتبقي (ج.م)'];
    const rows = CREDIT_DATA.customers.map(c => [
        c.id, c.name, c.phone, c.vehicle?.car || '', c.vehicle?.plate_number || '',
        TIER_LABELS[c.tier] || c.tier, c.purchases_total, c.credit_balance
    ]);
    // Quote every cell; prefix formula-leading values so spreadsheets do not execute them.
    const cell = v => {
        let s = String(v ?? '');
        if (/^[=+\-@]/.test(s)) s = "'" + s;
        return '"' + s.replace(/"/g, '""') + '"';
    };
    const csv = '﻿' + [headers, ...rows].map(r => r.map(cell).join(',')).join('\r\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `كشف_حسابات_الآجل_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(link.href);
}
</script>
@endsection
