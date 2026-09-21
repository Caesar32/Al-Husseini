@extends('admin.layouts.master')

@section('title', 'تقارير الحضور والغياب والخصومات | مركز الحسيني لبطاريات السيارات')

@section('css')
    {{-- 1. تخصيصات التنسيق والطباعة لتقارير الموارد البشرية --}}
    @include('admin.hr.partials.reports.styles')
@endsection

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'الموارد البشرية', 'title' => 'تقارير الحضور والغياب والخصومات'])

    {{-- 2. ترويسة الطباعة الرسمية وشريط التحكم والتصفية الزمنية --}}
    @include('admin.hr.partials.reports.filters')

    {{-- 3. بطاقات المؤشرات الإحصائية الرئيسية (KPI Cards) --}}
    @include('admin.hr.partials.reports.summary-cards')

    {{-- 4. الرسوم البيانية التفاعلية (ApexCharts) لتوزيع الحضور ومسار الالتزام --}}
    @include('admin.hr.partials.reports.charts')

    <!-- Main Navigation Tabs for Detailed Reports -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header p-0 bg-transparent border-bottom">
                    <ul class="nav nav-tabs nav-tabs-custom card-header-tabs border-bottom-0" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active fw-semibold fs-14 py-3" data-bs-toggle="tab" href="#tab-attendance-detail" role="tab">
                                <i class="ri-file-list-3-line me-1 text-primary"></i> كشف الحضور والانصراف التفصيلي
                                <span class="badge bg-primary-subtle text-primary ms-1" id="badge-count-attendance">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-semibold fs-14 py-3" data-bs-toggle="tab" href="#tab-employee-summary" role="tab">
                                <i class="ri-bar-chart-grouped-line me-1 text-info"></i> ملخص أداء ومعدلات غياب الموظفين
                                <span class="badge bg-info-subtle text-info ms-1" id="badge-count-staff">8</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-semibold fs-14 py-3" data-bs-toggle="tab" href="#tab-deductions-log" role="tab">
                                <i class="ri-money-dollar-box-line me-1 text-danger"></i> كشف الخصومات والجزاءات الإدارية
                                <span class="badge bg-danger-subtle text-danger ms-1" id="badge-count-deductions">0</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-0">
                    <div class="tab-content">
                        {{-- 5. جدول كشف الحضور والانصراف اليومي التفصيلي --}}
                        @include('admin.hr.partials.reports.attendance-tab')

                        {{-- 6. جدول ملخص أداء ومعدلات غياب وتأخير الموظفين --}}
                        @include('admin.hr.partials.reports.staff-tab')

                        {{-- 7. جدول سجل قرارات الخصومات والجزاءات الإدارية --}}
                        @include('admin.hr.partials.reports.deductions-tab')
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 8. تذييل التوقيعات الرسمية المعتمدة للطباعة الورقية --}}
    @include('admin.hr.partials.reports.print-footer')

    {{-- 9. نافذة كشف حساب وسجل انضباط الموظف الفردي --}}
    @include('admin.hr.partials.reports.modal-employee')
@endsection

@section('script')
    {{-- 10. محرك الرسوم البيانية والجافاسكريبت وتصدير التقارير --}}
    @include('admin.hr.partials.reports.scripts')
@endsection
