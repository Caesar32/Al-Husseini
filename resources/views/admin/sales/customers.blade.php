@extends('admin.layouts.master')

@section('title', 'دليل وسجل العملاء والسيارات | مركز الحسيني لبطاريات السيارات')

@php
    $tierLabels = ['standard' => 'ملاكي', 'vip' => 'تاكسي وأوبر', 'fleet' => 'ورش وشركات'];
    $canAdjustLimit = auth()->user()?->can('credit.adjust_limit');
@endphp

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'العملاء', 'title' => 'دليل وسجل العملاء والسيارات وتاريخ التعاملات'])

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Top Stats (live from the customers table) -->
    <div class="row mb-3">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate border-start border-primary border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">إجمالي العملاء المسجلين</p>
                            <h3 class="fs-22 fw-extrabold text-primary mb-1 font-monospace">{{ number_format($stats['total']) }} عميل</h3>
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
                            <h3 class="fs-22 fw-extrabold text-warning mb-1 font-monospace">{{ number_format($stats['in_debt_count']) }} عميل</h3>
                            <small class="text-danger fw-bold fs-11">إجمالي: {{ number_format($stats['in_debt_total'], 2) }} ج.م</small>
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
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">عملاء سيارات ملاكي</p>
                            <h3 class="fs-22 fw-extrabold text-success mb-1 font-monospace">{{ number_format($stats['standard_count']) }}</h3>
                            <small class="text-muted fs-11">تصنيف: ملاكي</small>
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
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">ورش وشركات وتاكسي</p>
                            <h3 class="fs-22 fw-extrabold text-info mb-1 font-monospace">{{ number_format($stats['commercial_count']) }}</h3>
                            <small class="text-muted fs-11">تصنيف: تاكسي وأوبر / ورش وشركات</small>
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
                    <form method="GET" action="{{ route('admin.sales.customers') }}" class="row g-2 align-items-center justify-content-between">
                        <div class="col-md-4 col-12">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="ri-search-line"></i></span>
                                <input type="text" class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="ابحث باسم العميل، الهاتف، أو اللوحة...">
                            </div>
                        </div>
                        <div class="col-md-3 col-12">
                            <select class="form-select form-select-sm" name="tier" onchange="this.form.submit()">
                                <option value="">جميع تصنيفات العملاء</option>
                                @foreach($tierLabels as $tier => $label)
                                    <option value="{{ $tier }}" @selected(($filters['tier'] ?? '') === $tier)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5 col-12 text-md-end">
                            <button type="submit" class="btn btn-sm btn-light border">بحث</button>
                            @can('customers.create')
                                <button type="button" class="btn btn-sm btn-primary fw-bold" onclick="openCustomerModal(null)">
                                    <i class="ri-user-add-line me-1"></i> إضافة عميل جديد
                                </button>
                            @endcan
                        </div>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-nowrap mb-0" id="customersTable">
                            <thead class="table-light">
                                <tr class="text-muted fs-12 text-uppercase">
                                    <th>كود العميل</th>
                                    <th>الاسم</th>
                                    <th>رقم الهاتف</th>
                                    <th>السيارات واللوحات</th>
                                    <th>التصنيف</th>
                                    <th>إجمالي المشتريات</th>
                                    <th>رصيد الآجل المتبقي</th>
                                    <th class="text-center" style="min-width: 150px;">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($customers as $customer)
                                    @php($credit = (float) $customer->current_credit_balance)
                                    <tr>
                                        <td><span class="badge bg-light text-dark border font-monospace fs-11">#{{ $customer->id }}</span></td>
                                        <td>
                                            <strong class="text-dark fs-13">{{ $customer->name }}</strong>
                                            @unless($customer->is_active)
                                                <span class="badge bg-secondary-subtle text-secondary fs-10 ms-1">موقوف</span>
                                            @endunless
                                        </td>
                                        <td><span class="font-monospace fw-semibold text-dark fs-12">{{ $customer->phone }}</span></td>
                                        <td>
                                            @forelse($customer->vehicles as $vehicle)
                                                <div class="fs-12">
                                                    <strong class="text-dark">{{ $vehicle->car_brand }} {{ $vehicle->car_model }}</strong>
                                                    <span class="badge bg-light text-secondary border font-monospace fs-11">{{ $vehicle->plate_number }}</span>
                                                </div>
                                            @empty
                                                <span class="text-muted fs-12">-</span>
                                            @endforelse
                                        </td>
                                        <td><span class="badge bg-primary-subtle text-primary fs-11">{{ $tierLabels[$customer->tier] ?? $customer->tier }}</span></td>
                                        <td>
                                            <span class="font-monospace text-muted fs-12">{{ number_format((float) $customer->purchases_total, 2) }} ج.م</span>
                                            <small class="text-muted d-block fs-11">{{ $customer->purchases_count }} فاتورة</small>
                                        </td>
                                        <td>
                                            @if($credit > 0)
                                                <span class="badge bg-danger text-white fs-12 font-monospace px-2 py-1">{{ number_format($credit, 2) }} ج.م</span>
                                            @else
                                                <span class="badge bg-success-subtle text-success fs-11">خالص</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-soft-primary js-customer-history" data-id="{{ $customer->id }}" title="سجل المشتريات والضمان">
                                                    <i class="ri-history-line"></i> السجل
                                                </button>
                                                @can('customers.edit')
                                                    <button type="button" class="btn btn-sm btn-soft-secondary js-edit-customer" title="تعديل"
                                                            data-customer="{{ json_encode($customer->only(['id', 'name', 'phone', 'national_id', 'tier', 'is_active', 'credit_limit'])) }}">
                                                        <i class="ri-edit-line"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-soft-info js-add-vehicle" data-id="{{ $customer->id }}" title="إضافة مركبة">
                                                        <i class="ri-car-line"></i>
                                                    </button>
                                                @endcan
                                                @can('customers.delete')
                                                    <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" onsubmit="return confirm('حذف هذا العميل؟');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-soft-danger"><i class="ri-delete-bin-line"></i></button>
                                                    </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center py-5 text-muted fs-13">لا يوجد عملاء مطابقين لبحثك.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($customers->hasPages())
                    <div class="card-footer bg-transparent border-top py-2 d-flex justify-content-center">
                        {{ $customers->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @canany(['customers.create', 'customers.edit'])
    <!-- Modal: Add/Edit Customer -->
    <div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white p-3">
                    <h5 class="modal-title fw-bold text-white fs-15" id="customerModalTitle">إضافة عميل جديد</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="customerForm" novalidate>
                    <input type="hidden" id="custFormId">
                    <div class="modal-body p-4">
                        <div class="alert alert-danger d-none" id="customerFormErrors"></div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark fs-13">اسم العميل بالكامل <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required maxlength="150">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold text-dark fs-13">رقم الهاتف <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control font-monospace" name="phone" required maxlength="30">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold text-dark fs-13">الرقم القومي</label>
                                <input type="text" class="form-control font-monospace" name="national_id" maxlength="30">
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold text-dark fs-13">تصنيف العميل</label>
                                <select class="form-select" name="tier">
                                    @foreach($tierLabels as $tier => $label)
                                        <option value="{{ $tier }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 d-none" id="custActiveGroup">
                                <label class="form-label fw-bold text-dark fs-13">الحالة</label>
                                <select class="form-select" name="is_active">
                                    <option value="1">مفعّل</option>
                                    <option value="0">موقوف</option>
                                </select>
                            </div>
                        </div>
                        @if($canAdjustLimit)
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark fs-13">سقف الائتمان (ج.م)</label>
                                <input type="number" class="form-control font-monospace" name="credit_limit" min="0" step="0.01">
                                <small class="text-muted fs-11">اتركه فارغاً لاستخدام السقف الافتراضي عند الإضافة.</small>
                            </div>
                        @endif
                        <div id="custVehicleGroup">
                            <hr>
                            <h6 class="fw-bold fs-13 text-dark">مركبة العميل (اختياري)</h6>
                            <div class="row g-2">
                                <div class="col-4">
                                    <label class="form-label fs-12">رقم اللوحة</label>
                                    <input type="text" class="form-control font-monospace text-center fw-bold" name="vehicle[plate_number]" maxlength="50">
                                </div>
                                <div class="col-4">
                                    <label class="form-label fs-12">الماركة</label>
                                    <input type="text" class="form-control" name="vehicle[car_brand]" maxlength="50">
                                </div>
                                <div class="col-4">
                                    <label class="form-label fs-12">الموديل</label>
                                    <input type="text" class="form-control" name="vehicle[car_model]" maxlength="50">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4" id="customerFormSubmit">
                            <i class="ri-save-line me-1"></i> حفظ بيانات العميل
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endcanany

    @can('customers.edit')
    <!-- Modal: Add Vehicle -->
    <div class="modal fade" id="vehicleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-info text-white p-3">
                    <h5 class="modal-title fw-bold text-white fs-15">إضافة مركبة للعميل</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="vehicleForm" novalidate>
                    <input type="hidden" id="vehicleCustomerId">
                    <div class="modal-body p-4">
                        <div class="alert alert-danger d-none" id="vehicleFormErrors"></div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label fw-bold fs-13">رقم اللوحة <span class="text-danger">*</span></label>
                                <input type="text" class="form-control font-monospace text-center fw-bold" name="plate_number" required maxlength="50">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold fs-13">سنة الصنع</label>
                                <input type="number" class="form-control font-monospace" name="model_year" min="1950" max="2100">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold fs-13">الماركة <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="car_brand" required maxlength="50">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold fs-13">الموديل <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="car_model" required maxlength="50">
                            </div>
                            <div class="col-12">
                                <label class="form-label fs-12">رقم الشاسيه</label>
                                <input type="text" class="form-control font-monospace" name="chassis_number" maxlength="100">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-info fw-bold px-4 text-white">حفظ المركبة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endcan

    <!-- Modal: Customer Profile & History -->
    <div class="modal fade" id="customerHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white p-3">
                    <h5 class="modal-title fw-bold text-white fs-15">سجل مشتريات وضمانات العميل</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="customerHistoryModalBody"></div>
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

(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const urls = {
        store: @json(route('admin.customers.store')),
        update: @json(route('admin.customers.update', ['customer' => '__ID__'])),
        show: @json(route('admin.customers.show', ['customer' => '__ID__'])),
        vehicleStore: @json(route('admin.customers.vehicles.store', ['customer' => '__ID__'])),
    };
    const tierLabels = @json($tierLabels);

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function money(value) {
        return Number(value || 0).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ج.م';
    }

    function formPayload(form) {
        const payload = {};
        new FormData(form).forEach(function (value, key) {
            const match = key.match(/^(\w+)\[(\w+)\]$/);
            if (match) {
                payload[match[1]] = payload[match[1]] || {};
                payload[match[1]][match[2]] = value === '' ? null : value;
            } else {
                payload[key] = value === '' ? null : value;
            }
        });
        return payload;
    }

    async function send(url, method, payload, errorsEl) {
        errorsEl.classList.add('d-none');
        try {
            const res = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(function () { return {}; });
            if (!res.ok) {
                const messages = data.errors ? Object.values(data.errors).flat() : [data.message || 'تعذر حفظ البيانات.'];
                errorsEl.textContent = messages.join(' | ');
                errorsEl.classList.remove('d-none');
                return false;
            }
            return true;
        } catch (err) {
            errorsEl.textContent = 'تعذر الاتصال بالخادم. حاول مرة أخرى.';
            errorsEl.classList.remove('d-none');
            return false;
        }
    }

    // ─── Customer create / edit ───
    const customerForm = document.getElementById('customerForm');
    if (customerForm) {
        const errorsEl = document.getElementById('customerFormErrors');
        const modalEl = document.getElementById('customerModal');

        window.openCustomerModal = function (customer) {
            customerForm.reset();
            errorsEl.classList.add('d-none');
            document.getElementById('custFormId').value = customer ? customer.id : '';
            document.getElementById('customerModalTitle').textContent = customer ? 'تعديل بيانات العميل' : 'إضافة عميل جديد';
            document.getElementById('custActiveGroup').classList.toggle('d-none', !customer);
            document.getElementById('custVehicleGroup').classList.toggle('d-none', !!customer);
            if (customer) {
                Object.keys(customer).forEach(function (key) {
                    const field = customerForm.elements[key];
                    if (!field) return;
                    let value = customer[key];
                    if (typeof value === 'boolean') value = value ? '1' : '0';
                    field.value = value === null || value === undefined ? '' : value;
                });
            }
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        };

        document.querySelectorAll('.js-edit-customer').forEach(function (btn) {
            btn.addEventListener('click', function () { window.openCustomerModal(JSON.parse(btn.dataset.customer)); });
        });

        customerForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const id = document.getElementById('custFormId').value;
            const payload = formPayload(customerForm);
            if (id) {
                delete payload.vehicle;
            } else {
                delete payload.is_active;
                if (payload.vehicle && !payload.vehicle.plate_number) delete payload.vehicle;
            }
            if ('credit_limit' in payload && payload.credit_limit === null) delete payload.credit_limit;

            const submitBtn = document.getElementById('customerFormSubmit');
            submitBtn.disabled = true;
            const ok = await send(id ? urls.update.replace('__ID__', encodeURIComponent(id)) : urls.store, id ? 'PUT' : 'POST', payload, errorsEl);
            submitBtn.disabled = false;
            if (ok) window.location.reload();
        });
    }

    // ─── Add vehicle ───
    const vehicleForm = document.getElementById('vehicleForm');
    if (vehicleForm) {
        const errorsEl = document.getElementById('vehicleFormErrors');
        document.querySelectorAll('.js-add-vehicle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                vehicleForm.reset();
                errorsEl.classList.add('d-none');
                document.getElementById('vehicleCustomerId').value = btn.dataset.id;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('vehicleModal')).show();
            });
        });
        vehicleForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const id = document.getElementById('vehicleCustomerId').value;
            const ok = await send(urls.vehicleStore.replace('__ID__', encodeURIComponent(id)), 'POST', formPayload(vehicleForm), errorsEl);
            if (ok) window.location.reload();
        });
    }

    // ─── Customer history (server data) ───
    document.querySelectorAll('.js-customer-history').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const body = document.getElementById('customerHistoryModalBody');
            body.innerHTML = '<div class="text-center py-4 text-muted">جاري التحميل...</div>';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('customerHistoryModal')).show();

            try {
                const res = await fetch(urls.show.replace('__ID__', encodeURIComponent(btn.dataset.id)), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                const c = data.customer;
                const vehicles = (c.vehicles || []).map(function (v) {
                    return escapeHtml(v.car_brand + ' ' + v.car_model) + ' - لوحة: <strong>' + escapeHtml(v.plate_number) + '</strong>';
                }).join('<br>') || '-';

                const invoiceRows = (data.invoices || []).map(function (inv) {
                    const items = (inv.items || []).map(function (it) {
                        const serial = it.battery_serial_number ? ' <span class="font-monospace text-primary">[' + escapeHtml(it.battery_serial_number) + ']</span>' : '';
                        return escapeHtml(it.product ? it.product.name : '-') + ' × ' + escapeHtml(it.quantity) + serial;
                    }).join('<br>');
                    return '<tr><td class="font-monospace fw-bold">' + escapeHtml(inv.invoice_number) + '</td>'
                        + '<td>' + escapeHtml(String(inv.created_at || '').slice(0, 10)) + '</td>'
                        + '<td>' + items + '</td>'
                        + '<td class="font-monospace">' + money(inv.final_amount) + '</td>'
                        + '<td class="font-monospace">' + money(inv.remaining_amount) + '</td>'
                        + '<td><span class="badge bg-light text-dark border">' + escapeHtml(inv.status) + '</span></td></tr>';
                }).join('') || '<tr><td colspan="6" class="text-center py-3 text-muted">لا توجد فواتير مسجلة</td></tr>';

                const warrantyRows = (data.warranties || []).map(function (w) {
                    return '<tr><td class="font-monospace text-primary">' + escapeHtml(w.serial_number) + '</td>'
                        + '<td>' + escapeHtml(w.invoice_item && w.invoice_item.product ? w.invoice_item.product.name : '-') + '</td>'
                        + '<td class="font-monospace">' + escapeHtml(String(w.end_date || '').slice(0, 10)) + '</td>'
                        + '<td><span class="badge bg-light text-dark border">' + escapeHtml(w.status) + '</span></td></tr>';
                }).join('') || '<tr><td colspan="4" class="text-center py-3 text-muted">لا توجد ضمانات مسجلة</td></tr>';

                body.innerHTML =
                    '<div class="p-3 bg-light rounded border mb-3"><div class="d-flex justify-content-between align-items-center">'
                    + '<div><h5 class="fw-bold mb-1 text-dark">' + escapeHtml(c.name) + '</h5>'
                    + '<p class="text-muted mb-0 fs-13">هاتف: ' + escapeHtml(c.phone) + ' | التصنيف: ' + escapeHtml(tierLabels[c.tier] || c.tier) + '</p>'
                    + '<p class="text-muted mb-0 fs-12"><i class="ri-car-line me-1"></i>' + vehicles + '</p></div>'
                    + '<div class="text-end"><span class="fs-12 text-muted d-block">رصيد الآجل المتبقي:</span>'
                    + '<span class="badge ' + (Number(c.current_credit_balance) > 0 ? 'bg-danger' : 'bg-success') + ' fs-14 font-monospace">' + money(c.current_credit_balance) + '</span>'
                    + '<span class="fs-11 text-muted d-block mt-1">سقف الائتمان: ' + money(c.credit_limit) + '</span></div>'
                    + '</div></div>'
                    + '<h6 class="fw-bold fs-14 text-dark mb-2">آخر الفواتير</h6>'
                    + '<div class="table-responsive mb-3"><table class="table table-bordered table-sm fs-12 mb-0"><thead class="table-light"><tr><th>الفاتورة</th><th>التاريخ</th><th>الأصناف</th><th>الصافي</th><th>المتبقي</th><th>الحالة</th></tr></thead><tbody>' + invoiceRows + '</tbody></table></div>'
                    + '<h6 class="fw-bold fs-14 text-dark mb-2">شهادات الضمان</h6>'
                    + '<div class="table-responsive"><table class="table table-bordered table-sm fs-12 mb-0"><thead class="table-light"><tr><th>السيريال</th><th>الصنف</th><th>انتهاء الضمان</th><th>الحالة</th></tr></thead><tbody>' + warrantyRows + '</tbody></table></div>';
            } catch (err) {
                body.innerHTML = '<div class="text-center py-4 text-danger">تعذر تحميل سجل العميل.</div>';
            }
        });
    });
})();
</script>
@endsection
