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
