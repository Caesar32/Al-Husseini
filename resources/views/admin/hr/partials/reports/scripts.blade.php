<!-- ApexCharts JS -->
<script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>

<script>
    'use strict';

    // Reports are built server-side from the database (HrReportController / HrReportService).
    const REPORT_URLS = {
        daily: @json(route('admin.hr.reports.daily')),
        monthly: @json(route('admin.hr.reports.monthly')),
        range: @json(route('admin.hr.reports.range')),
        employee: @json(route('admin.hr.reports.employee', ['employee' => '__ID__'])),
    };

    let currentMode = 'daily'; // 'daily' | 'monthly' | 'custom'
    let currentReportData = null;
    let attendancePieChart = null;
    let timelineBarChart = null;
    let reportRequestSeq = 0;

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, ch => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch]));
    const formatCurrency = (amount) => `${Number(amount || 0).toLocaleString('ar-EG')} ج.م`;
    const DEFAULT_AVATAR = @json(asset('assets/images/users/avatar-1.jpg'));

    function localDateString(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    async function fetchJson(url, params = {}) {
        const query = new URLSearchParams(params).toString();
        const res = await fetch(query ? `${url}?${query}` : url, { headers: { 'Accept': 'application/json' } });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.message || 'تعذر تحميل بيانات التقرير.');
        }
        return data;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const today = new Date();
        const y = today.getFullYear();
        const m = today.getMonth() + 1;
        const dStr = localDateString(today);

        const dailyInput = document.getElementById('input-daily-date');
        if (dailyInput) dailyInput.value = dStr;

        const monthSelect = document.getElementById('select-month');
        if (monthSelect) monthSelect.value = m;

        const yearSelect = document.getElementById('select-year');
        if (yearSelect && [...yearSelect.options].some(o => Number(o.value) === y)) yearSelect.value = y;

        const rangeStart = document.getElementById('input-range-start');
        const rangeEnd = document.getElementById('input-range-end');
        if (rangeStart && rangeEnd) {
            rangeStart.value = `${y}-${String(m).padStart(2, '0')}-01`;
            rangeEnd.value = dStr;
        }

        const printGenDate = document.getElementById('print-generation-date');
        if (printGenDate) {
            printGenDate.textContent = today.toLocaleDateString('ar-EG', { dateStyle: 'full' });
        }

        // URL parameters (e.g. ?search=EMP-0101&department=...&mode=monthly)
        const urlParams = new URLSearchParams(window.location.search);
        const searchParam = urlParams.get('search');
        const deptParam = urlParams.get('department') || urlParams.get('dept');
        const modeParam = urlParams.get('mode');

        if (searchParam) {
            const sInput = document.getElementById('search-employee');
            if (sInput) sInput.value = searchParam;
        }
        if (deptParam) {
            const dSelect = document.getElementById('filter-department');
            if (dSelect) dSelect.value = deptParam;
        }
        if (modeParam && ['daily', 'monthly', 'custom'].includes(modeParam)) {
            setReportMode(modeParam);
        } else {
            loadReport();
        }
    });

    function setReportMode(mode) {
        currentMode = mode;

        ['daily', 'monthly', 'custom'].forEach(m => {
            const btn = document.getElementById(`btn-mode-${m}`);
            const ctrl = document.getElementById(`controls-${m}`);
            if (btn) {
                if (m === mode) {
                    btn.classList.add('active', 'btn-primary');
                    btn.classList.remove('btn-outline-primary');
                } else {
                    btn.classList.remove('active', 'btn-primary');
                    btn.classList.add('btn-outline-primary');
                }
            }
            if (ctrl) {
                ctrl.classList.toggle('d-none', m !== mode);
            }
        });

        loadReport();
    }

    function navigateDay(offset) {
        const input = document.getElementById('input-daily-date');
        if (!input.value) return;
        const parts = input.value.split('-').map(Number);
        const curr = new Date(parts[0], parts[1] - 1, parts[2]);
        curr.setDate(curr.getDate() + offset);
        input.value = localDateString(curr);
        loadReport();
    }

    function setTodayDate() {
        const input = document.getElementById('input-daily-date');
        input.value = localDateString(new Date());
        loadReport();
    }

    async function loadReport() {
        const seq = ++reportRequestSeq;
        let periodTitle = '';
        let printPeriodText = '';
        let data;

        try {
            if (currentMode === 'daily') {
                const targetDate = document.getElementById('input-daily-date')?.value || localDateString(new Date());
                data = await fetchJson(REPORT_URLS.daily, { date: targetDate });

                const parts = targetDate.split('-').map(Number);
                const dateObj = new Date(parts[0], parts[1] - 1, parts[2]);
                const dateArabic = dateObj.toLocaleDateString('ar-EG', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                periodTitle = `تقرير يوم: ${dateArabic}`;
                printPeriodText = `كشف يوم: ${targetDate} (${dateArabic})`;
            } else if (currentMode === 'monthly') {
                const m = parseInt(document.getElementById('select-month')?.value || (new Date().getMonth() + 1));
                const y = parseInt(document.getElementById('select-year')?.value || new Date().getFullYear());
                data = await fetchJson(REPORT_URLS.monthly, { year: y, month: m });

                const monthNames = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
                periodTitle = `تقرير شهر: ${monthNames[m]} ${y}`;
                printPeriodText = `كشف شهر: ${monthNames[m]} ${y} (من ${data.startDate} إلى ${data.endDate})`;
            } else {
                let start = document.getElementById('input-range-start')?.value;
                let end = document.getElementById('input-range-end')?.value;
                if (!start || !end) return;
                if (start > end) {
                    [start, end] = [end, start];
                    document.getElementById('input-range-start').value = start;
                    document.getElementById('input-range-end').value = end;
                }
                data = await fetchJson(REPORT_URLS.range, { start, end });
                periodTitle = `تقرير الفترة: من ${start} إلى ${end}`;
                printPeriodText = `الفترة من ${start} إلى ${end}`;
            }
        } catch (err) {
            if (typeof Swal !== 'undefined') {
                Swal.fire('خطأ', err.message, 'error');
            }
            return;
        }

        // Ignore responses that arrive after a newer request was issued.
        if (seq !== reportRequestSeq) return;
        currentReportData = data;

        const periodTextEl = document.getElementById('report-period-text');
        if (periodTextEl) periodTextEl.textContent = periodTitle;

        const printPeriodEl = document.getElementById('print-period-label');
        if (printPeriodEl) printPeriodEl.textContent = printPeriodText;

        renderKPIs();
        renderCharts();
        applyFilters();
    }

    function renderKPIs() {
        if (!currentReportData) return;
        const summary = currentReportData.summary;

        if (currentMode === 'daily') {
            document.getElementById('kpi-attendance-rate').textContent = `${summary.attendanceRate}%`;
            document.getElementById('kpi-present-count').textContent = `${summary.presentCount} من ${summary.totalStaff}`;
            document.getElementById('kpi-lateness-minutes').textContent = `${summary.totalLateMins} دقيقة`;
            document.getElementById('kpi-late-count').textContent = summary.lateCount;
            document.getElementById('kpi-absence-count').textContent = `${summary.absentCount + summary.leaveCount} فرد`;
            document.getElementById('kpi-unexcused-count').textContent = summary.absentCount;
            document.getElementById('kpi-leave-count').textContent = summary.leaveCount;
        } else {
            document.getElementById('kpi-attendance-rate').textContent = `${summary.avgAttendanceRate}%`;
            document.getElementById('kpi-present-count').textContent = `${summary.totalPresents} يوم عمل`;
            document.getElementById('kpi-lateness-minutes').textContent = `${summary.totalLateMins} دقيقة`;
            document.getElementById('kpi-late-count').textContent = summary.totalLates;
            document.getElementById('kpi-absence-count').textContent = `${summary.totalAbsents} يوم غياب`;
            document.getElementById('kpi-unexcused-count').textContent = summary.totalAbsents;
            document.getElementById('kpi-leave-count').textContent = summary.totalLeaveDays;
        }
        document.getElementById('kpi-deductions-amount').textContent = formatCurrency(summary.totalDeductionsAmount);
        document.getElementById('kpi-deductions-count').textContent = summary.deductionsCount;
    }

    function renderCharts() {
        if (!currentReportData) return;
        const summary = currentReportData.summary;

        // 1. Donut Pie Chart
        const pieSeries = (currentMode === 'daily')
            ? [summary.onTimeCount, summary.lateCount, summary.absentCount, summary.leaveCount]
            : [summary.totalOnTimes, summary.totalLates, summary.totalAbsents, summary.totalLeaveDays];

        const pieOptions = {
            series: pieSeries,
            chart: { type: 'donut', height: 250, fontFamily: 'inherit' },
            labels: ['حاضر في الموعد', 'متأخر عن الوردية', 'غياب مسجل', 'إجازة رسمية'],
            colors: ['#2a9d8f', '#f4a261', '#e63946', '#457b9d'],
            legend: { show: false },
            dataLabels: {
                enabled: true,
                formatter: function (val) { return Math.round(val) + "%"; }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'إجمالي السجلات',
                                formatter: function (w) { return w.globals.seriesTotals.reduce((a, b) => a + b, 0); }
                            }
                        }
                    }
                }
            }
        };

        if (attendancePieChart) {
            attendancePieChart.updateOptions(pieOptions);
        } else {
            attendancePieChart = new ApexCharts(document.querySelector("#chart-attendance-pie"), pieOptions);
            attendancePieChart.render();
        }

        // 2. Timeline Bar / Column Chart
        let categories = [];
        let seriesOnTime = [];
        let seriesLate = [];
        let seriesAbsent = [];

        if (currentMode === 'daily') {
            // Compare departments on this day (departments come from the database)
            categories = currentReportData.departments || [];
            categories.forEach(dept => {
                const staffInDept = currentReportData.employees.filter(e => e.employee.department === dept);
                seriesOnTime.push(staffInDept.filter(s => s.status === 'on_time').length);
                seriesLate.push(staffInDept.filter(s => s.status === 'late').length);
                seriesAbsent.push(staffInDept.filter(s => s.status === 'absent' || s.status === 'on_leave').length);
            });
        } else {
            const dailySeries = currentReportData.dailySeries || [];
            categories = dailySeries.map(d => d.date.split('-').slice(1).join('/'));
            seriesOnTime = dailySeries.map(d => d.present - d.late);
            seriesLate = dailySeries.map(d => d.late);
            seriesAbsent = dailySeries.map(d => d.absent);
        }

        const barOptions = {
            series: [
                { name: 'حضور في الموعد', data: seriesOnTime },
                { name: 'حالات تأخير', data: seriesLate },
                { name: 'غياب', data: seriesAbsent }
            ],
            chart: { type: 'bar', height: 250, stacked: true, toolbar: { show: false }, fontFamily: 'inherit' },
            colors: ['#2a9d8f', '#f4a261', '#e63946'],
            plotOptions: { bar: { horizontal: false, borderRadius: 4, columnWidth: '45%' } },
            xaxis: { categories: categories, labels: { style: { fontSize: '11px' } } },
            yaxis: { title: { text: currentMode === 'daily' ? 'عدد الموظفين' : 'سجلات اليوم' } },
            legend: { position: 'top', horizontalAlign: 'right' },
            dataLabels: { enabled: false }
        };

        if (timelineBarChart) {
            timelineBarChart.updateOptions(barOptions);
        } else {
            timelineBarChart = new ApexCharts(document.querySelector("#chart-timeline-bar"), barOptions);
            timelineBarChart.render();
        }
    }

    // -------------------------------------------------------------
    // Universal Arabic Normalization & Fuzzy-Matching Engine
    // -------------------------------------------------------------
    function normalizeArabic(text) {
        if (!text) return '';
        return text.toString().toLowerCase()
            .replace(/[ً-ٰٟ]/g, '')
            .replace(/[أإآء]/g, 'ا')
            .replace(/ة/g, 'ه')
            .replace(/[يى]/g, 'ي')
            .replace(/[\s\-_]+/g, ' ')
            .trim();
    }

    function isMatch(target, query) {
        if (!query) return true;
        if (!target) return false;
        const normTarget = normalizeArabic(target);
        const normQuery = normalizeArabic(query);
        const rawTarget = target.toString().toLowerCase();
        const rawQuery = query.toString().toLowerCase();

        if (rawTarget.includes(rawQuery) || normTarget.includes(normQuery)) {
            return true;
        }

        const strippedTarget = rawTarget.replace(/[\s\-_]+/g, '');
        const strippedQuery = rawQuery.replace(/[\s\-_]+/g, '');
        return strippedTarget.includes(strippedQuery) || strippedQuery.includes(strippedTarget);
    }

    function matchesEmployee(emp, deptFilter, searchKeyword) {
        if (!emp) return !deptFilter && !searchKeyword;
        const matchesDept = !deptFilter || emp.department === deptFilter;
        const matchesSearch = !searchKeyword ||
            isMatch(emp.name, searchKeyword) ||
            isMatch(emp.code, searchKeyword) ||
            isMatch(emp.role, searchKeyword) ||
            isMatch(emp.department, searchKeyword);
        return matchesDept && matchesSearch;
    }

    window.clearReportSearch = function() {
        const input = document.getElementById('search-employee');
        if (input) {
            input.value = '';
            applyFilters();
            input.focus();
        }
    };

    function applyFilters() {
        if (!currentReportData) return;

        const deptFilter = (document.getElementById('filter-department')?.value || '').trim();
        const searchKeyword = (document.getElementById('search-employee')?.value || '').trim();

        // 1. Attendance detailed records
        let attendanceList = [];
        if (currentMode === 'daily') {
            attendanceList = currentReportData.employees;
        } else {
            currentReportData.staffReport.forEach(staff => {
                staff.attendanceDetails.forEach(att => {
                    attendanceList.push({
                        employee: staff.employee,
                        date: att.date,
                        status: att.status,
                        punchIn: att.punchIn || '-',
                        punchOut: att.punchOut || '-',
                        latenessMinutes: att.latenessMinutes || 0,
                        totalDeductions: 0
                    });
                });
            });
        }

        const filteredAttendance = attendanceList.filter(item => matchesEmployee(item.employee, deptFilter, searchKeyword));
        renderAttendanceTable(filteredAttendance);

        // 2. Staff summary
        const staffList = (currentMode === 'daily') ? currentReportData.employees.map(r => ({
            employee: r.employee,
            totalWorkDays: 1,
            presentDays: (r.status === 'on_time' || r.status === 'late') ? 1 : 0,
            onTimeDays: (r.status === 'on_time') ? 1 : 0,
            lateDays: (r.status === 'late') ? 1 : 0,
            absentDays: (r.status === 'absent') ? 1 : 0,
            leaveDays: (r.status === 'on_leave') ? 1 : 0,
            totalLateMinutes: r.latenessMinutes,
            totalDeductionsAmount: r.totalDeductions,
            attendanceRate: (r.status === 'on_time' || r.status === 'late') ? 100 : 0
        })) : currentReportData.staffReport;

        const filteredStaff = staffList.filter(item => matchesEmployee(item.employee, deptFilter, searchKeyword));
        renderStaffSummaryTable(filteredStaff);

        // 3. Deductions register
        const deductionsList = currentReportData.deductionsList || [];
        const filteredDeductions = deductionsList.filter(ded =>
            matchesEmployee(ded.employee, deptFilter, searchKeyword) || (searchKeyword && isMatch(ded.reason, searchKeyword))
        );
        renderDeductionsTable(filteredDeductions);

        document.getElementById('badge-count-attendance').textContent = filteredAttendance.length;
        document.getElementById('badge-count-staff').textContent = filteredStaff.length;
        document.getElementById('badge-count-deductions').textContent = filteredDeductions.length;
    }

    function statusBadge(status) {
        switch (status) {
            case 'on_time': return '<span class="badge badge-status-on_time px-2 py-1"><i class="ri-check-line me-1"></i>في الموعد</span>';
            case 'late': return '<span class="badge badge-status-late px-2 py-1"><i class="ri-alarm-warning-line me-1"></i>متأخر</span>';
            case 'on_leave': return '<span class="badge badge-status-on_leave px-2 py-1"><i class="ri-calendar-line me-1"></i>إجازة رسمية</span>';
            case 'absent': return '<span class="badge badge-status-absent px-2 py-1"><i class="ri-close-line me-1"></i>غياب مسجل</span>';
            default: return '<span class="badge bg-light text-muted px-2 py-1"><i class="ri-question-line me-1"></i>لا يوجد سجل</span>';
        }
    }

    function statusLabel(status) {
        return {on_time: 'في الموعد', late: 'متأخر', on_leave: 'إجازة', absent: 'غياب', not_recorded: 'لا يوجد سجل'}[status] || status;
    }

    function employeeCell(emp) {
        return `
            <div class="d-flex align-items-center">
                <img src="${DEFAULT_AVATAR}" alt="" class="avatar-xs rounded-circle me-2 border">
                <div>
                    <h6 class="fs-13 mb-0 fw-bold text-dark">${escapeHtml(emp?.name || 'موظف غير محدد')}</h6>
                    <span class="badge bg-light text-secondary fs-11">${escapeHtml(emp?.code || '-')}</span>
                </div>
            </div>`;
    }

    function renderAttendanceTable(records) {
        const tbody = document.getElementById('tbody-attendance');
        if (!tbody) return;

        if (records.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted fs-13">لا توجد سجلات حضور مطابقة للمحددات المحددة</td></tr>`;
            return;
        }

        tbody.innerHTML = records.map(r => {
            const emp = r.employee;
            const lateBadge = r.status === 'late'
                ? `<span class="badge bg-warning-subtle text-warning fw-bold fs-12">${Number(r.latenessMinutes)} دقيقة</span>`
                : '-';
            const punchInDisplay = r.punchIn && r.punchIn !== '-' ? `<span class="fw-semibold text-dark">${escapeHtml(r.punchIn)}</span>` : '<span class="text-muted">لم يبصم</span>';
            const punchOutDisplay = r.punchOut && r.punchOut !== '-' ? `<span class="fw-semibold text-dark">${escapeHtml(r.punchOut)}</span>` : '<span class="text-muted">-</span>';

            return `
                <tr>
                    <td>${employeeCell(emp)}</td>
                    <td>
                        <span class="fw-semibold fs-12 d-block">${escapeHtml(emp.role)}</span>
                        <span class="text-muted fs-11">${escapeHtml(emp.department)}</span>
                    </td>
                    <td><span class="fs-12 fw-medium text-dark">${escapeHtml(r.date || '-')}</span></td>
                    <td><span class="badge bg-light text-muted fs-11">${escapeHtml(emp.startTime)} - ${escapeHtml(emp.endTime)}</span></td>
                    <td>${punchInDisplay}</td>
                    <td>${punchOutDisplay}</td>
                    <td>${lateBadge}</td>
                    <td>${statusBadge(r.status)}</td>
                    <td class="no-print">
                        ${r.totalDeductions > 0 ? `<span class="badge bg-danger text-white fs-11">${formatCurrency(r.totalDeductions)}</span>` : '<span class="text-muted fs-11">-</span>'}
                    </td>
                </tr>`;
        }).join('');
    }

    function renderStaffSummaryTable(staffList) {
        const tbody = document.getElementById('tbody-staff-summary');
        if (!tbody) return;

        if (staffList.length === 0) {
            tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-muted fs-13">لا توجد بيانات موظفين مطابقة</td></tr>`;
            return;
        }

        tbody.innerHTML = staffList.map(s => {
            const emp = s.employee;
            const rate = Number(s.attendanceRate) || 0;
            const rateColor = rate >= 90 ? 'bg-success' : (rate >= 75 ? 'bg-warning' : 'bg-danger');

            return `
                <tr>
                    <td>${employeeCell(emp)}</td>
                    <td>
                        <span class="fw-semibold fs-12 d-block">${escapeHtml(emp.role)}</span>
                        <span class="text-muted fs-11">${escapeHtml(emp.department)}</span>
                    </td>
                    <td><span class="fw-bold">${Number(s.totalWorkDays)}</span></td>
                    <td><span class="badge bg-success-subtle text-success fs-12 fw-bold">${Number(s.presentDays)}</span></td>
                    <td><span class="badge ${s.lateDays > 0 ? 'bg-warning-subtle text-warning' : 'bg-light text-muted'} fs-12 fw-bold">${Number(s.lateDays)}</span></td>
                    <td><span class="fw-bold text-warning">${Number(s.totalLateMinutes)} دقيقة</span></td>
                    <td><span class="badge ${s.absentDays > 0 ? 'bg-danger-subtle text-danger' : 'bg-light text-muted'} fs-12 fw-bold">${Number(s.absentDays)}</span></td>
                    <td><span class="badge ${s.leaveDays > 0 ? 'bg-info-subtle text-info' : 'bg-light text-muted'} fs-12">${Number(s.leaveDays)}</span></td>
                    <td style="min-width: 140px;">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress progress-sm flex-grow-1" style="height: 6px;">
                                <div class="progress-bar ${rateColor}" role="progressbar" style="width: ${rate}%"></div>
                            </div>
                            <span class="fs-12 fw-bold text-dark">${rate}%</span>
                        </div>
                    </td>
                    <td><span class="fw-bold text-danger">${formatCurrency(s.totalDeductionsAmount)}</span></td>
                    <td class="no-print">
                        <button type="button" class="btn btn-sm btn-soft-primary" onclick="openEmployeeCard(${Number(emp.id)})" title="عرض السجل الشخصي">
                            <i class="ri-eye-line align-middle"></i> التفاصيل
                        </button>
                    </td>
                </tr>`;
        }).join('');
    }

    function deductionStatusBadge(status) {
        return status === 'applied'
            ? '<span class="badge bg-success-subtle text-success fs-11"><i class="ri-checkbox-circle-line me-1"></i>معتمد ومخصوم في مسير مصروف</span>'
            : '<span class="badge bg-info-subtle text-info fs-11"><i class="ri-time-line me-1"></i>معتمد — يُخصم في المسير</span>';
    }

    function renderDeductionsTable(deductions) {
        const tbody = document.getElementById('tbody-deductions');
        if (!tbody) return;

        if (deductions.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted fs-13">لا توجد قرارات خصم مسجلة خلال هذه الفترة</td></tr>`;
            return;
        }

        tbody.innerHTML = deductions.map(d => {
            const emp = d.employee;
            return `
                <tr>
                    <td><span class="badge bg-danger-subtle text-danger fs-12 fw-bold">${escapeHtml(d.decisionNo)}</span></td>
                    <td>${employeeCell(emp)}</td>
                    <td>
                        <span class="fs-12 fw-semibold d-block">${escapeHtml(emp?.role || '-')}</span>
                        <span class="text-muted fs-11">${escapeHtml(emp?.department || '-')}</span>
                    </td>
                    <td><span class="fs-12 text-muted">${escapeHtml(d.date)}</span></td>
                    <td><span class="fs-13 fw-bold text-danger">${formatCurrency(d.amount)}</span></td>
                    <td><span class="fw-semibold text-dark fs-12">${escapeHtml(d.reason)}</span></td>
                    <td><span class="text-muted fs-12">-</span></td>
                    <td>${deductionStatusBadge(d.status)}</td>
                </tr>`;
        }).join('');
    }

    // Modal: single employee discipline history (from the database)
    async function openEmployeeCard(empId) {
        let history;
        try {
            history = await fetchJson(REPORT_URLS.employee.replace('__ID__', encodeURIComponent(empId)));
        } catch (err) {
            if (typeof Swal !== 'undefined') Swal.fire('خطأ', err.message, 'error');
            return;
        }

        const emp = history.employee;
        const deds = history.deductions || [];

        document.getElementById('employeeModalTitle').textContent = `كشف انضباط: ${emp.name} (${emp.role})`;

        const dedsHtml = deds.length === 0
            ? '<p class="text-muted text-center fs-12 mb-0 py-2">سجل الموظف نظيف من الخصومات الإدارية ✨</p>'
            : `
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle fs-12 mb-0">
                        <thead class="table-light">
                            <tr><th>القرار</th><th>التاريخ</th><th>المبلغ</th><th>السبب</th><th>الحالة</th></tr>
                        </thead>
                        <tbody>
                            ${deds.map(d => `
                                <tr>
                                    <td><span class="badge bg-danger-subtle text-danger">${escapeHtml(d.decisionNo)}</span></td>
                                    <td>${escapeHtml(d.date)}</td>
                                    <td class="text-danger fw-bold">${formatCurrency(d.amount)}</td>
                                    <td>${escapeHtml(d.reason)}</td>
                                    <td>${deductionStatusBadge(d.status)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>`;

        document.getElementById('employeeModalBody').innerHTML = `
            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded mb-3">
                <img src="${DEFAULT_AVATAR}" class="avatar-md rounded-circle border shadow-sm" alt="">
                <div>
                    <h5 class="fw-bold mb-1 text-dark">${escapeHtml(emp.name)}</h5>
                    <p class="text-muted mb-1 fs-13"><i class="ri-briefcase-line me-1"></i> ${escapeHtml(emp.role)} - <span class="text-primary">${escapeHtml(emp.department)}</span></p>
                    <div class="d-flex gap-2">
                        <span class="badge bg-primary-subtle text-primary">كود: ${escapeHtml(emp.code)}</span>
                        <span class="badge bg-success-subtle text-success">الوردية: ${escapeHtml(emp.startTime)} - ${escapeHtml(emp.endTime)}</span>
                    </div>
                </div>
            </div>

            <div class="row g-2 mb-3 text-center">
                <div class="col-3"><div class="p-2 border rounded"><p class="text-muted fs-11 mb-1">أيام الحضور</p><h6 class="text-success fw-bold mb-0">${Number(history.presentDays)} يوم</h6></div></div>
                <div class="col-3"><div class="p-2 border rounded"><p class="text-muted fs-11 mb-1">مرات التأخير</p><h6 class="text-warning fw-bold mb-0">${Number(history.lateDays)} مرة</h6></div></div>
                <div class="col-3"><div class="p-2 border rounded"><p class="text-muted fs-11 mb-1">إجمالي التأخير</p><h6 class="text-danger fw-bold mb-0">${Number(history.totalLateMinutes)} دقيقة</h6></div></div>
                <div class="col-3"><div class="p-2 border rounded"><p class="text-muted fs-11 mb-1">إجمالي الخصومات</p><h6 class="text-danger fw-bold mb-0">${formatCurrency(history.totalDeductionsAmount)}</h6></div></div>
            </div>

            <h6 class="fw-bold fs-13 text-dark mb-2"><i class="ri-file-warning-line text-danger me-1"></i> سجل الخصومات والجزاءات المعتمدة:</h6>
            ${dedsHtml}
        `;

        const modal = new bootstrap.Modal(document.getElementById('employeeDetailModal'));
        modal.show();
    }

    function printModalEmployeeCard() {
        window.print();
    }

    function printOfficialReport() {
        window.print();
    }

    function exportToCSV(filename, headers, rows) {
        const cell = (value) => `"${(value === null || value === undefined ? '' : String(value)).replace(/"/g, '""')}"`;
        let csvContent = '﻿'; // UTF-8 BOM for Arabic text in Excel
        csvContent += headers.map(cell).join(',') + '\r\n';
        rows.forEach(row => { csvContent += row.map(cell).join(',') + '\r\n'; });

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', `${filename}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function exportReportCSV() {
        if (!currentReportData) return;

        const filename = `تقرير_مركز_الحسيني_${currentMode}_${localDateString(new Date())}`;
        const headers = ['كود الموظف', 'اسم الموظف', 'الوظيفة', 'القسم', 'التاريخ', 'وقت الحضور الفعلي', 'وقت الانصراف', 'دقائق التأخير', 'الحالة', 'الخصومات المطبقة (ج.م)'];
        const rows = [];

        if (currentMode === 'daily') {
            currentReportData.employees.forEach(r => {
                rows.push([r.employee.code, r.employee.name, r.employee.role, r.employee.department, r.date, r.punchIn, r.punchOut, r.latenessMinutes, statusLabel(r.status), r.totalDeductions]);
            });
        } else {
            currentReportData.staffReport.forEach(s => {
                s.attendanceDetails.forEach(att => {
                    rows.push([s.employee.code, s.employee.name, s.employee.role, s.employee.department, att.date, att.punchIn || '-', att.punchOut || '-', att.latenessMinutes || 0, statusLabel(att.status), 0]);
                });
            });
        }

        exportToCSV(filename, headers, rows);
    }
</script>
