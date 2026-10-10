@extends('admin.layouts.master')

@section('title', 'تفاصيل مسير الرواتب | مجموعة الحسيني')

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'مسير الرواتب', 'title' => 'تفاصيل مسير ' . $payroll->year . ' / ' . sprintf('%02d', $payroll->month)])

    @php
        $statusLabels = [
            'draft'     => ['bg-warning-subtle text-warning', 'مسودة للمراجعة'],
            'reviewed'  => ['bg-secondary-subtle text-secondary', 'تمت المراجعة'],
            'approved'  => ['bg-info-subtle text-info', 'معتمد وجاهز للصرف'],
            'disbursed' => ['bg-success-subtle text-success', 'تم الصرف والمطابقة'],
        ];
        [$statusClass, $statusLabel] = $statusLabels[$payroll->status] ?? ['bg-light text-body', $payroll->status];
    @endphp

    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <h5 class="card-title mb-0 flex-grow-1">
                        <i class="ri-file-list-3-line text-primary me-2"></i>
                        {{ $payroll->branch?->name }} — {{ $payroll->year }} / {{ sprintf('%02d', $payroll->month) }}
                    </h5>
                    <span class="badge {{ $statusClass }} fs-12 me-2">{{ $statusLabel }}</span>
                    <a href="{{ route('admin.hr.payroll') }}" class="btn btn-sm btn-light"><i class="ri-arrow-go-back-line me-1"></i> رجوع للمسيرات</a>
                </div>
                <div class="card-body">
                    <div class="row g-3 text-center">
                        <div class="col-md-2 col-6"><span class="text-muted fs-12 d-block">إجمالي الأساسي</span><strong><x-compact-money :amount="$consistency['basic']" /></strong></div>
                        <div class="col-md-2 col-6"><span class="text-muted fs-12 d-block">إجمالي البدلات</span><strong class="text-info"><x-compact-money :amount="$consistency['allowances']" /></strong></div>
                        <div class="col-md-2 col-6"><span class="text-muted fs-12 d-block">إجمالي الإضافي</span><strong class="text-info"><x-compact-money :amount="$consistency['overtime']" /></strong></div>
                        <div class="col-md-2 col-6"><span class="text-muted fs-12 d-block">إجمالي الخصومات</span><strong class="text-danger">-<x-compact-money :amount="$consistency['deductions']" /></strong></div>
                        <div class="col-md-2 col-6"><span class="text-muted fs-12 d-block">أرصدة مرحّلة</span><strong class="text-warning"><x-compact-money :amount="$consistency['carried_debt']" /></strong></div>
                        <div class="col-md-2 col-6"><span class="text-muted fs-12 d-block">صافي المسجل</span><strong class="{{ $consistency['consistent'] ? 'text-success' : 'text-warning' }}"><x-compact-money :amount="$consistency['stored_net']" /></strong></div>
                    </div>

                    <div class="mt-3 text-muted fs-12">
                        المعتمد بواسطة: {{ $payroll->approvedBy?->name ?? '—' }}
                        @if($payroll->disbursed_at)
                            | تاريخ الصرف: {{ $payroll->disbursed_at->format('Y-m-d H:i') }}
                        @endif
                    </div>

                    @if(!$consistency['consistent'])
                        <div class="alert alert-warning mt-3 mb-0" id="payrollConsistencyReasons">
                            <strong><i class="ri-error-warning-line me-1"></i> يحتاج مراجعة الأرقام قبل الاعتماد أو الصرف:</strong>
                            <ul class="mb-0 mt-1">
                                @foreach($consistency['reasons'] as $reason)
                                    <li>{{ $reason }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <div class="alert alert-success mt-3 mb-0">
                            <i class="ri-shield-check-line me-1"></i> إجماليات المسير مطابقة لبنوده (الصافي = الأساسي + البدلات + الإضافي - الخصومات + الرصيد المرحّل).
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0"><i class="ri-team-line text-primary me-2"></i> بنود المسير المسجلة</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>الموظف</th>
                                    <th class="text-end">الأساسي</th>
                                    <th class="text-end">البدلات والعمولات</th>
                                    <th class="text-end">الإضافي</th>
                                    <th class="text-end">الخصومات</th>
                                    <th class="text-end">منها سداد دين</th>
                                    <th class="text-end">رصيد مرحّل</th>
                                    <th class="text-end">أيام الغياب</th>
                                    <th class="text-end">الصافي</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($payroll->items as $item)
                                    <tr>
                                        <td>
                                            <div class="fw-bold fs-13">{{ $item->employee?->full_name }}</div>
                                            <small class="text-muted font-monospace">{{ $item->employee?->employee_code }}</small>
                                            <small class="text-muted d-block">{{ $item->employee?->jobTitle?->title_name }}</small>
                                        </td>
                                        <td class="text-end">{{ number_format((float) $item->basic_salary, 2) }}</td>
                                        <td class="text-end text-info">{{ number_format((float) $item->total_allowance, 2) }}</td>
                                        <td class="text-end text-info">{{ number_format((float) $item->total_overtime, 2) }}</td>
                                        <td class="text-end text-danger">-{{ number_format((float) $item->total_deduction, 2) }}</td>
                                        <td class="text-end">{{ number_format((float) $item->debt_repayment, 2) }}</td>
                                        <td class="text-end text-warning">{{ number_format((float) $item->carried_debt, 2) }}</td>
                                        <td class="text-end">{{ $item->absent_days }}</td>
                                        <td class="text-end fw-bold text-success">{{ number_format((float) $item->net_salary, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="text-center py-4 text-muted">لا توجد بنود مسجلة لهذا المسير.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
