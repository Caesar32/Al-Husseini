<!-- ============================================================== -->
<!-- 2. Top 4 Dynamic KPI Metric Cards                              -->
<!-- ============================================================== -->
<div class="row g-3 mb-3">
    <!-- Metric 1: Total Sales Revenue with Period Filter -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card-widget kpi-sales h-100 mb-0">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                        <div class="d-flex align-items-center gap-1 overflow-hidden">
                            <i class="ri-money-dollar-circle-line text-success fs-16 shrink-0"></i>
                            <span class="text-uppercase fw-bold text-muted text-truncate fs-12">إيرادات ومبيعات المركز</span>
                        </div>
                        <div class="shrink-0">
                            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5 fs-11 fw-bold" id="dashSalesPeriodBadge">
                                <i class="ri-calendar-check-line align-middle me-1"></i><span id="dashSalesPeriodBadgeText">{{ $selectedPeriod['badge'] ?? 'الإجمالي العام' }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="d-flex align-items-end justify-content-between mt-2">
                        <div>
                            {{-- الرقم الرئيسي: الإيراد النقدي الفعلي (paid + تحصيلات الآجل) --}}
                            <h3 class="fs-22 fw-extrabold text-success mb-0 font-monospace" id="dashTotalRevenue" style="transition: opacity 0.15s ease;">
                                {{ $selectedPeriod['revenue_formatted'] ?? number_format(round($selectedPeriod['revenue'] ?? $totalSales), 0) . ' ج.م' }}
                            </h3>
                            <div class="fs-11 text-muted mb-1">
                                <i class="ri-checkbox-circle-fill text-success align-middle"></i>
                                إيراد نقدي فعلي محصّل
                            </div>
                            {{-- سطر ثانوي: قيمة الفواتير الصادرة (دفترية) --}}
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                <span class="fs-11 text-muted">
                                    <i class="ri-file-list-3-line align-middle"></i>
                                    فواتير: <span id="dashTotalSales" class="fw-bold text-dark" style="transition: opacity 0.15s ease;">{{ $selectedPeriod['total_formatted'] ?? number_format(round($totalSales), 0) . ' ج.م' }}</span>
                                </span>
                                <span class="fs-11 text-warning fw-bold" id="dashCreditCollectedWrap" style="{{ ($selectedPeriod['credit_collected'] ?? 0) > 0 ? '' : 'display:none' }}">
                                    <i class="ri-hand-coin-line align-middle"></i>
                                    تحصيل آجل: <span id="dashCreditCollected">{{ $selectedPeriod['credit_collected_fmt'] ?? '0 ج.م' }}</span>
                                </span>
                            </div>
                            <a href="{{ $selectedPeriod['invoices_url'] ?? route('admin.sales.invoices') }}" id="dashInvoicesLink" class="text-decoration-underline text-muted fs-11" title="عرض فواتير هذه الفترة">
                                <span id="dashInvoicesCount" class="fw-bold">{{ $selectedPeriod['count'] ?? $invoicesCount }}</span> فاتورة معتمدة
                                <span class="text-secondary small">(<span id="dashInvoicesSublabel">{{ $selectedPeriod['sublabel'] ?? 'منذ البداية' }}</span>)</span>
                            </a>
                        </div>
                        <div class="avatar-sm shrink-0">
                            <span class="avatar-title bg-success-subtle rounded-3 fs-3 text-success shadow-xs">
                                <i class="ri-funds-line"></i>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Instant Period Filter Segmented Control -->
                <div class="sales-filter-wrapper mt-3 pt-2 border-top border-light-subtle">
                    <div class="segmented-control-bar" role="group" aria-label="فترة المبيعات">
                        @foreach($salesPeriods ?? [] as $key => $p)
                            <button type="button" 
                                    class="segmented-btn sales-period-pill {{ ($selectedPeriodKey ?? 'all') === $key ? 'active' : '' }}" 
                                    data-period="{{ $key }}"
                                    onclick="changeSalesPeriod('{{ $key }}', this)"
                                    title="{{ $p['badge'] }}">
                                {{ $p['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Metric 2: Credit / Receivables (Strictly called "الآجل") -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card-widget kpi-credit h-100 mb-0">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                        <div class="d-flex align-items-center gap-1 overflow-hidden">
                            <i class="ri-hand-coin-line text-warning fs-16 flex-shrink-0"></i>
                            <span class="text-uppercase fw-bold text-muted text-truncate fs-12">مستحقات العملاء (الآجل)</span>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-0.5 fs-11 fw-bold">
                                <i class="ri-time-line align-middle me-1"></i> واجبة التحصيل
                            </span>
                        </div>
                    </div>
                    <div class="d-flex align-items-end justify-content-between mt-2">
                        <div>
                            <h3 class="fs-22 fw-extrabold text-warning mb-1 font-monospace" id="dashTotalCredit">{{ number_format(round($totalCredit), 0) }} ج.م</h3>
                            <a href="{{ route('admin.sales.credit') }}" class="text-decoration-underline text-muted fs-11">
                                <span id="dashCreditCustomersCount" class="fw-bold">{{ $creditCustomersCount }}</span> عملاء عليهم آجل
                            </a>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-warning-subtle rounded-3 fs-3 text-warning shadow-xs">
                                <i class="ri-hand-coin-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 pt-2 border-top border-light-subtle d-flex align-items-center justify-content-between fs-11 text-muted">
                    <span>متابعة مديونيات العملاء</span>
                    <a href="{{ route('admin.sales.credit') }}" class="fw-semibold text-warning text-decoration-none">
                        تحصيل الآجل <i class="ri-arrow-left-s-line align-middle"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Metric 3: Registered Customers & Cars -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card-widget kpi-customers h-100 mb-0">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between gap-1">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-bold text-muted text-truncate mb-0 fs-12">
                                <i class="ri-user-shared-2-line text-primary align-middle me-1"></i> دليل العملاء والمركبات
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5 fs-11 fw-semibold">
                                <i class="ri-car-line align-middle me-1"></i> ورشة ومعرض
                            </span>
                        </div>
                    </div>
                    <div class="d-flex align-items-end justify-content-between mt-2.5">
                        <div>
                            <h3 class="fs-22 fw-extrabold text-primary mb-1 font-monospace" id="dashCustomersCount">{{ $customersCount }}</h3>
                            <a href="{{ route('admin.sales.customers') }}" class="text-decoration-underline text-muted fs-11">
                                <span class="fw-bold">{{ $vehiclesCount }}</span> سيارات ولوحات مسجلة
                            </a>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-primary-subtle rounded-3 fs-3 text-primary shadow-xs">
                                <i class="ri-user-shared-2-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 pt-2 border-top border-light-subtle d-flex align-items-center justify-content-between fs-11 text-muted">
                    <span>قاعدة بيانات المركبات</span>
                    <a href="{{ route('admin.sales.customers') }}" class="fw-semibold text-primary text-decoration-none">
                        دليل العملاء <i class="ri-arrow-left-s-line align-middle"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Metric 4: Inventory & Low Stock -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card-widget kpi-inventory h-100 mb-0">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between gap-1">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-bold text-muted text-truncate mb-0 fs-12">
                                <i class="ri-box-3-line text-info align-middle me-1"></i> مخزون البطاريات والزيوت
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="badge bg-info-subtle text-info rounded-pill px-2 py-0.5 fs-11 fw-semibold" id="dashLowStockBadge">
                                {{ $productsCount }} صنف متاح
                            </span>
                        </div>
                    </div>
                    <div class="d-flex align-items-end justify-content-between mt-2.5">
                        <div>
                            <h3 class="fs-22 fw-extrabold text-info mb-1 font-monospace" id="dashProductsCount">{{ $productsCount }}</h3>
                            <a href="{{ route('admin.sales.products') }}" class="text-decoration-underline text-muted fs-11">
                                <span class="text-danger fw-bold" id="dashLowStockCount">{{ $lowStockCount }}</span> أصناف أوشكت على النفاد
                            </a>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-info-subtle rounded-3 fs-3 text-info shadow-xs">
                                <i class="ri-battery-charge-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 pt-2 border-top border-light-subtle d-flex align-items-center justify-content-between fs-11 text-muted">
                    <span>حركة وجرد المخزون</span>
                    <a href="{{ route('admin.sales.products') }}" class="fw-semibold text-info text-decoration-none">
                        إدارة الأصناف <i class="ri-arrow-left-s-line align-middle"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
