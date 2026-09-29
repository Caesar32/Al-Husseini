<!-- Apexcharts JS -->
<script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>

<script>
'use strict';

let salesTrendChart = null;
let categoryDonutChart = null;

const salesPeriodsData = @json($salesPeriods ?? []);

function changeSalesPeriod(periodKey, triggerEl) {
    if (!salesPeriodsData || !salesPeriodsData[periodKey]) return;
    const period = salesPeriodsData[periodKey];

    // 1. Update text & numbers with a subtle transition
    const totalEl = document.getElementById('dashTotalSales');
    const countEl = document.getElementById('dashInvoicesCount');
    const badgeEl = document.getElementById('dashSalesPeriodBadgeText');
    const sublabelEl = document.getElementById('dashInvoicesSublabel');
    const linkEl = document.getElementById('dashInvoicesLink');

    if (totalEl) {
        totalEl.style.opacity = '0.3';
        setTimeout(() => {
            totalEl.textContent = period.total_formatted;
            totalEl.style.opacity = '1';
        }, 120);
    }

    if (countEl) countEl.textContent = period.count;
    if (badgeEl) badgeEl.textContent = period.badge;
    if (sublabelEl) sublabelEl.textContent = period.sublabel;
    if (linkEl && period.invoices_url) linkEl.href = period.invoices_url;

    // 2. Update active states on Card pills (.segmented-btn)
    document.querySelectorAll('.sales-period-pill').forEach(btn => {
        if (btn.dataset.period === periodKey) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // 3. Update active states on Welcome bar buttons if present
    document.querySelectorAll('.welcome-period-btn').forEach(btn => {
        if (btn.dataset.period === periodKey) {
            btn.classList.remove('btn-ghost-secondary', 'text-muted');
            btn.classList.add('btn-success', 'text-white', 'shadow-xs', 'active');
        } else {
            btn.classList.remove('btn-success', 'text-white', 'shadow-xs', 'active');
            btn.classList.add('btn-ghost-secondary', 'text-muted');
        }
    });

    // 4. Save preference in localStorage
    try {
        localStorage.setItem('alhusseini_dashboard_sales_period', periodKey);
    } catch(e) {}

    // 5. Update browser URL query without full reload
    if (window.history && window.history.replaceState) {
        const url = new URL(window.location);
        url.searchParams.set('sales_period', periodKey);
        window.history.replaceState({}, '', url);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    renderDashboardCharts();

    // Check if period was specified in URL or saved in localStorage
    const urlParams = new URLSearchParams(window.location.search);
    const urlPeriod = urlParams.get('sales_period');
    const savedPeriod = localStorage.getItem('alhusseini_dashboard_sales_period');
    const targetPeriod = urlPeriod || savedPeriod;
    if (targetPeriod && salesPeriodsData && salesPeriodsData[targetPeriod] && targetPeriod !== '{{ $selectedPeriodKey ?? 'all' }}') {
        changeSalesPeriod(targetPeriod);
    }

    window.addEventListener('alhusseini-sales-updated', function () {
        if (window.AlHusseiniSales) {
            loadKPIStats();
            loadRecentInvoicesTable();
            loadCreditDuesList();
            loadLowStockAlerts();
        }
    });

    window.addEventListener('alhusseini-hr-updated', function () {
        if (window.AlHusseiniHR) {
            loadHRDashboardStats();
        }
    });
});

function initAlHusseiniDashboard() {
    renderDashboardCharts();
}

// 1. KPI Stats
function loadKPIStats() {
    const invoices = window.AlHusseiniSales.getInvoices();
    const customers = window.AlHusseiniSales.getCustomers();
    const products = window.AlHusseiniSales.getProducts();

    // Total sales (respect active period)
    const activePeriodBtn = document.querySelector('.sales-period-pill.btn-success');
    const activePeriod = activePeriodBtn ? activePeriodBtn.dataset.period : 'all';
    if (activePeriod && salesPeriodsData && salesPeriodsData[activePeriod]) {
        document.getElementById('dashTotalSales').textContent = salesPeriodsData[activePeriod].total_formatted;
        document.getElementById('dashInvoicesCount').textContent = salesPeriodsData[activePeriod].count;
    } else {
        const totalSales = invoices.reduce((sum, inv) => sum + (Number(inv.totalAmount) || 0), 0);
        document.getElementById('dashTotalSales').textContent = window.AlHusseiniSales.formatCurrency(totalSales);
        document.getElementById('dashInvoicesCount').textContent = invoices.length;
    }

    // Total Credit (الآجل)
    const totalCredit = customers.reduce((sum, c) => sum + (Number(c.creditBalance) || 0), 0);
    document.getElementById('dashTotalCredit').textContent = window.AlHusseiniSales.formatCurrency(totalCredit);
    const creditCustCount = customers.filter(c => Number(c.creditBalance) > 0).length;
    document.getElementById('dashCreditCustomersCount').textContent = creditCustCount;

    // Customers count
    document.getElementById('dashCustomersCount').textContent = customers.length;

    // Products & Low stock count
    const lowStock = products.filter(p => p.stock !== undefined && p.stock <= 10);
    document.getElementById('dashProductsCount').textContent = products.length;
    document.getElementById('dashLowStockCount').textContent = lowStock.length;
    document.getElementById('dashLowStockBadge').textContent = `${products.length} صنف متاح`;
}

// 2. Recent Invoices Table
function loadRecentInvoicesTable() {
    const invoices = window.AlHusseiniSales.getInvoices();
    const tbody = document.getElementById('dashRecentInvoicesBody');
    if (!tbody) return;

    if (invoices.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-4 text-muted">
                    <i class="ri-file-list-line fs-20 d-block mb-1"></i>
                    لا توجد فواتير مسجلة حتى الآن
                </td>
            </tr>
        `;
        return;
    }

    const recent = invoices.slice(-6).reverse();
    let html = '';

    const paymentBadges = {
        'cash': '<span class="badge bg-success-subtle text-success">نقدي كاش</span>',
        'instapay': '<span class="badge bg-primary-subtle text-primary">إنستاباي</span>',
        'card': '<span class="badge bg-info-subtle text-info">فيزا بنكية</span>',
        'credit': '<span class="badge bg-warning-subtle text-warning fw-bold">الآجل ⏱️</span>'
    };

    recent.forEach(inv => {
        const payBadge = paymentBadges[inv.paymentMethod] || `<span class="badge bg-light text-dark">${inv.paymentMethod}</span>`;
        const firstItem = inv.items && inv.items[0] ? inv.items[0].name : 'صنف';
        const extraCount = inv.items && inv.items.length > 1 ? ` (+${inv.items.length - 1} أصناف)` : '';

        html += `
            <tr>
                <td class="text-center">
                    <a href="javascript:void(0);" onclick="viewInvoiceFromDashboard('${inv.id}')" class="fw-bold font-monospace link-primary">
                        #${inv.invoiceNo}
                    </a>
                    <small class="d-block text-muted fs-10 font-monospace">${inv.date}</small>
                </td>
                <td>
                    <strong class="text-dark d-block">${inv.customerName}</strong>
                    <small class="text-muted"><i class="ri-car-line me-1"></i>${inv.carModel} (${inv.carPlate})</small>
                </td>
                <td>
                    <span class="text-truncate d-inline-block text-dark fw-medium" style="max-width: 220px;" title="${firstItem}">
                        ${firstItem}${extraCount}
                    </span>
                </td>
                <td class="text-end">
                    <strong class="font-monospace text-success fs-13">${window.AlHusseiniSales.formatCurrency(inv.totalAmount)}</strong>
                </td>
                <td class="text-center">${payBadge}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-soft-primary px-2 py-1 fs-11" onclick="viewInvoiceFromDashboard('${inv.id}')" title="معاينة وطباعة">
                        <i class="ri-eye-line me-1"></i> عرض
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

// 3. Pending Credit / Dues List (الآجل)
function loadCreditDuesList() {
    const customers = window.AlHusseiniSales.getCustomers();
    const container = document.getElementById('dashCreditListContainer');
    if (!container) return;

    const debtors = customers.filter(c => Number(c.creditBalance) > 0)
                             .sort((a, b) => Number(b.creditBalance) - Number(a.creditBalance))
                             .slice(0, 5);

    if (debtors.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4 text-muted fs-12">
                <i class="ri-checkbox-circle-line fs-24 text-success d-block mb-1"></i>
                لا توجد أي مبالغ متأخرة بالآجل حالياً.. جميع الحسابات مسددة!
            </div>
        `;
        return;
    }

    let html = '';
    debtors.forEach(c => {
        html += `
            <div class="p-2 bg-light rounded border d-flex justify-content-between align-items-center">
                <div>
                    <strong class="fs-12 text-dark d-block">${c.name}</strong>
                    <small class="text-muted fs-11">
                        <i class="ri-car-line me-1"></i>${c.carModel} | لوحة: ${c.carPlate}
                    </small>
                </div>
                <div class="text-end">
                    <strong class="text-danger font-monospace fs-13 d-block">${window.AlHusseiniSales.formatCurrency(c.creditBalance)}</strong>
                    <a href="{{ route('admin.sales.credit') }}" class="btn btn-sm btn-soft-warning py-0 px-2 fs-10 fw-bold">
                        تحصيل <i class="ri-arrow-left-s-line"></i>
                    </a>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// 4. Low Stock Alerts
function loadLowStockAlerts() {
    const products = window.AlHusseiniSales.getProducts();
    const tbody = document.getElementById('dashLowStockTableBody');
    if (!tbody) return;

    const lowStock = products.filter(p => p.stock !== undefined && p.stock <= 12)
                             .sort((a, b) => a.stock - b.stock)
                             .slice(0, 5);

    if (lowStock.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-3 text-muted">
                    <i class="ri-checkbox-circle-fill text-success me-1"></i> جميع الأصناف متوفرة ومخزونها في المنطقة الآمنة
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    lowStock.forEach(p => {
        const isDanger = p.stock <= 5;
        const badgeClass = isDanger ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle';
        const badgeText = isDanger ? 'حرج جداً' : 'أوشك على النفاد';

        html += `
            <tr>
                <td>
                    <strong class="text-dark d-block fs-12">${p.name}</strong>
                    <span class="badge bg-light text-secondary border font-monospace fs-10" dir="ltr">${p.brand}</span>
                </td>
                <td><span class="badge bg-light text-secondary">${p.category}</span></td>
                <td class="text-center font-monospace fs-11">${p.barcode || '-'}</td>
                <td class="text-center font-monospace fw-bold ${isDanger ? 'text-danger' : 'text-warning'} fs-13">${p.stock}</td>
                <td class="text-center"><span class="badge ${badgeClass} fs-10">${badgeText}</span></td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

// 5. Workshop Technicians & Attendance
function loadHRDashboardStats() {
    if (!window.AlHusseiniHR) return;
    const employees = window.AlHusseiniHR.getEmployees();
    const today = new Date().toISOString().split('T')[0];
    const attendance = window.AlHusseiniHR.getAttendance(today);

    let present = 0;
    let late = 0;
    let absent = 0;

    attendance.forEach(att => {
        if (att.status === 'present') present++;
        else if (att.status === 'late') late++;
        else if (att.status === 'absent' || att.status === 'leave') absent++;
    });

    document.getElementById('hrPresentCount').textContent = present;
    document.getElementById('hrLateCount').textContent = late;
    document.getElementById('hrAbsentCount').textContent = absent;

    const container = document.getElementById('dashTechListContainer');
    if (!container) return;

    let html = '';
    let techStaff = employees.filter(e => e.department !== 'الإدارة والإشراف');
    if (techStaff.length === 0) techStaff = employees;
    techStaff = techStaff.slice(0, 4);

    techStaff.forEach(emp => {
        const att = attendance.find(a => a.employeeId === emp.id);
        const status = att ? att.status : 'present';
        let badgeHtml = '<span class="badge bg-success-subtle text-success border border-success-subtle">حاضر بالوردية</span>';
        if (status === 'late') badgeHtml = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">متأخر</span>';
        else if (status === 'absent') badgeHtml = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">غائب</span>';

        html += `
            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light border fs-12 mb-1">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-xs">
                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle fw-bold fs-11">
                            ${emp.name.charAt(0)}
                        </span>
                    </div>
                    <div>
                        <strong class="text-dark d-block fs-12">${emp.name}</strong>
                        <small class="text-muted fs-11"><i class="ri-user-settings-line me-1"></i>${emp.role || emp.department}</small>
                    </div>
                </div>
                <div class="text-end">
                    ${badgeHtml}
                    <small class="d-block text-muted font-monospace fs-10 mt-1">${emp.startTime || '09:00'} - ${emp.endTime || '18:00'}</small>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

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

// 7. Preview and Print Invoice Modal
function viewInvoiceFromDashboard(invId) {
    const inv = window.AlHusseiniSales.getInvoiceById(invId);
    if (!inv) return;

    const container = document.getElementById('dashPrintableInvoiceContent');
    const payLabels = { 'cash': 'نقدي (كاش)', 'instapay': 'إنستاباي / فوري', 'card': 'فيزا / بطاقة بنكية', 'credit': 'الآجل (مستحق)' };

    let itemsHtml = '';
    (inv.items || []).forEach((it, i) => {
        itemsHtml += `
            <tr>
                <td class="text-center font-monospace">${i + 1}</td>
                <td>
                    <strong class="text-dark fs-13 d-block">${it.name}</strong>
                    <small class="text-muted font-monospace">${it.barcode ? `باركود: ${it.barcode}` : it.brand}</small>
                </td>
                <td class="text-center font-monospace fw-bold">${it.qty}</td>
                <td class="text-end font-monospace">${window.AlHusseiniSales.formatCurrency(it.unitPrice)}</td>
                <td class="text-end font-monospace fw-bold">${window.AlHusseiniSales.formatCurrency(it.finalPrice || (it.unitPrice * it.qty))}</td>
            </tr>
        `;
    });

    container.innerHTML = `
        <div class="print-invoice-sheet text-dark" style="direction: rtl;">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="48" class="rounded-circle shadow-sm">
                    <div>
                        <h4 class="fw-extrabold text-primary mb-0">مركز الحسيني لبطاريات وزيوت السيارات</h4>
                        <small class="text-muted">صيانة متكاملة - بطاريات جافة وسائلة - زيوت معتمدة - فحص كمبيوتر دينامو</small>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-dark font-monospace fs-13 mb-1">فاتورة #${inv.invoiceNo}</span>
                    <div class="text-muted fs-11">${inv.date} | ${inv.time || ''}</div>
                </div>
            </div>

            <div class="row g-2 mb-3 p-3 bg-light rounded border">
                <div class="col-6"><strong>اسم العميل:</strong> ${inv.customerName}</div>
                <div class="col-6"><strong>رقم الهاتف:</strong> <span class="font-monospace">${inv.customerPhone}</span></div>
                <div class="col-6"><strong>السيارة:</strong> ${inv.carModel}</div>
                <div class="col-6"><strong>رقم اللوحة:</strong> <span class="font-monospace fw-bold">${inv.carPlate}</span></div>
            </div>

            <table class="table table-bordered align-middle mb-3">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 50px;">#</th>
                        <th>الصنف / الخدمة</th>
                        <th class="text-center" style="width: 70px;">الكمية</th>
                        <th class="text-end" style="width: 120px;">السعر</th>
                        <th class="text-end" style="width: 130px;">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml}
                </tbody>
            </table>

            <div class="row justify-content-end mb-3">
                <div class="col-md-6 col-12">
                    <div class="p-2 border rounded bg-light fs-12">
                        <div class="d-flex justify-content-between mb-1">
                            <span>المجموع:</span>
                            <span class="font-monospace fw-bold">${window.AlHusseiniSales.formatCurrency(inv.subtotal || inv.totalAmount)}</span>
                        </div>
                        ${inv.scrapDiscountTotal > 0 ? `
                            <div class="d-flex justify-content-between mb-1 text-success">
                                <span>خصم البطارية القديمة (الكهنة):</span>
                                <span class="font-monospace fw-bold">- ${window.AlHusseiniSales.formatCurrency(inv.scrapDiscountTotal)}</span>
                            </div>
                        ` : ''}
                        <div class="d-flex justify-content-between align-items-center border-top pt-1 mt-1 fs-14">
                            <strong class="text-dark">الصافي المطلوب:</strong>
                            <strong class="text-success font-monospace fs-16">${window.AlHusseiniSales.formatCurrency(inv.totalAmount)}</strong>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-1 mt-1 text-muted fs-11">
                            <span>طريقة السداد:</span>
                            <span class="fw-bold">${payLabels[inv.paymentMethod] || inv.paymentMethod}</span>
                        </div>
                        ${inv.remainingCredit > 0 ? `
                            <div class="d-flex justify-content-between text-danger fw-bold border-top pt-1 mt-1">
                                <span>المتبقي على الآجل:</span>
                                <span class="font-monospace">${window.AlHusseiniSales.formatCurrency(inv.remainingCredit)}</span>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>

            <div class="border-top pt-2 text-center text-muted fs-11">
                <p class="mb-1"><strong>سيريال الضمان المعتمد:</strong> <span class="font-monospace text-primary fw-bold">${inv.serialNumber || 'SN-78942'}</span> | ينتهي في: <span class="font-monospace">${inv.warrantyExpiry || '2027-09-20'}</span></p>
                <small>شكراً لتعاملكم مع مركز الحسيني - خدمة الدعم الفني والطوارئ: 01000000000</small>
            </div>
        </div>
    `;

    const modal = new bootstrap.Modal(document.getElementById('dashInvoicePrintModal'));
    modal.show();
}
</script>
