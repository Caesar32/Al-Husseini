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

    {{-- 1. بطاقات الإحصائيات المالية العلوية --}}
    @include('admin.hr.partials.payroll.stats')

    {{-- 2. شريط البحث وأزرار التحكم بالمسير والخصومات --}}
    @include('admin.hr.partials.payroll.controls')

    {{-- 3. مسيرات الرواتب الشهرية والمسودات المعتمدة --}}
    @include('admin.hr.partials.payroll.batches-table')

    {{-- 4. كشف استحقاقات الموظفين للشهر الحالي --}}
    @include('admin.hr.partials.payroll.breakdown-table')

    {{-- 5. سجل قرارات الخصم الإدارية المعتمدة --}}
    @include('admin.hr.partials.payroll.deductions-log')

    {{-- 6. نافذة احتساب مسير رواتب الشهر --}}
    @include('admin.hr.partials.payroll.modal-generate')

    {{-- 7. نافذة تطبيق خصم إداري على موظف --}}
    @include('admin.hr.partials.payroll.modal-deduction')

    {{-- 8. نافذة قسيمة الراتب الرقمية القابلة للطباعة (Payslip) --}}
    @include('admin.hr.partials.payroll.modal-payslip')
@endsection

@section('script')
    {{-- 9. محرك الجافاسكريبت والربط البرمجي لعمليات المسير والخصومات --}}
    @include('admin.hr.partials.payroll.scripts')
@endsection
