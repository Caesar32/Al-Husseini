@extends('admin.layouts.master')

@section('title', 'إدارة الأدوار والصلاحيات')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
            <h4 class="mb-sm-0 fw-bold"><i class="ri-shield-keyhole-line text-primary me-1"></i> إدارة الأدوار وتحديد الصلاحيات</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">الأدوار والصلاحيات</li>
                </ol>
            </div>
        </div>
    </div>
</div>

@if(session('status'))
<div class="alert alert-success alert-dismissible alert-label-icon label-arrow fade show shadow-sm" role="alert">
    <i class="ri-check-double-line label-icon"></i><strong>تم بنجاح!</strong> {{ session('status') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('warning'))
<div class="alert alert-warning alert-dismissible alert-label-icon label-arrow fade show shadow-sm" role="alert">
    <i class="ri-alert-line label-icon"></i><strong>تنبيه:</strong> {{ session('warning') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($errors->has('role_error'))
<div class="alert alert-danger alert-dismissible alert-label-icon label-arrow fade show shadow-sm" role="alert">
    <i class="ri-error-warning-line label-icon"></i><strong>خطأ:</strong> {{ $errors->first('role_error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Quick Metrics Row -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-animate border-0 shadow-sm h-100 mb-0">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-uppercase fw-semibold text-muted text-truncate mb-1 fs-13">الأدوار المعرفة</p>
                        <h4 class="fs-22 fw-bold ff-secondary mb-0">{{ $roles->count() }} <span class="fs-13 fw-normal text-muted">دور وظيفي</span></h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle rounded-3 fs-2">
                            <i class="ri-shield-user-line text-primary"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-animate border-0 shadow-sm h-100 mb-0">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-uppercase fw-semibold text-muted text-truncate mb-1 fs-13">إجمالي الصلاحيات بالنظام</p>
                        <h4 class="fs-22 fw-bold ff-secondary mb-0 text-success">{{ $totalPermissions }} <span class="fs-13 fw-normal text-muted">صلاحية محكمة</span></h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle rounded-3 fs-2">
                            <i class="ri-lock-unlock-line text-success"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-animate border-0 shadow-sm h-100 mb-0">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-uppercase fw-semibold text-muted text-truncate mb-1 fs-13">المستخدمين المرتبطين بالأدوار</p>
                        <h4 class="fs-22 fw-bold ff-secondary mb-0 text-info">{{ $roles->sum('users_count') }} <span class="fs-13 fw-normal text-muted">حساب مستخدم</span></h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-info-subtle rounded-3 fs-2">
                            <i class="ri-team-line text-info"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Roles Table Card -->
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pb-0">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-1 fw-bold">الأدوار المعتمدة وصلاحياتها</h5>
                <p class="text-muted small mb-0">يمكنك تعديل الصلاحيات المتاحة لكل دور، وإضافة أدوار جديدة وتعيينها للموظفين والكاشير.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.users.index') }}" class="btn btn-soft-secondary btn-sm d-flex align-items-center gap-1">
                    <i class="ri-user-settings-line"></i> تعيين الأدوار للمستخدمين
                </a>
                <a href="{{ route('admin.roles.create') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                    <i class="ri-add-line"></i> إضافة دور مخصص جديد
                </a>
            </div>
        </div>
    </div>

    <div class="card-body pt-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle table-nowrap mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th scope="col" style="width: 50px;">#</th>
                        <th scope="col">الدور الوظيفي</th>
                        <th scope="col">المعرف البرمجي</th>
                        <th scope="col">الوصف والمهام</th>
                        <th scope="col" class="text-center">المستخدمين</th>
                        <th scope="col" class="text-center">عدد الصلاحيات</th>
                        <th scope="col" class="text-center" style="width: 140px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $index => $role)
                    <tr>
                        <td class="fw-semibold text-muted">{{ $index + 1 }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar-xs flex-shrink-0">
                                    <span class="avatar-title rounded-circle {{ $role->badge_class }} fs-14">
                                        <i class="{{ $role->icon_class }}"></i>
                                    </span>
                                </span>
                                <div>
                                    <span class="fw-bold d-block text-dark">{{ $role->display_label }}</span>
                                    @if($role->is_system)
                                    <span class="badge bg-light text-muted border fs-10 py-0">دور أساسي بالنظام</span>
                                    @else
                                    <span class="badge bg-secondary-subtle text-secondary fs-10 py-0">دور مخصص</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <code class="text-primary bg-primary-subtle px-2 py-1 rounded fs-12">{{ $role->name }}</code>
                        </td>
                        <td>
                            <span class="text-muted small">{{ $role->description_text }}</span>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('admin.users.index', ['role' => $role->name]) }}" class="badge bg-info-subtle text-info fs-12 px-2 py-1" title="عرض المستخدمين">
                                <i class="ri-user-line me-1"></i>{{ $role->users_count }} مستخدم
                            </a>
                        </td>
                        <td class="text-center">
                            @if($role->name === 'super-admin')
                                <span class="badge bg-danger-subtle text-danger fs-12 px-2 py-1">
                                    <i class="ri-infinite-line me-1"></i>صلاحيات غير مقيدة (شاملة)
                                </span>
                            @else
                                <span class="badge bg-success-subtle text-success fs-12 px-2 py-1">
                                    {{ $role->permissions_count }} من {{ $totalPermissions }}
                                </span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-inline-flex gap-1">
                                @if($role->name === 'super-admin')
                                    <button class="btn btn-sm btn-ghost-secondary" disabled title="لا يمكن تقييد المشرف العام">
                                        <i class="ri-lock-line fs-14"></i>
                                    </button>
                                @else
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-soft-primary" title="تعديل الصلاحيات">
                                        <i class="ri-edit-line fs-14"></i> تعديل
                                    </a>
                                @endif

                                @if(!$role->is_system)
                                    <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف الدور ({{ $role->name }})؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-soft-danger" title="حذف الدور">
                                            <i class="ri-delete-bin-line fs-14"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
