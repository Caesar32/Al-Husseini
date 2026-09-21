@extends('admin.layouts.master')

@section('title', 'تقارير الحضور والغياب والخصومات | مركز الحسيني لبطاريات السيارات')

@section('css')
<style>
    /* Custom styles for Reports & Analytics */
    .report-filter-pill {
        border-radius: 30px;
        padding: 6px 18px;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    .report-stat-card {
        border-radius: 12px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .report-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.06);
    }
    .badge-status-on_time {
        background-color: rgba(42, 157, 143, 0.15);
        color: #2a9d8f;
        border: 1px solid rgba(42, 157, 143, 0.3);
    }
    .badge-status-late {
        background-color: rgba(244, 162, 97, 0.18);
        color: #e76f51;
        border: 1px solid rgba(244, 162, 97, 0.4);
    }
    .badge-status-absent {
        background-color: rgba(230, 57, 70, 0.15);
        color: #e63946;
        border: 1px solid rgba(230, 57, 70, 0.3);
    }
    .badge-status-on_leave {
        background-color: rgba(69, 123, 157, 0.15);
        color: #457b9d;
        border: 1px solid rgba(69, 123, 157, 0.3);
    }

    /* Print Specific Layout */
    @media print {
        body {
            background-color: #fff !important;
            color: #000 !important;
        }
        .app-menu, .topbar, .footer, .btn, .nav-tabs, .report-controls-card, .no-print {
            display: none !important;
        }
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        .page-content {
            padding: 0 !important;
        }
        .container-fluid {
            width: 100% !important;
            padding: 0 !important;
        }
        .print-header {
            display: block !important;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .print-footer {
            display: block !important;
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
            margin-bottom: 20px !important;
        }
        .table {
            border: 1px solid #000 !important;
        }
        .table th, .table td {
            border: 1px solid #ccc !important;
            color: #000 !important;
            font-size: 11pt !important;
        }
        .tab-content > .tab-pane {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
    }

    .print-header, .print-footer {
        display: none;
    }
</style>
@endsection

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'الموارد البشرية', 'title' => 'تقارير الحضور والغياب والخصومات'])

    <!-- Official Print Header (Visible only when printing) -->
    <div class="print-header text-center">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="text-start">
                <h5 class="fw-bold mb-0">مركز الحسيني لبيع وصيانة بطاريات السيارات</h5>
                <p class="text-muted fs-12 mb-0">إدارة الموارد البشرية وشؤون الفنيين والعاملين</p>
                <p class="text-muted fs-11 mb-0">سجل تجاري / بطاقة ضريبية معتمدة</p>
            </div>
            <div class="text-center">
                <h3 class="fw-bold text-dark mb-1" id="print-report-title">تقرير كشف الحضور والغياب والجزاءات</h3>
                <div class="badge bg-light text-dark border fs-12 py-1 px-3" id="print-period-label">الفترة: -</div>
            </div>
            <div class="text-end">
                <p class="text-muted fs-11 mb-0">تاريخ الطباعة: <span id="print-generation-date"></span></p>
                <p class="text-muted fs-11 mb-0">طبع بواسطة: الإدارة التنفيذية</p>
            </div>
        </div>
    </div>

    <!-- Control & Period Selector Bar -->
    <div class="row report-controls-card">
        <div class="col-12">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-3">
                    <div class="row g-3 align-items-center">
                        <!-- Mode Selector (Daily, Monthly, Custom) -->
                        <div class="col-xl-4 col-lg-5 col-md-12">
                            <label class="form-label fs-12 fw-bold text-muted mb-1 d-block">نطاق التقرير الزمني:</label>
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-outline-primary active report-filter-pill" id="btn-mode-daily" onclick="setReportMode('daily')">
                                    <i class="ri-calendar-check-line me-1"></i> تقرير يومي
                                </button>
                                <button type="button" class="btn btn-outline-primary report-filter-pill" id="btn-mode-monthly" onclick="setReportMode('monthly')">
                                    <i class="ri-calendar-todo-line me-1"></i> تقرير شهري
                                </button>
                                <button type="button" class="btn btn-outline-primary report-filter-pill" id="btn-mode-custom" onclick="setReportMode('custom')">
                                    <i class="ri-calendar-event-line me-1"></i> فترة مخصصة
                                </button>
                            </div>
                        </div>

                        <!-- Dynamic Date / Month Controls -->
                        <div class="col-xl-5 col-lg-7 col-md-12">
                            <!-- Daily Control -->
                            <div id="controls-daily" class="date-controls-group">
                                <label class="form-label fs-12 fw-bold text-muted mb-1">اختر اليوم للمتابعة اليومية:</label>
                                <div class="input-group">
                                    <button class="btn btn-light border" type="button" onclick="navigateDay(-1)" title="اليوم السابق">
                                        <i class="ri-arrow-right-s-line"></i>
                                    </button>
                                    <input type="date" class="form-control text-center fw-bold" id="input-daily-date" onchange="loadReport()">
                                    <button class="btn btn-light border" type="button" onclick="navigateDay(1)" title="اليوم التالي">
                                        <i class="ri-arrow-left-s-line"></i>
                                    </button>
                                    <button class="btn btn-soft-primary" type="button" onclick="setTodayDate()">اليوم</button>
                                </div>
                            </div>

                            <!-- Monthly Control -->
                            <div id="controls-monthly" class="date-controls-group d-none">
                                <label class="form-label fs-12 fw-bold text-muted mb-1">اختر الشهر والسنة:</label>
                                <div class="row g-2">
                                    <div class="col-7">
                                        <select class="form-select fw-semibold" id="select-month" onchange="loadReport()">
                                            <option value="1">يناير (1)</option>
                                            <option value="2">فبراير (2)</option>
                                            <option value="3">مارس (3)</option>
                                            <option value="4">أبريل (4)</option>
                                            <option value="5">مايو (5)</option>
                                            <option value="6">يونيو (6)</option>
                                            <option value="7">يوليو (7)</option>
                                            <option value="8">أغسطس (8)</option>
                                            <option value="9">سبتمبر (9)</option>
                                            <option value="10">أكتوبر (10)</option>
                                            <option value="11">نوفمبر (11)</option>
                                            <option value="12">ديسمبر (12)</option>
                                        </select>
                                    </div>
                                    <div class="col-5">
                                        <select class="form-select fw-semibold" id="select-year" onchange="loadReport()">
                                            <option value="2026" selected>2026</option>
                                            <option value="2025">2025</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Custom Range Control -->
                            <div id="controls-custom" class="date-controls-group d-none">
                                <label class="form-label fs-12 fw-bold text-muted mb-1">تحديد نطاق التواريخ:</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="date" class="form-control" id="input-range-start" onchange="loadReport()" title="من تاريخ">
                                    </div>
                                    <div class="col-6">
                                        <input type="date" class="form-control" id="input-range-end" onchange="loadReport()" title="إلى تاريخ">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Secondary Filters & Export Actions -->
                        <div class="col-xl-3 col-lg-12 text-xl-end">
                            <label class="form-label fs-12 fw-bold text-muted mb-1 d-block">إجراءات التقرير:</label>
                            <div class="d-flex gap-2 justify-content-xl-end">
                                <button type="button" class="btn btn-primary" onclick="printOfficialReport()" title="طباعة كشف رسمي معتمد">
                                    <i class="ri-printer-line align-middle me-1"></i> طباعة
                                </button>
                                <button type="button" class="btn btn-success" onclick="exportReportCSV()" title="تنزيل كشف Excel / CSV">
                                    <i class="ri-file-excel-2-line align-middle me-1"></i> تصدير Excel
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Filter by Department & Search Bar -->
                    <div class="row g-3 align-items-center mt-2 pt-2 border-top">
                        <div class="col-md-4">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="ri-building-line"></i></span>
                                <select class="form-select" id="filter-department" onchange="applyFilters()">
                                    <option value="">جميع الأقسام والورش</option>
                                    <option value="المبيعات والمعرض">المبيعات والمعرض (صالة البيع)</option>
                                    <option value="ورشة الصيانة والشحن">ورشة الصيانة وشحن البطاريات ومياه النار</option>
                                    <option value="فنيو التركيب والكهرباء">فنيو التركيب وكهرباء السيارات وفحص الدينامو</option>
                                    <option value="خدمة الطوارئ والإنقاذ المتنقل">طوارئ وإنقاذ بطاريات الطريق</option>
                                    <option value="المخازن وسلاسل الإمداد">مخزن البطاريات والكهنة المسترجعة</option>
                                    <option value="الإدارة والإشراف">الإدارة والإشراف</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="ri-user-search-line"></i></span>
                                <input type="text" class="form-control" id="search-employee" placeholder="بحث باسم الموظف أو الكود..." oninput="applyFilters()">
                            </div>
                        </div>

                        <div class="col-md-4 text-md-end">
                            <span class="badge bg-light text-secondary border fs-12 px-3 py-2" id="report-period-badge">
                                <i class="ri-time-line me-1 text-primary"></i> <span id="report-period-text">جاري تحميل الفترة...</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Summary Metric Cards -->
    <div class="row mb-3">
        <!-- 1. Attendance & Punctuality -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate report-stat-card border-start border-success border-4 h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-semibold text-muted fs-12 mb-1">نسبة الحضور والالتزام</p>
                            <h4 class="fs-22 fw-bold text-success mb-1" id="kpi-attendance-rate">0%</h4>
                            <p class="text-muted fs-12 mb-0">
                                <span class="fw-semibold text-dark" id="kpi-present-count">0</span> حاضر في الموعد
                            </p>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-20 shadow-sm">
                                <i class="ri-checkbox-circle-fill"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Lateness Minutes -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate report-stat-card border-start border-warning border-4 h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-semibold text-muted fs-12 mb-1">إجمالي دقائق التأخير</p>
                            <h4 class="fs-22 fw-bold text-warning mb-1" id="kpi-lateness-minutes">0 دقيقة</h4>
                            <p class="text-muted fs-12 mb-0">
                                بواقع <span class="fw-semibold text-warning" id="kpi-late-count">0</span> حالة تأخير
                            </p>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-20 shadow-sm">
                                <i class="ri-time-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Absence & Leaves -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate report-stat-card border-start border-danger border-4 h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-semibold text-muted fs-12 mb-1">الغياب والإجازات</p>
                            <h4 class="fs-22 fw-bold text-danger mb-1" id="kpi-absence-count">0 يوم</h4>
                            <p class="text-muted fs-12 mb-0">
                                <span class="text-danger fw-semibold" id="kpi-unexcused-count">0</span> بدون إذن | <span class="text-info" id="kpi-leave-count">0</span> إجازة
                            </p>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-20 shadow-sm">
                                <i class="ri-user-unfollow-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Total Deductions -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate report-stat-card border-start border-primary border-4 h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-semibold text-muted fs-12 mb-1">إجمالي الجزاءات والخصومات</p>
                            <h4 class="fs-22 fw-bold text-primary mb-1" id="kpi-deductions-amount">0 ج.م</h4>
                            <p class="text-muted fs-12 mb-0">
                                عدد <span class="fw-semibold text-dark" id="kpi-deductions-count">0</span> قرار إداري معتمد
                            </p>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20 shadow-sm">
                                <i class="ri-scissors-cut-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Visual Analytics Charts (Attendance Distribution & Trend) -->
    <div class="row mb-4 no-print">
        <!-- Attendance Breakdown Donut -->
        <div class="col-xl-4 col-lg-5 mb-3">
            <div class="card shadow-sm h-100 mb-0">
                <div class="card-header border-0 pb-0">
                    <h5 class="card-title mb-0 fw-bold fs-14">
                        <i class="ri-pie-chart-2-line text-primary me-1"></i> توزيع نسب الحضور والانضباط
                    </h5>
                </div>
                <div class="card-body d-flex flex-column justify-content-center">
                    <div id="chart-attendance-pie" style="min-height: 250px;"></div>
                    <div class="d-flex justify-content-around text-center mt-2 pt-2 border-top fs-12">
                        <div>
                            <span class="badge badge-dot bg-success me-1"></span>
                            <span class="text-muted">في الموعد</span>
                        </div>
                        <div>
                            <span class="badge badge-dot bg-warning me-1"></span>
                            <span class="text-muted">متأخر</span>
                        </div>
                        <div>
                            <span class="badge badge-dot bg-danger me-1"></span>
                            <span class="text-muted">غياب</span>
                        </div>
                        <div>
                            <span class="badge badge-dot bg-info me-1"></span>
                            <span class="text-muted">إجازة</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily / Monthly Progression Chart -->
        <div class="col-xl-8 col-lg-7 mb-3">
            <div class="card shadow-sm h-100 mb-0">
                <div class="card-header border-0 d-flex justify-content-between align-items-center pb-0">
                    <h5 class="card-title mb-0 fw-bold fs-14">
                        <i class="ri-line-chart-line text-success me-1"></i> مسار حركة الحضور والتأخير اليومي
                    </h5>
                    <span class="badge bg-light text-muted border fs-11">محدث لحظياً</span>
                </div>
                <div class="card-body">
                    <div id="chart-timeline-bar" style="min-height: 250px;"></div>
                </div>
            </div>
        </div>
    </div>

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
                        <!-- TAB 1: DETAILED ATTENDANCE LOG -->
                        <div class="tab-pane active p-3" id="tab-attendance-detail" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle table-nowrap mb-0" id="table-attendance">
                                    <thead class="table-light">
                                        <tr class="text-uppercase fs-12 text-muted">
                                            <th>الموظف والكود</th>
                                            <th>القسم والوظيفة بمركز البطاريات</th>
                                            <th>التاريخ</th>
                                            <th>الوردية الرسمية</th>
                                            <th>وقت البصمة الفعلي</th>
                                            <th>وقت الانصراف</th>
                                            <th>دقائق التأخير</th>
                                            <th>الحالة</th>
                                            <th class="no-print">الخصومات المترتبة</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-attendance">
                                        <!-- Rendered dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 2: AGGREGATED STAFF ABSENCE & PUNCTUALITY -->
                        <div class="tab-pane p-3" id="tab-employee-summary" role="tabpanel">
                            <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-3 fs-13">
                                <i class="ri-information-line fs-20 me-2"></i>
                                <div>
                                    هذا الجدول يلخص إجمالي عدد أيام العمل، مرات الحضور، التأخيرات المجمعة، ونسبة الالتزام لكل فني وبائع بالمركز خلال الفترة المختارة.
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle table-nowrap mb-0" id="table-staff-summary">
                                    <thead class="table-light">
                                        <tr class="text-uppercase fs-12 text-muted">
                                            <th>الموظف</th>
                                            <th>التخصص والوظيفة</th>
                                            <th>أيام العمل المسجلة</th>
                                            <th>أيام الحضور</th>
                                            <th>مرات التأخير</th>
                                            <th>إجمالي دقائق التأخير</th>
                                            <th>أيام الغياب</th>
                                            <th>أيام الإجازة</th>
                                            <th>معدل الالتزام</th>
                                            <th>إجمالي الخصومات</th>
                                            <th class="no-print">كشف حساب</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-staff-summary">
                                        <!-- Rendered dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 3: DEDUCTIONS & PENALTIES REGISTER -->
                        <div class="tab-pane p-3" id="tab-deductions-log" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle table-nowrap mb-0" id="table-deductions">
                                    <thead class="table-light">
                                        <tr class="text-uppercase fs-12 text-muted">
                                            <th>رقم القرار الإداري</th>
                                            <th>الموظف المحرر ضده الخصم</th>
                                            <th>القسم بمركز البطاريات</th>
                                            <th>تاريخ الواقعة</th>
                                            <th>قيمة الخصم (ج.م)</th>
                                            <th>سبب الجزاء (المخالفة)</th>
                                            <th>ملاحظات مدير المركز</th>
                                            <th>الحالة</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-deductions">
                                        <!-- Rendered dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Official Print Signatures Footer (Visible only when printing) -->
    <div class="print-footer">
        <div class="row text-center mt-5 pt-4 border-top">
            <div class="col-4">
                <p class="fw-bold mb-1">مسؤول الحضور والبصمة</p>
                <p class="text-muted fs-11 mb-4">قسم شؤون العاملين</p>
                <p class="text-muted fs-12 mb-0">التوقيع: ...........................</p>
            </div>
            <div class="col-4">
                <p class="fw-bold mb-1">المحاسب المالي ومسؤول الرواتب</p>
                <p class="text-muted fs-11 mb-4">الإدارة المالية</p>
                <p class="text-muted fs-12 mb-0">التوقيع: ...........................</p>
            </div>
            <div class="col-4">
                <p class="fw-bold mb-1">مدير عام مركز البطاريات</p>
                <p class="text-muted fs-11 mb-4">الحاج محمود الحسيني</p>
                <p class="text-muted fs-12 mb-0">يعتمد: ...........................</p>
            </div>
        </div>
    </div>

    <!-- Individual Employee Detail Modal -->
    <div class="modal fade" id="employeeDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="employeeModalTitle">كشف حساب وانضباط الموظف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="employeeModalBody">
                    <!-- Populated dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-primary" onclick="printModalEmployeeCard()">
                        <i class="ri-printer-line me-1"></i> طباعة كشف الموظف
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
<!-- ApexCharts JS -->
<script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>

<script>
    'use strict';

    let currentMode = 'daily'; // 'daily' | 'monthly' | 'custom'
    let currentReportData = null;
    let attendancePieChart = null;
    let timelineBarChart = null;

    document.addEventListener('DOMContentLoaded', function () {
        // Initialize Default Values
        const today = new Date();
        const y = today.getFullYear();
        const m = today.getMonth() + 1;
        const dStr = today.toISOString().split('T')[0];

        const dailyInput = document.getElementById('input-daily-date');
        if (dailyInput) dailyInput.value = dStr;

        const monthSelect = document.getElementById('select-month');
        if (monthSelect) monthSelect.value = m;

        const yearSelect = document.getElementById('select-year');
        if (yearSelect) yearSelect.value = y;

        const rangeStart = document.getElementById('input-range-start');
        const rangeEnd = document.getElementById('input-range-end');
        if (rangeStart && rangeEnd) {
            rangeStart.value = `${y}-${String(m).padStart(2, '0')}-01`;
            rangeEnd.value = dStr;
        }

        const printGenDate = document.getElementById('print-generation-date');
        if (printGenDate) {
            printGenDate.textContent = today.toLocaleDateString('ar-EG', { dateStyle: 'full' });
        }

        // Load initial daily report
        loadReport();

        // Listen for external updates
        window.addEventListener('alhusseini-hr-updated', function () {
            loadReport();
        });
    });

    function setReportMode(mode) {
        currentMode = mode;

        // Button styles
        ['daily', 'monthly', 'custom'].forEach(m => {
            const btn = document.getElementById(`btn-mode-${m}`);
            const ctrl = document.getElementById(`controls-${m}`);
            if (btn) {
                if (m === mode) {
                    btn.classList.add('active', 'btn-primary');
                    btn.classList.remove('btn-outline-primary');
                } else {
                    btn.classList.remove('active', 'btn-primary');
                    btn.classList.add('btn-outline-primary');
                }
            }
            if (ctrl) {
                if (m === mode) {
                    ctrl.classList.remove('d-none');
                } else {
                    ctrl.classList.add('d-none');
                }
            }
        });

        loadReport();
    }

    function navigateDay(offset) {
        const input = document.getElementById('input-daily-date');
        if (!input.value) return;
        const curr = new Date(input.value);
        curr.setDate(curr.getDate() + offset);
        input.value = curr.toISOString().split('T')[0];
        loadReport();
    }

    function setTodayDate() {
        const input = document.getElementById('input-daily-date');
        input.value = new Date().toISOString().split('T')[0];
        loadReport();
    }

    function loadReport() {
        if (!window.AlHusseiniHR) return;

        let periodTitle = '';
        let printPeriodText = '';

        if (currentMode === 'daily') {
            const targetDate = document.getElementById('input-daily-date').value;
            currentReportData = window.AlHusseiniHR.getDailyReport(targetDate);
            
            const dateObj = new Date(targetDate);
            const dateArabic = dateObj.toLocaleDateString('ar-EG', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            periodTitle = `تقرير يوم: ${dateArabic}`;
            printPeriodText = `كشف يوم: ${targetDate} (${dateArabic})`;
        } else if (currentMode === 'monthly') {
            const m = parseInt(document.getElementById('select-month').value);
            const y = parseInt(document.getElementById('select-year').value);
            currentReportData = window.AlHusseiniHR.getMonthlyReport(y, m);
            
            const monthNames = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
            periodTitle = `تقرير شهر: ${monthNames[m]} ${y}`;
            printPeriodText = `كشف شهر: ${monthNames[m]} ${y} (من ${currentReportData.startDate} إلى ${currentReportData.endDate})`;
        } else {
            const start = document.getElementById('input-range-start').value;
            const end = document.getElementById('input-range-end').value;
            currentReportData = window.AlHusseiniHR.getRangeReport(start, end);
            periodTitle = `تقرير الفترة: من ${start} إلى ${end}`;
            printPeriodText = `الفترة من ${start} إلى ${end}`;
        }

        // Update Period Badges
        const periodTextEl = document.getElementById('report-period-text');
        if (periodTextEl) periodTextEl.textContent = periodTitle;

        const printPeriodEl = document.getElementById('print-period-label');
        if (printPeriodEl) printPeriodEl.textContent = printPeriodText;

        renderKPIs();
        renderCharts();
        applyFilters();
    }

    function renderKPIs() {
        if (!currentReportData) return;
        const summary = currentReportData.summary;

        if (currentMode === 'daily') {
            document.getElementById('kpi-attendance-rate').textContent = `${summary.attendanceRate}%`;
            document.getElementById('kpi-present-count').textContent = `${summary.presentCount} من ${summary.totalStaff}`;
            document.getElementById('kpi-lateness-minutes').textContent = `${summary.totalLateMins} دقيقة`;
            document.getElementById('kpi-late-count').textContent = summary.lateCount;
            document.getElementById('kpi-absence-count').textContent = `${summary.absentCount + summary.leaveCount} فرد`;
            document.getElementById('kpi-unexcused-count').textContent = summary.absentCount;
            document.getElementById('kpi-leave-count').textContent = summary.leaveCount;
            document.getElementById('kpi-deductions-amount').textContent = window.AlHusseiniHR.formatCurrency(summary.totalDeductionsAmount);
            document.getElementById('kpi-deductions-count').textContent = summary.deductionsCount;
        } else {
            document.getElementById('kpi-attendance-rate').textContent = `${summary.avgAttendanceRate}%`;
            document.getElementById('kpi-present-count').textContent = `${summary.totalPresents} يوم عمل`;
            document.getElementById('kpi-lateness-minutes').textContent = `${summary.totalLateMins} دقيقة`;
            document.getElementById('kpi-late-count').textContent = summary.totalLates;
            document.getElementById('kpi-absence-count').textContent = `${summary.totalAbsents} يوم غياب`;
            document.getElementById('kpi-unexcused-count').textContent = summary.totalAbsents;
            document.getElementById('kpi-leave-count').textContent = summary.staffReport.reduce((s, r) => s + r.leaveDays, 0);
            document.getElementById('kpi-deductions-amount').textContent = window.AlHusseiniHR.formatCurrency(summary.totalDeductionsAmount);
            document.getElementById('kpi-deductions-count').textContent = summary.deductionsCount;
        }
    }

    function renderCharts() {
        if (!currentReportData) return;
        const summary = currentReportData.summary;

        // 1. Donut Pie Chart
        let pieSeries = [];
        if (currentMode === 'daily') {
            pieSeries = [summary.onTimeCount, summary.lateCount, summary.absentCount, summary.leaveCount];
        } else {
            const onTime = summary.totalOnTimes;
            const late = summary.totalLates;
            const absent = summary.totalAbsents;
            const leave = summary.staffReport.reduce((s, r) => s + r.leaveDays, 0);
            pieSeries = [onTime, late, absent, leave];
        }

        const pieOptions = {
            series: pieSeries,
            chart: {
                type: 'donut',
                height: 250,
                fontFamily: 'inherit'
            },
            labels: ['حاضر في الموعد', 'متأخر عن الوردية', 'غياب بدون إذن', 'إجازة رسمية'],
            colors: ['#2a9d8f', '#f4a261', '#e63946', '#457b9d'],
            legend: { show: false },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return Math.round(val) + "%";
                }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'إجمالي السجلات',
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                }
                            }
                        }
                    }
                }
            }
        };

        if (attendancePieChart) {
            attendancePieChart.updateOptions(pieOptions);
        } else {
            attendancePieChart = new ApexCharts(document.querySelector("#chart-attendance-pie"), pieOptions);
            attendancePieChart.render();
        }

        // 2. Timeline Bar / Column Chart
        let categories = [];
        let seriesOnTime = [];
        let seriesLate = [];
        let seriesAbsent = [];

        if (currentMode === 'daily') {
            // Compare Departments on this day
            const depts = window.AlHusseiniHR.getDepartments();
            categories = depts.map(d => d.replace('بمركز البطاريات', '').trim());

            categories.forEach(dept => {
                const staffInDept = currentReportData.employees.filter(e => e.employee.department.includes(dept));
                seriesOnTime.push(staffInDept.filter(s => s.status === 'on_time').length);
                seriesLate.push(staffInDept.filter(s => s.status === 'late').length);
                seriesAbsent.push(staffInDept.filter(s => s.status === 'absent' || s.status === 'on_leave').length);
            });
        } else {
            // Daily trend progression across the month
            const dailySeries = currentReportData.dailySeries || [];
            categories = dailySeries.map(d => d.date.split('-').slice(1).join('/'));
            seriesOnTime = dailySeries.map(d => d.present - d.late);
            seriesLate = dailySeries.map(d => d.late);
            seriesAbsent = dailySeries.map(d => d.absent);
        }

        const barOptions = {
            series: [
                { name: 'حضور في الموعد', data: seriesOnTime },
                { name: 'حالات تأخير', data: seriesLate },
                { name: 'غياب', data: seriesAbsent }
            ],
            chart: {
                type: 'bar',
                height: 250,
                stacked: true,
                toolbar: { show: false },
                fontFamily: 'inherit'
            },
            colors: ['#2a9d8f', '#f4a261', '#e63946'],
            plotOptions: {
                bar: {
                    horizontal: false,
                    borderRadius: 4,
                    columnWidth: '45%'
                }
            },
            xaxis: {
                categories: categories,
                labels: {
                    style: { fontSize: '11px' }
                }
            },
            yaxis: {
                title: { text: currentMode === 'daily' ? 'عدد الفنيين' : 'سجلات اليوم' }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right'
            },
            dataLabels: { enabled: false }
        };

        if (timelineBarChart) {
            timelineBarChart.updateOptions(barOptions);
        } else {
            timelineBarChart = new ApexCharts(document.querySelector("#chart-timeline-bar"), barOptions);
            timelineBarChart.render();
        }
    }

    function applyFilters() {
        if (!currentReportData) return;

        const deptFilter = document.getElementById('filter-department').value.toLowerCase();
        const searchKeyword = document.getElementById('search-employee').value.trim().toLowerCase();

        // 1. Filter Attendance Detailed Records
        let attendanceList = [];
        if (currentMode === 'daily') {
            attendanceList = currentReportData.employees;
        } else {
            // In Monthly or Range mode, flatten each employee's attendance details
            currentReportData.staffReport.forEach(staff => {
                staff.attendanceDetails.forEach(att => {
                    attendanceList.push({
                        employee: staff.employee,
                        date: att.date,
                        status: att.status,
                        punchIn: att.punchIn || '-',
                        punchOut: att.punchOut || '-',
                        latenessMinutes: att.latenessMinutes || 0,
                        totalDeductions: 0
                    });
                });
            });
        }

        // Apply Dept & Search Filters to Attendance List
        const filteredAttendance = attendanceList.filter(item => {
            const matchesDept = !deptFilter || item.employee.department.toLowerCase().includes(deptFilter);
            const matchesSearch = !searchKeyword ||
                item.employee.name.toLowerCase().includes(searchKeyword) ||
                item.employee.id.toLowerCase().includes(searchKeyword) ||
                item.employee.role.toLowerCase().includes(searchKeyword);
            return matchesDept && matchesSearch;
        });

        renderAttendanceTable(filteredAttendance);

        // 2. Filter Staff Summary Table
        let staffList = (currentMode === 'daily') ? currentReportData.employees.map(r => ({
            employee: r.employee,
            totalWorkDays: 1,
            presentDays: (r.status === 'on_time' || r.status === 'late') ? 1 : 0,
            onTimeDays: (r.status === 'on_time') ? 1 : 0,
            lateDays: (r.status === 'late') ? 1 : 0,
            absentDays: (r.status === 'absent') ? 1 : 0,
            leaveDays: (r.status === 'on_leave') ? 1 : 0,
            totalLateMinutes: r.latenessMinutes,
            totalDeductionsAmount: r.totalDeductions,
            attendanceRate: (r.status === 'on_time' || r.status === 'late') ? 100 : 0
        })) : currentReportData.staffReport;

        const filteredStaff = staffList.filter(item => {
            const matchesDept = !deptFilter || item.employee.department.toLowerCase().includes(deptFilter);
            const matchesSearch = !searchKeyword ||
                item.employee.name.toLowerCase().includes(searchKeyword) ||
                item.employee.id.toLowerCase().includes(searchKeyword) ||
                item.employee.role.toLowerCase().includes(searchKeyword);
            return matchesDept && matchesSearch;
        });

        renderStaffSummaryTable(filteredStaff);

        // 3. Filter Deductions Register
        const deductionsList = currentReportData.deductionsList || [];
        const filteredDeductions = deductionsList.filter(ded => {
            const emp = window.AlHusseiniHR.getEmployeeById(ded.employeeId);
            if (!emp) return true;
            const matchesDept = !deptFilter || emp.department.toLowerCase().includes(deptFilter);
            const matchesSearch = !searchKeyword ||
                emp.name.toLowerCase().includes(searchKeyword) ||
                emp.id.toLowerCase().includes(searchKeyword) ||
                ded.reason.toLowerCase().includes(searchKeyword);
            return matchesDept && matchesSearch;
        });

        renderDeductionsTable(filteredDeductions);

        // Update Badges
        document.getElementById('badge-count-attendance').textContent = filteredAttendance.length;
        document.getElementById('badge-count-staff').textContent = filteredStaff.length;
        document.getElementById('badge-count-deductions').textContent = filteredDeductions.length;
    }

    function renderAttendanceTable(records) {
        const tbody = document.getElementById('tbody-attendance');
        if (!tbody) return;

        if (records.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted fs-13">لا توجد سجلات حضور مطابقة للمحددات المحددة</td></tr>`;
            return;
        }

        let html = '';
        records.forEach(r => {
            const emp = r.employee;
            let statusBadge = '';
            let lateBadge = '-';

            if (r.status === 'on_time') {
                statusBadge = '<span class="badge badge-status-on_time px-2 py-1"><i class="ri-check-line me-1"></i>في الموعد</span>';
            } else if (r.status === 'late') {
                statusBadge = '<span class="badge badge-status-late px-2 py-1"><i class="ri-alarm-warning-line me-1"></i>متأخر</span>';
                lateBadge = `<span class="badge bg-warning-subtle text-warning fw-bold fs-12">${r.latenessMinutes} دقيقة</span>`;
            } else if (r.status === 'on_leave') {
                statusBadge = '<span class="badge badge-status-on_leave px-2 py-1"><i class="ri-calendar-line me-1"></i>إجازة رسمية</span>';
            } else {
                statusBadge = '<span class="badge badge-status-absent px-2 py-1"><i class="ri-close-line me-1"></i>غياب بدون إذن</span>';
            }

            const punchInDisplay = r.punchIn !== '-' ? `<span class="fw-semibold text-dark">${r.punchIn}</span>` : '<span class="text-muted">لم يبصم</span>';
            const punchOutDisplay = r.punchOut !== '-' ? `<span class="fw-semibold text-dark">${r.punchOut}</span>` : '<span class="text-muted">-</span>';

            html += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="${emp.avatar || '/assets/images/users/avatar-1.jpg'}" alt="" class="avatar-xs rounded-circle me-2 border">
                            <div>
                                <h6 class="fs-13 mb-0 fw-bold text-dark">${emp.name}</h6>
                                <span class="badge bg-light text-secondary fs-11">${emp.id}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="fw-semibold fs-12 d-block">${emp.role}</span>
                        <span class="text-muted fs-11">${emp.department}</span>
                    </td>
                    <td><span class="fs-12 fw-medium text-dark">${r.date || '-'}</span></td>
                    <td><span class="badge bg-light text-muted fs-11">${emp.startTime || '09:00'} - ${emp.endTime || '18:00'}</span></td>
                    <td>${punchInDisplay}</td>
                    <td>${punchOutDisplay}</td>
                    <td>${lateBadge}</td>
                    <td>${statusBadge}</td>
                    <td class="no-print">
                        ${r.totalDeductions > 0 ? `<span class="badge bg-danger text-white fs-11">${window.AlHusseiniHR.formatCurrency(r.totalDeductions)}</span>` : '<span class="text-muted fs-11">-</span>'}
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    function renderStaffSummaryTable(staffList) {
        const tbody = document.getElementById('tbody-staff-summary');
        if (!tbody) return;

        if (staffList.length === 0) {
            tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-muted fs-13">لا توجد بيانات موظفين مطابقة</td></tr>`;
            return;
        }

        let html = '';
        staffList.forEach(s => {
            const emp = s.employee;
            const rateColor = s.attendanceRate >= 90 ? 'bg-success' : (s.attendanceRate >= 75 ? 'bg-warning' : 'bg-danger');

            html += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="${emp.avatar || '/assets/images/users/avatar-1.jpg'}" alt="" class="avatar-xs rounded-circle me-2 border">
                            <div>
                                <h6 class="fs-13 mb-0 fw-bold text-dark">${emp.name}</h6>
                                <span class="badge bg-light text-secondary fs-11">${emp.id}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="fw-semibold fs-12 d-block">${emp.role}</span>
                        <span class="text-muted fs-11">${emp.department}</span>
                    </td>
                    <td><span class="fw-bold">${s.totalWorkDays}</span></td>
                    <td><span class="badge bg-success-subtle text-success fs-12 fw-bold">${s.presentDays}</span></td>
                    <td><span class="badge ${s.lateDays > 0 ? 'bg-warning-subtle text-warning' : 'bg-light text-muted'} fs-12 fw-bold">${s.lateDays}</span></td>
                    <td><span class="fw-bold text-warning">${s.totalLateMinutes} دقيقة</span></td>
                    <td><span class="badge ${s.absentDays > 0 ? 'bg-danger-subtle text-danger' : 'bg-light text-muted'} fs-12 fw-bold">${s.absentDays}</span></td>
                    <td><span class="badge ${s.leaveDays > 0 ? 'bg-info-subtle text-info' : 'bg-light text-muted'} fs-12">${s.leaveDays}</span></td>
                    <td style="min-width: 140px;">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress progress-sm flex-grow-1" style="height: 6px;">
                                <div class="progress-bar ${rateColor}" role="progressbar" style="width: ${s.attendanceRate}%"></div>
                            </div>
                            <span class="fs-12 fw-bold text-dark">${s.attendanceRate}%</span>
                        </div>
                    </td>
                    <td>
                        <span class="fw-bold text-danger">${window.AlHusseiniHR.formatCurrency(s.totalDeductionsAmount)}</span>
                    </td>
                    <td class="no-print">
                        <button type="button" class="btn btn-sm btn-soft-primary" onclick="openEmployeeCard('${emp.id}')" title="عرض السجل الشخصي">
                            <i class="ri-eye-line align-middle"></i> التفاصيل
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    function renderDeductionsTable(deductions) {
        const tbody = document.getElementById('tbody-deductions');
        if (!tbody) return;

        if (deductions.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted fs-13">لا توجد قرارات خصم مسجلة خلال هذه الفترة</td></tr>`;
            return;
        }

        let html = '';
        deductions.forEach(d => {
            const emp = window.AlHusseiniHR.getEmployeeById(d.employeeId);
            const empName = emp ? emp.name : 'موظف غير محدد';
            const empRole = emp ? emp.role : '-';
            const empDept = emp ? emp.department : '-';

            html += `
                <tr>
                    <td><span class="badge bg-danger-subtle text-danger fs-12 fw-bold">${d.decisionNo || d.id}</span></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="${emp?.avatar || '/assets/images/users/avatar-1.jpg'}" alt="" class="avatar-xs rounded-circle me-2 border">
                            <div>
                                <h6 class="fs-13 mb-0 fw-bold text-dark">${empName}</h6>
                                <span class="badge bg-light text-secondary fs-11">${d.employeeId}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="fs-12 fw-semibold d-block">${empRole}</span>
                        <span class="text-muted fs-11">${empDept}</span>
                    </td>
                    <td><span class="fs-12 text-muted">${d.date}</span></td>
                    <td><span class="fs-13 fw-bold text-danger">${window.AlHusseiniHR.formatCurrency(d.amount)}</span></td>
                    <td><span class="fw-semibold text-dark fs-12">${d.reason}</span></td>
                    <td><span class="text-muted fs-12">${d.managerNotes || '-'}</span></td>
                    <td><span class="badge bg-success-subtle text-success fs-11"><i class="ri-checkbox-circle-line me-1"></i>معتمد ومخصوم</span></td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    // Modal for Single Employee Performance & Deductions History
    function openEmployeeCard(empId) {
        const emp = window.AlHusseiniHR.getEmployeeById(empId);
        if (!emp) return;

        const allAtt = window.AlHusseiniHR.getAllAttendance().filter(a => a.employeeId === empId);
        const allDeds = window.AlHusseiniHR.getDeductions().filter(d => d.employeeId === empId);

        const totalLate = allAtt.reduce((sum, a) => sum + (a.latenessMinutes || 0), 0);
        const totalDedAmount = allDeds.reduce((sum, d) => sum + Number(d.amount || 0), 0);

        document.getElementById('employeeModalTitle').textContent = `كشف انضباط: ${emp.name} (${emp.role})`;

        let dedsHtml = '';
        if (allDeds.length === 0) {
            dedsHtml = '<p class="text-muted text-center fs-12 mb-0 py-2">سجل الموظف نظيف من الخصومات الإدارية ✨</p>';
        } else {
            dedsHtml = `
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle fs-12 mb-0">
                        <thead class="table-light">
                            <tr><th>القرار</th><th>التاريخ</th><th>المبلغ</th><th>السبب</th><th>ملاحظات المدير</th></tr>
                        </thead>
                        <tbody>
                            ${allDeds.map(d => `
                                <tr>
                                    <td><span class="badge bg-danger-subtle text-danger">${d.decisionNo || d.id}</span></td>
                                    <td>${d.date}</td>
                                    <td class="text-danger fw-bold">${window.AlHusseiniHR.formatCurrency(d.amount)}</td>
                                    <td>${d.reason}</td>
                                    <td class="text-muted">${d.managerNotes || '-'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        const modalBody = document.getElementById('employeeModalBody');
        modalBody.innerHTML = `
            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded mb-3">
                <img src="${emp.avatar}" class="avatar-md rounded-circle border shadow-sm" alt="">
                <div>
                    <h5 class="fw-bold mb-1 text-dark">${emp.name}</h5>
                    <p class="text-muted mb-1 fs-13"><i class="ri-briefcase-line me-1"></i> ${emp.role} - <span class="text-primary">${emp.department}</span></p>
                    <div class="d-flex gap-2">
                        <span class="badge bg-primary-subtle text-primary">كود: ${emp.id}</span>
                        <span class="badge bg-success-subtle text-success">الوردية: ${emp.startTime} - ${emp.endTime}</span>
                        <span class="badge bg-info-subtle text-info">الراتب الأساسي: ${window.AlHusseiniHR.formatCurrency(emp.baseSalary)}</span>
                    </div>
                </div>
            </div>

            <div class="row g-2 mb-3 text-center">
                <div class="col-3">
                    <div class="p-2 border rounded">
                        <p class="text-muted fs-11 mb-1">أيام الحضور</p>
                        <h6 class="text-success fw-bold mb-0">${allAtt.filter(a => a.status === 'on_time' || a.status === 'late').length} يوم</h6>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 border rounded">
                        <p class="text-muted fs-11 mb-1">مرات التأخير</p>
                        <h6 class="text-warning fw-bold mb-0">${allAtt.filter(a => a.status === 'late').length} مرة</h6>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 border rounded">
                        <p class="text-muted fs-11 mb-1">إجمالي التأخير</p>
                        <h6 class="text-danger fw-bold mb-0">${totalLate} دقيقة</h6>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 border rounded">
                        <p class="text-muted fs-11 mb-1">إجمالي الخصومات</p>
                        <h6 class="text-danger fw-bold mb-0">${window.AlHusseiniHR.formatCurrency(totalDedAmount)}</h6>
                    </div>
                </div>
            </div>

            <h6 class="fw-bold fs-13 text-dark mb-2"><i class="ri-file-warning-line text-danger me-1"></i> سجل الخصومات والجزاءات المحررة:</h6>
            ${dedsHtml}
        `;

        const modal = new bootstrap.Modal(document.getElementById('employeeDetailModal'));
        modal.show();
    }

    function printModalEmployeeCard() {
        window.print();
    }

    function printOfficialReport() {
        window.print();
    }

    function exportReportCSV() {
        if (!currentReportData) return;

        let filename = `تقرير_مركز_الحسيني_${currentMode}_${new Date().toISOString().split('T')[0]}`;
        const headers = ['كود الموظف', 'اسم الموظف', 'الوظيفة', 'القسم', 'التاريخ', 'وقت الحضور الفعلي', 'وقت الانصراف', 'دقائق التأخير', 'الحالة', 'الخصومات المطبقة (ج.م)'];

        const rows = [];
        if (currentMode === 'daily') {
            currentReportData.employees.forEach(r => {
                const statusLabel = r.status === 'on_time' ? 'في الموعد' : (r.status === 'late' ? 'متأخر' : (r.status === 'on_leave' ? 'إجازة' : 'غياب'));
                rows.push([
                    r.employee.id,
                    r.employee.name,
                    r.employee.role,
                    r.employee.department,
                    r.date,
                    r.punchIn,
                    r.punchOut,
                    r.latenessMinutes,
                    statusLabel,
                    r.totalDeductions
                ]);
            });
        } else {
            currentReportData.staffReport.forEach(s => {
                s.attendanceDetails.forEach(att => {
                    const statusLabel = att.status === 'on_time' ? 'في الموعد' : (att.status === 'late' ? 'متأخر' : (att.status === 'on_leave' ? 'إجازة' : 'غياب'));
                    rows.push([
                        s.employee.id,
                        s.employee.name,
                        s.employee.role,
                        s.employee.department,
                        att.date,
                        att.punchIn || '-',
                        att.punchOut || '-',
                        att.latenessMinutes || 0,
                        statusLabel,
                        0
                    ]);
                });
            });
        }

        window.AlHusseiniHR.exportToCSV(filename, headers, rows);
    }
</script>
@endsection
