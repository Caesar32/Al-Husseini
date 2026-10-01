@extends('admin.layouts.master')

@section('title', 'فحص الضمان والاستبدال السريع | مركز الحسيني')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">فحص الضمان والاستبدال السريع</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.warranties.index') }}">الضمانات</a></li>
                    <li class="breadcrumb-item active">فحص الضمان</li>
                </ol>
            </div>
        </div>
    </div>
</div>

@if(session('status'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="ri-check-double-line me-1"></i> {{ session('status') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <ul class="mb-0">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Hero Scanner Card -->
<div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
    <div class="card-body p-4 bg-body">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-primary-subtle text-primary rounded-circle p-2 fs-16">
                        <i class="ri-qr-scan-2-line"></i>
                    </span>
                    <h5 class="fw-bold mb-0 text-primary">فحص صلاحية وسريان سيريال البطارية فورياً</h5>
                </div>
                <p class="text-muted fs-13 mb-3">
                    مرر قارئ الباركود على سيريال البطارية أو اكتب الرقم للاستعلام الفوري عن سريان شهادة الضمان، تاريخ الشراء، وبيانات المركبة.
                </p>

                <!-- Search Form -->
                <form id="verifyForm" method="GET" action="{{ route('admin.warranties.verify') }}">
                    <input type="hidden" name="view" value="1">
                    <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
                        <span class="input-group-text bg-light border-0 px-3">
                            <i class="ri-barcode-line fs-20 text-muted"></i>
                        </span>
                        <input type="text" 
                               name="serial_number" 
                               id="serialInput" 
                               class="form-control border-0 fs-16 fw-bold px-2" 
                               placeholder="امسح الباركود أو أدخل رقم السيريال (مثال: SN-ACD-297456)..." 
                               value="{{ $serial }}" 
                               autocomplete="off" 
                               autofocus>
                        <button class="btn btn-primary px-4 fw-bold d-flex align-items-center gap-1" type="submit" id="searchBtn">
                            <i class="ri-search-eye-line fs-18"></i>
                            <span>فحص الضمان</span>
                        </button>
                    </div>
                </form>

                <!-- Quick Serial Suggestions -->
                <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted fs-12">سيريالات سريعة للاختبار:</span>
                    <button type="button" class="badge bg-light text-body border sample-serial-btn" data-serial="SN-ACD-297456">SN-ACD-297456</button>
                    <button type="button" class="badge bg-light text-body border sample-serial-btn" data-serial="SN-CHL-858016">SN-CHL-858016</button>
                    <button type="button" class="badge bg-light text-body border sample-serial-btn" data-serial="SN-HAN-512824">SN-HAN-512824</button>
                </div>
            </div>

            <div class="col-lg-4 mt-3 mt-lg-0 text-center">
                <div class="d-inline-flex gap-3">
                    <div class="p-3 bg-light rounded-3 text-center border">
                        <div class="text-muted fs-12 mb-1">الضمانات السارية</div>
                        <h4 class="mb-0 text-success fw-bold">{{ $activeWarrantiesCount }}</h4>
                    </div>
                    <div class="p-3 bg-light rounded-3 text-center border">
                        <div class="text-muted fs-12 mb-1">مطالبات معلقة</div>
                        <h4 class="mb-0 text-danger fw-bold">{{ $pendingClaimsCount }}</h4>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="{{ route('admin.warranties.index') }}" class="btn btn-soft-secondary btn-sm">
                        <i class="ri-file-list-3-line me-1"></i> الانتقال لسجل المطالبات
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic Search Results Container -->
<div id="resultsWrapper">
    @if($result !== null)
        @if(!$result['exists'])
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body p-4 text-center">
                    <div class="avatar-lg mx-auto mb-3">
                        <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-36">
                            <i class="ri-close-circle-line"></i>
                        </span>
                    </div>
                    <h5 class="fw-bold text-danger mb-2">السيريال غير مسجل في المنظومة</h5>
                    <p class="text-muted fs-14 mb-3">
                        السيريال <span class="badge bg-light text-dark fs-14 border px-2 py-1">{{ $serial }}</span> غير مسجل في منظومة الضمان الإلكتروني.<br>
                        يرجى التأكد من صحة الرقم الممسوح أو مراجعة فاتورة البيع الأصلية.
                    </p>
                    <button type="button" onclick="document.getElementById('serialInput').focus(); document.getElementById('serialInput').select();" class="btn btn-soft-primary btn-sm">
                        <i class="ri-refresh-line me-1"></i> إعادة المحاولة
                    </button>
                </div>
            </div>
        @else
            @php
                $w = $result['warranty'];
                $product = $w->invoiceItem?->product;
                $invoice = $w->invoiceItem?->invoice;
                $customer = $w->customer;
                $vehicle = $w->customerVehicle;
                $isValid = $result['is_valid'];
                $isExpired = $result['is_expired'];
            @endphp

            <!-- Status Banner -->
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                <div class="p-3 {{ $isValid ? 'bg-success text-white' : ($isExpired ? 'bg-danger text-white' : 'bg-warning text-dark') }}">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="{{ $isValid ? 'ri-checkbox-circle-fill' : 'ri-alert-fill' }} fs-24"></i>
                            <div>
                                <h5 class="mb-0 fw-bold {{ $isValid || $isExpired ? 'text-white' : 'text-dark' }}">
                                    {{ $result['message'] }}
                                </h5>
                                <span class="fs-12 opacity-75">
                                    رقم السيريال المعتمد: {{ $w->serial_number }}
                                </span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            @if($isValid)
                                <span class="badge bg-white text-success fs-13 px-3 py-2 shadow-xs fw-bold">
                                    <i class="ri-time-line me-1"></i> متبقي {{ $result['days_remaining'] }} يوم ضمان
                                </span>
                                <button type="button" 
                                        class="btn btn-light text-danger fw-bold shadow-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#newClaimModal" 
                                        onclick="prefillClaimModal('{{ $w->serial_number }}')">
                                    <i class="ri-alarm-warning-line me-1"></i> صرف استبدال فوري
                                </button>
                            @else
                                <span class="badge bg-white text-danger fs-13 px-3 py-2 shadow-xs fw-bold">
                                    غير مؤهل للاستبدال المجاني
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card-body p-4 bg-body">
                    <div class="row g-4">
                        <!-- 1. مواصفات البطارية -->
                        <div class="col-md-6 col-xl-3">
                            <div class="p-3 rounded-3 bg-light border h-100">
                                <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold fs-13">
                                    <i class="ri-battery-2-charge-line fs-18"></i>
                                    <span>بيانات البطارية</span>
                                </div>
                                <h6 class="fw-bold mb-1 fs-14">{{ $product?->name ?? 'غير محدد' }}</h6>
                                <div class="text-muted fs-12 mb-1">الماركة: <strong>{{ $product?->brand ?? '—' }}</strong></div>
                                <div class="text-muted fs-12 mb-1">السعة: <strong>{{ $product?->capacity_ah ? $product->capacity_ah . ' Ah' : '—' }}</strong></div>
                                <div class="text-muted fs-12">السيريال: <span class="font-monospace text-primary fw-bold">{{ $w->serial_number }}</span></div>
                            </div>
                        </div>

                        <!-- 2. بيانات العميل والمركبة -->
                        <div class="col-md-6 col-xl-3">
                            <div class="p-3 rounded-3 bg-light border h-100">
                                <div class="d-flex align-items-center gap-2 mb-2 text-info fw-bold fs-13">
                                    <i class="ri-user-star-line fs-18"></i>
                                    <span>العميل والمركبة</span>
                                </div>
                                <h6 class="fw-bold mb-1 fs-14">{{ $customer?->name ?? 'عميل نقدي' }}</h6>
                                <div class="text-muted fs-12 mb-1">الهاتف: <strong>{{ $customer?->phone ?? '—' }}</strong></div>
                                <div class="text-muted fs-12 mb-1">السيارة: <strong>{{ $vehicle ? $vehicle->car_brand . ' ' . $vehicle->car_model : '—' }}</strong></div>
                                <div class="text-muted fs-12">رقم اللوحة: <strong>{{ $vehicle?->plate_number ?? '—' }}</strong></div>
                            </div>
                        </div>

                        <!-- 3. بيانات الفاتورة والضمان -->
                        <div class="col-md-6 col-xl-3">
                            <div class="p-3 rounded-3 bg-light border h-100">
                                <div class="d-flex align-items-center gap-2 mb-2 text-warning fw-bold fs-13">
                                    <i class="ri-file-text-line fs-18"></i>
                                    <span>الفاتورة وتواريخ الضمان</span>
                                </div>
                                <div class="text-muted fs-12 mb-1">
                                    رقم الفاتورة: 
                                    @if($invoice)
                                        <a href="{{ route('admin.invoices.show', $invoice->id) }}" class="fw-bold text-primary">
                                            #{{ $invoice->invoice_number }}
                                        </a>
                                    @else
                                        <strong>—</strong>
                                    @endif
                                </div>
                                <div class="text-muted fs-12 mb-1">تاريخ الشراء: <strong>{{ $w->start_date ? \Carbon\Carbon::parse($w->start_date)->format('Y-m-d') : '—' }}</strong></div>
                                <div class="text-muted fs-12 mb-1">تاريخ الانتهاء: <strong>{{ $w->end_date ? \Carbon\Carbon::parse($w->end_date)->format('Y-m-d') : '—' }}</strong></div>
                                <div class="text-muted fs-12">حالة القيد: <span class="badge bg-secondary-subtle text-secondary">{{ $w->status }}</span></div>
                            </div>
                        </div>

                        <!-- 4. سجل المطالبات السابقة -->
                        <div class="col-md-6 col-xl-3">
                            <div class="p-3 rounded-3 bg-light border h-100">
                                <div class="d-flex align-items-center gap-2 mb-2 text-danger fw-bold fs-13">
                                    <i class="ri-history-line fs-18"></i>
                                    <span>سجل مطالبات الضمان</span>
                                </div>
                                @if($w->claims && $w->claims->count() > 0)
                                    <span class="badge bg-danger-subtle text-danger mb-2">تم تسجيل {{ $w->claims->count() }} مطالبة مسبقاً</span>
                                    @foreach($w->claims as $claim)
                                        <div class="fs-12 text-muted border-top pt-1 mt-1">
                                            تذكرة #{{ $claim->claim_number }} - {{ $claim->decision }}
                                        </div>
                                    @endforeach
                                @else
                                    <p class="text-muted fs-12 mb-0">لا توجد أي مطالبات سابقة مسجلة على هذه البطارية.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @else
        <!-- Initial Guidance Placeholder -->
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-body p-5 text-center">
                <div class="avatar-lg mx-auto mb-3">
                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-36">
                        <i class="ri-barcode-box-line"></i>
                    </span>
                </div>
                <h5 class="fw-bold mb-1">جاهز للفحص والمطابقة</h5>
                <p class="text-muted fs-13 mb-3">
                    قم بمسح الباركود الخاص بالبطارية أو كتابة السيريال ثم اضغط على زر "فحص الضمان" لعرض كامل بيانات الفاتورة وسريان الضمان.
                </p>
                <div class="d-inline-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm sample-serial-btn" data-serial="SN-ACD-297456">
                        <i class="ri-magic-line me-1"></i> تجربة سيريال: SN-ACD-297456
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm sample-serial-btn" data-serial="SN-CHL-858016">
                        <i class="ri-magic-line me-1"></i> تجربة سيريال: SN-CHL-858016
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Modal: New Instant Warranty Claim -->
<div class="modal fade" id="newClaimModal" tabindex="-1" aria-labelledby="newClaimModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('admin.warranties.claims.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="newClaimModalLabel">فتح تذكرة فحص واستبدال ضمان فوري</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">سيريال البطارية التالفة / المعيبة <span class="text-danger">*</span></label>
                        <input type="text" name="defective_serial" id="modalDefectiveSerial" class="form-control" required placeholder="امسح أو اكتب السيريال">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">الفني القائم بفحص البطارية <span class="text-danger">*</span></label>
                        <select name="technician_id" class="form-select" required>
                            <option value="">-- اختر الفني --</option>
                            @foreach($technicians as $tech)
                            <option value="{{ $tech->id }}">{{ $tech->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">قراءة فولتية البطارية بجهاز التيستر (V) <span class="text-danger">*</span></label>
                        <input type="number" step="0.1" name="battery_voltage_tested" class="form-control" required placeholder="مثال: 10.2">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">قراءة تيار البدء CCA (اختياري)</label>
                        <input type="number" step="1" name="cca_tested" class="form-control" placeholder="مثال: 180">
                    </div>
                    <div class="col-12">
                        <label class="form-label">تقرير الفحص الفني ووصف العيب <span class="text-danger">*</span></label>
                        <textarea name="issue_description" class="form-control" rows="2" required placeholder="مثال: هبوط الجهد عند بدء التشغيل وتلف خلية داخلية..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">القرار الفني المعتمد <span class="text-danger">*</span></label>
                        <select name="decision" id="decisionSelect" class="form-select" required>
                            <option value="replaced">استبدال فوري ببطارية جديدة من المخزن</option>
                            <option value="recharged">إعادة شحن وتسليم نفس البطارية</option>
                            <option value="rejected">رفض الضمان (سوء استخدام / دينامو زائد)</option>
                        </select>
                    </div>
                    <div class="col-md-6 replacement-fields">
                        <label class="form-label">صنف البطارية البديلة <span class="text-danger">*</span></label>
                        <select name="replacement_product_id" class="form-select">
                            <option value="">-- اختر الصنف البديل --</option>
                            @foreach($replacementProducts as $rp)
                            <option value="{{ $rp->id }}">{{ $rp->name }} (مخزون: {{ $rp->current_stock }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 replacement-fields">
                        <label class="form-label">سيريال البطارية الجديدة المنصرفة <span class="text-danger">*</span></label>
                        <input type="text" name="replacement_battery_serial" class="form-control" placeholder="سيريال البطارية البديلة">
                    </div>
                    <div class="col-12">
                        <label class="form-label">سبب رفض الضمان <small class="text-muted">(مطلوب عند اختيار رفض الضمان)</small></label>
                        <textarea name="rejection_reason" class="form-control" rows="2" maxlength="500" placeholder="مثال: كسر في الغطاء / سوء استخدام"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-danger">تأكيد القرار وصرف الاستبدال</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const serialInput = document.getElementById('serialInput');
    const sampleBtns = document.querySelectorAll('.sample-serial-btn');

    sampleBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            serialInput.value = this.dataset.serial;
            document.getElementById('verifyForm').submit();
        });
    });

    const decisionSelect = document.getElementById('decisionSelect');
    const replacementFields = document.querySelectorAll('.replacement-fields');
    if (decisionSelect) {
        decisionSelect.addEventListener('change', function () {
            if (this.value === 'replaced') {
                replacementFields.forEach(el => el.classList.remove('d-none'));
            } else {
                replacementFields.forEach(el => el.classList.add('d-none'));
            }
        });
    }
});

function prefillClaimModal(serial) {
    const input = document.getElementById('modalDefectiveSerial');
    if (input) {
        input.value = serial;
    }
}
</script>
@endsection
