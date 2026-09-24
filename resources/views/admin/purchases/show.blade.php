@extends('admin.layouts.master')

@section('title', 'تفاصيل فاتورة المشتريات - ' . $purchase->invoice_number)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">تفاصيل فاتورة المشتريات والتوريد</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.purchases.index') }}">المشتريات</a></li>
                        <li class="breadcrumb-item active">{{ $purchase->invoice_number }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Header Actions -->
    <div class="row mb-3">
        <div class="col-12 text-end">
            <a href="{{ route('admin.purchases.print', $purchase) }}" class="btn btn-primary">
                <i class="ri-printer-line align-bottom me-1"></i> طباعة إذن الاستلام
            </a>
            <a href="{{ route('admin.suppliers.ledger', $purchase->supplier) }}" class="btn btn-soft-secondary ms-2">
                <i class="ri-file-list-3-line align-bottom me-1"></i> كشف حساب المورد
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header border-bottom-dashed d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">فاتورة توريد رقم: <span class="text-primary">{{ $purchase->invoice_number }}</span></h5>
            <span class="badge {{ $purchase->payment_status === 'paid' ? 'bg-success' : 'bg-warning' }} fs-12">
                {{ $purchase->payment_status }}
            </span>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <p class="text-muted mb-1">المورد:</p>
                    <h6 class="fs-14 fw-bold">{{ $purchase->supplier?->company_name }}</h6>
                </div>
                <div class="col-md-3">
                    <p class="text-muted mb-1">تاريخ الفاتورة:</p>
                    <h6 class="fs-14">{{ $purchase->invoice_date->format('Y-m-d') }}</h6>
                </div>
                <div class="col-md-3">
                    <p class="text-muted mb-1">المستخدم المستلم:</p>
                    <h6 class="fs-14">{{ $purchase->receivedByUser?->name }}</h6>
                </div>
                <div class="col-md-3">
                    <p class="text-muted mb-1">الفرع:</p>
                    <h6 class="fs-14">{{ $purchase->branch?->name }}</h6>
                </div>
            </div>

            <!-- Items Table -->
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>المنتج / الصنف</th>
                            <th>التصنيف</th>
                            <th>الكمية الموردة</th>
                            <th>سعر تكلفة الشراء</th>
                            <th>الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchase->items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="fw-bold">{{ $item->product?->name }}</td>
                            <td>{{ $item->product?->category?->name ?? '---' }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format($item->unit_cost_price, 2) }} ج.م</td>
                            <td class="fw-bold">{{ number_format($item->total_cost_price, 2) }} ج.م</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="row justify-content-end">
                <div class="col-md-4">
                    <div class="bg-light p-3 rounded">
                        <div class="d-flex justify-content-between mb-2">
                            <span>المجموع الفرعي:</span>
                            <strong>{{ number_format($purchase->subtotal, 2) }} ج.م</strong>
                        </div>
                        @if($purchase->tax_amount > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span>الضريبة:</span>
                            <strong>+{{ number_format($purchase->tax_amount, 2) }} ج.م</strong>
                        </div>
                        @endif
                        @if($purchase->discount_amount > 0)
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>الخصم:</span>
                            <strong>-{{ number_format($purchase->discount_amount, 2) }} ج.م</strong>
                        </div>
                        @endif
                        <hr>
                        <div class="d-flex justify-content-between mb-2 fs-15">
                            <span class="fw-bold">الصافي الإجمالي:</span>
                            <strong class="text-primary">{{ number_format($purchase->final_amount, 2) }} ج.م</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>المسدد:</span>
                            <strong>{{ number_format($purchase->paid_amount, 2) }} ج.م</strong>
                        </div>
                        <div class="d-flex justify-content-between text-danger fw-bold">
                            <span>المتبقي آجل:</span>
                            <strong>{{ number_format($purchase->remaining_amount, 2) }} ج.م</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
