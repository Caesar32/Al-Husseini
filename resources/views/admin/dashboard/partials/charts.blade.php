<!-- ============================================================== -->
<!-- 3. Interactive Analytics: Sales Trend & Category Distribution  -->
<!-- ============================================================== -->
<div class="row g-3 mb-3">
    <!-- Sales Trend & Category Breakdown Chart (Col-8) -->
    <div class="col-xl-8">
        <div class="card dashboard-card h-100 mb-0">
            <div class="card-header dashboard-header align-items-center d-flex justify-content-between bg-transparent">
                <h5 class="card-title mb-0 fw-bold fs-14">
                    <i class="ri-line-chart-line text-primary me-1"></i> حركة الإيرادات والمبيعات الأسبوعية للأقسام
                </h5>
                <div class="d-flex gap-1">
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                        <i class="ri-checkbox-circle-fill me-1"></i> تحديث تلقائي مباشر
                    </span>
                </div>
            </div>

            <!-- Sub-summary counters bar -->
            <div class="p-3 bg-light-subtle border-bottom">
                <div class="row g-2 text-center">
                    <div class="col-6 col-sm-3">
                        <div class="p-2.5 bg-body border rounded-3 dash-mini-tile">
                            <span class="text-muted fs-11 d-block mb-1">🔋 بطاريات سيارات</span>
                            <h6 class="mb-0 fw-bold font-monospace text-primary fs-13" id="catStatBatteries">{{ number_format(round($catStatBatteries), 0) }} ج.م</h6>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="p-2.5 bg-body border rounded-3 dash-mini-tile">
                            <span class="text-muted fs-11 d-block mb-1">🛢️ زيوت وفلاتر</span>
                            <h6 class="mb-0 fw-bold font-monospace text-success fs-13" id="catStatOils">{{ number_format(round($catStatOils), 0) }} ج.م</h6>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="p-2.5 bg-body border rounded-3 dash-mini-tile">
                            <span class="text-muted fs-11 d-block mb-1">🔧 صيانة وخدمات</span>
                            <h6 class="mb-0 fw-bold font-monospace text-info fs-13" id="catStatServices">{{ number_format(round($catStatServices), 0) }} ج.م</h6>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="p-2.5 bg-body border rounded-3 dash-mini-tile">
                            <span class="text-muted fs-11 d-block mb-1">♻️ كهنة مسترجعة</span>
                            <h6 class="mb-0 fw-bold font-monospace text-danger fs-13" id="catStatScrap">- {{ number_format(round($catStatScrap), 0) }} ج.م</h6>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-3">
                <div id="alhusseini_sales_trend_chart" style="min-height: 280px;" dir="ltr"></div>
            </div>
        </div>
    </div>

    <!-- Category Sales Donut Chart (Col-4) -->
    <div class="col-xl-4">
        <div class="card dashboard-card h-100 mb-0">
            <div class="card-header dashboard-header align-items-center d-flex justify-content-between bg-transparent">
                <h5 class="card-title mb-0 fw-bold fs-14">
                    <i class="ri-pie-chart-line text-primary me-1"></i> توزيع المبيعات حسب القسم
                </h5>
            </div>

            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div id="alhusseini_category_donut_chart" style="min-height: 220px;" dir="ltr"></div>

                <!-- Custom Category Legend Grid -->
                <div class="pt-3 border-top mt-2">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="p-2 bg-body-tertiary rounded-3 border fs-11 dash-mini-tile">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="text-dark fw-bold"><i class="ri-checkbox-blank-circle-fill text-primary fs-9 me-1"></i>بطاريات</span>
                                    <strong class="font-monospace text-primary" id="donutPctBatteries">{{ $pctBatteries }}%</strong>
                                </div>
                                <small class="text-muted font-monospace fs-10 d-block text-truncate" id="donutValBatteries">{{ number_format($catStatBatteries, 2) }} ج.م</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-body-tertiary rounded-3 border fs-11 dash-mini-tile">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="text-dark fw-bold"><i class="ri-checkbox-blank-circle-fill text-success fs-9 me-1"></i>زيوت وفلاتر</span>
                                    <strong class="font-monospace text-success" id="donutPctOils">{{ $pctOils }}%</strong>
                                </div>
                                <small class="text-muted font-monospace fs-10 d-block text-truncate" id="donutValOils">{{ number_format($catStatOils, 2) }} ج.م</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-body-tertiary rounded-3 border fs-11 dash-mini-tile">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="text-dark fw-bold"><i class="ri-checkbox-blank-circle-fill text-warning fs-9 me-1"></i>شحوم وسوائل</span>
                                    <strong class="font-monospace text-warning" id="donutPctGreases">{{ $pctGreases }}%</strong>
                                </div>
                                <small class="text-muted font-monospace fs-10 d-block text-truncate" id="donutValGreases">{{ number_format($catStatGreases, 2) }} ج.م</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-body-tertiary rounded-3 border fs-11 dash-mini-tile">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="text-dark fw-bold"><i class="ri-checkbox-blank-circle-fill text-info fs-9 me-1"></i>صيانة وورشة</span>
                                    <strong class="font-monospace text-info" id="donutPctServices">{{ $pctServices }}%</strong>
                                </div>
                                <small class="text-muted font-monospace fs-10 d-block text-truncate" id="donutValServices">{{ number_format($catStatServices, 2) }} ج.م</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
