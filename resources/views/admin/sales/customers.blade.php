@extends('admin.layouts.master')

@section('title', 'دليل وسجل العملاء والسيارات | مركز الحسيني لبطاريات السيارات')

@php
    $tierLabels = ['standard' => 'ملاكي', 'vip' => 'تاكسي وأوبر', 'fleet' => 'ورش وشركات'];
    $canAdjustLimit = auth()->user()?->can('credit.adjust_limit');
@endphp

@section('content')
@php
    $pageConfig = [
        'urls' => [
            'store' => route('admin.customers.store'),
            'update' => route('admin.customers.update', ['customer' => '__ID__']),
            'show' => route('admin.customers.show', ['customer' => '__ID__']),
            'vehicleStore' => route('admin.customers.vehicles.store', ['customer' => '__ID__']),
        ],
        'tierLabels' => $tierLabels,
    ];
@endphp
<div data-page="sales.customers" data-config='@json($pageConfig)'>
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
                            <select class="form-select form-select-sm" name="tier" data-action="submit-form">
                                <option value="">جميع تصنيفات العملاء</option>
                                @foreach($tierLabels as $tier => $label)
                                    <option value="{{ $tier }}" @selected(($filters['tier'] ?? '') === $tier)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5 col-12 text-md-end">
                            <button type="submit" class="btn btn-sm btn-light border">بحث</button>
                            @can('customers.create')
                                <button type="button" class="btn btn-sm btn-primary fw-bold" data-action="customers.new">
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
                                                    <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" data-confirm-message="حذف هذا العميل؟">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-soft-danger"><i class="ri-delete-bin-line"></i></button>
                                                    </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="py-5 fs-13"><x-empty-state message="لا يوجد عملاء مطابقين لبحثك." /></td></tr>
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
</div>
@endsection

@section('script')
<script src="{{ asset('assets/js/admin/sales/customers.js') }}"></script>
@endsection
