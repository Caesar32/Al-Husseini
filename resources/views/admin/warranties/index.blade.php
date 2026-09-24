@extends('admin.layouts.master')

@section('title', 'إدارة الضمانات ومطالبات البطاريات المعيبة | مركز الحسيني')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">منظومة الضمان الإلكتروني ومطالبات البطاريات المعيبة</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item active">الضمانات والبطاريات التالفة</li>
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

    <!-- Instant Serial Lookup Card -->
    <div class="card shadow-sm border-primary border-2 mb-4">
        <div class="card-body bg-light p-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h5 class="fw-bold text-primary mb-1">
                        <i class="ri-barcode-line me-1"></i> فحص صلاحية وسريان سيريال البطارية فورياً
                    </h5>
                    <p class="text-muted fs-13 mb-3">مرر قارئ الباركود على سيريال البطارية أو اكتب الرقم للاستعلام عن تاريخ الشراء وسريان الضمان وبيانات المركبة.</p>
                    <div class="input-group">
                        <input type="text" id="serialSearchInput" class="form-control form-control-lg fs-15 fw-bold" placeholder="أدخل أو امسح سيريال البطارية هنا...">
                        <button class="btn btn-primary px-4 fw-bold" type="button" id="serialSearchBtn">
                            <i class="ri-search-eye-line me-1"></i> فحص الضمان
                        </button>
                    </div>
                </div>
                <div class="col-lg-4 mt-3 mt-lg-0 text-center">
                    <div class="d-inline-flex gap-3">
                        <div class="p-3 bg-body rounded shadow-sm text-center">
                            <div class="text-muted fs-12 mb-1">شهادات الضمان السارية</div>
                            <h4 class="mb-0 text-success fw-bold">{{ $activeWarrantiesCount }}</h4>
                        </div>
                        <div class="p-3 bg-body rounded shadow-sm text-center">
                            <div class="text-muted fs-12 mb-1">تذاكر بانتظار المورد</div>
                            <h4 class="mb-0 text-danger fw-bold">{{ $pendingClaimsCount }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Verification Result Box -->
            <div id="verificationResultBox" class="mt-3 p-3 bg-body rounded border d-none">
                <!-- Injected via Javascript -->
            </div>
        </div>
    </div>

    <!-- Actions & Filter Bar -->
    <div class="row mb-3">
        <div class="col-sm-6">
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#newClaimModal">
                <i class="ri-alarm-warning-line align-bottom me-1"></i> فتح تذكرة استبدال ضمان فورية
            </button>
        </div>
        <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
            <form method="GET" action="{{ route('admin.warranties.index') }}" class="d-inline-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="سيريال أو رقم تذكرة..." value="{{ request('search') }}">
                <select name="supplier_resolution" class="form-select form-select-sm">
                    <option value="">-- كل حالات المورد --</option>
                    <option value="pending" {{ request('supplier_resolution') === 'pending' ? 'selected' : '' }}>بانتظار المورد (Pending)</option>
                    <option value="settled_replacement" {{ request('supplier_resolution') === 'settled_replacement' ? 'selected' : '' }}>تم التعويض ببطارية</option>
                    <option value="settled_credit_note" {{ request('supplier_resolution') === 'settled_credit_note' ? 'selected' : '' }}>تم التعويض بإشعار خصم</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary">تصفية</button>
            </form>
        </div>
    </div>

    <!-- Claims Table Card -->
    <div class="card shadow-sm">
        <div class="card-header border-bottom-dashed">
            <h5 class="card-title mb-0">سجل مطالبات وتذاكر الضمان والبطاريات التالفة ({{ $claims->total() }})</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>رقم التذكرة</th>
                            <th>العميل</th>
                            <th>سيريال البطارية التالفة</th>
                            <th>القرار الفني</th>
                            <th>البديل المنصرف</th>
                            <th>متابعة المورد</th>
                            <th>الفني الفاحص</th>
                            <th>تاريخ الاستلام</th>
                            <th class="text-center">إجراء التسوية</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($claims as $claim)
                        <tr>
                            <td class="fw-bold text-primary">{{ $claim->claim_number ?? "#{$claim->id}" }}</td>
                            <td>{{ $claim->warranty?->customer?->name ?? '---' }}</td>
                            <td>
                                <span class="badge bg-danger-subtle text-danger fs-12">{{ $claim->defective_battery_serial }}</span>
                                <div class="fs-10 text-muted">فولت: {{ $claim->battery_voltage_tested }}V</div>
                            </td>
                            <td>
                                @if($claim->decision === 'replaced')
                                <span class="badge bg-success">استبدال فوري</span>
                                @elseif($claim->decision === 'recharged')
                                <span class="badge bg-info">إعادة شحن</span>
                                @elseif($claim->decision === 'rejected')
                                <span class="badge bg-danger">مرفوض</span>
                                @else
                                <span class="badge bg-secondary">{{ $claim->decision }}</span>
                                @endif
                            </td>
                            <td>
                                @if($claim->replacement_battery_serial)
                                <span class="badge bg-primary-subtle text-primary fs-12">{{ $claim->replacement_battery_serial }}</span>
                                <div class="fs-10 text-muted">{{ $claim->replacementProduct?->name }}</div>
                                @else
                                <span class="text-muted">---</span>
                                @endif
                            </td>
                            <td>
                                @if($claim->supplier_resolution === 'pending')
                                <span class="badge bg-warning-subtle text-warning">بانتظار المندوب</span>
                                @elseif($claim->supplier_resolution === 'settled_replacement')
                                <span class="badge bg-success-subtle text-success">تم استلام بديل</span>
                                @elseif($claim->supplier_resolution === 'settled_credit_note')
                                <span class="badge bg-info-subtle text-info">تم إشعار الخصم</span>
                                @else
                                <span class="badge bg-secondary-subtle text-secondary">{{ $claim->supplier_resolution }}</span>
                                @endif
                            </td>
                            <td>{{ $claim->technician?->full_name ?? '---' }}</td>
                            <td>{{ $claim->claim_date->format('Y-m-d') }}</td>
                            <td class="text-center">
                                @if($claim->supplier_resolution === 'pending')
                                <button type="button" class="btn btn-sm btn-soft-success settle-btn"
                                        data-claim-id="{{ $claim->id }}"
                                        data-claim-number="{{ $claim->claim_number }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#settleSupplierModal">
                                    <i class="ri-check-line align-middle"></i> تسوية المورد
                                </button>
                                @else
                                <span class="badge bg-light text-muted">تمت التسوية</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">لا توجد مطالبات ضمان مسجلة.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3 d-flex justify-content-end">
                {{ $claims->links() }}
            </div>
        </div>
    </div>
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
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-danger">تأكيد القرار وصرف الاستبدال</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Settle Claim With Supplier -->
<div class="modal fade" id="settleSupplierModal" tabindex="-1" aria-labelledby="settleSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="settleForm" action="" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="settleSupplierModalLabel">تسوية مطالبة الضمان مع مندوب المورد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">تذكرة الضمان:</label>
                    <input type="text" id="settleClaimNumberDisplay" class="form-control" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">نوع تسوية المورد <span class="text-danger">*</span></label>
                    <select name="action" class="form-select" id="settleActionSelect" required>
                        <option value="settled_replacement">تعويض شحنة: استلام بطارية جديدة بديلة من المورد (زيادة المخزون)</option>
                        <option value="settled_credit_note">إشعار خصم مالي: خصم قيمة البطارية من كشف حساب المورد</option>
                        <option value="sent_to_supplier">تم تسليم البطارية للمندوب وبانتظار التقرير</option>
                        <option value="rejected">رفض المورد للضمان</option>
                    </select>
                </div>
                <div class="mb-3" id="creditAmountGroup">
                    <label class="form-label">مبلغ إشعار الخصم المالي المعتمد (ج.م)</label>
                    <input type="number" step="0.01" name="credit_amount" class="form-control" placeholder="0.00">
                </div>
                <div class="mb-3">
                    <label class="form-label">ملاحظات ورقم إذن استلام المورد</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="بيان التسوية"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-success">تأكيد التسوية وإغلاق التذكرة</button>
            </div>
        </form>
    </div>
</div>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('serialSearchInput');
    const searchBtn = document.getElementById('serialSearchBtn');
    const resultBox = document.getElementById('verificationResultBox');

    function verifySerial() {
        const serial = searchInput.value.trim();
        if (!serial) return;

        resultBox.classList.remove('d-none');
        resultBox.innerHTML = '<div class="text-muted"><i class="ri-loader-4-line spin"></i> جاري الفحص في قاعدة البيانات...</div>';

        fetch("{{ route('admin.warranties.verify') }}?serial_number=" + encodeURIComponent(serial))
            .then(res => res.json())
            .then(data => {
                if (!data.exists) {
                    resultBox.innerHTML = `
                        <div class="alert alert-danger mb-0">
                            <strong><i class="ri-close-circle-line me-1"></i> السيريال غير مسجل:</strong> ${data.message}
                        </div>
                    `;
                } else {
                    const statusClass = data.is_valid ? 'alert-success' : 'alert-warning';
                    resultBox.innerHTML = `
                        <div class="alert ${statusClass} mb-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="fw-bold mb-1"><i class="ri-shield-check-line me-1"></i> ${data.message}</h6>
                                    <div>العميل: <strong>${data.warranty.customer ? data.warranty.customer.name : 'عميل نقدي'}</strong> (${data.warranty.customer ? data.warranty.customer.phone : ''})</div>
                                    <div>تاريخ الانتهاء: <strong>${data.warranty.end_date}</strong> (المتبقي: ${data.days_remaining} يوم)</div>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="openClaimModal('${serial}')">
                                        <i class="ri-alarm-warning-line me-1"></i> فتح تذكرة استبدال فوري
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(() => {
                resultBox.innerHTML = '<div class="alert alert-danger mb-0">حدث خطأ أثناء الاتصال بقاعدة البيانات.</div>';
            });
    }

    searchBtn.addEventListener('click', verifySerial);
    searchInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            verifySerial();
        }
    });

    window.openClaimModal = function(serial) {
        document.getElementById('modalDefectiveSerial').value = serial;
        const modal = new bootstrap.Modal(document.getElementById('newClaimModal'));
        modal.show();
    };

    // Settle supplier modal handling
    document.querySelectorAll('.settle-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const claimId = this.getAttribute('data-claim-id');
            const claimNum = this.getAttribute('data-claim-number');
            document.getElementById('settleClaimNumberDisplay').value = claimNum;
            document.getElementById('settleForm').action = "/admin/warranties/claims/" + claimId + "/settle";
        });
    });
});
</script>
@endsection
@endsection
