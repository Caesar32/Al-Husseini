<!-- ============================================================== -->
<!-- 2. Top 4 Dynamic KPI Metric Cards                              -->
<!-- ============================================================== -->
<div class="row g-3 mb-3">
    <!-- Metric 1: Total Sales Revenue -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card-widget kpi-sales h-100 mb-0">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-bold text-muted text-truncate mb-0 fs-12">إجمالي مبيعات المركز</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 fs-11">
                            <i class="ri-arrow-up-line align-middle"></i> مباشر + صيانة
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <h3 class="fs-22 fw-extrabold text-success mb-1 font-monospace" id="dashTotalSales">{{ number_format($totalSales, 2) }} ج.م</h3>
                        <a href="{{ route('admin.sales.invoices') }}" class="text-decoration-underline text-muted fs-11">
                            <span id="dashInvoicesCount">{{ $invoicesCount }}</span> فاتورة معتمدة
                        </a>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle rounded-3 fs-3 text-success">
                            <i class="ri-money-dollar-circle-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Metric 2: Credit / Receivables (Strictly called "الآجل") -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card-widget kpi-credit h-100 mb-0">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-bold text-muted text-truncate mb-0 fs-12">مستحقات العملاء (الآجل)</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-1 fs-11">
                            <i class="ri-time-line align-middle"></i> واجبة التحصيل
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <h3 class="fs-22 fw-extrabold text-warning mb-1 font-monospace" id="dashTotalCredit">{{ number_format($totalCredit, 2) }} ج.م</h3>
                        <a href="{{ route('admin.sales.credit') }}" class="text-decoration-underline text-muted fs-11">
                            <span id="dashCreditCustomersCount">{{ $creditCustomersCount }}</span> عملاء عليهم آجل
                        </a>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle rounded-3 fs-3 text-warning">
                            <i class="ri-hand-coin-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Metric 3: Registered Customers & Cars -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card-widget kpi-customers h-100 mb-0">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-bold text-muted text-truncate mb-0 fs-12">دليل العملاء والمركبات</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1 fs-11">
                            <i class="ri-car-line align-middle"></i> ورشة ومعرض
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <h3 class="fs-22 fw-extrabold text-primary mb-1 font-monospace" id="dashCustomersCount">{{ $customersCount }}</h3>
                        <a href="{{ route('admin.sales.customers') }}" class="text-decoration-underline text-muted fs-11">
                            {{ $vehiclesCount }} سيارات ولوحات مسجلة
                        </a>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle rounded-3 fs-3 text-primary">
                            <i class="ri-user-shared-2-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Metric 4: Inventory & Low Stock -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card-widget kpi-inventory h-100 mb-0">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-bold text-muted text-truncate mb-0 fs-12">مخزون البطاريات والزيوت</p>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="badge bg-info-subtle text-info rounded-pill px-2 py-1 fs-11" id="dashLowStockBadge">
                            {{ $productsCount }} صنف متاح
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <h3 class="fs-22 fw-extrabold text-info mb-1 font-monospace" id="dashProductsCount">{{ $productsCount }}</h3>
                        <a href="{{ route('admin.sales.products') }}" class="text-decoration-underline text-muted fs-11">
                            <span class="text-danger fw-bold" id="dashLowStockCount">{{ $lowStockCount }}</span> أصناف أوشكت على النفاد
                        </a>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-info-subtle rounded-3 fs-3 text-info">
                            <i class="ri-battery-charge-line"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
