<!-- Stat Cards -->
<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate border-start border-primary border-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">إجمالي الموظفين</p>
                        <h4 class="fs-22 fw-bold ff-secondary mb-0 mt-2" id="stat-total-employees">{{ $stats['total'] ?? 0 }}</h4>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20">
                                <i class="ri-team-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card card-animate border-start border-success border-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">على رأس العمل</p>
                        <h4 class="fs-22 fw-bold ff-secondary text-success mb-0 mt-2" id="stat-active-employees">{{ $stats['active'] ?? 0 }}</h4>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-20">
                                <i class="ri-user-follow-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card card-animate border-start border-warning border-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">في إجازة رسمية</p>
                        <h4 class="fs-22 fw-bold ff-secondary text-warning mb-0 mt-2" id="stat-leave-employees">{{ $stats['on_leave'] ?? 0 }}</h4>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-20">
                                <i class="ri-calendar-event-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card card-animate border-start border-info border-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">متوسط الرواتب الأساسية</p>
                        <h4 class="fs-20 fw-bold ff-secondary text-info mb-0 mt-2" id="stat-avg-salary">{{ number_format($stats['avg_salary'] ?? 0) }} ج.م</h4>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-info-subtle text-info rounded-circle fs-20">
                                <i class="ri-money-dollar-box-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
