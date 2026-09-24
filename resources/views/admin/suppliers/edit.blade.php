@extends('admin.layouts.master')

@section('title', 'تعديل بيانات المورد - ' . $supplier->company_name)

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">تعديل بيانات المورد</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.suppliers.index') }}">الموردون</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.suppliers.show', $supplier) }}">{{ $supplier->company_name }}</a></li>
                        <li class="breadcrumb-item active">تعديل</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

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

    <div class="row justify-content-center">
        <div class="col-xxl-8 col-lg-10">
            <form action="{{ route('admin.suppliers.update', $supplier) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="card shadow-sm">
                    <div class="card-header border-bottom-dashed d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">بيانات الشركة ومسؤول الاتصال</h5>
                        <a href="{{ route('admin.suppliers.show', $supplier) }}" class="btn btn-sm btn-soft-secondary">
                            <i class="ri-arrow-go-back-line me-1"></i> العودة للملف
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required fw-semibold">اسم الشركة أو التوكيل <span class="text-danger">*</span></label>
                                <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $supplier->company_name) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required fw-semibold">اسم المندوب أو مسؤول الحساب <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $supplier->name) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required fw-semibold">رقم الهاتف الرئيسي <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone', $supplier->phone) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">هاتف إضافي / واتساب</label>
                                <input type="text" name="alt_phone" class="form-control" value="{{ old('alt_phone', $supplier->alt_phone) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">البريد الإلكتروني</label>
                                <input type="email" name="email" class="form-control" value="{{ old('email', $supplier->email) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required fw-semibold">سقف المديونية والائتمان (ج.م) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="credit_limit" class="form-control" value="{{ old('credit_limit', $supplier->credit_limit) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">الرقم الضريبي</label>
                                <input type="text" name="tax_number" class="form-control" value="{{ old('tax_number', $supplier->tax_number) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">السجل التجاري</label>
                                <input type="text" name="commercial_register" class="form-control" value="{{ old('commercial_register', $supplier->commercial_register) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">عنوان المقر أو المخزن</label>
                                <textarea name="address" rows="2" class="form-control">{{ old('address', $supplier->address) }}</textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch form-switch-md">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" id="isActiveSwitch" name="is_active" value="1" {{ old('is_active', $supplier->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold ms-2" for="isActiveSwitch">حساب المورد نشط وجاهز للتعامل والتوريد</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light d-flex justify-content-between">
                        <a href="{{ route('admin.suppliers.show', $supplier) }}" class="btn btn-outline-secondary">إلغاء</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="ri-save-line align-bottom me-1"></i> حفظ التعديلات
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
