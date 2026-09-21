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
