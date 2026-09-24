@extends('admin.layouts.master')

@section('title', 'كشف حساب أستاذ المورد - ' . $supplier->company_name)

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">كشف حساب الأستاذ المالي للمورد</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.suppliers.index') }}">الموردون</a></li>
                        <li class="breadcrumb-item active">{{ $supplier->company_name }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-start-primary border-3 shadow-sm">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-1">الرصيد الدائن المستحق للمورد</p>
                    <h4 class="fs-20 fw-bold mb-0 text-danger">{{ number_format($statement['current_balance'], 2) }} ج.م</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-start-info border-3 shadow-sm">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-1">إجمالي التوريدات والفواتير</p>
                    <h4 class="fs-20 fw-bold mb-0 text-primary">{{ number_format($statement['total_purchases'], 2) }} ج.م</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-start-success border-3 shadow-sm">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-1">إجمالي المدفوعات المسددة</p>
                    <h4 class="fs-20 fw-bold mb-0 text-success">{{ number_format($statement['total_payments'], 2) }} ج.م</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-start-warning border-3 shadow-sm">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-1">سقف الائتمان المسموح</p>
                    <h4 class="fs-20 fw-bold mb-0 text-dark">{{ number_format($supplier->credit_limit, 2) }} ج.م</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions & Filter Bar -->
    <div class="row mb-3">
        <div class="col-sm-6">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                <i class="ri-hand-coin-line align-bottom me-1"></i> تسجيل سند صرف / سداد دفعة
            </button>
            <button onclick="window.print()" class="btn btn-soft-secondary ms-2">
                <i class="ri-printer-line align-bottom me-1"></i> طباعة كشف الحساب
            </button>
        </div>
        <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
            <form method="GET" action="{{ route('admin.suppliers.ledger', $supplier) }}" class="d-inline-flex gap-2">
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                <button type="submit" class="btn btn-sm btn-primary">تصفية</button>
            </form>
        </div>
    </div>

    <!-- Ledger Entries Table -->
    <div class="card shadow-sm">
        <div class="card-header border-bottom-dashed">
            <h5 class="card-title mb-0">حركات دفتر الأستاذ المالي ({{ $statement['entries']->count() }})</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 15%;">التاريخ والوقت</th>
                            <th style="width: 20%;">نوع الحركة</th>
                            <th style="width: 15%;">المبلغ</th>
                            <th style="width: 15%;">الرصيد السابق</th>
                            <th style="width: 15%;">الرصيد بعد الحركة</th>
                            <th style="width: 15%;">البيان والملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($statement['entries'] as $entry)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $entry->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                @if($entry->entry_type === 'purchase_invoice')
                                <span class="badge bg-danger-subtle text-danger fs-12">فاتورة مشتريات (مديونية)</span>
                                @elseif($entry->entry_type === 'supplier_payment')
                                <span class="badge bg-success-subtle text-success fs-12">سند صرف (سداد)</span>
                                @elseif($entry->entry_type === 'purchase_return')
                                <span class="badge bg-info-subtle text-info fs-12">مرتجع بضاعة</span>
                                @else
                                <span class="badge bg-secondary fs-12">تسوية حساب</span>
                                @endif
                            </td>
                            <td class="fw-bold {{ $entry->entry_type === 'purchase_invoice' ? 'text-danger' : 'text-success' }}">
                                {{ number_format($entry->amount, 2) }} ج.م
                            </td>
                            <td>{{ number_format($entry->balance_before, 2) }} ج.م</td>
                            <td class="fw-bold text-dark">{{ number_format($entry->balance_after, 2) }} ج.م</td>
                            <td>
                                <div>{{ $entry->notes ?? '---' }}</div>
                                @if($entry->payment_method)
                                <small class="text-muted">طريقة الدفع: {{ $entry->payment_method }}</small>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">لا توجد حركات مالية مسجلة لهذا المورد.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Record Supplier Payment -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-labelledby="recordPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.suppliers.payments', $supplier) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="recordPaymentModalLabel">تسجيل سند صرف / سداد دفعة للمورد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">المورد المستلم</label>
                    <input type="text" class="form-control" value="{{ $supplier->company_name }}" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">الرصيد المستحق حالياً</label>
                    <input type="text" class="form-control text-danger fw-bold" value="{{ number_format($supplier->current_balance, 2) }} ج.م" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">مبلغ الدفعة المسددة (ج.م) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0.00">
                </div>
                <div class="mb-3">
                    <label class="form-label">طريقة السداد <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select" required>
                        <option value="cash">نقداً من الخزينة (Cash)</option>
                        <option value="bank_transfer">تحويل بنكي (Bank Transfer)</option>
                        <option value="cheque">شيك بنكي (Cheque)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">رقم إيصال السداد / سند الصرف</label>
                    <input type="text" name="receipt_number" class="form-control" placeholder="RC-XXXX">
                </div>
                <div class="mb-3">
                    <label class="form-label">ملاحظات وبيان الصرف</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="بيان سند الصرف"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-success">تأكيد وسداد الدفعة</button>
            </div>
        </form>
    </div>
</div>
@endsection
