<!-- Top Stats -->
<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate border-start border-primary border-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">إجمالي الرواتب الأساسية</p>
                        <h4 class="fs-22 fw-bold text-primary mb-0 mt-2" id="stat-total-base">{{ number_format($totalBase) }} ج.م</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20">
                            <i class="ri-bank-card-line"></i>
                        </span>
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
                        <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">إجمالي البدلات والمكافآت</p>
                        <h4 class="fs-22 fw-bold text-info mb-0 mt-2" id="stat-total-allowances">{{ number_format($totalAllowances) }} ج.م</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-info-subtle text-info rounded-circle fs-20">
                            <i class="ri-gift-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card card-animate border-start border-danger border-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">إجمالي الخصومات المطبقة</p>
                        <h4 class="fs-22 fw-bold text-danger mb-0 mt-2" id="stat-total-deductions">{{ number_format($totalDeductions) }} ج.م</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-20">
                            <i class="ri-scissors-cut-line"></i>
                        </span>
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
                        <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">صافي المستحق للصرف</p>
                        <h4 class="fs-22 fw-bold text-success mb-0 mt-2" id="stat-total-net">{{ number_format($totalNet) }} ج.م</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle text-success rounded-circle fs-20">
                            <i class="ri-hand-coin-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
