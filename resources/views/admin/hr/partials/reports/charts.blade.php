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
