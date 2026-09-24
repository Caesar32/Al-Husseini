@extends('admin.layouts.master')

@section('title', 'دليل الموردين وشركات توزيع البطاريات | مركز الحسيني')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">دليل الموردين وشركات البطاريات</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item active">الموردون</li>
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

    <!-- Action Bar -->
    <div class="row mb-3">
        <div class="col-sm-6">
            <button type="button" class="btn btn-success add-btn" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                <i class="ri-add-line align-bottom me-1"></i> إضافة مورد جديد
            </button>
            <a href="{{ route('admin.purchases.create') }}" class="btn btn-primary ms-2">
                <i class="ri-shopping-cart-line align-bottom me-1"></i> تسجيل فاتورة توريد
            </a>
        </div>
        <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
            <form method="GET" action="{{ route('admin.suppliers.index') }}" class="d-inline-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="بحث باسم المورد أو الشركة..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-sm btn-soft-secondary">بحث</button>
            </form>
        </div>
    </div>

    <!-- Suppliers Table Card -->
    <div class="card shadow-sm">
        <div class="card-header border-bottom-dashed d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">قائمة الموردين المعتمدين ({{ $suppliers->total() }})</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">اسم الشركة / المورد</th>
                            <th scope="col">المسؤول وجهة الاتصال</th>
                            <th scope="col">رقم الهاتف</th>
                            <th scope="col">الرصيد المستحق (الدائن)</th>
                            <th scope="col">سقف الائتمان</th>
                            <th scope="col">عدد الأصناف</th>
                            <th scope="col">الحالة</th>
                            <th scope="col" class="text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($suppliers as $supplier)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <a href="{{ route('admin.suppliers.show', $supplier) }}" class="fw-bold text-primary">
                                    {{ $supplier->company_name }}
                                </a>
                            </td>
                            <td>{{ $supplier->name }}</td>
                            <td>{{ $supplier->phone }}</td>
                            <td>
                                <span class="badge {{ $supplier->current_balance > 0 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }} fs-12 fw-bold">
                                    {{ number_format($supplier->current_balance, 2) }} ج.م
                                </span>
                            </td>
                            <td>{{ number_format($supplier->credit_limit, 2) }} ج.م</td>
                            <td>
                                <span class="badge bg-info-subtle text-info">{{ $supplier->products->count() }} صنف</span>
                            </td>
                            <td>
                                @if($supplier->is_active)
                                <span class="badge bg-success">نشط</span>
                                @else
                                <span class="badge bg-danger">معطل</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-soft-secondary btn-sm dropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ri-more-fill align-middle"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a href="{{ route('admin.suppliers.show', $supplier) }}" class="dropdown-item"><i class="ri-eye-fill align-bottom me-2 text-muted"></i> عرض الملف والكتالوج</a></li>
                                        <li><a href="{{ route('admin.suppliers.ledger', $supplier) }}" class="dropdown-item"><i class="ri-file-list-3-line align-bottom me-2 text-muted"></i> كشف حساب الأستاذ</a></li>
                                        <li><a href="{{ route('admin.suppliers.edit', $supplier) }}" class="dropdown-item"><i class="ri-pencil-fill align-bottom me-2 text-muted"></i> تعديل البيانات</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">لا يوجد موردون مسجلون حالياً.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-3 d-flex justify-content-end">
                {{ $suppliers->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Supplier -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('admin.suppliers.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="addSupplierModalLabel">تسجيل شركة توريد / مورد جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">اسم شركة التوريد <span class="text-danger">*</span></label>
                        <input type="text" name="company_name" class="form-control" required placeholder="مثال: شركة كلورايد مصر">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">اسم جهة الاتصال / المسؤول <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="مثال: م. أحمد عبد العزيز">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">رقم الهاتف الرئيسي <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" required placeholder="010XXXXXXXX">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">رقم هاتف إضافي / واتساب</label>
                        <input type="text" name="alt_phone" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">سقف المديونية / الائتمان المتاح (ج.م) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="credit_limit" class="form-control" value="0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">الرقم الضريبي</label>
                        <input type="text" name="tax_number" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">السجل التجاري</label>
                        <input type="text" name="commercial_register" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">العنوان والمقر الرئيسي</label>
                        <input type="text" name="address" class="form-control" placeholder="دمياط - المنطقة الصناعية">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-success">حفظ وتأكيد المورد</button>
            </div>
        </form>
    </div>
</div>
@endsection
