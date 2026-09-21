<!-- Big 4 Visual Stat Cards -->
<div class="row mb-3">
    <!-- 1. Present On Time -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-filter-card border-start border-success border-4 shadow-sm mb-0 active-filter" id="card-filter-present" onclick="setQuickTabFilter('present')">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-uppercase fw-bold text-muted fs-12 mb-1">🟢 حاضرون في الموعد</p>
                        <h3 class="fs-24 fw-extrabold text-success mb-0" id="statCountOnTime">{{ $stats['present'] ?? 0 }}</h3>
                        <small class="text-muted fs-11">منضبطون في وردية اليوم</small>
                    </div>
                    <div class="avatar-sm">
                        <span class="avatar-title bg-success-subtle text-success rounded-circle fs-20">
                            <i class="ri-checkbox-circle-fill"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Late Staff -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-filter-card border-start border-warning border-4 shadow-sm mb-0" id="card-filter-late" onclick="setQuickTabFilter('late')">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-uppercase fw-bold text-muted fs-12 mb-1">🟡 متأخرون عن الوردية</p>
                        <h3 class="fs-24 fw-extrabold text-warning mb-0" id="statCountLate">{{ $stats['late'] ?? 0 }}</h3>
                        <small class="text-muted fs-11">تجاوزوا وقت الحضور</small>
                    </div>
                    <div class="avatar-sm">
                        <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-20">
                            <i class="ri-alarm-warning-fill"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Absent / Not Arrived -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-filter-card border-start border-danger border-4 shadow-sm mb-0" id="card-filter-absent" onclick="setQuickTabFilter('absent')">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-uppercase fw-bold text-muted fs-12 mb-1">🔴 غائبون / لم يسجلوا بعد</p>
                        <h3 class="fs-24 fw-extrabold text-danger mb-0" id="statCountAbsent">{{ $stats['absent'] ?? 0 }}</h3>
                        <small class="text-muted fs-11">لم يثبتوا بصمتهم اليوم</small>
                    </div>
                    <div class="avatar-sm">
                        <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-20">
                            <i class="ri-user-unfollow-fill"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Total Staff & Discipline Rate -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-filter-card border-start border-primary border-4 shadow-sm mb-0" id="card-filter-all" onclick="setQuickTabFilter('all')">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-uppercase fw-bold text-muted fs-12 mb-1">🔵 إجمالي فريق المركز</p>
                        <h3 class="fs-24 fw-extrabold text-primary mb-0" id="statCountTotal">{{ $stats['total_expected'] ?? count($employees) }} موظف</h3>
                        <small class="text-success fw-bold fs-11" id="statPunctualityText">نسبة الحضور: 0%</small>
                    </div>
                    <div class="avatar-sm">
                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20">
                            <i class="ri-team-fill"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
