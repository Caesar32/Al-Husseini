<!-- Active Staff Monthly Payroll Breakdown Table Card -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header align-items-center d-flex">
                <h5 class="card-title mb-0 flex-grow-1"><i class="ri-file-list-3-line text-primary me-2"></i> آخر استحقاقات مسجلة للموظفين (من بنود المسيرات المحتسبة)</h5>
                <div class="flex-shrink-0">
                    <span class="badge bg-info-subtle text-info fs-12 px-3 py-2">
                        <i class="ri-database-2-line me-1"></i> القيم المسجلة في آخر مسير لكل موظف
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
                                <th scope="col">المسير</th>
                                <th scope="col">الراتب الأساسي</th>
                                <th scope="col">البدلات والإضافي</th>
                                <th scope="col">الخصومات المطبقة</th>
                                <th scope="col">صافي الراتب المستحق</th>
                                <th scope="col">الحالة</th>
                                <th scope="col" class="text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="payrollTableBody">
                            @forelse($employees as $emp)
                                @php
                                    // Stored values from the employee's latest payroll item — never a client-side estimate.
                                    $item = $latestItems->get($emp->id);
                                    $base = (float) ($item?->basic_salary ?? $emp->currentSalary?->basic_salary ?? 0);
                                    $allow = $item ? (float) $item->total_allowance + (float) $item->total_overtime : null;
                                    $empDeds = $item ? (float) $item->total_deduction : null;
                                    $net = $item ? (float) $item->net_salary : null;
                                    $shortfall = $item ? (float) $item->carried_debt : 0;
                                    $jobTitle = $emp->jobTitle?->title ?? $emp->jobTitle?->title_name ?? '';
                                    $deptName = $emp->jobTitle?->department?->name ?? '';
                                @endphp
                                <tr class="payroll-emp-row"
                                    data-branch="{{ $emp->branch_id }}"
                                    data-name="{{ $emp->full_name }}"
                                    data-code="{{ $emp->employee_code }}"
                                    data-role="{{ $jobTitle }}"
                                    data-department="{{ $deptName }}"
                                    data-phone="{{ $emp->phone ?? '' }}">
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
                                    <td>
                                        @if($item)
                                            <span class="badge bg-dark-subtle text-dark font-monospace fs-12">{{ $item->payroll->year }} / {{ sprintf('%02d', $item->payroll->month) }}</span>
                                        @else
                                            <span class="text-muted fs-12">—</span>
                                        @endif
                                    </td>
                                    <td><span class="fw-bold fs-13 text-dark">{{ number_format($base) }} ج.م</span></td>
                                    <td>
                                        @if($item)
                                            <span class="fw-bold fs-13 text-info">{{ number_format($allow) }} ج.م</span>
                                        @else
                                            <span class="text-muted fs-12">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!$item)
                                            <span class="text-muted fs-12">—</span>
                                        @elseif($empDeds > 0)
                                            <span class="badge bg-danger-subtle text-danger fs-12 fw-bold font-monospace">-{{ number_format($empDeds) }} ج.م</span>
                                        @else
                                            <span class="badge bg-light text-muted fs-12">0 ج.م</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!$item)
                                            <span class="text-muted fs-12">—</span>
                                        @elseif($shortfall > 0)
                                            <div class="fw-bold fs-14 text-danger">{{ number_format($net) }} ج.م</div>
                                            <small class="text-danger">عجز مرحّل: {{ number_format($shortfall) }} ج.م</small>
                                        @else
                                            <span class="fw-bold fs-14 text-success">{{ number_format($net) }} ج.م</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!$item)
                                            <span class="badge bg-light text-muted fs-12 px-2 py-1"><i class="ri-time-line me-1"></i>لم يُحتسب في مسير بعد</span>
                                        @elseif($shortfall > 0)
                                            <span class="badge bg-warning-subtle text-warning fs-12 px-2 py-1">
                                                <i class="ri-error-warning-line me-1"></i>يتطلب مراجعة
                                            </span>
                                        @else
                                            <span class="badge bg-success-subtle text-success fs-12 px-2 py-1"><i class="ri-check-double-line me-1"></i>جاهز للصرف</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <button type="button" class="btn btn-sm btn-soft-danger btn-direct-deduct" data-id="{{ $emp->id }}" data-name="{{ $emp->full_name }}" title="تطبيق خصم">
                                                <i class="ri-hand-coin-line"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-soft-primary btn-view-payslip"
                                                data-payroll-id="{{ $item?->payroll_id }}"
                                                data-employee-id="{{ $emp->id }}"
                                                data-branch="{{ $emp->branch?->name }}"
                                                @disabled(!$item)
                                                title="{{ $item ? 'قسيمة الراتب المسجلة' : 'لا يوجد مسير محتسب لهذا الموظف بعد' }}">
                                                <i class="ri-file-text-line me-1"></i> القسيمة
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center py-4 text-muted">لا يوجد موظفون مسجلون في النظام</td></tr>
                            @endforelse
                            <tr id="payrollNoResultsRow" style="display: none;">
                                <td colspan="9" class="text-center py-5 text-muted fs-14">
                                    <i class="ri-user-search-line fs-28 d-block mb-2 text-warning"></i>
                                    لا توجد استحقاقات مطابقة لمعايير البحث المحددة
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
