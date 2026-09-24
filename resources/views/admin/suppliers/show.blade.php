@extends('admin.layouts.master')

@section('title', 'ملف المورد وكتالوج الأصناف - ' . $supplier->company_name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">الملف التعريفي والكتالوج للمورد</h4>
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

    <!-- Actions Bar -->
    <div class="row mb-3">
        <div class="col-12 text-end">
            <a href="{{ route('admin.purchases.create') }}" class="btn btn-success">
                <i class="ri-shopping-cart-line align-bottom me-1"></i> تسجيل فاتورة توريد جديدة
            </a>
            <a href="{{ route('admin.suppliers.ledger', $supplier) }}" class="btn btn-primary ms-2">
                <i class="ri-file-list-3-line align-bottom me-1"></i> كشف حساب الأستاذ المالي
            </a>
            <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="btn btn-soft-secondary ms-2">
                <i class="ri-pencil-line align-bottom me-1"></i> تعديل البيانات والكتالوج
            </a>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-start border-start-primary border-3">
                <div class="card-body">
                    <p class="text-muted mb-1 fs-12">الرصيد الدائن المستحق</p>
                    <h4 class="fs-20 fw-bold mb-0 text-danger">{{ number_format($stats['current_balance'], 2) }} ج.م</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-start border-start-info border-3">
                <div class="card-body">
                    <p class="text-muted mb-1 fs-12">إجمالي فواتير التوريد</p>
                    <h4 class="fs-20 fw-bold mb-0 text-primary">{{ number_format($stats['total_purchases'], 2) }} ج.م</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-start border-start-success border-3">
                <div class="card-body">
                    <p class="text-muted mb-1 fs-12">المسدد للمورد</p>
                    <h4 class="fs-20 fw-bold mb-0 text-success">{{ number_format($stats['total_paid'], 2) }} ج.م</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-start border-start-warning border-3">
                <div class="card-body">
                    <p class="text-muted mb-1 fs-12">سقف الائتمان المسموح به</p>
                    <h4 class="fs-20 fw-bold mb-0 text-dark">{{ number_format($stats['credit_limit'], 2) }} ج.م</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Details & Linked Catalog -->
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">بيانات الاتصال والتسجيل</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>شركة التوريد:</strong> {{ $supplier->company_name }}</p>
                    <p class="mb-2"><strong>المسؤول:</strong> {{ $supplier->name }}</p>
                    <p class="mb-2"><strong>الهاتف:</strong> {{ $supplier->phone }}</p>
                    @if($supplier->alt_phone)<p class="mb-2"><strong>هاتف بديل:</strong> {{ $supplier->alt_phone }}</p>@endif
                    @if($supplier->email)<p class="mb-2"><strong>البريد:</strong> {{ $supplier->email }}</p>@endif
                    @if($supplier->tax_number)<p class="mb-2"><strong>الرقم الضريبي:</strong> {{ $supplier->tax_number }}</p>@endif
                    @if($supplier->commercial_register)<p class="mb-2"><strong>السجل التجاري:</strong> {{ $supplier->commercial_register }}</p>@endif
                    @if($supplier->address)<p class="mb-2"><strong>العنوان:</strong> {{ $supplier->address }}</p>@endif
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">كتالوج الأصناف الموردة من هذا المورد ({{ $supplier->products->count() }})</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>الصنف / المنتج</th>
                                    <th>كود المورد (SKU)</th>
                                    <th>آخر سعر شراء</th>
                                    <th>مورد رئيسي؟</th>
                                    <th>المخزون الحالي</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($supplier->products as $prod)
                                <tr>
                                    <td class="fw-bold">{{ $prod->name }}</td>
                                    <td>{{ $prod->pivot->supplier_sku ?? '---' }}</td>
                                    <td class="text-success fw-bold">{{ number_format($prod->pivot->last_purchase_price, 2) }} ج.م</td>
                                    <td>
                                        @if($prod->pivot->is_primary_supplier)
                                        <span class="badge bg-success">نعم (مفضل)</span>
                                        @else
                                        <span class="badge bg-secondary">ثانوي</span>
                                        @endif
                                    </td>
                                    <td>{{ $prod->current_stock }} قطعة</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">لم يتم ربط أصناف بعد بهذا المورد.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
