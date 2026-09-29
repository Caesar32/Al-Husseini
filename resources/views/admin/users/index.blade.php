@extends('admin.layouts.master')

@section('title', 'إدارة المستخدمين والحسابات')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
            <h4 class="mb-sm-0 fw-bold"><i class="ri-team-line text-primary me-1"></i> إدارة المستخدمين وحسابات الموظفين</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">المستخدمين والحسابات</li>
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

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
    <strong>تنبيه:</strong>
    <ul class="mb-0 mt-1">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Users Filter & Actions Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">البحث (الاسم، البريد، الهاتف)</label>
                <div class="position-relative">
                    <input type="text" name="search" class="form-control form-control-sm pe-4" placeholder="ابحث بالاسم أو البريد..." value="{{ request('search') }}">
                    <i class="ri-search-line position-absolute top-50 end-0 translate-middle-y me-2 text-muted"></i>
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">تصفية حسب الدور الوظيفي</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="">جميع الأدوار</option>
                    @foreach($roles as $r)
                        <option value="{{ $r->name }}" {{ request('role') == $r->name ? 'selected' : '' }}>{{ $r->display_label }} ({{ $r->name }})</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">الفرع</label>
                <select name="branch_id" class="form-select form-select-sm">
                    <option value="">جميع الفروع</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">حالة الحساب</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشط</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>معطل</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="ri-filter-3-line me-1"></i> تصفية
                </button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-light btn-sm" title="إعادة تعيين">
                    <i class="ri-refresh-line"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Users List Card -->
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h5 class="card-title mb-1 fw-bold">حسابات مستخدمي النظام</h5>
            <p class="text-muted small mb-0">تعيين الأدوار الوظيفية، إتاحة دخول الكاشير للـ POS، والتحكم في تفعيل أو تعطيل الحسابات.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.roles.index') }}" class="btn btn-soft-secondary btn-sm">
                <i class="ri-shield-keyhole-line me-1"></i> إدارة الصلاحيات
            </a>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="ri-user-add-line me-1"></i> إضافة حساب مستخدم
            </button>
        </div>
    </div>

    <div class="card-body pt-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle table-nowrap mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th scope="col" style="width: 50px;">#</th>
                        <th scope="col">المستخدم</th>
                        <th scope="col">الفرع</th>
                        <th scope="col">الدور الحالي</th>
                        <th scope="col" class="text-center">حالة الحساب</th>
                        <th scope="col">تاريخ الإنشاء</th>
                        <th scope="col" class="text-center" style="width: 180px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $index => $u)
                    @php
                        $userRole = $u->roles->first();
                        $roleMeta = $userRole ? \App\Services\PermissionRegistry::getRoleMetadata($userRole->name) : null;
                    @endphp
                    <tr>
                        <td class="fw-semibold text-muted">{{ $users->firstItem() + $index }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-xs flex-shrink-0">
                                    <span class="avatar-title rounded-circle bg-primary-subtle text-primary fw-bold fs-12">
                                        {{ mb_substr($u->name, 0, 1) }}
                                    </span>
                                </div>
                                <div>
                                    <span class="fw-bold d-block text-dark">{{ $u->name }}</span>
                                    <span class="text-muted fs-11">{{ $u->email }}</span>
                                    @if($u->phone)
                                        <span class="text-muted fs-11 ms-2"><i class="ri-phone-line"></i> {{ $u->phone }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($u->branch)
                                <span class="badge bg-light text-dark border"><i class="ri-store-2-line me-1"></i>{{ $u->branch->name }}</span>
                            @else
                                <span class="text-muted fs-12">المركز الرئيسي / عام</span>
                            @endif
                        </td>
                        <td>
                            @if($userRole)
                                <span class="badge {{ $roleMeta['badge'] ?? 'bg-secondary-subtle text-secondary' }} fs-12 px-2 py-1">
                                    <i class="{{ $roleMeta['icon'] ?? 'ri-shield-line' }} me-1"></i>{{ $roleMeta['label'] ?? $userRole->name }}
                                </span>
                            @else
                                <span class="badge bg-warning-subtle text-warning fs-12">بدون دور</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($u->is_active)
                                <span class="badge bg-success-subtle text-success fs-11 px-2 py-1"><i class="ri-check-line me-1"></i>نشط</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger fs-11 px-2 py-1"><i class="ri-close-line me-1"></i>معطل</span>
                            @endif
                        </td>
                        <td>
                            <span class="text-muted small">{{ $u->created_at ? $u->created_at->format('Y-m-d') : '-' }}</span>
                        </td>
                        <td class="text-center">
                            <div class="d-inline-flex gap-1">
                                <!-- Change Role Button -->
                                <button type="button" class="btn btn-sm btn-soft-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#changeRoleModal-{{ $u->id }}"
                                        title="تغيير الدور">
                                    <i class="ri-shield-user-line fs-14"></i> الدور
                                </button>

                                <!-- Set/Reset Password Button -->
                                <button type="button" class="btn btn-sm btn-soft-warning" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#resetPasswordModal-{{ $u->id }}"
                                        title="تعيين كلمة مرور جديدة">
                                    <i class="ri-key-2-line fs-14"></i> الباسورد
                                </button>

                                <!-- Edit User Button -->
                                <button type="button" class="btn btn-sm btn-soft-secondary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editUserModal-{{ $u->id }}"
                                        title="تعديل البيانات">
                                    <i class="ri-edit-line fs-14"></i>
                                </button>

                                <!-- Toggle Status Button -->
                                @if($u->id !== auth()->id())
                                <form action="{{ route('admin.users.toggle-status', $u) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $u->is_active ? 'btn-soft-warning' : 'btn-soft-success' }}" 
                                            title="{{ $u->is_active ? 'تعطيل الحساب' : 'تفعيل الحساب' }}"
                                            onclick="return confirm('{{ $u->is_active ? 'هل أنت متأكد من تعطيل هذا الحساب؟' : 'هل أنت متأكد من تفعيل هذا الحساب؟' }}')">
                                        <i class="{{ $u->is_active ? 'ri-user-unfollow-line text-warning' : 'ri-user-follow-line text-success' }} fs-14"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="ri-user-search-line fs-36 d-block text-secondary mb-2"></i>
                            لا توجد حسابات مطابقة لمعايير البحث الحالية.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
        <div class="d-flex justify-content-center mt-4">
            {{ $users->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Modals for Users (Rendered cleanly outside the table) -->
@foreach($users as $u)
    @php
        $userRole = $u->roles->first();
        $roleMeta = $userRole ? \App\Services\PermissionRegistry::getRoleMetadata($userRole->name) : null;
    @endphp

    <!-- Modal: Change Role for User -->
    <div class="modal fade" id="changeRoleModal-{{ $u->id }}" tabindex="-1" aria-labelledby="changeRoleLabel-{{ $u->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form action="{{ route('admin.users.role', $u) }}" method="POST">
                    @csrf
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold" id="changeRoleLabel-{{ $u->id }}"><i class="ri-shield-keyhole-line text-primary me-1"></i> تغيير دور: {{ $u->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">اختر الدور الجديد:</label>
                            @foreach($roles as $r)
                            <div class="form-check card-radio mb-2">
                                <input class="form-check-input" type="radio" name="role" id="role_{{ $u->id }}_{{ $r->id }}" value="{{ $r->name }}" {{ ($userRole && $userRole->id == $r->id) ? 'checked' : '' }}>
                                <label class="form-check-label d-flex align-items-center justify-content-between p-3 border rounded cursor-pointer" for="role_{{ $u->id }}_{{ $r->id }}">
                                    <div>
                                        <span class="fw-bold d-block text-dark">{{ $r->display_label }}</span>
                                        <small class="text-muted">{{ $r->name }}</small>
                                    </div>
                                    <span class="badge {{ $r->badge_class }} fs-12">{{ $r->name }}</span>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary fw-semibold">حفظ التغيير</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Reset Password for User -->
    <div class="modal fade" id="resetPasswordModal-{{ $u->id }}" tabindex="-1" aria-labelledby="resetPasswordLabel-{{ $u->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form action="{{ route('admin.users.reset-password', $u) }}" method="POST">
                    @csrf
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold" id="resetPasswordLabel-{{ $u->id }}"><i class="ri-key-2-line text-warning me-1"></i> تعيين كلمة مرور جديدة: {{ $u->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="ri-information-line me-1"></i> بصفتك المشرف العام، يمكنك تحديد أو إعادة ضبط كلمة المرور الخاصة بهذا المستخدم فوراً.
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">كلمة المرور الجديدة <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="reset_password_{{ $u->id }}" class="form-control font-monospace" placeholder="أدخل كلمة المرور الجديدة (6 أحرف فأكثر)" required minlength="6" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassVisibility('reset_password_{{ $u->id }}', this)">
                                    <i class="ri-eye-line"></i>
                                </button>
                            </div>
                            <small class="text-muted">احرص على تسليم كلمة المرور للمستخدم لتسجيل الدخول بها.</small>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-warning fw-semibold">
                            <i class="ri-save-line me-1"></i> حفظ كلمة المرور
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Edit User -->
    <div class="modal fade" id="editUserModal-{{ $u->id }}" tabindex="-1" aria-labelledby="editUserLabel-{{ $u->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form action="{{ route('admin.users.update', $u) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold" id="editUserLabel-{{ $u->id }}"><i class="ri-user-settings-line text-primary me-1"></i> تعديل بيانات: {{ $u->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">الاسم الكامل <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ $u->name }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">البريد الإلكتروني <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="{{ $u->email }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">رقم الهاتف</label>
                            <input type="text" name="phone" class="form-control" value="{{ $u->phone }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">الفرع المخصص</label>
                            <select name="branch_id" class="form-select">
                                <option value="">بدون فرع محدد (عام)</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" {{ $u->branch_id == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">الدور والصلاحيات <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                @foreach($roles as $r)
                                    <option value="{{ $r->name }}" {{ ($userRole && $userRole->id == $r->id) ? 'selected' : '' }}>
                                        {{ $r->display_label }} ({{ $r->name }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">كلمة المرور الجديدة (اختياري)</label>
                            <div class="input-group">
                                <input type="password" name="password" id="edit_password_{{ $u->id }}" class="form-control" placeholder="اتركه فارغاً للاحتفاظ بكلمة المرور الحالية" minlength="6" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassVisibility('edit_password_{{ $u->id }}', this)">
                                    <i class="ri-eye-line"></i>
                                </button>
                            </div>
                            <small class="text-muted">اتركه فارغاً إذا كنت لا ترغب في تغيير كلمة المرور.</small>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary fw-semibold">تحديث البيانات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

<!-- Modal: Add New User -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('admin.users.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="addUserLabel"><i class="ri-user-add-line text-success me-1"></i> إضافة حساب مستخدم جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">الاسم الكامل <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="مثال: أحمد عبد الله" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">البريد الإلكتروني <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="user@alhusseini.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control" placeholder="01xxxxxxxxx">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">الفرع المخصص</label>
                        <select name="branch_id" class="form-select">
                            <option value="">بدون فرع محدد (عام)</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">الدور والصلاحيات <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="">-- اختر الدور --</option>
                            @foreach($roles as $r)
                                <option value="{{ $r->name }}">{{ $r->display_label }} ({{ $r->name }})</option>
                            @endforeach
                        </select>
                        <small class="text-muted">عند اختيار دور <strong>كاشير مبيعات</strong> سيتم توجيهه تلقائياً لشاشة الـ POS.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">كلمة المرور الأولية <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="add_user_password" class="form-control" placeholder="6 أحرف على الأقل" required minlength="6" autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassVisibility('add_user_password', this)">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success fw-semibold">حفظ وإنشاء الحساب</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function togglePassVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('ri-eye-line');
            icon.classList.add('ri-eye-off-line');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('ri-eye-off-line');
            icon.classList.add('ri-eye-line');
        }
    }
}
</script>
@endpush
