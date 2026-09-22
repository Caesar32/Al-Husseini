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
                        <tbody id="deductionsTableBody">
                            @forelse($recentDeductions as $ded)
                                <tr class="deduction-log-row"
                                    data-employee="{{ $ded->employee?->full_name }}"
                                    data-code="{{ $ded->employee?->employee_code }}"
                                    data-id="DED-{{ $ded->id }}"
                                    data-reason="{{ $ded->reason }}">
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
                            <tr id="deductionNoResultsRow" style="display: none;">
                                <td colspan="7" class="text-center py-3 text-muted fs-13">
                                    <i class="ri-file-search-line fs-20 d-block mb-1 text-muted"></i>
                                    لا توجد قرارات خصم مطابقة للبحث
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
