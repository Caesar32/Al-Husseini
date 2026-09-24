@extends('admin.layouts.master')

@section('title', 'فواتير المشتريات وشحنات التوريد | مركز الحسيني')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">فواتير المشتريات وشحنات التوريد</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">المشتريات</li>
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

    <!-- Actions & Filter Bar -->
    <div class="row mb-3">
        <div class="col-sm-6">
            <a href="{{ route('admin.purchases.create') }}" class="btn btn-success">
                <i class="ri-add-line align-bottom me-1"></i> تسجيل فاتورة توريد جديدة
            </a>
            <a href="{{ route('admin.suppliers.index') }}" class="btn btn-soft-primary ms-2">
                <i class="ri-truck-line align-bottom me-1"></i> دليل الموردين
            </a>
        </div>
        <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
            <form method="GET" action="{{ route('admin.purchases.index') }}" class="d-inline-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="رقم الفاتورة أو المورد..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-sm btn-primary">بحث</button>
            </form>
        </div>
    </div>

    <!-- Purchases Table -->
    <div class="card shadow-sm">
        <div class="card-header border-bottom-dashed">
            <h5 class="card-title mb-0">سجل فواتير التوريد ({{ $invoices->total() }})</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>المورد</th>
                            <th>تاريخ الاستلام</th>
                            <th>المستلم</th>
                            <th>الصافي الإجمالي</th>
                            <th>المسدد</th>
                            <th>المتبقي (آجل)</th>
                            <th>حالة السداد</th>
                            <th class="text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoices as $inv)
                        <tr>
                            <td>
                                <a href="{{ route('admin.purchases.show', $inv) }}" class="fw-bold text-primary">
                                    {{ $inv->invoice_number }}
                                </a>
                            </td>
                            <td>{{ $inv->supplier?->company_name ?? '---' }}</td>
                            <td>{{ $inv->invoice_date->format('Y-m-d') }}</td>
                            <td>{{ $inv->receivedByUser?->name ?? '---' }}</td>
                            <td class="fw-bold">{{ number_format($inv->final_amount, 2) }} ج.م</td>
                            <td class="text-success">{{ number_format($inv->paid_amount, 2) }} ج.م</td>
                            <td class="text-danger fw-bold">{{ number_format($inv->remaining_amount, 2) }} ج.م</td>
                            <td>
                                @if($inv->payment_status === 'paid')
                                <span class="badge bg-success-subtle text-success">مسددة بالكامل</span>
                                @elseif($inv->payment_status === 'partially_paid')
                                <span class="badge bg-warning-subtle text-warning">سداد جزئي</span>
                                @else
                                <span class="badge bg-danger-subtle text-danger">غير مسددة</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.purchases.show', $inv) }}" class="btn btn-sm btn-soft-primary">
                                    <i class="ri-eye-line"></i> التفاصيل
                                </a>
                                <a href="{{ route('admin.purchases.print', $inv) }}" class="btn btn-sm btn-soft-secondary ms-1">
                                    <i class="ri-printer-line"></i> طباعة
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">لا توجد فواتير مشتريات مسجلة.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3 d-flex justify-content-end">
                {{ $invoices->links() }}
            </div>
        </div>
    </div>
@endsection
