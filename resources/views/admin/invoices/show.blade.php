@extends('admin.layouts.master')

@section('title', 'تفاصيل فاتورة المبيعات - ' . $invoice->invoice_number)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">تفاصيل فاتورة المبيعات</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.invoices.index') }}">فواتير المبيعات</a></li>
                        <li class="breadcrumb-item active">{{ $invoice->invoice_number }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success') || session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ri-checkbox-circle-line me-1 align-middle"></i>
            {{ session('success') ?? session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ri-error-warning-line me-1 align-middle"></i>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Header Actions -->
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline-secondary">
                    <i class="ri-arrow-right-line align-bottom me-1"></i> العودة لقائمة الفواتير
                </a>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.pos.receipt', $invoice) }}" target="_blank" class="btn btn-primary">
                    <i class="ri-printer-line align-bottom me-1"></i> طباعة إيصال حراري (POS)
                </a>
                @if($invoice->items->contains(fn($item) => $item->product?->is_battery))
                    <a href="{{ route('admin.pos.warranty_cert', $invoice) }}" target="_blank" class="btn btn-info text-white">
                        <i class="ri-shield-check-line align-bottom me-1"></i> طباعة شهادة الضمان
                    </a>
                @endif
                @can('invoices.cancel')
                    @if($invoice->status !== 'returned' && $invoice->status !== 'cancelled')
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#returnModal">
                            <i class="ri-arrow-go-back-line align-bottom me-1"></i> تسجيل مرتجع مبيعات
                        </button>
                    @endif
                @endcan
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Invoice Info -->
        <div class="col-xl-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header border-bottom-dashed d-flex justify-content-between align-items-center bg-light">
                    <div>
                        <h5 class="card-title mb-0">فاتورة مبيعات: <span class="text-primary font-monospace">{{ $invoice->invoice_number }}</span></h5>
                        <small class="text-muted">تاريخ الإصدار: {{ $invoice->created_at?->format('Y-m-d h:i A') }}</small>
                    </div>
                    <div>
                        @php
                            $statusBadge = match($invoice->status) {
                                'paid' => ['bg' => 'bg-success', 'label' => 'مدفوعة بالكامل'],
                                'partial' => ['bg' => 'bg-warning text-dark', 'label' => 'مدفوعة جزئياً (آجل)'],
                                'unpaid' => ['bg' => 'bg-danger', 'label' => 'آجل غير مسدد'],
                                'returned' => ['bg' => 'bg-secondary', 'label' => 'مرتجع'],
                                default => ['bg' => 'bg-info', 'label' => $invoice->status],
                            };
                        @endphp
                        <span class="badge {{ $statusBadge['bg'] }} fs-12 px-3 py-2">
                            {{ $statusBadge['label'] }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- General Details Grid -->
                    <div class="row g-3 mb-4 border-bottom pb-3">
                        <div class="col-md-3">
                            <p class="text-muted mb-1 fs-12">الفرع / نقطة البيع:</p>
                            <h6 class="fs-14 fw-bold mb-0">{{ $invoice->branch?->name ?? 'الفرع الرئيسي' }}</h6>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-1 fs-12">الكاشير المسئول:</p>
                            <h6 class="fs-14 fw-bold mb-0">{{ $invoice->cashier?->name ?? 'غير محدد' }}</h6>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-1 fs-12">الفني المسؤول عن التركيب:</p>
                            <h6 class="fs-14 fw-bold mb-0">{{ $invoice->technician?->name ?? 'بدون فني' }}</h6>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-1 fs-12">طريقة الدفع الرئيسية:</p>
                            <h6 class="fs-14 fw-bold mb-0 text-capitalize">
                                @php
                                    $methodLabels = [
                                        'cash' => 'نقداً (Cash)',
                                        'card' => 'بطاقة / فيزا',
                                        'credit' => 'آجل على الحساب',
                                        'bank_transfer' => 'تحويل بنكي',
                                        'mixed' => 'دفع مركب / مختلط',
                                    ];
                                @endphp
                                {{ $methodLabels[$invoice->payment_method] ?? $invoice->payment_method }}
                            </h6>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <h6 class="fw-bold mb-3"><i class="ri-shopping-cart-2-line me-1"></i> المنتجات والأصناف المباعة</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="fs-12 text-muted">
                                    <th style="width: 50px;">#</th>
                                    <th>اسم المنتج والمواصفات</th>
                                    <th>التصنيف</th>
                                    <th>سيريال البطارية / الضمان</th>
                                    <th class="text-center">الكمية</th>
                                    <th class="text-end">سعر الوحدة</th>
                                    <th class="text-end">الخصم</th>
                                    <th class="text-end">الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($invoice->items as $index => $item)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $item->product?->name ?? 'منتج غير معرف' }}</div>
                                            @if($item->product?->sku)
                                                <small class="text-muted font-monospace">SKU: {{ $item->product->sku }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                {{ $item->product?->category?->name ?? 'عام' }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($item->serial_number)
                                                <span class="badge bg-info-subtle text-info font-monospace fs-11">
                                                    {{ $item->serial_number }}
                                                </span>
                                            @else
                                                <span class="text-muted fs-12">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center fw-bold">{{ $item->quantity }}</td>
                                        <td class="text-end font-monospace">{{ number_format($item->unit_price, 2) }} ج.م</td>
                                        <td class="text-end font-monospace text-danger">{{ number_format($item->discount_amount ?? 0, 2) }} ج.م</td>
                                        <td class="text-end fw-bold font-monospace text-primary">{{ number_format($item->total_price, 2) }} ج.م</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-3">لا توجد أصناف مسجلة في هذه الفاتورة.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Scrap Battery Section (Trade-in) -->
                    @if($invoice->scrapBattery || $invoice->scrap_discount > 0)
                        <div class="alert alert-warning border-warning-subtle d-flex align-items-center justify-content-between p-3 mb-4">
                            <div>
                                <h6 class="alert-heading fw-bold mb-1">
                                    <i class="ri-recycle-line me-1"></i> استبدال بطارية قديمة (كهنة / تخريد)
                                </h6>
                                <p class="mb-0 fs-13">
                                    تم خصم قيمة البطارية القديمة من الفاتورة وتم توريدها إلى مخزن الكهنة تلقائياً.
                                    @if($invoice->scrapBattery)
                                        | الموديل: <strong>{{ $invoice->scrapBattery->brand ?? '' }} {{ $invoice->scrapBattery->capacity_ah ? $invoice->scrapBattery->capacity_ah . 'Ah' : '' }}</strong>
                                    @endif
                                </p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-danger fs-13 font-monospace px-3 py-2">
                                    -{{ number_format($invoice->scrap_discount, 2) }} ج.م
                                </span>
                            </div>
                        </div>
                    @endif

                    <!-- Payments Log -->
                    <h6 class="fw-bold mb-3"><i class="ri-money-dollar-circle-line me-1"></i> سجل المدفوعات والتحصيلات</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr class="fs-12 text-muted">
                                    <th>تاريخ السداد</th>
                                    <th>طريقة الدفع</th>
                                    <th>رقم الإيصال / المرجع</th>
                                    <th class="text-end">المبلغ المسدد</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($invoice->payments as $payment)
                                    <tr>
                                        <td class="font-monospace fs-12">{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary">
                                                {{ $payment->payment_method }}
                                            </span>
                                        </td>
                                        <td class="font-monospace text-muted">{{ $payment->reference_number ?? '-' }}</td>
                                        <td class="text-end fw-bold font-monospace text-success">{{ number_format($payment->amount, 2) }} ج.م</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-2">لا توجد حركات سداد مسجلة.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Customer & Financials -->
        <div class="col-xl-4">
            <!-- Customer & Vehicle Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light border-bottom">
                    <h5 class="card-title mb-0"><i class="ri-user-3-line me-1"></i> بيانات العميل والمركبة</h5>
                </div>
                <div class="card-body">
                    @if($invoice->customer)
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-sm me-3">
                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-18">
                                    <i class="ri-user-star-line"></i>
                                </span>
                            </div>
                            <div>
                                <h6 class="fs-15 fw-bold mb-0">{{ $invoice->customer->name }}</h6>
                                <p class="text-muted mb-0 font-monospace fs-13">{{ $invoice->customer->phone }}</p>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted fs-12">رصيد المديونية الحالي:</span>
                                <span class="fw-bold font-monospace {{ $invoice->customer->current_credit_balance > 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($invoice->customer->current_credit_balance, 2) }} ج.م
                                </span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted fs-12">الحد الائتماني:</span>
                                <span class="fw-bold font-monospace text-muted">
                                    {{ number_format($invoice->customer->credit_limit, 2) }} ج.م
                                </span>
                            </div>
                        </div>

                        <div class="text-center mb-3">
                            <a href="{{ route('admin.credit.statement', $invoice->customer) }}" class="btn btn-sm btn-outline-primary w-100">
                                <i class="ri-file-list-3-line me-1"></i> عرض كشف حساب العميل
                            </a>
                        </div>
                    @else
                        <div class="text-center py-3 text-muted">
                            <i class="ri-user-line fs-24 mb-2 d-block"></i>
                            عميل نقدي سريع (Walk-in Customer)
                        </div>
                    @endif

                    <!-- Vehicle Info -->
                    @if($invoice->customerVehicle)
                        <hr>
                        <h6 class="fw-bold fs-13 mb-2"><i class="ri-roadster-line me-1"></i> بيانات سيارة العميل:</h6>
                        <ul class="list-unstyled mb-0 fs-13">
                            <li class="mb-1"><strong>النوع والموديل:</strong> {{ $invoice->customerVehicle->car_brand }} {{ $invoice->customerVehicle->car_model }} ({{ $invoice->customerVehicle->manufacture_year ?? '-' }})</li>
                            <li class="mb-1"><strong>رقم اللوحة:</strong> <span class="badge bg-dark font-monospace">{{ $invoice->customerVehicle->plate_number }}</span></li>
                            @if($invoice->customerVehicle->chassis_number)
                                <li class="mb-1"><strong>رقم الشاسيه:</strong> <span class="font-monospace text-muted">{{ $invoice->customerVehicle->chassis_number }}</span></li>
                            @endif
                        </ul>
                    @endif
                </div>
            </div>

            <!-- Financial Summary Card -->
            <div class="card shadow-sm border-top border-primary border-3">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0"><i class="ri-calculator-line me-1"></i> ملخص الحساب والإجماليات</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">إجمالي المنتجات (قبل الخصم):</span>
                        <span class="font-monospace fw-semibold">{{ number_format($invoice->subtotal, 2) }} ج.م</span>
                    </div>

                    @if($invoice->discount_amount > 0)
                        <div class="d-flex justify-content-between py-2 border-bottom text-danger">
                            <span>خصم تجاري / ترويجي:</span>
                            <span class="font-monospace fw-semibold">-{{ number_format($invoice->discount_amount, 2) }} ج.م</span>
                        </div>
                    @endif

                    @if($invoice->scrap_discount > 0)
                        <div class="d-flex justify-content-between py-2 border-bottom text-warning">
                            <span>بدل تخريد بطارية قديمة:</span>
                            <span class="font-monospace fw-semibold">-{{ number_format($invoice->scrap_discount, 2) }} ج.م</span>
                        </div>
                    @endif

                    @if($invoice->tax_amount > 0)
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">ضريبة القيمة المضافة:</span>
                            <span class="font-monospace fw-semibold">+{{ number_format($invoice->tax_amount, 2) }} ج.م</span>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between py-3 border-bottom bg-light px-2 my-2 rounded">
                        <span class="fs-16 fw-bold">الصافي الإجمالي للفاتورة:</span>
                        <span class="fs-18 fw-extrabold text-primary font-monospace">{{ number_format($invoice->total_amount, 2) }} ج.م</span>
                    </div>

                    <div class="d-flex justify-content-between py-2 border-bottom text-success">
                        <span class="fw-bold">المبلغ المسدد:</span>
                        <span class="font-monospace fw-bold">{{ number_format($invoice->paid_amount, 2) }} ج.م</span>
                    </div>

                    <div class="d-flex justify-content-between py-2 {{ $invoice->remaining_amount > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                        <span>المبلغ المتبقي (آجل):</span>
                        <span class="font-monospace fs-15">{{ number_format($invoice->remaining_amount, 2) }} ج.م</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Process Return -->
@can('invoices.cancel')
<div class="modal fade" id="returnModal" tabindex="-1" aria-labelledby="returnModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('admin.invoices.return', $invoice) }}" method="POST">
                @csrf
                <div class="modal-header bg-danger text-white p-3">
                    <h5 class="modal-title fw-bold text-white fs-15" id="returnModalLabel">
                        <i class="ri-arrow-go-back-line me-1"></i> تسجيل مرتجع مبيعات للفاتورة
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted fs-13 mb-3">
                        حدد الأصناف والكميات المراد إرجاعها إلى المخزن. سيتم عكس القيود المحاسبية، وفي حال كانت الفاتورة بالآجل سيتم خصم المبلغ من حساب العميل تلقائياً.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-bold">الأصناف المرتجعة:</label>
                        @foreach($invoice->items as $item)
                            <div class="form-check border p-2 rounded mb-2 d-flex align-items-center justify-content-between">
                                <div>
                                    <input class="form-check-input ms-2" type="checkbox" name="items[{{ $loop->index }}][product_id]" value="{{ $item->product_id }}" id="item_{{ $item->id }}" checked>
                                    <label class="form-check-label fw-semibold" for="item_{{ $item->id }}">
                                        {{ $item->product?->name }}
                                    </label>
                                </div>
                                <div class="d-flex align-items-center" style="width: 120px;">
                                    <span class="fs-12 text-muted me-2">كمية:</span>
                                    <input type="number" class="form-control form-control-sm text-center" name="items[{{ $loop->index }}][quantity]" value="{{ $item->quantity }}" min="1" max="{{ $item->quantity }}" required>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mb-3">
                        <label for="returnReason" class="form-label fw-bold">سبب الإرجاع: <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="returnReason" name="reason" rows="3" placeholder="اكتب سبب طلب الإرجاع (مثال: عيب مصنعي، خطأ في المقاس، استرجاع عميل)..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger fw-bold">
                        <i class="ri-check-line me-1"></i> تأكيد إرجاع الأصناف
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endsection
