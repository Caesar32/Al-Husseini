@extends('admin.layouts.master')

@section('title', 'سجل فواتير المبيعات والضمان | مركز الحسيني لبطاريات السيارات')

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'المبيعات', 'title' => 'سجل فواتير المبيعات وشهادات الضمان'])

    <!-- Top KPI Stats (Dynamic based on selected date and filters) -->
    <div class="row mb-3">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate border-start border-primary border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">إجمالي قيمة المبيعات</p>
                            <h3 class="fs-22 fw-extrabold text-primary mb-1 font-monospace">
                                <x-compact-money :amount="$stats['total_sales'] ?? 0" />
                            </h3>
                            <small class="text-muted fs-11">
                                @if(request('date_from') || request('date_to'))
                                    خلال الفترة المحددة
                                @else
                                    إجمالي الفترة الحالية
                                @endif
                            </small>
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
                            <h3 class="fs-22 fw-extrabold text-success mb-1 font-monospace">
                                {{ number_format($stats['invoices_count'] ?? 0) }}
                            </h3>
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
                            <h3 class="fs-22 fw-extrabold text-warning mb-1 font-monospace">
                                {{ number_format($stats['credit_invoices_count'] ?? 0) }}
                            </h3>
                            <small class="text-danger fw-bold fs-11">
                                متبقي: <x-compact-money :amount="$stats['total_remaining_credit'] ?? 0" />
                            </small>
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
                            <h3 class="fs-22 fw-extrabold text-info mb-1 font-monospace">
                                {{ number_format($stats['scrap_count'] ?? 0) }} بطارية
                            </h3>
                            <small class="text-muted fs-11">تم استبدالها وخصمها للعملاء</small>
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

    <!-- Advanced Filter & Date Range Selection Bar -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ request()->url() }}" id="invoicesFilterForm">
                @if(request('branch_id'))
                    <input type="hidden" name="branch_id" value="{{ request('branch_id') }}">
                @endif
                <!-- Row 1: Date Range, Search & Filter Controls -->
                <div class="row g-2 align-items-center">
                    <!-- Date From -->
                    <div class="col-xl-2 col-md-3 col-sm-6">
                        <label class="form-label fs-12 fw-bold text-muted mb-1">
                            <i class="ri-calendar-line me-1 text-primary"></i> من تاريخ (Date From)
                        </label>
                        <input type="date" name="date_from" id="filter_date_from" class="form-control form-control-sm"
                            value="{{ request('date_from') }}">
                    </div>

                    <!-- Date To -->
                    <div class="col-xl-2 col-md-3 col-sm-6">
                        <label class="form-label fs-12 fw-bold text-muted mb-1">
                            <i class="ri-calendar-check-line me-1 text-primary"></i> إلى تاريخ (Date To)
                        </label>
                        <input type="date" name="date_to" id="filter_date_to" class="form-control form-control-sm"
                            value="{{ request('date_to') }}">
                    </div>

                    <!-- Status Filter -->
                    <div class="col-xl-2 col-md-3 col-sm-6">
                        <label class="form-label fs-12 fw-bold text-muted mb-1">
                            <i class="ri-filter-3-line me-1 text-primary"></i> حالة السداد
                        </label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">جميع الحالات</option>
                            <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>مسددة بالكامل (خالصة)</option>
                            <option value="partially_paid" {{ request('status') === 'partially_paid' ? 'selected' : '' }}>سداد جزئي</option>
                            <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>غير مسددة (آجل)</option>
                            <option value="refunded" {{ request('status') === 'refunded' ? 'selected' : '' }}>مرتجع بالكامل</option>
                            <option value="partially_refunded" {{ request('status') === 'partially_refunded' ? 'selected' : '' }}>مرتجع جزئي</option>
                            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>ملغاة</option>
                        </select>
                    </div>

                    <!-- Search Input -->
                    <div class="col-xl-3 col-md-3 col-sm-6">
                        <label class="form-label fs-12 fw-bold text-muted mb-1">
                            <i class="ri-search-line me-1 text-primary"></i> بحث سريع
                        </label>
                        <input type="text" name="search" class="form-control form-control-sm"
                            placeholder="رقم الفاتورة، اسم العميل، الهاتف..." value="{{ request('search') }}">
                    </div>

                    <!-- Action Buttons -->
                    <div class="col-xl-3 col-md-12 text-md-end pt-md-3 pt-2">
                        <div class="d-flex gap-2 justify-content-xl-end flex-wrap">
                            <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">
                                <i class="ri-filter-fill align-middle me-1"></i> تطبيق الفلتر
                            </button>
                            @if(request('date_from') || request('date_to') || request('status') || request('search') || request('branch_id'))
                                <a href="{{ request()->url() }}" class="btn btn-sm btn-soft-danger px-2" title="إلغاء جميع الفلاتر">
                                    <i class="ri-refresh-line align-middle me-1"></i> إعادة تعيين
                                </a>
                            @endif
                            <a href="{{ route('admin.pos.index') }}" class="btn btn-sm btn-success fw-bold px-3">
                                <i class="ri-add-circle-line align-middle me-1"></i> فاتورة POS
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportTableToCSV()">
                                <i class="ri-file-excel-2-line align-middle me-1"></i> تصدير Excel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Quick Day Preset Buttons -->
                <div class="row mt-2 pt-2 border-top align-items-center">
                    <div class="col-12 d-flex flex-wrap align-items-center gap-1">
                        <span class="fs-12 text-muted fw-bold me-2">
                            <i class="ri-time-line align-middle"></i> اختصارات الأيام:
                        </span>
                        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 fs-12 {{ request('date_from') === date('Y-m-d') && request('date_to') === date('Y-m-d') ? 'active fw-bold' : '' }}" onclick="applyDatePreset('today')">
                            اليوم
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 fs-12 {{ request('date_from') === date('Y-m-d', strtotime('-1 day')) && request('date_to') === date('Y-m-d', strtotime('-1 day')) ? 'active fw-bold' : '' }}" onclick="applyDatePreset('yesterday')">
                            أمس
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 fs-12" onclick="applyDatePreset('last7')">
                            آخر 7 أيام
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 fs-12" onclick="applyDatePreset('this_month')">
                            هذا الشهر
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 fs-12" onclick="applyDatePreset('clear')">
                            كل التواريخ (مسح)
                        </button>

                        @if(request('date_from') || request('date_to'))
                            <span class="badge bg-primary-subtle text-primary ms-auto fs-12 px-2 py-1">
                                <i class="ri-check-line align-middle me-1"></i> مفلتر: من {{ request('date_from') ?? 'البداية' }} إلى {{ request('date_to') ?? 'الآن' }}
                            </span>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Invoices Table Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="card-title mb-0 fw-bold fs-15">
                            <i class="ri-file-list-3-line me-1 text-primary"></i> سجل فواتير المبيعات الصادرة
                            <span class="badge bg-secondary-subtle text-secondary fs-12 ms-1">
                                {{ $invoices->total() }} فاتورة
                            </span>
                        </h5>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ request()->fullUrlWithQuery(['status' => '']) }}"
                            class="btn btn-xs {{ empty(request('status')) ? 'btn-primary fw-bold' : 'btn-outline-secondary' }}">
                            الكل ({{ $stats['invoices_count'] ?? 0 }})
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['status' => 'paid']) }}"
                            class="btn btn-xs {{ request('status') === 'paid' ? 'btn-success fw-bold' : 'btn-outline-secondary' }}">
                            خالصة ومسددة
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['status' => 'unpaid']) }}"
                            class="btn btn-xs {{ request('status') === 'unpaid' ? 'btn-danger fw-bold' : 'btn-outline-secondary' }}">
                            فواتير الآجل ({{ $stats['credit_invoices_count'] ?? 0 }})
                        </a>
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
                                    <th>السلعة المباعة / البطارية</th>
                                    <th>استبدال قديمة</th>
                                    <th>إجمالي الفاتورة</th>
                                    <th>طريقة الدفع</th>
                                    <th>حالة السداد</th>
                                    <th class="text-center" style="min-width: 140px;">معاينة وطباعة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($invoices as $inv)
                                    @php
                                        $firstItem = $inv->items->first();
                                        $isBattery = $inv->items->contains(fn($it) => $it->product?->is_battery);
                                    @endphp
                                    <tr>
                                        <!-- Invoice Number -->
                                        <td>
                                            <a href="{{ route('admin.invoices.show', $inv) }}" class="fw-bold font-monospace text-primary fs-13">
                                                {{ $inv->invoice_number }}
                                            </a>
                                        </td>

                                        <!-- Date & Time -->
                                        <td>
                                            <span class="text-dark fs-12 fw-semibold d-block">
                                                {{ $inv->created_at->format('Y-m-d') }}
                                            </span>
                                            <small class="text-muted font-monospace fs-11">
                                                {{ $inv->created_at->format('h:i A') }}
                                            </small>
                                        </td>

                                        <!-- Customer -->
                                        <td>
                                            @if($inv->customer)
                                                <strong class="text-dark fs-13 d-block">{{ $inv->customer->name }}</strong>
                                                <small class="text-muted font-monospace">{{ $inv->customer->phone }}</small>
                                            @else
                                                <span class="badge bg-light text-secondary">عميل نقدي</span>
                                            @endif
                                        </td>

                                        <!-- Vehicle -->
                                        <td>
                                            @if($inv->customerVehicle)
                                                <span class="fs-12 fw-semibold text-dark d-block">
                                                    {{ $inv->customerVehicle->car_brand }} {{ $inv->customerVehicle->car_model }}
                                                </span>
                                                <span class="badge bg-light text-secondary font-monospace fs-11">
                                                    {{ $inv->customerVehicle->plate_number }}
                                                </span>
                                            @else
                                                <span class="text-muted fs-12">---</span>
                                            @endif
                                        </td>

                                        <!-- Items / Battery -->
                                        <td>
                                            @if($firstItem && $firstItem->product)
                                                <strong class="fs-12 text-dark d-block">{{ $firstItem->product->name }}</strong>
                                                <div class="d-flex align-items-center gap-1 mt-1">
                                                    @if($firstItem->battery_serial_number)
                                                        <span class="badge bg-info-subtle text-info font-monospace fs-10">
                                                            سيريال: {{ $firstItem->battery_serial_number }}
                                                        </span>
                                                    @endif
                                                    @if($inv->items->count() > 1)
                                                        <span class="badge bg-secondary-subtle text-secondary fs-10">
                                                            +{{ $inv->items->count() - 1 }} صنف
                                                        </span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-muted fs-12">صيانة / خدمات</span>
                                            @endif
                                        </td>

                                        <!-- Scrap Trade-In -->
                                        <td>
                                            @if($inv->scrap_deduction_amount > 0)
                                                <span class="badge bg-success-subtle text-success fs-11">
                                                    <i class="ri-check-line"></i> استبدال ({{ number_format($inv->scrap_deduction_amount, 2) }} ج.م)
                                                </span>
                                            @else
                                                <span class="text-muted fs-11">بدون قديمة</span>
                                            @endif
                                        </td>

                                        <!-- Final Amount -->
                                        <td>
                                            <strong class="text-success font-monospace fs-13">
                                                {{ number_format($inv->final_amount, 2) }} ج.م
                                            </strong>
                                        </td>

                                        <!-- Payment Method -->
                                        <td>
                                            @if($inv->payment_method === 'cash')
                                                <span class="badge bg-success-subtle text-success fs-11">نقدي (كاش)</span>
                                            @elseif($inv->payment_method === 'instapay')
                                                <span class="badge bg-primary-subtle text-primary fs-11">إنستاباي / محفظة</span>
                                            @elseif($inv->payment_method === 'card')
                                                <span class="badge bg-info-subtle text-info fs-11">بطاقة بنكية</span>
                                            @elseif($inv->payment_method === 'credit')
                                                <span class="badge bg-warning text-dark fs-11 fw-bold">آجل ⏱️</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary fs-11">{{ $inv->payment_method }}</span>
                                            @endif
                                        </td>

                                        <!-- Payment Status -->
                                        <td>
                                            @if($inv->status === 'paid' || $inv->remaining_amount <= 0)
                                                <span class="badge bg-success-subtle text-success fs-11">
                                                    <i class="ri-checkbox-circle-line me-1"></i> مسددة بالكامل
                                                </span>
                                            @elseif($inv->status === 'partially_paid')
                                                <span class="badge bg-warning-subtle text-warning fs-11 fw-bold">
                                                    سداد جزئي (متبقي: {{ number_format($inv->remaining_amount, 2) }})
                                                </span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger fs-11 fw-bold">
                                                    غير مسددة ({{ number_format($inv->remaining_amount, 2) }} ج.م)
                                                </span>
                                            @endif
                                        </td>

                                        <!-- Actions -->
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-1">
                                                <a href="{{ route('admin.invoices.show', $inv) }}" class="btn btn-sm btn-soft-primary" title="عرض التفاصيل">
                                                    <i class="ri-eye-line align-middle"></i>
                                                </a>
                                                <a href="{{ route('admin.pos.receipt', $inv) }}" target="_blank" class="btn btn-sm btn-soft-secondary" title="طباعة إيصال حراري (POS)">
                                                    <i class="ri-printer-line align-middle"></i>
                                                </a>
                                                @if($isBattery)
                                                    <a href="{{ route('admin.pos.warranty_cert', $inv) }}" target="_blank" class="btn btn-sm btn-soft-info" title="طباعة شهادة الضمان">
                                                        <i class="ri-shield-check-line align-middle"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-5">
                                            <div class="avatar-md mx-auto mb-3">
                                                <span class="avatar-title bg-light text-muted rounded-circle fs-36">
                                                    <i class="ri-file-search-line"></i>
                                                </span>
                                            </div>
                                            <h6 class="text-muted fw-bold">لا توجد فواتير مبيعات مطابقة لمعايير البحث</h6>
                                            <p class="text-muted fs-12 mb-3">
                                                @if(request('date_from') || request('date_to'))
                                                    لا توجد عمليات مبيعات في الفترة من <strong>{{ request('date_from') }}</strong> إلى <strong>{{ request('date_to') }}</strong>.
                                                @else
                                                    لم يتم تسجيل أي فواتير بعد.
                                                @endif
                                            </p>
                                            @if(request('date_from') || request('date_to') || request('search') || request('status'))
                                                <a href="{{ request()->url() }}" class="btn btn-sm btn-primary">
                                                    <i class="ri-refresh-line me-1 align-middle"></i> إظهار كافة الفواتير
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination Footer -->
                @if($invoices->hasPages())
                    <div class="card-footer bg-transparent border-top py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="text-muted fs-12">
                            عرض من <strong>{{ $invoices->firstItem() }}</strong> إلى <strong>{{ $invoices->lastItem() }}</strong> من إجمالي <strong>{{ $invoices->total() }}</strong> فاتورة
                        </div>
                        <div>
                            {{ $invoices->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
'use strict';

// Apply Quick Date Presets
function applyDatePreset(preset) {
    const today = new Date();
    const dateFromInput = document.getElementById('filter_date_from');
    const dateToInput = document.getElementById('filter_date_to');
    const form = document.getElementById('invoicesFilterForm');

    function formatDate(d) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    if (preset === 'today') {
        const todayStr = formatDate(today);
        dateFromInput.value = todayStr;
        dateToInput.value = todayStr;
    } else if (preset === 'yesterday') {
        const yest = new Date();
        yest.setDate(today.getDate() - 1);
        const yestStr = formatDate(yest);
        dateFromInput.value = yestStr;
        dateToInput.value = yestStr;
    } else if (preset === 'last7') {
        const d7 = new Date();
        d7.setDate(today.getDate() - 6);
        dateFromInput.value = formatDate(d7);
        dateToInput.value = formatDate(today);
    } else if (preset === 'this_month') {
        const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        dateFromInput.value = formatDate(startOfMonth);
        dateToInput.value = formatDate(today);
    } else if (preset === 'clear') {
        dateFromInput.value = '';
        dateToInput.value = '';
    }

    if (form) {
        form.submit();
    }
}

// Export Table to CSV
function exportTableToCSV() {
    const table = document.getElementById('invoicesTable');
    if (!table) return;

    let csv = [];
    const rows = table.querySelectorAll('tr');

    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll('td, th');
        // Exclude the last action column
        for (let j = 0; j < cols.length - 1; j++) {
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/(\s\s+)/gm, ' ').trim();
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        if (row.length > 0) {
            csv.push(row.join(','));
        }
    }

    const csvFile = new Blob(['\uFEFF' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const downloadLink = document.createElement('a');
    const dateStr = new Date().toISOString().split('T')[0];
    downloadLink.download = `فواتير_مبيعات_مركز_الحسيني_${dateStr}.csv`;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>
@endsection
