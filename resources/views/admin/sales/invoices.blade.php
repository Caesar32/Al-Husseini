@extends('admin.layouts.master')

@section('title', 'سجل فواتير المبيعات والضمان | مركز الحسيني لبطاريات السيارات')

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'المبيعات', 'title' => 'سجل فواتير المبيعات وشهادات الضمان'])

    <!-- Top Stats -->
    <div class="row mb-3">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate border-start border-primary border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">إجمالي قيمة المبيعات</p>
                            <h3 class="fs-22 fw-extrabold text-primary mb-1 font-monospace" id="statTotalSales">0 ج.م</h3>
                            <small class="text-muted fs-11">صافي الفواتير الصادرة</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20">
                                <i class="ri-money-dollar-circle-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate border-start border-success border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">عدد الفواتير الصادرة</p>
                            <h3 class="fs-22 fw-extrabold text-success mb-1 font-monospace" id="statInvoicesCount">0</h3>
                            <small class="text-muted fs-11">معتمدة مع شهادات الضمان</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-20">
                                <i class="ri-file-list-3-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate border-start border-warning border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">فواتير بالآجل (أقساط/متبقي)</p>
                            <h3 class="fs-22 fw-extrabold text-warning mb-1 font-monospace" id="statCreditInvoicesCount">0</h3>
                            <small class="text-danger fw-bold fs-11" id="statCreditRemaining">متبقي: 0 ج.م</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-20">
                                <i class="ri-hand-coin-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate border-start border-info border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">بطاريات مسترجعة (كهنة)</p>
                            <h3 class="fs-22 fw-extrabold text-info mb-1 font-monospace" id="statScrapCount">0 بطارية</h3>
                            <small class="text-muted fs-11">تم استبدالها بالمخزن</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-info-subtle text-info rounded-circle fs-20">
                                <i class="ri-recycle-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Invoices Table Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom p-3">
                    <div class="row g-2 align-items-center justify-content-between">
                        <!-- Filter Tabs -->
                        <div class="col-lg-6 col-12">
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-primary fw-bold px-3" id="tab-inv-all" onclick="filterInvoices('all')">جميع الفواتير</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="tab-inv-credit" onclick="filterInvoices('credit')">فواتير الآجل فقط ⏱️</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="tab-inv-paid" onclick="filterInvoices('paid')">فواتير خالصة ومسددة ✅</button>
                            </div>
                        </div>

                        <!-- Search & Export -->
                        <div class="col-lg-6 col-12 text-lg-end">
                            <div class="d-flex gap-2 justify-content-lg-end">
                                <div class="input-group input-group-sm" style="max-width: 250px;">
                                    <span class="input-group-text bg-light"><i class="ri-search-line"></i></span>
                                    <input type="text" class="form-control" id="searchInvoiceInput" placeholder="بحث برقم الفاتورة أو العميل..." oninput="renderInvoicesTable()">
                                </div>
                                <a href="{{ route('admin.sales.pos') }}" class="btn btn-sm btn-success fw-bold">
                                    <i class="ri-add-circle-line me-1"></i> فاتورة جديدة (POS)
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportInvoicesCSV()">
                                    <i class="ri-file-excel-2-line me-1"></i> Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-nowrap mb-0" id="invoicesTable">
                            <thead class="table-light">
                                <tr class="text-muted fs-12 text-uppercase">
                                    <th>رقم الفاتورة</th>
                                    <th>التاريخ والوقت</th>
                                    <th>العميل ورقم الهاتف</th>
                                    <th>السيارة ورقم اللوحة</th>
                                    <th>البطارية المباعة</th>
                                    <th>استبدال قديمة</th>
                                    <th>إجمالي الفاتورة</th>
                                    <th>طريقة الدفع</th>
                                    <th>حالة السداد</th>
                                    <th class="text-center" style="min-width: 120px;">معاينة وطباعة</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyInvoices">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: View & Print Invoice / Warranty -->
    <div class="modal fade" id="invoiceDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white p-3">
                    <h5 class="modal-title fw-bold text-white fs-15">
                        <i class="ri-file-list-3-line me-1"></i> تفاصيل الفاتورة والضمان المعتمد
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="invoiceDetailModalBody">
                    <!-- Dynamic details -->
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-primary fw-bold" onclick="window.print()">
                        <i class="ri-printer-line me-1"></i> طباعة الفاتورة
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
<script>
'use strict';

let currentInvoiceFilter = 'all';

document.addEventListener('DOMContentLoaded', function () {
    renderInvoicesDashboard();

    window.addEventListener('alhusseini-sales-updated', function () {
        renderInvoicesDashboard();
    });
});

function renderInvoicesDashboard() {
    if (!window.AlHusseiniSales) return;

    const invoices = window.AlHusseiniSales.getInvoices();

    const totalSales = invoices.reduce((sum, i) => sum + Number(i.totalAmount || 0), 0);
    const creditInvoices = invoices.filter(i => (Number(i.remainingCredit) || 0) > 0);
    const totalRemaining = creditInvoices.reduce((sum, i) => sum + Number(i.remainingCredit), 0);
    const scrapCount = invoices.filter(i => i.items?.some(it => it.hasTradeIn)).length;

    document.getElementById('statTotalSales').textContent = window.AlHusseiniSales.formatCurrency(totalSales);
    document.getElementById('statInvoicesCount').textContent = `${invoices.length} فاتورة`;
    document.getElementById('statCreditInvoicesCount').textContent = `${creditInvoices.length} فاتورة آجل`;
    document.getElementById('statCreditRemaining').textContent = `متبقي آجل: ${window.AlHusseiniSales.formatCurrency(totalRemaining)}`;
    document.getElementById('statScrapCount').textContent = `${scrapCount} قديمة`;

    renderInvoicesTable();
}

function filterInvoices(filter) {
    currentInvoiceFilter = filter;

    ['all', 'credit', 'paid'].forEach(f => {
        const btn = document.getElementById(`tab-inv-${f}`);
        if (btn) {
            if (f === filter) btn.className = 'btn btn-sm btn-primary fw-bold px-3';
            else btn.className = 'btn btn-sm btn-outline-secondary';
        }
    });

    renderInvoicesTable();
}

function renderInvoicesTable() {
    if (!window.AlHusseiniSales) return;

    const invoices = window.AlHusseiniSales.getInvoices();
    const searchVal = (document.getElementById('searchInvoiceInput')?.value || '').trim().toLowerCase();
    const tbody = document.getElementById('tbodyInvoices');
    if (!tbody) return;

    const filtered = invoices.filter(inv => {
        if (currentInvoiceFilter === 'credit' && (Number(inv.remainingCredit) || 0) === 0) return false;
        if (currentInvoiceFilter === 'paid' && (Number(inv.remainingCredit) || 0) > 0) return false;

        if (searchVal) {
            const matchNo = inv.invoiceNo.toLowerCase().includes(searchVal);
            const matchName = inv.customerName.toLowerCase().includes(searchVal);
            const matchCar = (inv.carPlate || '').toLowerCase().includes(searchVal);
            const matchBattery = (inv.items[0]?.name || '').toLowerCase().includes(searchVal);
            if (!matchNo && !matchName && !matchCar && !matchBattery) return false;
        }
        return true;
    });

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted fs-13">لا توجد فواتير مطابقة للبحث</td></tr>`;
        return;
    }

    const payLabels = {
        'cash': '<span class="badge bg-success-subtle text-success fs-11">نقدي (كاش)</span>',
        'instapay': '<span class="badge bg-primary-subtle text-primary fs-11">إنستاباي</span>',
        'card': '<span class="badge bg-info-subtle text-info fs-11">بطاقة بنكية</span>',
        'credit': '<span class="badge bg-warning text-dark fs-11 fw-bold">الآجل ⏱️</span>'
    };

    let html = '';
    filtered.forEach(inv => {
        const item = inv.items[0] || {};
        const hasTradeInBadge = item.hasTradeIn ? 
            `<span class="badge bg-success-subtle text-success fs-11"><i class="ri-check-line"></i> استبدال (${item.scrapDiscount} ج.م)</span>` : 
            '<span class="text-muted fs-11">بدون قديمة</span>';

        let statusBadge = '';
        if (inv.remainingCredit > 0) {
            statusBadge = `<span class="badge bg-danger-subtle text-danger fs-11 fw-bold">متبقي آجل: ${inv.remainingCredit} ج.م</span>`;
        } else {
            statusBadge = `<span class="badge bg-success-subtle text-success fs-11"><i class="ri-checkbox-circle-line me-1"></i>خالص ومسدد</span>`;
        }

        html += `
            <tr>
                <td><span class="badge bg-light text-dark border font-monospace fw-bold fs-12">${inv.invoiceNo}</span></td>
                <td><span class="text-muted fs-12">${inv.date} <small class="text-muted font-monospace">${inv.time || ''}</small></span></td>
                <td>
                    <strong class="text-dark fs-13 d-block">${inv.customerName}</strong>
                    <small class="text-muted font-monospace">${inv.customerPhone || ''}</small>
                </td>
                <td>
                    <span class="fs-12 fw-semibold text-dark d-block">${inv.carModel || '-'}</span>
                    <span class="badge bg-light text-secondary font-monospace fs-11">${inv.carPlate || '-'}</span>
                </td>
                <td>
                    <strong class="fs-12 text-dark d-block">${item.name || '-'}</strong>
                    <span class="badge bg-primary-subtle text-primary fs-10 font-monospace">${item.amp || ''}</span>
                </td>
                <td>${hasTradeInBadge}</td>
                <td><strong class="text-success font-monospace fs-13">${window.AlHusseiniSales.formatCurrency(inv.totalAmount)}</strong></td>
                <td>${payLabels[inv.paymentMethod] || inv.paymentMethod}</td>
                <td>${statusBadge}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-soft-primary" onclick="viewInvoiceModal('${inv.id}')">
                        <i class="ri-eye-line align-middle me-1"></i> الفاتورة
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function viewInvoiceModal(invId) {
    const inv = window.AlHusseiniSales.getInvoiceById(invId);
    if (!inv) return;

    const modalBody = document.getElementById('invoiceDetailModalBody');
    const payLabels = { 'cash': 'نقدي (كاش)', 'instapay': 'إنستاباي / محفظة', 'card': 'فيزا / بنك', 'credit': 'الآجل (أقساط/مستحق)' };

    modalBody.innerHTML = `
        <div class="border p-3 rounded bg-light mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h5 class="fw-bold mb-0 text-dark">مركز الحسيني لبطاريات السيارات</h5>
                    <small class="text-muted">فاتورة رقم: <strong class="font-monospace text-primary">${inv.invoiceNo}</strong></small>
                </div>
                <div class="text-end">
                    <span class="badge bg-primary fs-12">التاريخ: ${inv.date}</span>
                </div>
            </div>
            <div class="row g-2 border-top pt-2 fs-12">
                <div class="col-6"><span class="text-muted">العميل:</span> <strong class="text-dark">${inv.customerName}</strong></div>
                <div class="col-6"><span class="text-muted">الهاتف:</span> <strong class="font-monospace">${inv.customerPhone}</strong></div>
                <div class="col-6"><span class="text-muted">السيارة:</span> <strong class="text-dark">${inv.carModel}</strong></div>
                <div class="col-6"><span class="text-muted">اللوحة:</span> <strong class="font-monospace text-dark">${inv.carPlate}</strong></div>
            </div>
        </div>

        <table class="table table-bordered table-sm fs-12 mb-3">
            <thead class="table-light">
                <tr><th>البيان</th><th>الأمبير</th><th>السعر</th><th>خصم القديمة</th><th>الصافي</th></tr>
            </thead>
            <tbody>
                ${inv.items.map(it => `
                    <tr>
                        <td><strong>${it.name}</strong><br><small class="text-muted">سيريال: ${inv.serialNumber}</small></td>
                        <td class="font-monospace">${it.amp}</td>
                        <td class="font-monospace">${it.unitPrice} ج.م</td>
                        <td class="font-monospace text-danger">${it.hasTradeIn ? `- ${it.scrapDiscount} ج.م` : '0 ج.م'}</td>
                        <td class="font-monospace fw-bold text-success">${it.finalPrice} ج.م</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>

        <div class="p-3 bg-light rounded border fs-13">
            <div class="d-flex justify-content-between mb-1">
                <span>طريقة الدفع:</span>
                <strong class="text-dark">${payLabels[inv.paymentMethod]}</strong>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span>المبلغ المدفوع:</span>
                <strong class="font-monospace text-success">${inv.paidAmount} ج.م</strong>
            </div>
            ${inv.remainingCredit > 0 ? `
                <div class="d-flex justify-content-between mb-1 text-danger">
                    <span>المتبقي على حساب الآجل:</span>
                    <strong class="font-monospace">${inv.remainingCredit} ج.م (استحقاق: ${inv.creditDueDate || '-'})</strong>
                </div>
            ` : ''}
            <div class="d-flex justify-content-between border-top pt-2 mt-2 fs-15">
                <strong class="text-dark">صافي الفاتورة الإجمالي:</strong>
                <strong class="text-success font-monospace">${inv.totalAmount} ج.م</strong>
            </div>
        </div>
        <div class="mt-2 text-muted fs-11 text-center">
            تاريخ انتهاء فترة الضمان المعتمد: <strong>${inv.warrantyExpiry}</strong>
        </div>
    `;

    const modal = new bootstrap.Modal(document.getElementById('invoiceDetailModal'));
    modal.show();
}

function exportInvoicesCSV() {
    if (!window.AlHusseiniSales) return;
    const invoices = window.AlHusseiniSales.getInvoices();
    const filename = `فواتير_مبيعات_مركز_الحسيني_${new Date().toISOString().split('T')[0]}`;

    const headers = ['رقم الفاتورة', 'التاريخ', 'اسم العميل', 'الهاتف', 'السيارة', 'اللوحة', 'البطارية', 'إجمالي الفاتورة (ج.م)', 'المدفوع', 'المتبقي بالآجل', 'طريقة الدفع'];
    const rows = invoices.map(i => [
        i.invoiceNo,
        i.date,
        i.customerName,
        i.customerPhone,
        i.carModel,
        i.carPlate,
        i.items[0]?.name || '',
        i.totalAmount,
        i.paidAmount,
        i.remainingCredit,
        i.paymentMethod
    ]);

    window.AlHusseiniSales.exportToCSV(filename, headers, rows);
}
</script>
@endsection
