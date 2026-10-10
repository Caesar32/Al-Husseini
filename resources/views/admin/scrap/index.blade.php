@extends('admin.layouts.master')

@section('title', 'مخزن بطاريات الكهنة وتجارة الرصاص | مركز الحسيني')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">مخزن بطاريات الكهنة وتجارة الرصاص</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item active">مخزن الكهنة</li>
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

    <!-- Metrics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-start-primary border-3 shadow-sm">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-1">إجمالي بطاريات الكهنة بالمخزن</p>
                    <h4 class="fs-22 fw-bold mb-0 text-primary">{{ number_format($metrics['total_units']) }} <span class="fs-12 text-muted fw-normal">بطارية قديمة</span></h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-start-success border-3 shadow-sm">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-1">القيمة التقديرية لرصيد الكهنة</p>
                    <h4 class="fs-22 fw-bold mb-0 text-success"><x-compact-money :amount="$metrics['total_scrap_value']" /></h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-start-warning border-3 shadow-sm">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-1">الوزن التقديري لكتلة الرصاص</p>
                    <h4 class="fs-22 fw-bold mb-0 text-dark">{{ number_format($metrics['total_metric_tons'], 3) }} <span class="fs-12 text-muted fw-normal">طن تقريباً</span></h4>
                    <small class="text-muted">({{ number_format($metrics['total_lead_weight_kg'], 1) }} كجم)</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-start-info border-3 shadow-sm">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-1">توزيع السعات المجمعة</p>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        @foreach($metrics['capacity_breakdown'] as $cap)
                        <span class="badge bg-light text-dark border">{{ $cap['capacity'] }}: {{ $cap['count'] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions & Pricing Tiers Bar -->
    <div class="row mb-3">
        <div class="col-sm-6">
            <button type="button" class="btn btn-danger" id="openSellModalBtn" disabled data-bs-toggle="modal" data-bs-target="#sellScrapModal">
                <i class="ri-truck-line align-bottom me-1"></i> بيع وتفريغ شحنة كهنة لمصنع تدوير (<span id="selectedCountDisplay">0</span>)
            </button>
            <button type="button" class="btn btn-soft-secondary ms-2" data-bs-toggle="modal" data-bs-target="#pricingTiersModal">
                <i class="ri-settings-4-line align-bottom me-1"></i> جدول تسعير الكهنة المعتمد
            </button>
        </div>
        <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
            <form method="GET" action="{{ route('admin.scrap.index') }}" class="d-inline-flex gap-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- كل الحالات --</option>
                    <option value="in_stock" {{ request('status', 'in_stock') === 'in_stock' ? 'selected' : '' }}>موجود بالمخزن (In Stock)</option>
                    <option value="sold_to_factory" {{ request('status') === 'sold_to_factory' ? 'selected' : '' }}>تم البيع للمصنع</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary">تصفية</button>
            </form>
        </div>
    </div>

    <!-- Inventory Table Card -->
    <div class="card shadow-sm">
        <div class="card-header border-bottom-dashed d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">سجل بطاريات الكهنة المستلمة بالفرع ({{ $inventory->total() }})</h5>
            <div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllBtn">تحديد الكل بالمخزن</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 4%;">
                                <input type="checkbox" id="checkAllMaster" class="form-check-input">
                            </th>
                            <th>#</th>
                            <th>سعة البطارية (Ah)</th>
                            <th>قيمة الخصم المعتمدة</th>
                            <th>الوزن التقديري للرصاص</th>
                            <th>رقم فاتورة البيع المرتبطة</th>
                            <th>العميل المستبدل</th>
                            <th>الفني المستلم</th>
                            <th>الحالة</th>
                            <th>تاريخ الاستلام</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inventory as $item)
                        <tr>
                            <td>
                                @if($item->status === 'in_stock')
                                <input type="checkbox" class="form-check-input scrap-checkbox" value="{{ $item->id }}" data-value="{{ $item->scrap_value }}">
                                @else
                                <i class="ri-check-line text-muted"></i>
                                @endif
                            </td>
                            <td>{{ $loop->iteration }}</td>
                            <td><span class="badge bg-dark fs-12">{{ $item->capacity_ah }}</span></td>
                            <td class="fw-bold text-success">{{ number_format($item->scrap_value, 2) }} ج.م</td>
                            <td>{{ $item->lead_weight_kg ? number_format($item->lead_weight_kg, 1) . ' كجم' : '---' }}</td>
                            <td>
                                @if($item->invoice)
                                <a href="{{ route('admin.invoices.show', $item->invoice) }}" class="fw-bold">
                                    {{ $item->invoice->invoice_number }}
                                </a>
                                @else
                                <span class="text-muted">---</span>
                                @endif
                            </td>
                            <td>{{ $item->invoice?->customer?->name ?? 'عميل نقدي' }}</td>
                            <td>{{ $item->receivedByEmployee?->full_name ?? '---' }}</td>
                            <td>
                                @if($item->status === 'in_stock')
                                <span class="badge bg-success">بالمخزن</span>
                                @else
                                <span class="badge bg-secondary">تم البيع ({{ $item->batch_number }})</span>
                                @endif
                            </td>
                            <td>{{ $item->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">لا توجد بطاريات كهنة في هذا السجل.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3 d-flex justify-content-end">
                {{ $inventory->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Modal: Sell Scrap Batch -->
<div class="modal fade" id="sellScrapModal" tabindex="-1" aria-labelledby="sellScrapModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.scrap.sell_batch') }}" class="modal-content" id="sellBatchForm">
            @csrf
            <div id="hiddenInputsContainer"></div>
            <div class="modal-header">
                <h5 class="modal-title" id="sellScrapModalLabel">بيع شحنة كهنة وتفريغ المخزن</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2">
                    عدد البطاريات المحددة للبيع: <strong id="modalBatchCount">0</strong> بطارية<br>
                    إجمالي تكلفة استلامها من العملاء: <strong id="modalBatchCost">0.00</strong> ج.م
                </div>
                <div class="mb-3">
                    <label class="form-label">اسم شركة التدوير / مصنع الرصاص / التاجر <span class="text-danger">*</span></label>
                    <input type="text" name="buyer_name" class="form-control" required placeholder="مثال: مصنع الشرق لإعادة تدوير الرصاص">
                </div>
                <div class="mb-3">
                    <label class="form-label">رقم هاتف المشتري</label>
                    <input type="text" name="buyer_phone" class="form-control" placeholder="010XXXXXXXX">
                </div>
                <div class="mb-3">
                    <label class="form-label">إجمالي مبلغ بيع الشحنة المتفق عليه (ج.م) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="total_amount" class="form-control" required placeholder="0.00">
                </div>
                <div class="mb-3">
                    <label class="form-label">طريقة استلام المبلغ <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select" required>
                        <option value="cash">نقداً في الخزينة</option>
                        <option value="bank_transfer">تحويل بنكي</option>
                        <option value="cheque">شيك بنكي</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">ملاحظات على الشحنة أو رقم السيارة الشاحنة</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="بيان الشحنة"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-danger">تأكيد البيع وتفريغ الشحنة</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Pricing Tiers -->
<div class="modal fade" id="pricingTiersModal" tabindex="-1" aria-labelledby="pricingTiersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('admin.scrap.update_tiers') }}" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title" id="pricingTiersModalLabel">جدول تسعير بطاريات الكهنة المعتمد (حسب سعة الأمبير)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted fs-13 mb-3">هذا الجدول يحدد بدقة وصارمة الخصم الممنوح للعميل عند استبدال بطاريته القديمة، ويمنع أي تعديل يدوي من الكاشير.</p>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>الشريحة والتصنيف</th>
                                <th>نطاق السعة (Ah)</th>
                                <th>سعر الخصم المعتمد للكاشير (ج.م)</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tiers as $tier)
                            <tr>
                                <td>
                                    <strong>{{ $tier->tier_name }}</strong>
                                    <input type="hidden" name="tiers[{{ $loop->index }}][id]" value="{{ $tier->id }}">
                                </td>
                                <td>{{ $tier->capacity_min_ah }} - {{ $tier->capacity_max_ah }} Ah</td>
                                <td>
                                    <input type="number" step="10" name="tiers[{{ $loop->index }}][default_scrap_price]" class="form-control" value="{{ $tier->default_scrap_price }}" required>
                                </td>
                                <td>
                                    <span class="badge {{ $tier->is_active ? 'bg-success' : 'bg-danger' }}">
                                        {{ $tier->is_active ? 'مفعل' : 'معطل' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
                <button type="submit" class="btn btn-primary">حفظ الأسعار الجديدة</button>
            </div>
        </form>
    </div>
</div>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('.scrap-checkbox');
    const checkAllMaster = document.getElementById('checkAllMaster');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const openSellBtn = document.getElementById('openSellModalBtn');
    const selectedCountDisplay = document.getElementById('selectedCountDisplay');
    const modalBatchCount = document.getElementById('modalBatchCount');
    const modalBatchCost = document.getElementById('modalBatchCost');
    const hiddenInputs = document.getElementById('hiddenInputsContainer');

    function updateSelection() {
        let count = 0;
        let totalCost = 0;
        hiddenInputs.innerHTML = '';

        checkboxes.forEach(cb => {
            if (cb.checked) {
                count++;
                totalCost += parseFloat(cb.getAttribute('data-value')) || 0;

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'scrap_battery_ids[]';
                input.value = cb.value;
                hiddenInputs.appendChild(input);
            }
        });

        selectedCountDisplay.innerText = count;
        modalBatchCount.innerText = count;
        modalBatchCost.innerText = totalCost.toFixed(2);

        if (count > 0) {
            openSellBtn.removeAttribute('disabled');
        } else {
            openSellBtn.setAttribute('disabled', 'disabled');
        }
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelection);
    });

    if (checkAllMaster) {
        checkAllMaster.addEventListener('change', function () {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateSelection();
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            checkboxes.forEach(cb => cb.checked = true);
            if (checkAllMaster) checkAllMaster.checked = true;
            updateSelection();
        });
    }
});
</script>
@endsection
@endsection
