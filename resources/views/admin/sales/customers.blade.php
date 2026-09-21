@extends('admin.layouts.master')

@section('title', 'دليل وسجل العملاء والسيارات | مركز الحسيني لبطاريات السيارات')

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'العملاء', 'title' => 'دليل وسجل العملاء والسيارات وتاريخ التعاملات'])

    <!-- Top Stats -->
    <div class="row mb-3">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate border-start border-primary border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">إجمالي العملاء المسجلين</p>
                            <h3 class="fs-22 fw-extrabold text-primary mb-1 font-monospace" id="custStatTotal">0 عميل</h3>
                            <small class="text-muted fs-11">أصحاب سيارات وورش وشركات</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20">
                                <i class="ri-user-star-line"></i>
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
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">عملاء لديهم حسابات آجل</p>
                            <h3 class="fs-22 fw-extrabold text-warning mb-1 font-monospace" id="custStatCreditCount">0 عميل</h3>
                            <small class="text-danger fw-bold fs-11" id="custStatCreditAmount">إجمالي: 0 ج.م</small>
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
            <div class="card card-animate border-start border-success border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">عملاء سيارات ملاكي خاصة</p>
                            <h3 class="fs-22 fw-extrabold text-success mb-1 font-monospace" id="custStatPrivate">0</h3>
                            <small class="text-muted fs-11">أفراد وأسر</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-20">
                                <i class="ri-car-line"></i>
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
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">ورش صيانة وتجاري ونقل</p>
                            <h3 class="fs-22 fw-extrabold text-info mb-1 font-monospace" id="custStatCommercial">0</h3>
                            <small class="text-muted fs-11">ورش وتريلات وأوبر</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-info-subtle text-info rounded-circle fs-20">
                                <i class="ri-truck-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Customers Table Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom p-3">
                    <div class="row g-2 align-items-center justify-content-between">
                        <div class="col-md-4 col-12">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="ri-search-line"></i></span>
                                <input type="text" class="form-control" id="searchCustomerInput" placeholder="ابحث باسم العميل، الهاتف، أو اللوحة..." oninput="renderCustomersTable()">
                            </div>
                        </div>
                        <div class="col-md-3 col-12">
                            <select class="form-select form-select-sm" id="filterCustomerType" onchange="renderCustomersTable()">
                                <option value="all">جميع تصنيفات العملاء</option>
                                <option value="ملاكي">ملاكي</option>
                                <option value="ورش وشركات">ورش وشركات</option>
                                <option value="تاكسي وأوبر">تاكسي وأوبر</option>
                                <option value="نقل وتريلات">نقل وتريلات</option>
                            </select>
                        </div>
                        <div class="col-md-5 col-12 text-md-end">
                            <button type="button" class="btn btn-sm btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="openNewCustomerModal()">
                                <i class="ri-user-add-line me-1"></i> إضافة عميل جديد
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-nowrap mb-0" id="customersTable">
                            <thead class="table-light">
                                <tr class="text-muted fs-12 text-uppercase">
                                    <th>كود العميل</th>
                                    <th>الاسم والبيانات</th>
                                    <th>رقم الهاتف المحمول</th>
                                    <th>السيارة والموديل</th>
                                    <th>رقم اللوحة</th>
                                    <th>التصنيف</th>
                                    <th>إجمالي المشتريات</th>
                                    <th>رصيد الآجل المتبقي</th>
                                    <th class="text-center" style="min-width: 150px;">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyCustomers">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Add/Edit Customer -->
    <div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white p-3">
                    <h5 class="modal-title fw-bold text-white fs-15" id="customerModalTitle">إضافة عميل جديد</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="customerForm" onsubmit="saveCustomerData(event)">
                    <input type="hidden" id="custFormId">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark fs-13">اسم العميل بالكامل <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="custFormName" required placeholder="مثلاً: الحاج عادل المنياوي">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark fs-13">رقم الهاتف المحمول <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control font-monospace" id="custFormPhone" required placeholder="010XXXXXXXX">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label fw-bold text-dark fs-13">نوع وموديل السيارة</label>
                                <input type="text" class="form-control" id="custFormCar" placeholder="تويوتا ياريس 2020">
                            </div>
                            <div class="col-5">
                                <label class="form-label fw-bold text-dark fs-13">رقم اللوحة</label>
                                <input type="text" class="form-control font-monospace text-center fw-bold" id="custFormPlate" placeholder="ر ق ط 9821">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark fs-13">تصنيف العميل</label>
                            <select class="form-select" id="custFormType">
                                <option value="ملاكي">سيارة ملاكي خاصة</option>
                                <option value="تاكسي وأوبر">تاكسي وأوبر</option>
                                <option value="نقل وتريلات">نقل ثقيل وجامبو</option>
                                <option value="ورش وشركات">ورشة صيانة وشركات</option>
                            </select>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold text-muted fs-12">ملاحظات إضافية:</label>
                            <textarea class="form-control" id="custFormNotes" rows="2" placeholder="أي تفاصيل خاصة بحالة العميل أو البطارية المفضلة..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4">
                            <i class="ri-save-line me-1"></i> حفظ بيانات العميل
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Customer Profile & History (كشف السجل الكامل) -->
    <div class="modal fade" id="customerHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white p-3">
                    <h5 class="modal-title fw-bold text-white fs-15">سجل مشتريات وضمانات العميل</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="customerHistoryModalBody">
                    <!-- Populated dynamically -->
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
<script>
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    renderCustomersDashboard();

    window.addEventListener('alhusseini-sales-updated', function () {
        renderCustomersDashboard();
    });
});

function renderCustomersDashboard() {
    if (!window.AlHusseiniSales) return;

    const customers = window.AlHusseiniSales.getCustomers();
    const creditCustomers = customers.filter(c => (Number(c.creditBalance) || 0) > 0);
    const totalCredit = creditCustomers.reduce((sum, c) => sum + Number(c.creditBalance), 0);

    const privateCount = customers.filter(c => c.type === 'ملاكي').length;
    const commercialCount = customers.length - privateCount;

    document.getElementById('custStatTotal').textContent = `${customers.length} عميل`;
    document.getElementById('custStatCreditCount').textContent = `${creditCustomers.length} عميل`;
    document.getElementById('custStatCreditAmount').textContent = `إجمالي: ${window.AlHusseiniSales.formatCurrency(totalCredit)}`;
    document.getElementById('custStatPrivate').textContent = `${privateCount} عميل`;
    document.getElementById('custStatCommercial').textContent = `${commercialCount} عميل / ورشة`;

    renderCustomersTable();
}

function renderCustomersTable() {
    if (!window.AlHusseiniSales) return;

    const customers = window.AlHusseiniSales.getCustomers();
    const searchVal = (document.getElementById('searchCustomerInput')?.value || '').trim().toLowerCase();
    const typeVal = document.getElementById('filterCustomerType')?.value || 'all';
    const tbody = document.getElementById('tbodyCustomers');
    if (!tbody) return;

    const filtered = customers.filter(c => {
        if (typeVal !== 'all' && c.type !== typeVal) return false;
        if (searchVal) {
            const matchName = c.name.toLowerCase().includes(searchVal);
            const matchPhone = c.phone.toLowerCase().includes(searchVal);
            const matchCar = (c.carModel || '').toLowerCase().includes(searchVal);
            const matchPlate = (c.carPlate || '').toLowerCase().includes(searchVal);
            if (!matchName && !matchPhone && !matchCar && !matchPlate) return false;
        }
        return true;
    });

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-muted fs-13">لا يوجد عملاء مطابقين لبحثك</td></tr>`;
        return;
    }

    let html = '';
    filtered.forEach(c => {
        const credit = Number(c.creditBalance) || 0;
        const creditBadge = credit > 0 ? 
            `<span class="badge bg-danger text-white fs-12 font-monospace px-2 py-1">${window.AlHusseiniSales.formatCurrency(credit)}</span>` : 
            '<span class="badge bg-success-subtle text-success fs-11">خالص (0 ج.م)</span>';

        html += `
            <tr>
                <td><span class="badge bg-light text-dark border font-monospace fs-11">${c.id}</span></td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-xs me-2">
                            <span class="avatar-title rounded-circle bg-primary-subtle text-primary fw-bold fs-12">
                                ${c.name.charAt(0)}
                            </span>
                        </div>
                        <div>
                            <strong class="text-dark fs-13">${c.name}</strong>
                            <small class="text-muted d-block fs-11">${c.notes || ''}</small>
                        </div>
                    </div>
                </td>
                <td><span class="font-monospace fw-semibold text-dark fs-12">${c.phone}</span></td>
                <td><strong class="text-dark fs-12">${c.carModel || '-'}</strong></td>
                <td><span class="badge bg-light text-secondary border font-monospace fs-11">${c.carPlate || '-'}</span></td>
                <td><span class="badge bg-primary-subtle text-primary fs-11">${c.type}</span></td>
                <td><span class="font-monospace text-muted fs-12">${window.AlHusseiniSales.formatCurrency(c.totalPurchases)}</span></td>
                <td>${creditBadge}</td>
                <td class="text-center">
                    <div class="d-inline-flex gap-1">
                        <button type="button" class="btn btn-sm btn-soft-primary" onclick="viewCustomerHistory('${c.id}')" title="سجل المشتريات والضمان">
                            <i class="ri-history-line"></i> السجل
                        </button>
                        <button type="button" class="btn btn-sm btn-soft-secondary" onclick="editCustomer('${c.id}')" title="تعديل">
                            <i class="ri-edit-line"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function openNewCustomerModal() {
    document.getElementById('customerModalTitle').textContent = 'إضافة عميل جديد';
    document.getElementById('custFormId').value = '';
    document.getElementById('custFormName').value = '';
    document.getElementById('custFormPhone').value = '';
    document.getElementById('custFormCar').value = '';
    document.getElementById('custFormPlate').value = '';
    document.getElementById('custFormNotes').value = '';
}

function editCustomer(custId) {
    const cust = window.AlHusseiniSales.getCustomerById(custId);
    if (!cust) return;

    document.getElementById('customerModalTitle').textContent = 'تعديل بيانات العميل';
    document.getElementById('custFormId').value = cust.id;
    document.getElementById('custFormName').value = cust.name;
    document.getElementById('custFormPhone').value = cust.phone;
    document.getElementById('custFormCar').value = cust.carModel || '';
    document.getElementById('custFormPlate').value = cust.carPlate || '';
    document.getElementById('custFormType').value = cust.type || 'ملاكي';
    document.getElementById('custFormNotes').value = cust.notes || '';

    const modal = new bootstrap.Modal(document.getElementById('customerModal'));
    modal.show();
}

function saveCustomerData(e) {
    e.preventDefault();
    const id = document.getElementById('custFormId').value;
    const name = document.getElementById('custFormName').value.trim();
    const phone = document.getElementById('custFormPhone').value.trim();
    const car = document.getElementById('custFormCar').value.trim();
    const plate = document.getElementById('custFormPlate').value.trim();
    const type = document.getElementById('custFormType').value;
    const notes = document.getElementById('custFormNotes').value.trim();

    window.AlHusseiniSales.saveCustomer({
        id: id || null,
        name: name,
        phone: phone,
        carModel: car,
        carPlate: plate,
        type: type,
        notes: notes
    });

    const modalEl = document.getElementById('customerModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    Swal.fire({
        icon: 'success',
        title: 'تم حفظ بيانات العميل بنجاح!',
        timer: 1500,
        showConfirmButton: false
    });

    renderCustomersDashboard();
}

function viewCustomerHistory(custId) {
    const cust = window.AlHusseiniSales.getCustomerById(custId);
    if (!cust) return;

    const invoices = window.AlHusseiniSales.getInvoices().filter(i => i.customerId === custId);
    const modalBody = document.getElementById('customerHistoryModalBody');

    modalBody.innerHTML = `
        <div class="p-3 bg-light rounded border mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1 text-dark">${cust.name}</h5>
                    <p class="text-muted mb-0 fs-13"><i class="ri-car-line me-1"></i> ${cust.carModel} - لوحة: <strong>${cust.carPlate}</strong> | هاتف: ${cust.phone}</p>
                </div>
                <div class="text-end">
                    <span class="fs-12 text-muted d-block">رصيد الآجل المتبقي:</span>
                    <span class="badge ${cust.creditBalance > 0 ? 'bg-danger' : 'bg-success'} fs-14 font-monospace">${window.AlHusseiniSales.formatCurrency(cust.creditBalance)}</span>
                </div>
            </div>
        </div>

        <h6 class="fw-bold fs-14 text-dark mb-2"><i class="ri-battery-charge-line text-success me-1"></i> البطاريات المشتراة وتواريخ سريان الضمان:</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-sm fs-12 mb-0">
                <thead class="table-light">
                    <tr><th>الفاتورة</th><th>التاريخ</th><th>نوع وموديل البطارية</th><th>السيريال</th><th>انتهاء الضمان</th><th>حالة الضمان</th></tr>
                </thead>
                <tbody>
                    ${invoices.map(inv => `
                        <tr>
                            <td class="font-monospace fw-bold">${inv.invoiceNo}</td>
                            <td>${inv.date}</td>
                            <td>${inv.items[0]?.name || '-'}</td>
                            <td class="font-monospace text-primary">${inv.serialNumber}</td>
                            <td class="font-monospace">${inv.warrantyExpiry}</td>
                            <td><span class="badge bg-success-subtle text-success">ساري ومفعل ✅</span></td>
                        </tr>
                    `).join('')}
                    ${invoices.length === 0 ? `<tr><td colspan="6" class="text-center py-3 text-muted">لا توجد فواتير سابقة مسجلة</td></tr>` : ''}
                </tbody>
            </table>
        </div>
    `;

    const modal = new bootstrap.Modal(document.getElementById('customerHistoryModal'));
    modal.show();
}
</script>
@endsection
