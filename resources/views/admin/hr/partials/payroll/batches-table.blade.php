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
                                    @php
                                        $rowBasic = round((float) $p->items->sum('basic_salary'), 2);
                                        $rowAllowances = round((float) $p->items->sum('total_allowance'), 2);
                                        $rowDeductions = round((float) $p->items->sum('total_deduction'), 2);
                                        $rowStoredNet = round((float) $p->items->sum('net_salary'), 2);
                                        $rowCarriedDebt = round((float) $p->items->sum('carried_debt'), 2);
                                        $rowExpectedNet = round($rowBasic + $rowAllowances - $rowDeductions + $rowCarriedDebt, 2);
                                        $rowNet = $rowExpectedNet;
                                        $rowConsistent = abs($rowStoredNet - $rowExpectedNet) < 0.01;
                                        $rowHasZeroWithComponents = abs($rowStoredNet) < 0.01 && abs($rowExpectedNet) > 0.01;
                                        $rowNeedsReview = !$rowConsistent || $rowHasZeroWithComponents;
                                    @endphp
                                    <tr class="payroll-batch-row" data-branch="{{ $p->branch_id }}" data-has-debt="{{ $p->items->contains(fn($item) => (float) $item->carried_debt > 0) ? '1' : '0' }}" data-needs-review="{{ $rowNeedsReview ? '1' : '0' }}">
                                        <td><span class="badge bg-dark-subtle text-dark fs-12 fw-bold font-monospace">{{ $p->year }} / {{ sprintf('%02d', $p->month) }}</span></td>
                                        <td><span class="fw-semibold">{{ $p->branch?->name }}</span></td>
                                        <td>{{ number_format($rowBasic) }} ج.م</td>
                                        <td class="text-info">{{ number_format($rowAllowances) }} ج.م</td>
                                        <td class="text-danger">-{{ number_format($rowDeductions) }} ج.م</td>
                                        <td class="fw-bold fs-14 {{ $rowNeedsReview ? 'text-warning' : 'text-success' }}">{{ number_format($rowNet) }} ج.م</td>
                                        <td>
                                            @if($rowNeedsReview)
                                                <span class="badge bg-warning-subtle text-warning fs-12">
                                                    <i class="ri-error-warning-line me-1"></i>يحتاج مراجعة الأرقام
                                                </span>
                                            @elseif($p->status === 'draft')
                                                <span class="badge bg-warning-subtle text-warning fs-12"><i class="ri-time-line me-1"></i>مسودة للمراجعة</span>
                                            @elseif($p->status === 'approved')
                                                <span class="badge bg-info-subtle text-info fs-12"><i class="ri-check-line me-1"></i>معتمد وجاهز للصرف</span>
                                            @else
                                                <span class="badge bg-success-subtle text-success fs-12"><i class="ri-check-double-line me-1"></i>تم الصرف والمطابقة</span>
                                            @endif
                                        </td>
                                        <td>{{ $p->approvedBy?->name ?? '—' }}</td>
                                        <td class="text-center">
                                            @if($rowNeedsReview)
                                                <span class="text-warning fs-12"><i class="ri-lock-line me-1"></i>ممنوع حتى تصحيح الأرقام</span>
                                            @elseif($p->status === 'draft')
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
                                <tr id="batchNoResultsRow" style="display: none;">
                                    <td colspan="9" class="text-center py-4 text-muted fs-13">
                                        <i class="ri-file-history-line fs-20 d-block mb-1 text-muted"></i>
                                        لا توجد مسيرات مسجلة لهذا الفرع
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
