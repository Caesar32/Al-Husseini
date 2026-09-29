@extends('admin.layouts.master')

@section('title', 'إضافة دور وصلاحيات جديدة')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
            <h4 class="mb-sm-0 fw-bold"><i class="ri-add-box-line text-primary me-1"></i> إضافة دور مخصص جديد</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.roles.index') }}">الأدوار والصلاحيات</a></li>
                    <li class="breadcrumb-item active">إضافة دور جديد</li>
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

<form action="{{ route('admin.roles.store') }}" method="POST" id="createRoleForm">
    @csrf

    <!-- Role Metadata Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light-subtle">
            <h5 class="card-title mb-0 fw-bold">البيانات التعريفية للدور</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="roleName" class="form-label fw-semibold">المعرف البرمجي للدور (Slug) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="roleName" name="name" value="{{ old('name') }}" placeholder="مثال: store-keeper أو sales-rep" required>
                    <div class="form-text text-muted">حروف وأرقام وشرطات بالإنجليزية فقط (مثال: supervisor, cashier-night).</div>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="displayName" class="form-label fw-semibold">المسمى الوظيفي بالعربية</label>
                    <input type="text" class="form-control" id="displayName" name="display_name" value="{{ old('display_name') }}" placeholder="مثال: أمين المخزن أو مندوب مبيعات خارجي">
                    <div class="form-text text-muted">اسم توضيحي يظهر في شاشات الإدارة وتقارير الموظفين.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-0">تحديد الصلاحيات الممنوحة لهذا الدور</h5>
                    <p class="text-muted small mb-0">اختر الصلاحيات التي يستطيع شاغل هذا الدور الوصول إليها وتنفيذها.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnSelectAllGlobal">
                        <i class="ri-checkbox-multiple-line me-1"></i> تحديد الكل
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDeselectAllGlobal">
                        <i class="ri-checkbox-blank-line me-1"></i> إلغاء تحديد الكل
                    </button>
                    <a href="{{ route('admin.roles.index') }}" class="btn btn-light btn-sm">إلغاء</a>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">
                        <i class="ri-save-line me-1"></i> حفظ وإنشاء الدور
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
                    <div class="list-group list-group-flush">
                        @foreach($group['permissions'] as $permKey => $perm)
                        @php
                            $isChecked = is_array(old('permissions')) && in_array($permKey, old('permissions'), true);
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
            <span class="text-muted small">سيصبح الدور متاحاً فوراً لتعيينه للمستخدمين بعد الحفظ.</span>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.roles.index') }}" class="btn btn-light">رجوع</a>
                <button type="submit" class="btn btn-primary px-4 fw-semibold">
                    <i class="ri-save-line me-1"></i> حفظ وإنشاء الدور
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
