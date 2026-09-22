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
                            <input type="text" class="form-control" id="search-employee" placeholder="بحث باسم الموظف أو الكود..." value="{{ request('search', '') }}" oninput="applyFilters()">
                            <button class="btn btn-light border" type="button" onclick="clearReportSearch()" title="مسح البحث">
                                <i class="ri-close-line"></i>
                            </button>
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
