@extends('admin.layouts.master')

@section('title', 'مسير الرواتب والخصومات | مجموعة الحسيني')

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'الموارد البشرية', 'title' => 'مسير الرواتب والخصومات الإدارية'])

    @php
        $totalBase = $employees->sum(fn($e) => $e->currentSalary?->basic_salary ?? 0);
        $totalAllowances = $employees->sum(fn($e) => ($e->currentSalary?->housing_allowance ?? 0) + ($e->currentSalary?->transport_allowance ?? 0) + ($e->currentSalary?->other_allowances ?? 0));
        $totalDeductions = $recentDeductions->where('status', 'approved')->sum('amount');
        $totalNet = max(0, ($totalBase + $totalAllowances) - $totalDeductions);
    @endphp

    <!-- Top Stats -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-primary border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">إجمالي الرواتب الأساسية</p>
                            <h4 class="fs-22 fw-bold text-primary mb-0 mt-2" id="stat-total-base">{{ number_format($totalBase) }} ج.م</h4>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20">
                                <i class="ri-bank-card-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-info border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">إجمالي البدلات والمكافآت</p>
                            <h4 class="fs-22 fw-bold text-info mb-0 mt-2" id="stat-total-allowances">{{ number_format($totalAllowances) }} ج.م</h4>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-info-subtle text-info rounded-circle fs-20">
                                <i class="ri-gift-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-danger border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">إجمالي الخصومات المطبقة</p>
                            <h4 class="fs-22 fw-bold text-danger mb-0 mt-2" id="stat-total-deductions">{{ number_format($totalDeductions) }} ج.م</h4>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-20">
                                <i class="ri-scissors-cut-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-success border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">صافي المستحق للصرف</p>
                            <h4 class="fs-22 fw-bold text-success mb-0 mt-2" id="stat-total-net">{{ number_format($totalNet) }} ج.م</h4>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-20">
                                <i class="ri-hand-coin-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manager Control & Actions Bar -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card mb-0">
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-4 col-md-6">
                            <div class="search-box">
                                <input type="text" class="form-control" id="searchPayrollInput" placeholder="بحث باسم الموظف أو الوظيفة...">
                                <i class="ri-search-line search-icon"></i>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <select class="form-select" id="payrollBranchFilter">
                                <option value="all">جميع فروع المركز</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-5 col-md-12 text-md-end">
                            <div class="d-flex gap-2 justify-content-lg-end">
                                <button type="button" class="btn btn-primary" id="btnOpenGenerateModal">
                                    <i class="ri-calculator-line align-bottom me-1"></i> احتساب مسير الشهر
                                </button>
                                <button type="button" class="btn btn-danger" id="btnOpenManagerDeduction">
                                    <i class="ri-hand-coin-fill align-bottom me-1"></i> تطبيق خصم إداري
                                </button>
                                <button type="button" class="btn btn-soft-secondary" onclick="window.print();">
                                    <i class="ri-printer-line align-bottom me-1"></i> طباعة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Generated Payroll Batches (Drafts & Approved) -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header align-items-center d-flex bg-light-subtle">
                    <h5 class="card-title mb-0 flex-grow-1"><i class="ri-file-history-line text-primary me-2"></i> مسيرات الرواتب المعتمدة والمسودات الشهرية</h5>
                    <span class="badge bg-primary-subtle text-primary fs-12">{{ $payrolls->total() }} مسير مسجل</span>
                </div>
                <div class="card-body">
                    @if($payrolls->isEmpty())
                        <div class="text-center py-4 text-muted">
                            <i class="ri-folder-open-line fs-32 d-block mb-1"></i>
                            لم يتم احتساب أي مسير شهري بعد. اضغط على <strong>"احتساب مسير الشهر"</strong> بالأعلى لتوليد المسير تلقائياً.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>الفترة</th>
                                        <th>الفرع</th>
                                        <th>إجمالي الأساسي</th>
                                        <th>إجمالي البدلات</th>
                                        <th>إجمالي الخصومات</th>
                                        <th>صافي المبلغ للصرف</th>
                                        <th>الحالة</th>
                                        <th>المعتمد بواسطة</th>
                                        <th class="text-center">الإجراءات والاعتماد</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payrolls as $p)
                                        <tr>
                                            <td><span class="badge bg-dark-subtle text-dark fs-12 fw-bold font-monospace">{{ $p->year }} / {{ sprintf('%02d', $p->month) }}</span></td>
                                            <td><span class="fw-semibold">{{ $p->branch?->name }}</span></td>
                                            <td>{{ number_format($p->total_basic_salaries) }} ج.م</td>
                                            <td class="text-info">+{{ number_format($p->total_allowances) }} ج.م</td>
                                            <td class="text-danger">-{{ number_format($p->total_deductions) }} ج.م</td>
                                            <td class="fw-bold text-success fs-14">{{ number_format($p->total_net_salaries) }} ج.م</td>
                                            <td>
                                                @if($p->status === 'draft')
                                                    <span class="badge bg-warning-subtle text-warning fs-12"><i class="ri-time-line me-1"></i>مسودة للمراجعة</span>
                                                @elseif($p->status === 'approved')
                                                    <span class="badge bg-info-subtle text-info fs-12"><i class="ri-check-line me-1"></i>معتمد وجاهز للصرف</span>
                                                @else
                                                    <span class="badge bg-success-subtle text-success fs-12"><i class="ri-check-double-line me-1"></i>تم الصرف والمطابقة</span>
                                                @endif
                                            </td>
                                            <td>{{ $p->approvedBy?->name ?? '—' }}</td>
                                            <td class="text-center">
                                                @if($p->status === 'draft')
                                                    <button type="button" class="btn btn-sm btn-success btn-approve-payroll" data-id="{{ $p->id }}">
                                                        <i class="ri-shield-check-line me-1"></i> اعتماد المسير
                                                    </button>
                                                @elseif($p->status === 'approved')
                                                    <button type="button" class="btn btn-sm btn-primary btn-disburse-payroll" data-id="{{ $p->id }}">
                                                        <i class="ri-money-dollar-box-line me-1"></i> صرف المسير
                                                    </button>
                                                @else
                                                    <span class="text-muted fs-12"><i class="ri-lock-line me-1"></i>مغلق ومصروف</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Active Staff Monthly Payroll Breakdown Table Card -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h5 class="card-title mb-0 flex-grow-1"><i class="ri-file-list-3-line text-primary me-2"></i> كشف استحقاقات الموظفين للشهر الحالي ({{ date('F Y') }})</h5>
                    <div class="flex-shrink-0">
                        <span class="badge bg-success-subtle text-success fs-12 px-3 py-2">
                            <i class="ri-shield-check-fill me-1"></i> مطابق للبيانات الحية
                        </span>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle table-nowrap table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">الموظف</th>
                                    <th scope="col">الفرع والقسم</th>
                                    <th scope="col">الراتب الأساسي</th>
                                    <th scope="col">إجمالي البدلات</th>
                                    <th scope="col">الخصومات المطبقة</th>
                                    <th scope="col">صافي الراتب المستحق</th>
                                    <th scope="col">الحالة</th>
                                    <th scope="col" class="text-center">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody id="payrollTableBody">
                                @forelse($employees as $emp)
                                    @php
                                        $base = (float) ($emp->currentSalary?->basic_salary ?? 0);
                                        $allow = (float) (($emp->currentSalary?->housing_allowance ?? 0) + ($emp->currentSalary?->transport_allowance ?? 0) + ($emp->currentSalary?->other_allowances ?? 0));
                                        $empDeds = $recentDeductions->where('employee_id', $emp->id)->where('status', 'approved')->sum('amount');
                                        $net = max(0, ($base + $allow) - $empDeds);
                                    @endphp
                                    <tr data-branch="{{ $emp->branch_id }}" data-name="{{ strtolower($emp->full_name) }}" data-role="{{ strtolower($emp->jobTitle?->title_name ?? '') }}">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-xs me-2">
                                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle fw-bold">
                                                        {{ mb_substr($emp->full_name, 0, 1) }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fs-13 fw-bold">{{ $emp->full_name }}</h6>
                                                    <small class="text-muted font-monospace">{{ $emp->employee_code }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-body fs-12">{{ $emp->branch?->name }}</span>
                                            <small class="text-muted d-block fs-11">{{ $emp->jobTitle?->title_name }}</small>
                                        </td>
                                        <td><span class="fw-bold fs-13 text-dark">{{ number_format($base) }} ج.م</span></td>
                                        <td><span class="fw-bold fs-13 text-info">+{{ number_format($allow) }} ج.م</span></td>
                                        <td>
                                            @if($empDeds > 0)
                                                <span class="badge bg-danger-subtle text-danger fs-12 fw-bold font-monospace">-{{ number_format($empDeds) }} ج.م</span>
                                            @else
                                                <span class="badge bg-light text-muted fs-12">0 ج.م</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fw-bold fs-14 text-success">{{ number_format($net) }} ج.م</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success fs-12 px-2 py-1"><i class="ri-check-double-line me-1"></i>جاهز للصرف</span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex gap-1 justify-content-center">
                                                <button type="button" class="btn btn-sm btn-soft-danger btn-direct-deduct" data-id="{{ $emp->id }}" data-name="{{ $emp->full_name }}" title="تطبيق خصم">
                                                    <i class="ri-hand-coin-line"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-soft-primary btn-view-payslip" 
                                                    data-name="{{ $emp->full_name }}"
                                                    data-code="{{ $emp->employee_code }}"
                                                    data-role="{{ $emp->jobTitle?->title_name }}"
                                                    data-branch="{{ $emp->branch?->name }}"
                                                    data-base="{{ $base }}"
                                                    data-allow="{{ $allow }}"
                                                    data-ded="{{ $empDeds }}"
                                                    data-net="{{ $net }}"
                                                    title="قسيمة الراتب">
                                                    <i class="ri-file-text-line me-1"></i> القسيمة
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center py-4 text-muted">لا يوجد موظفون مسجلون في النظام</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Deductions History Log Card -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h5 class="card-title mb-0 flex-grow-1"><i class="ri-history-line text-danger me-2"></i> سجل قرارات الخصم الإدارية المعتمدة</h5>
                    <span class="badge bg-danger-subtle text-danger fs-12">{{ count($recentDeductions) }} قرار مسجل</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle table-nowrap mb-0 table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>رقم القرار</th>
                                    <th>الموظف</th>
                                    <th>المبلغ المخصوم</th>
                                    <th>سبب الخصم</th>
                                    <th>تاريخ القرار</th>
                                    <th>الحالة</th>
                                    <th class="text-center">إلغاء الخصم</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentDeductions as $ded)
                                    <tr>
                                        <td><span class="badge bg-light text-body font-monospace">DED-{{ $ded->id }}</span></td>
                                        <td><span class="fw-bold">{{ $ded->employee?->full_name }}</span></td>
                                        <td><span class="badge bg-danger-subtle text-danger fw-bold font-monospace">-{{ number_format($ded->amount) }} ج.م</span></td>
                                        <td>{{ $ded->reason }}</td>
                                        <td><small class="text-muted font-monospace">{{ $ded->deduction_date }}</small></td>
                                        <td>
                                            @if($ded->status === 'approved')
                                                <span class="badge bg-success-subtle text-success">معتمد</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary">ملغي</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($ded->status === 'approved')
                                                <button type="button" class="btn btn-sm btn-soft-danger btn-cancel-deduction" data-id="{{ $ded->id }}">
                                                    <i class="ri-close-line"></i> إلغاء
                                                </button>
                                            @else
                                                <span class="text-muted fs-11">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center py-3 text-muted">لا توجد خصومات مسجلة حتى الآن</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Generate Monthly Payroll -->
    <div class="modal fade" id="generatePayrollModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header bg-primary text-white p-3">
                    <h5 class="modal-title fw-bold text-white"><i class="ri-calculator-line me-2"></i> احتساب وتوليد مسير الرواتب الشهري</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="generatePayrollForm">
                    <div class="modal-body p-4">
                        <div class="alert alert-info border-0 p-3 mb-3 fs-13">
                            <i class="ri-information-line me-2 fs-16 align-middle"></i>
                            سيقوم النظام تلقائياً باحتساب الرواتب الأساسية، والبدلات، والخصومات المستحقة على البصمة خلال الشهر، وتوليد مسودة المسير للاعتماد المالي.
                        </div>

                        <div class="mb-3">
                            <label for="genBranchSelect" class="form-label fw-semibold">الفرع المراد احتساب رواتبه <span class="text-danger">*</span></label>
                            <select class="form-select" id="genBranchSelect" required>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label for="genYearInput" class="form-label fw-semibold">السنة <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="genYearInput" required value="{{ date('Y') }}" min="2024" max="2030">
                            </div>
                            <div class="col-6">
                                <label for="genMonthInput" class="form-label fw-semibold">الشهر <span class="text-danger">*</span></label>
                                <select class="form-select" id="genMonthInput" required>
                                    @for($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}" {{ date('n') == $m ? 'selected' : '' }}>{{ $m }} - {{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary fw-bold">
                            <i class="ri-magic-line me-1"></i> بدء الاحتساب التلقائي
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Manager Apply Deduction -->
    <div class="modal fade" id="managerDeductionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0">
                <div class="modal-header bg-danger text-white p-3">
                    <h5 class="modal-title fw-bold text-white"><i class="ri-hand-coin-line me-2"></i> قرار إداري: تطبيق خصم على موظف</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="managerDeductionForm">
                    <div class="modal-body p-4">
                        <div class="alert alert-danger-subtle text-danger border-0 p-3 mb-3">
                            <i class="ri-error-warning-line me-2 fs-16 align-middle"></i>
                            <strong>تنبيه إداري:</strong> الخصم المعتمد سيتم استقطاعه فورياً من صافي راتب الموظف للشهر الحالي، وسيرسل النظام إشعاراً رسمياً للإدارة المالية.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="deductEmployeeSelect" class="form-label fw-semibold">اختر الموظف <span class="text-danger">*</span></label>
                                <select class="form-select" id="deductEmployeeSelect" required>
                                    <option value="">-- اختر الموظف --</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_code }} - {{ $emp->branch?->name }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="deductAmountInput" class="form-label fw-semibold">مبلغ الخصم (جنيه مصري EGP) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="deductAmountInput" required min="50" step="50" placeholder="مثال: 500">
                            </div>

                            <div class="col-md-12">
                                <label for="deductReasonSelect" class="form-label fw-semibold">سبب الخصم <span class="text-danger">*</span></label>
                                <select class="form-select" id="deductReasonSelect" required>
                                    <option value="تأخير عن موعد فتح صالة المعرض واستقبال العملاء">تأخير عن موعد فتح صالة المعرض واستقبال العملاء</option>
                                    <option value="تأخير عن بدء وردية ورشة فحص وشحن البطاريات">تأخير عن بدء وردية ورشة فحص وشحن البطاريات</option>
                                    <option value="تأخير في الاستجابة لبلاغ طوارئ إنقاذ بطارية طريق">تأخير في الاستجابة لبلاغ طوارئ إنقاذ بطارية طريق</option>
                                    <option value="غياب كامل بدون إذن مسبق في يوم ذروة بيع">غياب كامل بدون إذن مسبق في يوم ذروة بيع</option>
                                    <option value="إهمال في فحص كفاءة البطارية ودينامو سيارة العميل">إهمال في فحص كفاءة البطارية ودينامو سيارة العميل</option>
                                    <option value="خطأ أو إهمال في تسجيل بطاقة ضمان البطارية">خطأ أو إهمال في تسجيل بطاقة ضمان البطارية</option>
                                    <option value="تلف كابلات أو معدات أثناء الفحص والشحن">تلف كابلات أو معدات أثناء الفحص والشحن</option>
                                    <option value="مخالفة لوائح وسياسات مركز الحسيني للبطاريات">مخالفة لوائح وسياسات مركز الحسيني للبطاريات</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label for="deductNotesInput" class="form-label fw-semibold">تفاصيل وسند القرار الإداري</label>
                                <textarea class="form-control" id="deductNotesInput" rows="3" placeholder="اكتب أسباب وحيثيات القرار وتاريخ المخالفة..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-danger fw-bold">
                            <i class="ri-check-line align-middle me-1"></i> اعتماد الخصم فورياً
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Digital Printable Payslip -->
    <div class="modal fade" id="payslipModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0">
                <div class="modal-header bg-dark text-white p-3">
                    <h5 class="modal-title fw-bold text-white"><i class="ri-file-paper-2-line me-2"></i> قسيمة الراتب الرسمية (Payslip)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="payslipPrintArea">
                    <!-- Populated dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" onclick="window.print();">
                        <i class="ri-printer-line me-1"></i> طباعة القسيمة
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const generatePayrollModal = new bootstrap.Modal(document.getElementById('generatePayrollModal'));
    const managerDeductionModal = new bootstrap.Modal(document.getElementById('managerDeductionModal'));
    const payslipModal = new bootstrap.Modal(document.getElementById('payslipModal'));

    // Open Generate Modal
    document.getElementById('btnOpenGenerateModal').onclick = () => generatePayrollModal.show();
    document.getElementById('btnOpenManagerDeduction').onclick = () => managerDeductionModal.show();

    // Direct Deduct button from table
    document.querySelectorAll('.btn-direct-deduct').forEach(btn => {
        btn.onclick = function() {
            const empId = this.getAttribute('data-id');
            document.getElementById('deductEmployeeSelect').value = empId;
            managerDeductionModal.show();
        };
    });

    // Generate Payroll Form Submission
    document.getElementById('generatePayrollForm').onsubmit = function(e) {
        e.preventDefault();

        const branchId = document.getElementById('genBranchSelect').value;
        const year = document.getElementById('genYearInput').value;
        const month = document.getElementById('genMonthInput').value;

        fetch('/admin/hr/payroll/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                branch_id: branchId,
                year: year,
                month: month
            })
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'تعذر احتساب المسير.');
            return data;
        })
        .then(data => {
            generatePayrollModal.hide();
            Swal.fire({
                icon: 'success',
                title: 'تم بنجاح!',
                text: data.message,
                confirmButtonText: 'تحديث الصفحة'
            }).then(() => window.location.reload());
        })
        .catch(err => {
            Swal.fire('خطأ في العملية', err.message, 'error');
        });
    };

    // Manager Deduction Form Submission
    document.getElementById('managerDeductionForm').onsubmit = function(e) {
        e.preventDefault();

        const empId = document.getElementById('deductEmployeeSelect').value;
        const amount = document.getElementById('deductAmountInput').value;
        const reason = document.getElementById('deductReasonSelect').value;
        const notes = document.getElementById('deductNotesInput').value;

        fetch('/admin/hr/deductions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                employee_id: empId,
                amount: amount,
                reason: notes ? `${reason} - ${notes}` : reason,
                deduction_date: new Date().toISOString().split('T')[0]
            })
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'تعذر تسجيل الجزاء.');
            return data;
        })
        .then(data => {
            managerDeductionModal.hide();
            Swal.fire({
                icon: 'success',
                title: 'تم اعتماد الخصم!',
                text: data.message,
                confirmButtonText: 'حسناً'
            }).then(() => window.location.reload());
        })
        .catch(err => {
            Swal.fire('خطأ', err.message, 'error');
        });
    };

    // Approve Payroll Batch
    document.querySelectorAll('.btn-approve-payroll').forEach(btn => {
        btn.onclick = function() {
            const id = this.getAttribute('data-id');

            Swal.fire({
                title: 'اعتماد مسير الرواتب',
                text: 'هل أنت متأكد من اعتماد هذا المسير وإرساله للصرف النهائي؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، اعتماد الآن',
                cancelButtonText: 'إلغاء'
            }).then((res) => {
                if (res.isConfirmed) {
                    fetch(`/admin/hr/payroll/${id}/approve`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    })
                    .then(async r => {
                        const data = await r.json();
                        if (!r.ok) throw new Error(data.message || 'فشل الاعتماد.');
                        return data;
                    })
                    .then(data => {
                        Swal.fire('تم الاعتماد!', data.message, 'success').then(() => window.location.reload());
                    })
                    .catch(err => Swal.fire('خطأ', err.message, 'error'));
                }
            });
        };
    });

    // Disburse Payroll Batch
    document.querySelectorAll('.btn-disburse-payroll').forEach(btn => {
        btn.onclick = function() {
            const id = this.getAttribute('data-id');

            Swal.fire({
                title: 'تأكيد صرف المسير',
                text: 'سيتم تحويل حالة المسير إلى (مصروف) وإغلاق حسابات الشهر لهذا الفرع.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، تأكيد الصرف',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#0ab39c'
            }).then((res) => {
                if (res.isConfirmed) {
                    fetch(`/admin/hr/payroll/${id}/disburse`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    })
                    .then(async r => {
                        const data = await r.json();
                        if (!r.ok) throw new Error(data.message || 'فشل الصرف.');
                        return data;
                    })
                    .then(data => {
                        Swal.fire('تم الصرف بنجاح!', data.message, 'success').then(() => window.location.reload());
                    })
                    .catch(err => Swal.fire('خطأ', err.message, 'error'));
                }
            });
        };
    });

    // Cancel Deduction
    document.querySelectorAll('.btn-cancel-deduction').forEach(btn => {
        btn.onclick = function() {
            const id = this.getAttribute('data-id');

            Swal.fire({
                title: 'إلغاء قرار الخصم',
                text: 'هل ترغب بإلغاء هذا الجزاء واسترداده للموظف؟',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، إلغاء الخصم',
                cancelButtonText: 'تراجع'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch(`/admin/hr/deductions/${id}/status`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ status: 'cancelled' })
                    })
                    .then(async r => {
                        const data = await r.json();
                        if (!r.ok) throw new Error(data.message || 'تعذر الإلغاء');
                        return data;
                    })
                    .then(data => {
                        Swal.fire('تم الإلغاء!', data.message, 'success').then(() => window.location.reload());
                    })
                    .catch(err => Swal.fire('خطأ', err.message, 'error'));
                }
            });
        };
    });

    // View Payslip Modal
    document.querySelectorAll('.btn-view-payslip').forEach(btn => {
        btn.onclick = function() {
            const name = this.getAttribute('data-name');
            const code = this.getAttribute('data-code');
            const role = this.getAttribute('data-role');
            const branch = this.getAttribute('data-branch');
            const base = Number(this.getAttribute('data-base') || 0);
            const allow = Number(this.getAttribute('data-allow') || 0);
            const ded = Number(this.getAttribute('data-ded') || 0);
            const net = Number(this.getAttribute('data-net') || 0);

            document.getElementById('payslipPrintArea').innerHTML = `
                <div class="border p-4 rounded-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                        <div>
                            <h4 class="fw-bold mb-0 text-primary">مركز الحسيني لبطاريات السيارات</h4>
                            <small class="text-muted">قسيمة استحقاق وصرف الراتب الشهري</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light text-body font-monospace fs-13">كود: ${code}</span>
                            <div class="text-muted fs-12 mt-1">تاريخ الإصدار: ${new Date().toLocaleDateString('ar-EG')}</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <span class="text-muted fs-12">اسم الموظف:</span>
                            <h6 class="fw-bold mb-0">${name}</h6>
                        </div>
                        <div class="col-6">
                            <span class="text-muted fs-12">المسمى الوظيفي:</span>
                            <h6 class="fw-bold mb-0">${role}</h6>
                        </div>
                        <div class="col-6">
                            <span class="text-muted fs-12">فرع المركز:</span>
                            <div class="fw-semibold">${branch}</div>
                        </div>
                        <div class="col-6">
                            <span class="text-muted fs-12">شهر الاستحقاق:</span>
                            <div class="fw-semibold font-monospace">${new Date().getMonth() + 1} / ${new Date().getFullYear()}</div>
                        </div>
                    </div>

                    <table class="table table-bordered align-middle mb-4">
                        <thead class="table-light">
                            <tr>
                                <th>بيان الاستحقاقات</th>
                                <th class="text-end">المبلغ (ج.م)</th>
                                <th>بيان الاستقطاعات</th>
                                <th class="text-end">المبلغ (ج.م)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>الراتب الأساسي</td>
                                <td class="text-end fw-bold text-dark">${base.toLocaleString('ar-EG')}</td>
                                <td>الخصومات والجزاءات الإدارية</td>
                                <td class="text-end fw-bold text-danger">-${ded.toLocaleString('ar-EG')}</td>
                            </tr>
                            <tr>
                                <td>إجمالي البدلات والمكافآت</td>
                                <td class="text-end fw-bold text-info">+${allow.toLocaleString('ar-EG')}</td>
                                <td>تأمينات واستقطاعات أخرى</td>
                                <td class="text-end fw-bold text-muted">0</td>
                            </tr>
                            <tr class="table-light fw-bold">
                                <td>إجمالي الدخل</td>
                                <td class="text-end text-success">${(base + allow).toLocaleString('ar-EG')} ج.م</td>
                                <td>إجمالي الاستقطاع</td>
                                <td class="text-end text-danger">-${ded.toLocaleString('ar-EG')} ج.م</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="p-3 bg-success-subtle rounded text-center mb-4">
                        <span class="text-muted fs-13 d-block mb-1">صافي الراتب المستحق للصرف النهائي:</span>
                        <h3 class="fw-extrabold text-success mb-0">${net.toLocaleString('ar-EG')} جنيه مصري</h3>
                    </div>

                    <div class="row pt-4 text-center fs-12 text-muted border-top">
                        <div class="col-4">توقيع المستلم: .....................</div>
                        <div class="col-4">توقيع الموارد البشرية: .....................</div>
                        <div class="col-4">اعتماد المدير المالي: .....................</div>
                    </div>
                </div>
            `;

            payslipModal.show();
        };
    });

    // Client-side search and branch filter on the table
    const searchInput = document.getElementById('searchPayrollInput');
    const branchFilter = document.getElementById('payrollBranchFilter');

    function filterPayrollRows() {
        const query = searchInput.value.trim().toLowerCase();
        const branch = branchFilter.value;

        document.querySelectorAll('#payrollTableBody tr').forEach(row => {
            const rowBranch = row.getAttribute('data-branch');
            const rowName = row.getAttribute('data-name') || '';
            const rowRole = row.getAttribute('data-role') || '';

            const matchesBranch = (branch === 'all' || rowBranch === branch);
            const matchesQuery = (!query || rowName.includes(query) || rowRole.includes(query));

            row.style.display = (matchesBranch && matchesQuery) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterPayrollRows);
    branchFilter.addEventListener('change', filterPayrollRows);
});
</script>
@endsection
