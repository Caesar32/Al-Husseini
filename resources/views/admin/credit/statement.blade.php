@extends('admin.layouts.master')

@section('title', 'كشف حساب آجل - ' . $customer->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">كشف حساب مديونية الآجل التفصيلي</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.credit.index') }}">إدارة الآجل</a></li>
                        <li class="breadcrumb-item active">{{ $customer->name }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ri-checkbox-circle-line me-1 align-middle"></i>
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Actions -->
    <div class="row mb-3 no-print">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <a href="{{ route('admin.credit.index') }}" class="btn btn-outline-secondary">
                <i class="ri-arrow-right-line me-1 align-bottom"></i> العودة لقائمة عملاء الآجل
            </a>
            <div class="d-flex gap-2">
                @can('credit.settle')
                    @if($customer->current_credit_balance > 0)
                        <button type="button" class="btn btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#settleModal">
                            <i class="ri-hand-coin-line me-1 align-bottom"></i> تحصيل دفعة نقدية الآن
                        </button>
                    @endif
                @endcan
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="ri-printer-line me-1 align-bottom"></i> طباعة كشف الحساب
                </button>
            </div>
        </div>
    </div>

    <!-- Customer Overview Card -->
    <div class="card shadow-sm mb-4 border-start border-4 {{ $customer->current_credit_balance > 0 ? 'border-danger' : 'border-success' }}">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center mb-2">
                        <div class="avatar-md me-3">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-24">
                                <i class="ri-user-star-line"></i>
                            </span>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-1">{{ $customer->name }}</h4>
                            <p class="text-muted mb-0 font-monospace fs-14">
                                <i class="ri-phone-line me-1"></i> {{ $customer->phone ?? 'بدون رقم مسجل' }}
                                @if($customer->email)
                                    | <i class="ri-mail-line ms-2 me-1"></i> {{ $customer->email }}
                                @endif
                            </p>
                        </div>
                    </div>
                    @if($customer->vehicles->count() > 0)
                        <div class="mt-2 fs-13 text-muted">
                            <strong>المركبات المسجلة:</strong>
                            @foreach($customer->vehicles as $v)
                                <span class="badge bg-light text-dark border me-1">
                                    {{ $v->car_brand }} {{ $v->car_model }} ({{ $v->plate_number }})
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="col-md-6 mt-3 mt-md-0">
                    <div class="row text-center g-2">
                        <div class="col-6">
                            <div class="p-3 bg-light rounded">
                                <span class="text-muted fs-12 d-block mb-1">الرصيد المدين الحالي (المستحق):</span>
                                <h3 class="fw-extrabold mb-0 font-monospace {{ $customer->current_credit_balance > 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($customer->current_credit_balance, 2) }} ج.م
                                </h3>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-light rounded">
                                <span class="text-muted fs-12 d-block mb-1">الحد الائتماني المسموح به:</span>
                                <h3 class="fw-bold mb-0 font-monospace text-secondary">
                                    {{ number_format($customer->credit_limit, 2) }} ج.م
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Outstanding Invoices -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="ri-file-list-3-line me-1 text-primary"></i> فواتير المبيعات بالآجل غير المسددة بالكامل</h5>
            <span class="badge bg-warning-subtle text-warning font-monospace fs-12">{{ $customer->invoices->count() }} فواتير</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-12 text-muted">
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>التاريخ</th>
                            <th>الأصناف المباعة</th>
                            <th class="text-end">إجمالي الفاتورة</th>
                            <th class="text-end">المسدد منها</th>
                            <th class="text-end">المتبقي (آجل)</th>
                            <th class="text-center">معاينة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customer->invoices as $inv)
                            <tr>
                                <td><span class="badge bg-light text-dark border font-monospace fw-bold">{{ $inv->invoice_number }}</span></td>
                                <td class="fs-12 text-muted font-monospace">{{ $inv->created_at?->format('Y-m-d') }}</td>
                                <td>
                                    @foreach($inv->items as $it)
                                        <span class="badge bg-light text-dark me-1 border fs-11">{{ $it->product?->name }} (×{{ $it->quantity }})</span>
                                    @endforeach
                                </td>
                                <td class="text-end font-monospace fw-semibold">{{ number_format($inv->total_amount, 2) }} ج.م</td>
                                <td class="text-end font-monospace text-success">{{ number_format($inv->paid_amount, 2) }} ج.م</td>
                                <td class="text-end font-monospace fw-bold text-danger">{{ number_format($inv->remaining_amount, 2) }} ج.م</td>
                                <td class="text-center">
                                    <a href="{{ route('admin.invoices.show', $inv) }}" class="btn btn-sm btn-soft-primary">
                                        <i class="ri-eye-line align-middle"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">لا توجد فواتير آجل مفتوحة حالياً لهذا العميل.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Credit Ledger Entries History -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="ri-history-line me-1 text-info"></i> سجل حركات دفتر أستاذ الآجل (Ledger)</h5>
            <small class="text-muted">مقياس دقيق لجميع حركات الإضافة والخصم والرصيد التراكمي</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light fs-12 text-muted">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>التاريخ والوقت</th>
                            <th>نوع الحركة</th>
                            <th>رقم السند / المرجع</th>
                            <th class="text-end">مدين (+) إضافة دين</th>
                            <th class="text-end">دائن (-) تحصيل/سداد</th>
                            <th class="text-end">الرصيد بعد الحركة</th>
                            <th>المسؤول / المحصل</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customer->creditLedgers as $index => $entry)
                            @php
                                $typeBadge = match($entry->entry_type) {
                                    'sale_on_credit' => ['label' => 'فاتورة بيع بالآجل', 'class' => 'bg-danger-subtle text-danger'],
                                    'payment_collection', 'payment_received' => ['label' => 'سداد دفعة نقدية', 'class' => 'bg-success-subtle text-success'],
                                    'sales_return_refund' => ['label' => 'مرتجع مبيعات', 'class' => 'bg-info-subtle text-info'],
                                    default => ['label' => $entry->entry_type, 'class' => 'bg-secondary-subtle text-secondary'],
                                };
                                $isDebit = $entry->entry_type === 'sale_on_credit';
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td class="fs-12 font-monospace">{{ $entry->created_at?->format('Y-m-d H:i') }}</td>
                                <td><span class="badge {{ $typeBadge['class'] }}">{{ $typeBadge['label'] }}</span></td>
                                <td class="font-monospace text-muted">{{ $entry->receipt_number ?? '-' }}</td>
                                <td class="text-end font-monospace {{ $isDebit ? 'text-danger fw-bold' : 'text-muted' }}">
                                    {{ $isDebit ? '+' . number_format($entry->amount, 2) . ' ج.م' : '-' }}
                                </td>
                                <td class="text-end font-monospace {{ !$isDebit ? 'text-success fw-bold' : 'text-muted' }}">
                                    {{ !$isDebit ? '-' . number_format($entry->amount, 2) . ' ج.م' : '-' }}
                                </td>
                                <td class="text-end font-monospace fw-bold text-dark">
                                    {{ number_format($entry->balance_after, 2) }} ج.م
                                </td>
                                <td><small class="text-muted">{{ $entry->collectedByUser?->name ?? 'النظام' }}</small></td>
                                <td><small class="text-muted">{{ $entry->notes ?? '-' }}</small></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">لا توجد حركات دفتر أستاذ مسجلة لهذا العميل حتى الآن.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Settle Payment -->
@can('credit.settle')
<div class="modal fade" id="settleModal" tabindex="-1" aria-labelledby="settleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('admin.credit.settle') }}" method="POST">
                @csrf
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                <div class="modal-header bg-success text-white p-3">
                    <h5 class="modal-title fw-bold text-white fs-15" id="settleModalLabel">
                        <i class="ri-hand-coin-line me-1"></i> تحصيل دفعة من مديونية: {{ $customer->name }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-warning mb-3">
                        <div class="d-flex justify-content-between">
                            <span>الرصيد المدين الحالي:</span>
                            <strong class="font-monospace text-danger fs-15">{{ number_format($customer->current_credit_balance, 2) }} ج.م</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="settleAmount" class="form-label fw-bold">مبلغ التحصيل (ج.م): <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" max="{{ $customer->current_credit_balance }}" class="form-control font-monospace fs-16 fw-bold" id="settleAmount" name="amount" value="{{ $customer->current_credit_balance }}" required>
                    </div>

                    <div class="mb-3">
                        <label for="settleMethod" class="form-label fw-bold">طريقة الدفع: <span class="text-danger">*</span></label>
                        <select class="form-select" id="settleMethod" name="payment_method" required>
                            <option value="cash" selected>نقداً في الخزينة (Cash)</option>
                            <option value="card">بطاقة بنكية / فيزا (POS Terminal)</option>
                            <option value="bank_transfer">تحويل بنكي / إنستاباي (InstaPay)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="settleReceipt" class="form-label fw-bold">رقم الإيصال / السند الورقي (اختياري):</label>
                        <input type="text" class="form-control font-monospace" id="settleReceipt" name="receipt_number" placeholder="مثال: REC-1049">
                    </div>

                    <div class="mb-3">
                        <label for="settleNotes" class="form-label fw-bold">ملاحظات التحصيل:</label>
                        <textarea class="form-control" id="settleNotes" name="notes" rows="2" placeholder="ملاحظات إضافية على عملية السداد..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success fw-bold">
                        <i class="ri-check-line me-1"></i> تأكيد تسجيل التحصيل
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endsection
