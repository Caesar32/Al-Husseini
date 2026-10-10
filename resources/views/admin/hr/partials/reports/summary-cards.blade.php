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
                        <h4 class="fs-22 fw-bold text-primary mb-1" id="kpi-deductions-amount" style="cursor: help;" data-bs-toggle="tooltip" data-bs-placement="top" title="0.00 ج.م">0 ج.م</h4>
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
