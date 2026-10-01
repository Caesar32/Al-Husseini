@extends('admin.layouts.master')

@section('title', 'الملف الشخصي | مجموعة الحسيني')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">الملف الشخصي</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">الملف الشخصي</li>
                </ol>
            </div>
        </div>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success alert-border-left alert-dismissible fade show" role="alert">
        <i class="ri-check-double-line me-2 align-middle fs-16"></i> {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-border-left alert-dismissible fade show" role="alert">
        <i class="ri-error-warning-line me-2 align-middle fs-16"></i>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="position-relative mx-n4 mt-n4">
    <div class="profile-wid-bg profile-setting-img">
        <div class="p-4" style="background: linear-gradient(135deg, #0d3b66 0%, #001e3d 100%); height: 160px; border-radius: 0 0 12px 12px;">
            <div class="text-white-50 fs-13">
                <i class="ri-shield-user-line me-1"></i> بوابة إدارة حساب المستخدم وتأمينه
            </div>
        </div>
    </div>
</div>

<div class="row mt-n5">
    <div class="col-xxl-3 col-lg-4">
        <div class="card card-body p-4 text-center shadow-sm">
            <div class="profile-user position-relative d-inline-block mx-auto mb-3">
                <img src="{{ $user->avatarUrl() }}" class="rounded-circle avatar-xl img-thumbnail user-profile-image shadow" alt="user-profile-image">
                <div class="avatar-xs p-0 rounded-circle profile-photo-edit position-absolute end-0 bottom-0">
                    <label for="profile-img-file-input" class="profile-photo-edit avatar-xs">
                        <span class="avatar-title rounded-circle bg-light text-body shadow cursor-pointer">
                            <i class="ri-camera-fill"></i>
                        </span>
                    </label>
                </div>
            </div>
            <h5 class="fs-17 mb-1 fw-bold text-dark">{{ $user->name }}</h5>
            <p class="text-muted mb-2 fs-13">{{ $user->email }}</p>
            <div>
                <span class="badge bg-primary-subtle text-primary fs-12 px-3 py-1 mb-2">
                    <i class="ri-shield-star-line me-1"></i> {{ $user->roles->first()?->name ?? 'super-admin' }}
                </span>
            </div>
            <div class="border-top pt-3 text-start">
                <div class="d-flex justify-content-between mb-2 fs-13">
                    <span class="text-muted">الفرع التابع له:</span>
                    <span class="fw-semibold text-dark">{{ $user->branch?->name ?? 'الإدارة العامة المركزية' }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2 fs-13">
                    <span class="text-muted">حالة الحساب:</span>
                    <span class="badge bg-success-subtle text-success">نشط ومفعل</span>
                </div>
                <div class="d-flex justify-content-between fs-13">
                    <span class="text-muted">تاريخ الانضمام:</span>
                    <span class="text-muted">{{ $user->created_at ? $user->created_at->format('Y-m-d') : '2024-01-01' }}</span>
                </div>
            </div>

            <!-- Upload Avatar Form (Hidden Trigger) -->
            <form action="{{ route('admin.profile.avatar') }}" method="POST" enctype="multipart/form-data" id="avatar-form" class="d-none">
                @csrf
                <input type="file" name="avatar" id="profile-img-file-input" accept="image/*" onchange="document.getElementById('avatar-form').submit();">
            </form>
        </div>

        <div class="card shadow-sm">
            <div class="card-header border-bottom">
                <h6 class="card-title mb-0 fs-14 fw-bold"><i class="ri-lock-password-line me-1 text-primary"></i> أمان وسرعة الوصول</h6>
            </div>
            <div class="card-body">
                <a href="{{ route('admin.lockscreen') }}" class="btn btn-outline-warning w-100 mb-2">
                    <i class="ri-lock-line me-1 align-middle"></i> قفل الشاشة مؤقتاً
                </a>
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-soft-danger w-100">
                        <i class="ri-logout-box-r-line me-1 align-middle"></i> تسجيل الخروج من النظام
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xxl-9 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <ul class="nav nav-tabs-custom rounded card-header-tabs border-bottom-0" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active fw-semibold" data-bs-toggle="tab" href="#personalDetails" role="tab">
                            <i class="ri-user-settings-line me-1 align-middle"></i> البيانات الشخصية
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" data-bs-toggle="tab" href="#changePassword" role="tab">
                            <i class="ri-key-2-line me-1 align-middle"></i> كلمة المرور والتأمين
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" data-bs-toggle="tab" href="#rolesPermissions" role="tab">
                            <i class="ri-shield-keyhole-line me-1 align-middle"></i> الصلاحيات والأذونات
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body p-4">
                <div class="tab-content">
                    <!-- Personal Info Tab -->
                    <div class="tab-pane active" id="personalDetails" role="tabpanel">
                        <form action="{{ route('admin.profile.info') }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label for="nameInput" class="form-label fw-semibold">الاسم بالكامل <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" id="nameInput" value="{{ old('name', $user->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label for="emailInput" class="form-label fw-semibold">البريد الإلكتروني <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" id="emailInput" value="{{ old('email', $user->email) }}" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label for="phoneInput" class="form-label fw-semibold">رقم الهاتف / الموبايل</label>
                                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" id="phoneInput" placeholder="مثال: 01012345678" value="{{ old('phone', $user->phone) }}">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label fw-semibold">الفرع المعين عليه</label>
                                    <input type="text" class="form-control bg-light" value="{{ $user->branch?->name ?? 'فرع دمياط الجديدة - شارع المحجوب' }}" readonly>
                                    <small class="text-muted fs-11">لا يمكن تغيير فرع العمل إلا من خلال المشرف العام.</small>
                                </div>
                                <div class="col-12 mt-3 text-end">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="ri-save-3-line align-middle me-1"></i> حفظ تعديلات البيانات الشخصية
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password Tab -->
                    <div class="tab-pane" id="changePassword" role="tabpanel">
                        <form action="{{ route('admin.profile.password') }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="row g-2">
                                <div class="col-lg-4 mb-3">
                                    <label for="oldpasswordInput" class="form-label fw-semibold">كلمة المرور الحالية <span class="text-danger">*</span></label>
                                    <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" id="oldpasswordInput" placeholder="أدخل كلمة المرور الحالية" required>
                                    @error('current_password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-lg-4 mb-3">
                                    <label for="newpasswordInput" class="form-label fw-semibold">كلمة المرور الجديدة <span class="text-danger">*</span></label>
                                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" id="newpasswordInput" placeholder="8 أحرف وأرقام على الأقل" required>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-lg-4 mb-3">
                                    <label for="confirmpasswordInput" class="form-label fw-semibold">تأكيد كلمة المرور الجديدة <span class="text-danger">*</span></label>
                                    <input type="password" name="password_confirmation" class="form-control" id="confirmpasswordInput" placeholder="أعد إدخال كلمة المرور" required>
                                </div>
                                <div class="col-lg-12">
                                    <div class="alert alert-info border-0 d-flex align-items-center">
                                        <i class="ri-information-line fs-18 me-2"></i>
                                        <span>ينصح باستخدام كلمة مرور معقدة تحتوي على مزيج من الأحرف الكبيرة والصغيرة والأرقام لضمان أعلى مستويات الأمان.</span>
                                    </div>
                                </div>
                                <div class="col-12 text-end mt-2">
                                    <button type="submit" class="btn btn-success px-4">
                                        <i class="ri-shield-check-line align-middle me-1"></i> تحديث كلمة المرور
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Roles & Permissions Tab -->
                    <div class="tab-pane" id="rolesPermissions" role="tabpanel">
                        <div class="mb-3">
                            <h6 class="fw-bold mb-2 text-dark">الأدوار المخصصة لك (Assigned Roles):</h6>
                            <div class="d-flex flex-wrap gap-2">
                                @forelse ($user->roles as $role)
                                    <span class="badge bg-primary fs-13 py-2 px-3">
                                        <i class="ri-shield-user-fill me-1"></i> {{ $role->name }}
                                    </span>
                                @empty
                                    <span class="text-muted">لا توجد أدوار مخصصة مباشرة.</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="border-top pt-3 mt-4">
                            <h6 class="fw-bold mb-3 text-dark">نطاق الصلاحيات الفعلي في النظام:</h6>
                            @if ($user->hasRole('super-admin'))
                                <div class="alert alert-warning alert-border-left" role="alert">
                                    <i class="ri-vip-crown-fill me-2 fs-18 align-middle"></i>
                                    <strong>حساب مشرف عام (Super Administrator):</strong> يمتلك هذا الحساب حق الوصول الكامل والمطلق لكافة أقسام وفروع وإعدادات وعمليات نظام الحسيني تلقائياً.
                                </div>
                            @endif

                            <div class="row g-2">
                                @php
                                    $allPermissions = $user->getAllPermissions();
                                @endphp
                                @forelse ($allPermissions as $perm)
                                    <div class="col-md-4 col-sm-6">
                                        <div class="p-2 border rounded bg-light d-flex align-items-center">
                                            <i class="ri-checkbox-circle-fill text-success fs-16 me-2"></i>
                                            <span class="fs-12 text-dark fw-medium">{{ $perm->name }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12 text-muted">
                                        تتم إدارة الصلاحيات ديناميكياً من خلال دور المشرف العام.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
