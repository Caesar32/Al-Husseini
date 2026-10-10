<!-- Apexcharts JS -->
<script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>

<script>
'use strict';

let salesTrendChart = null;
let categoryDonutChart = null;

const salesPeriodsData = @json($salesPeriods ?? []);

/** Clean compact form for a raw number, e.g. 26781945.84 -> "26.78 مليون ج.م". Mirrors the
 *  backend's DashboardController::formatCompactCurrency() for values the server didn't already
 *  format (defensive fallback only — every period from the server carries its own *_compact). */
function formatCompactCurrency(val) {
    const n = Number(val) || 0;
    if (n >= 1e6) return (n / 1e6).toFixed(2) + ' مليون ج.م';
    if (n >= 1e3) return (n / 1e3).toFixed(1) + ' ألف ج.م';
    return Math.round(n).toLocaleString('ar-EG') + ' ج.م';
}

/** Initializes (once) or live-updates a Bootstrap tooltip's text without losing its instance. */
function initOrUpdateTooltip(el, title) {
    if (!el || typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
    el.setAttribute('title', title);
    el.setAttribute('data-bs-original-title', title);
    const existing = bootstrap.Tooltip.getInstance(el);
    if (existing) {
        existing.setContent({ '.tooltip-inner': title });
    } else {
        new bootstrap.Tooltip(el);
    }
}

function initDashboardTooltips() {
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        if (!bootstrap.Tooltip.getInstance(el)) {
            new bootstrap.Tooltip(el);
        }
    });
}

function changeSalesPeriod(periodKey, triggerEl) {
    if (!salesPeriodsData || !salesPeriodsData[periodKey]) return;
    const period = salesPeriodsData[periodKey];

    // 1. تحديث الأرقام والنصوص بانيميشن ناعم
    const revenueEl           = document.getElementById('dashTotalRevenue');
    const salesEl             = document.getElementById('dashTotalSales');
    const countEl             = document.getElementById('dashInvoicesCount');
    const badgeEl             = document.getElementById('dashSalesPeriodBadgeText');
    const sublabelEl          = document.getElementById('dashInvoicesSublabel');
    const linkEl              = document.getElementById('dashInvoicesLink');
    const creditCollectedEl   = document.getElementById('dashCreditCollected');
    const creditCollectedWrap = document.getElementById('dashCreditCollectedWrap');

    // الإيراد النقدي الفعلي (paid + تحصيلات الآجل): نص مختصر + التلميح يحمل القيمة الدقيقة
    if (revenueEl) {
        revenueEl.style.opacity = '0.3';
        setTimeout(() => {
            revenueEl.textContent = period.revenue_compact || formatCompactCurrency(period.revenue);
            initOrUpdateTooltip(revenueEl, period.revenue_formatted || (Number(period.revenue || 0).toFixed(2) + ' ج.م'));
            revenueEl.style.opacity = '1';
        }, 120);
    }

    // قيمة الفواتير الصادرة (دفترية): نص مختصر + التلميح يحمل القيمة الدقيقة
    if (salesEl) {
        salesEl.style.opacity = '0.3';
        setTimeout(() => {
            salesEl.textContent = period.total_compact || formatCompactCurrency(period.total);
            initOrUpdateTooltip(salesEl, period.total_formatted || (Number(period.total || 0).toFixed(2) + ' ج.م'));
            salesEl.style.opacity = '1';
        }, 120);
    }

    // تحصيلات الآجل للفترة: نص مختصر + التلميح يحمل القيمة الدقيقة
    if (creditCollectedEl && creditCollectedWrap) {
        const creditAmt = period.credit_collected || 0;
        if (creditAmt > 0) {
            creditCollectedEl.textContent = period.credit_collected_compact || formatCompactCurrency(creditAmt);
            initOrUpdateTooltip(creditCollectedEl, period.credit_collected_fmt || (creditAmt.toFixed(2) + ' ج.م'));
            creditCollectedWrap.style.display = '';
        } else {
            creditCollectedWrap.style.display = 'none';
        }
    }

    if (countEl) countEl.textContent = period.count;
    if (badgeEl) badgeEl.textContent = period.badge;
    if (sublabelEl) sublabelEl.textContent = period.sublabel;
    if (linkEl && period.invoices_url) linkEl.href = period.invoices_url;

    // 2. تحديث الحالة النشطة على الأزرار (Segmented)
    document.querySelectorAll('.sales-period-pill').forEach(btn => {
        if (btn.dataset.period === periodKey) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // 3. تحديث الحالة النشطة على أزرار شريط الترحيب
    document.querySelectorAll('.welcome-period-btn').forEach(btn => {
        if (btn.dataset.period === periodKey) {
            btn.classList.remove('btn-ghost-secondary', 'text-muted');
            btn.classList.add('btn-success', 'text-white', 'shadow-xs', 'active');
        } else {
            btn.classList.remove('btn-success', 'text-white', 'shadow-xs', 'active');
            btn.classList.add('btn-ghost-secondary', 'text-muted');
        }
    });

    // 4. حفظ التفضيل في localStorage
    try {
        localStorage.setItem('alhusseini_dashboard_sales_period', periodKey);
    } catch(e) {}

    // 5. تحديث URL بدون إعادة تحميل
    if (window.history && window.history.replaceState) {
        const url = new URL(window.location);
        url.searchParams.set('sales_period', periodKey);
        window.history.replaceState({}, '', url);
    }
}


document.addEventListener('DOMContentLoaded', function () {
    renderDashboardCharts();
    initDashboardTooltips();

    // Check if period was specified in URL or saved in localStorage
    const urlParams = new URLSearchParams(window.location.search);
    const urlPeriod = urlParams.get('sales_period');
    const savedPeriod = localStorage.getItem('alhusseini_dashboard_sales_period');
    const targetPeriod = urlPeriod || savedPeriod;
    if (targetPeriod && salesPeriodsData && salesPeriodsData[targetPeriod] && targetPeriod !== '{{ $selectedPeriodKey ?? 'all' }}') {
        changeSalesPeriod(targetPeriod);
    }


});


// 6. ApexCharts: Sales Trend & Donut
function renderDashboardCharts() {
    const dbTrendCategories = @json($trendCategories);
    const dbTrendBatteries = @json($trendBatteries);
    const dbTrendOils = @json($trendOils);
    const dbTrendServices = @json($trendServices);

    let sumBatteries = {{ (float) $catStatBatteries }};
    let sumOils = {{ (float) $catStatOils }};
    let sumGreases = {{ (float) $catStatGreases }};
    let sumServices = {{ (float) $catStatServices }};

    // Fallback for visual display if store is completely empty
    if ((sumBatteries + sumOils + sumGreases + sumServices) === 0) {
        sumBatteries = 24500;
        sumOils = 13800;
        sumGreases = 3200;
        sumServices = 4900;
    }

    // 1. Column / Area Trend Chart
    const trendEl = document.querySelector("#alhusseini_sales_trend_chart");
    if (trendEl) {
        if (salesTrendChart) salesTrendChart.destroy();

        const optionsTrend = {
            series: [
                { name: 'بطاريات سيارات', data: dbTrendBatteries },
                { name: 'زيوت وفلاتر', data: dbTrendOils },
                { name: 'صيانة وكهرباء', data: dbTrendServices }
            ],
            chart: {
                type: 'area',
                height: 280,
                toolbar: { show: false },
                fontFamily: 'Cairo, Almarai, sans-serif'
            },
            colors: ['#405189', '#0ab39c', '#299cdb'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            fill: {
                type: 'gradient',
                gradient: { opacityFrom: 0.45, opacityTo: 0.05 }
            },
            xaxis: {
                categories: dbTrendCategories,
                labels: { style: { fontFamily: 'Cairo', fontSize: '11px' } }
            },
            yaxis: {
                labels: {
                    formatter: val => `${val.toLocaleString('ar-EG')} ج.م`,
                    style: { fontFamily: 'Cairo', fontSize: '11px' }
                }
            },
            tooltip: {
                y: { formatter: val => `${val.toLocaleString('ar-EG')} ج.م` }
            },
            legend: { position: 'top', horizontalAlign: 'right', fontFamily: 'Cairo' }
        };

        salesTrendChart = new ApexCharts(trendEl, optionsTrend);
        salesTrendChart.render();
    }

    // 2. Category Donut Chart
    const donutEl = document.querySelector("#alhusseini_category_donut_chart");
    if (donutEl) {
        if (categoryDonutChart) categoryDonutChart.destroy();

        const optionsDonut = {
            series: [sumBatteries, sumOils, sumGreases, sumServices],
            labels: ['بطاريات سيارات', 'زيوت وفلاتر', 'شحوم وسوائل', 'صيانة وخدمات'],
            chart: {
                type: 'donut',
                height: 215,
                fontFamily: 'Cairo, Almarai, sans-serif'
            },
            colors: ['#405189', '#0ab39c', '#f7b84b', '#299cdb'],
            dataLabels: { enabled: false },
            legend: { show: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            name: { show: true, fontSize: '13px', fontFamily: 'Cairo' },
                            value: {
                                show: true,
                                fontSize: '15px',
                                fontWeight: 700,
                                fontFamily: 'Cairo',
                                formatter: val => `${Number(val).toLocaleString('ar-EG')} ج.م`
                            },
                            total: {
                                show: true,
                                label: 'المبيعات',
                                fontSize: '12px',
                                formatter: w => `${w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString('ar-EG')} ج.م`
                            }
                        }
                    }
                }
            }
        };

        categoryDonutChart = new ApexCharts(donutEl, optionsDonut);
        categoryDonutChart.render();
    }
}

</script>
