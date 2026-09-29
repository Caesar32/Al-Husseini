@extends('admin.layouts.master')

@section('title', 'تحديد صلاحيات الدور: ' . $role->display_label)

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
            <h4 class="mb-sm-0 fw-bold"><i class="ri-shield-check-line text-primary me-1"></i> ضبط صلاحيات: {{ $role->display_label }}</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.roles.index') }}">الأدوار والصلاحيات</a></li>
                    <li class="breadcrumb-item active">تعديل الصلاحيات</li>
                </ol>
            </div>
        </div>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
    <strong>يرجى تصحيح الأخطاء التالية:</strong>
    <ul class="mb-0 mt-1">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($role->name === 'cashier')
<div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-3 mb-4" role="alert">
    <div class="avatar-sm flex-shrink-0">
        <span class="avatar-title bg-info-subtle text-info rounded-circle fs-20">
            <i class="ri-information-line"></i>
        </span>
    </div>
    <div>
        <h6 class="alert-heading fw-bold mb-1">إرشادات دور الكاشير:</h6>
        <p class="mb-0 small text-muted">
            دور الكاشير مخصص لعمليات البيع اليومية المباشرة. عند تسجيل دخول الكاشير، يتم توجيهه تلقائياً إلى <strong>شاشة نقطة البيع (POS)</strong>.
            إذا لم يتم منح الكاشير صلاحية <code>invoices.discount</code> (منح الخصومات وتعديل الأسعار)، فلن يستطيع تطبيق أي خصم أو تخفيض في الفاتورة إلا بإدخال <strong>كود موافقة المشرف</strong>.
        </p>
    </div>
</div>
@endif

<form action="{{ route('admin.roles.update', $role) }}" method="POST" id="rolePermissionsForm">
    @csrf
    @method('PUT')

    <!-- Header Actions Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-md flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle text-primary rounded-3 fs-24">
                            <i class="ri-shield-keyhole-line"></i>
                        </span>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">{{ $role->display_label }} <code class="text-primary fs-13 bg-light px-2 py-0 rounded">({{ $role->name }})</code></h5>
                        <p class="text-muted small mb-0">{{ $role->description_text }}</p>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnSelectAllGlobal">
                        <i class="ri-checkbox-multiple-line me-1"></i> تحديد الكل
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDeselectAllGlobal">
                        <i class="ri-checkbox-blank-line me-1"></i> إلغاء تحديد الكل
                    </button>
                    <a href="{{ route('admin.roles.index') }}" class="btn btn-light btn-sm">إلغاء</a>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">
                        <i class="ri-save-line me-1"></i> حفظ التعديلات
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Permission Categories -->
    <div class="row g-4">
        @foreach($groupedPermissions as $groupKey => $group)
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-light-subtle border-0 d-flex align-items-center justify-content-between py-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge {{ $group['badge'] }} p-2 rounded-circle">
                            <i class="{{ $group['icon'] }} fs-16"></i>
                        </span>
                        <h6 class="card-title mb-0 fw-bold fs-14">{{ $group['title'] }}</h6>
                    </div>
                    <button type="button" class="btn btn-ghost-primary btn-sm fs-12 px-2 py-1 btn-toggle-category" data-target="{{ $groupKey }}">
                        <i class="ri-check-line me-1"></i> تحديد الكل بالقسم
                    </button>
                </div>

                <div class="card-body pt-2">
                    <div class="list-group list-group-flush" id="group-{{ $groupKey }}">
                        @foreach($group['permissions'] as $permKey => $perm)
                        @php
                            $isChecked = in_array($permKey, $rolePermissions, true);
                            $isSensitive = $perm['is_sensitive'] ?? false;
                        @endphp
                        <label class="list-group-item d-flex align-items-start justify-content-between py-3 px-2 border-dashed permission-row cursor-pointer" for="perm_{{ Str::slug($permKey) }}">
                            <div class="me-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-bold fs-13 text-dark">{{ $perm['label'] }}</span>
                                    <code class="text-muted fs-11 bg-light px-1 rounded">{{ $permKey }}</code>
                                    @if($isSensitive)
                                    <span class="badge bg-danger-subtle text-danger fs-10 px-1 py-0">
                                        <i class="ri-alert-line me-1"></i>صلاحية حساسة
                                    </span>
                                    @endif
                                </div>
                                <p class="text-muted small mb-0">{{ $perm['desc'] }}</p>
                            </div>

                            <div class="form-check form-switch form-switch-md form-switch-success mt-1">
                                <input class="form-check-input perm-checkbox category-{{ $groupKey }}" 
                                       type="checkbox" 
                                       role="switch" 
                                       id="perm_{{ Str::slug($permKey) }}" 
                                       name="permissions[]" 
                                       value="{{ $permKey }}"
                                       {{ $isChecked ? 'checked' : '' }}>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Bottom Save Sticky / Bar -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body d-flex align-items-center justify-content-between">
            <span class="text-muted small">تأكد من مراجعة الصلاحيات قبل الحفظ لتجنب منح صلاحيات غير مرغوبة.</span>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.roles.index') }}" class="btn btn-light">رجوع</a>
                <button type="submit" class="btn btn-primary px-4 fw-semibold">
                    <i class="ri-save-line me-1"></i> حفظ وتحديث الصلاحيات
                </button>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Select All Global
    document.getElementById('btnSelectAllGlobal')?.addEventListener('click', function () {
        document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = true);
    });

    // Deselect All Global
    document.getElementById('btnDeselectAllGlobal')?.addEventListener('click', function () {
        document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
    });

    // Toggle by category
    document.querySelectorAll('.btn-toggle-category').forEach(btn => {
        btn.addEventListener('click', function () {
            const cat = this.getAttribute('data-target');
            const checkboxes = document.querySelectorAll('.category-' + cat);
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
        });
    });
});
</script>
@endpush
@endsection
