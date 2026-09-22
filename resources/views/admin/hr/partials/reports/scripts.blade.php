<!-- ApexCharts JS -->
<script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>

<script>
    'use strict';

    let currentMode = 'daily'; // 'daily' | 'monthly' | 'custom'
    let currentReportData = null;
    let attendancePieChart = null;
    let timelineBarChart = null;

    document.addEventListener('DOMContentLoaded', function () {
        // Initialize Default Values
        const today = new Date();
        const y = today.getFullYear();
        const m = today.getMonth() + 1;
        const dStr = today.toISOString().split('T')[0];

        const dailyInput = document.getElementById('input-daily-date');
        if (dailyInput) dailyInput.value = dStr;

        const monthSelect = document.getElementById('select-month');
        if (monthSelect) monthSelect.value = m;

        const yearSelect = document.getElementById('select-year');
        if (yearSelect) yearSelect.value = y;

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

        // Check URL parameters on page load (e.g. ?search=EMP-0101&department=المبيعات)
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
            // Load initial daily report
            loadReport();
        }

        // Listen for external updates
        window.addEventListener('alhusseini-hr-updated', function () {
            loadReport();
        });
    });

    function setReportMode(mode) {
        currentMode = mode;

        // Button styles
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
                if (m === mode) {
                    ctrl.classList.remove('d-none');
                } else {
                    ctrl.classList.add('d-none');
                }
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
        const nextY = curr.getFullYear();
        const nextM = String(curr.getMonth() + 1).padStart(2, '0');
        const nextD = String(curr.getDate()).padStart(2, '0');
        input.value = `${nextY}-${nextM}-${nextD}`;
        loadReport();
    }

    function setTodayDate() {
        const input = document.getElementById('input-daily-date');
        const today = new Date();
        const y = today.getFullYear();
        const m = String(today.getMonth() + 1).padStart(2, '0');
        const d = String(today.getDate()).padStart(2, '0');
        input.value = `${y}-${m}-${d}`;
        loadReport();
    }

    function loadReport() {
        if (!window.AlHusseiniHR) return;

        let periodTitle = '';
        let printPeriodText = '';

        if (currentMode === 'daily') {
            const targetDate = document.getElementById('input-daily-date')?.value || new Date().toISOString().split('T')[0];
            currentReportData = window.AlHusseiniHR.getDailyReport(targetDate);
            
            const parts = targetDate.split('-').map(Number);
            const dateObj = new Date(parts[0], parts[1] - 1, parts[2]);
            const dateArabic = dateObj.toLocaleDateString('ar-EG', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            periodTitle = `تقرير يوم: ${dateArabic}`;
            printPeriodText = `كشف يوم: ${targetDate} (${dateArabic})`;
        } else if (currentMode === 'monthly') {
            const m = parseInt(document.getElementById('select-month')?.value || (new Date().getMonth() + 1));
            const y = parseInt(document.getElementById('select-year')?.value || new Date().getFullYear());
            currentReportData = window.AlHusseiniHR.getMonthlyReport(y, m);
            
            const monthNames = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
            periodTitle = `تقرير شهر: ${monthNames[m]} ${y}`;
            printPeriodText = `كشف شهر: ${monthNames[m]} ${y} (من ${currentReportData.startDate} إلى ${currentReportData.endDate})`;
        } else {
            let start = document.getElementById('input-range-start')?.value;
            let end = document.getElementById('input-range-end')?.value;
            if (start && end && start > end) {
                const temp = start;
                start = end;
                end = temp;
                document.getElementById('input-range-start').value = start;
                document.getElementById('input-range-end').value = end;
            }
            currentReportData = window.AlHusseiniHR.getRangeReport(start, end);
            periodTitle = `تقرير الفترة: من ${start} إلى ${end}`;
            printPeriodText = `الفترة من ${start} إلى ${end}`;
        }

        // Update Period Badges
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
            document.getElementById('kpi-deductions-amount').textContent = window.AlHusseiniHR.formatCurrency(summary.totalDeductionsAmount);
            document.getElementById('kpi-deductions-count').textContent = summary.deductionsCount;
        } else {
            document.getElementById('kpi-attendance-rate').textContent = `${summary.avgAttendanceRate}%`;
            document.getElementById('kpi-present-count').textContent = `${summary.totalPresents} يوم عمل`;
            document.getElementById('kpi-lateness-minutes').textContent = `${summary.totalLateMins} دقيقة`;
            document.getElementById('kpi-late-count').textContent = summary.totalLates;
            document.getElementById('kpi-absence-count').textContent = `${summary.totalAbsents} يوم غياب`;
            document.getElementById('kpi-unexcused-count').textContent = summary.totalAbsents;
            document.getElementById('kpi-leave-count').textContent = summary.staffReport.reduce((s, r) => s + r.leaveDays, 0);
            document.getElementById('kpi-deductions-amount').textContent = window.AlHusseiniHR.formatCurrency(summary.totalDeductionsAmount);
            document.getElementById('kpi-deductions-count').textContent = summary.deductionsCount;
        }
    }

    function renderCharts() {
        if (!currentReportData) return;
        const summary = currentReportData.summary;

        // 1. Donut Pie Chart
        let pieSeries = [];
        if (currentMode === 'daily') {
            pieSeries = [summary.onTimeCount, summary.lateCount, summary.absentCount, summary.leaveCount];
        } else {
            const onTime = summary.totalOnTimes;
            const late = summary.totalLates;
            const absent = summary.totalAbsents;
            const leave = summary.staffReport.reduce((s, r) => s + r.leaveDays, 0);
            pieSeries = [onTime, late, absent, leave];
        }

        const pieOptions = {
            series: pieSeries,
            chart: {
                type: 'donut',
                height: 250,
                fontFamily: 'inherit'
            },
            labels: ['حاضر في الموعد', 'متأخر عن الوردية', 'غياب بدون إذن', 'إجازة رسمية'],
            colors: ['#2a9d8f', '#f4a261', '#e63946', '#457b9d'],
            legend: { show: false },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return Math.round(val) + "%";
                }
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
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                }
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
            // Compare Departments on this day
            const depts = window.AlHusseiniHR.getDepartments();
            categories = depts.map(d => d.replace('بمركز البطاريات', '').trim());

            categories.forEach(dept => {
                const staffInDept = currentReportData.employees.filter(e => e.employee.department.includes(dept));
                seriesOnTime.push(staffInDept.filter(s => s.status === 'on_time').length);
                seriesLate.push(staffInDept.filter(s => s.status === 'late').length);
                seriesAbsent.push(staffInDept.filter(s => s.status === 'absent' || s.status === 'on_leave').length);
            });
        } else {
            // Daily trend progression across the month
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
            chart: {
                type: 'bar',
                height: 250,
                stacked: true,
                toolbar: { show: false },
                fontFamily: 'inherit'
            },
            colors: ['#2a9d8f', '#f4a261', '#e63946'],
            plotOptions: {
                bar: {
                    horizontal: false,
                    borderRadius: 4,
                    columnWidth: '45%'
                }
            },
            xaxis: {
                categories: categories,
                labels: {
                    style: { fontSize: '11px' }
                }
            },
            yaxis: {
                title: { text: currentMode === 'daily' ? 'عدد الفنيين' : 'سجلات اليوم' }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right'
            },
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
            .replace(/[\u064B-\u065F\u0670]/g, '')
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

        // 1. Filter Attendance Detailed Records
        let attendanceList = [];
        if (currentMode === 'daily') {
            attendanceList = currentReportData.employees;
        } else {
            // In Monthly or Range mode, flatten each employee's attendance details
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

        // Apply Dept & Search Filters to Attendance List
        const filteredAttendance = attendanceList.filter(item => {
            const matchesDept = !deptFilter || isMatch(item.employee.department, deptFilter);
            const matchesSearch = !searchKeyword ||
                isMatch(item.employee.name, searchKeyword) ||
                isMatch(item.employee.id, searchKeyword) ||
                isMatch(item.employee.role, searchKeyword) ||
                isMatch(item.employee.department, searchKeyword);
            return matchesDept && matchesSearch;
        });

        renderAttendanceTable(filteredAttendance);

        // 2. Filter Staff Summary Table
        let staffList = (currentMode === 'daily') ? currentReportData.employees.map(r => ({
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

        const filteredStaff = staffList.filter(item => {
            const matchesDept = !deptFilter || isMatch(item.employee.department, deptFilter);
            const matchesSearch = !searchKeyword ||
                isMatch(item.employee.name, searchKeyword) ||
                isMatch(item.employee.id, searchKeyword) ||
                isMatch(item.employee.role, searchKeyword) ||
                isMatch(item.employee.department, searchKeyword);
            return matchesDept && matchesSearch;
        });

        renderStaffSummaryTable(filteredStaff);

        // 3. Filter Deductions Register
        const deductionsList = currentReportData.deductionsList || [];
        const filteredDeductions = deductionsList.filter(ded => {
            const emp = window.AlHusseiniHR.getEmployeeById(ded.employeeId);
            if (!emp) return true;
            const matchesDept = !deptFilter || isMatch(emp.department, deptFilter);
            const matchesSearch = !searchKeyword ||
                isMatch(emp.name, searchKeyword) ||
                isMatch(emp.id, searchKeyword) ||
                isMatch(ded.reason, searchKeyword);
            return matchesDept && matchesSearch;
        });

        renderDeductionsTable(filteredDeductions);

        // Update Badges
        document.getElementById('badge-count-attendance').textContent = filteredAttendance.length;
        document.getElementById('badge-count-staff').textContent = filteredStaff.length;
        document.getElementById('badge-count-deductions').textContent = filteredDeductions.length;
    }

    function renderAttendanceTable(records) {
        const tbody = document.getElementById('tbody-attendance');
        if (!tbody) return;

        if (records.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted fs-13">لا توجد سجلات حضور مطابقة للمحددات المحددة</td></tr>`;
            return;
        }

        let html = '';
        records.forEach(r => {
            const emp = r.employee;
            let statusBadge = '';
            let lateBadge = '-';

            if (r.status === 'on_time') {
                statusBadge = '<span class="badge badge-status-on_time px-2 py-1"><i class="ri-check-line me-1"></i>في الموعد</span>';
            } else if (r.status === 'late') {
                statusBadge = '<span class="badge badge-status-late px-2 py-1"><i class="ri-alarm-warning-line me-1"></i>متأخر</span>';
                lateBadge = `<span class="badge bg-warning-subtle text-warning fw-bold fs-12">${r.latenessMinutes} دقيقة</span>`;
            } else if (r.status === 'on_leave') {
                statusBadge = '<span class="badge badge-status-on_leave px-2 py-1"><i class="ri-calendar-line me-1"></i>إجازة رسمية</span>';
            } else {
                statusBadge = '<span class="badge badge-status-absent px-2 py-1"><i class="ri-close-line me-1"></i>غياب بدون إذن</span>';
            }

            const punchInDisplay = r.punchIn !== '-' ? `<span class="fw-semibold text-dark">${r.punchIn}</span>` : '<span class="text-muted">لم يبصم</span>';
            const punchOutDisplay = r.punchOut !== '-' ? `<span class="fw-semibold text-dark">${r.punchOut}</span>` : '<span class="text-muted">-</span>';

            html += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="${emp.avatar || '/assets/images/users/avatar-1.jpg'}" alt="" class="avatar-xs rounded-circle me-2 border">
                            <div>
                                <h6 class="fs-13 mb-0 fw-bold text-dark">${emp.name}</h6>
                                <span class="badge bg-light text-secondary fs-11">${emp.id}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="fw-semibold fs-12 d-block">${emp.role}</span>
                        <span class="text-muted fs-11">${emp.department}</span>
                    </td>
                    <td><span class="fs-12 fw-medium text-dark">${r.date || '-'}</span></td>
                    <td><span class="badge bg-light text-muted fs-11">${emp.startTime || '09:00'} - ${emp.endTime || '18:00'}</span></td>
                    <td>${punchInDisplay}</td>
                    <td>${punchOutDisplay}</td>
                    <td>${lateBadge}</td>
                    <td>${statusBadge}</td>
                    <td class="no-print">
                        ${r.totalDeductions > 0 ? `<span class="badge bg-danger text-white fs-11">${window.AlHusseiniHR.formatCurrency(r.totalDeductions)}</span>` : '<span class="text-muted fs-11">-</span>'}
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    function renderStaffSummaryTable(staffList) {
        const tbody = document.getElementById('tbody-staff-summary');
        if (!tbody) return;

        if (staffList.length === 0) {
            tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-muted fs-13">لا توجد بيانات موظفين مطابقة</td></tr>`;
            return;
        }

        let html = '';
        staffList.forEach(s => {
            const emp = s.employee;
            const rateColor = s.attendanceRate >= 90 ? 'bg-success' : (s.attendanceRate >= 75 ? 'bg-warning' : 'bg-danger');

            html += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="${emp.avatar || '/assets/images/users/avatar-1.jpg'}" alt="" class="avatar-xs rounded-circle me-2 border">
                            <div>
                                <h6 class="fs-13 mb-0 fw-bold text-dark">${emp.name}</h6>
                                <span class="badge bg-light text-secondary fs-11">${emp.id}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="fw-semibold fs-12 d-block">${emp.role}</span>
                        <span class="text-muted fs-11">${emp.department}</span>
                    </td>
                    <td><span class="fw-bold">${s.totalWorkDays}</span></td>
                    <td><span class="badge bg-success-subtle text-success fs-12 fw-bold">${s.presentDays}</span></td>
                    <td><span class="badge ${s.lateDays > 0 ? 'bg-warning-subtle text-warning' : 'bg-light text-muted'} fs-12 fw-bold">${s.lateDays}</span></td>
                    <td><span class="fw-bold text-warning">${s.totalLateMinutes} دقيقة</span></td>
                    <td><span class="badge ${s.absentDays > 0 ? 'bg-danger-subtle text-danger' : 'bg-light text-muted'} fs-12 fw-bold">${s.absentDays}</span></td>
                    <td><span class="badge ${s.leaveDays > 0 ? 'bg-info-subtle text-info' : 'bg-light text-muted'} fs-12">${s.leaveDays}</span></td>
                    <td style="min-width: 140px;">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress progress-sm flex-grow-1" style="height: 6px;">
                                <div class="progress-bar ${rateColor}" role="progressbar" style="width: ${s.attendanceRate}%"></div>
                            </div>
                            <span class="fs-12 fw-bold text-dark">${s.attendanceRate}%</span>
                        </div>
                    </td>
                    <td>
                        <span class="fw-bold text-danger">${window.AlHusseiniHR.formatCurrency(s.totalDeductionsAmount)}</span>
                    </td>
                    <td class="no-print">
                        <button type="button" class="btn btn-sm btn-soft-primary" onclick="openEmployeeCard('${emp.id}')" title="عرض السجل الشخصي">
                            <i class="ri-eye-line align-middle"></i> التفاصيل
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    function renderDeductionsTable(deductions) {
        const tbody = document.getElementById('tbody-deductions');
        if (!tbody) return;

        if (deductions.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted fs-13">لا توجد قرارات خصم مسجلة خلال هذه الفترة</td></tr>`;
            return;
        }

        let html = '';
        deductions.forEach(d => {
            const emp = window.AlHusseiniHR.getEmployeeById(d.employeeId);
            const empName = emp ? emp.name : 'موظف غير محدد';
            const empRole = emp ? emp.role : '-';
            const empDept = emp ? emp.department : '-';

            html += `
                <tr>
                    <td><span class="badge bg-danger-subtle text-danger fs-12 fw-bold">${d.decisionNo || d.id}</span></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="${emp?.avatar || '/assets/images/users/avatar-1.jpg'}" alt="" class="avatar-xs rounded-circle me-2 border">
                            <div>
                                <h6 class="fs-13 mb-0 fw-bold text-dark">${empName}</h6>
                                <span class="badge bg-light text-secondary fs-11">${d.employeeId}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="fs-12 fw-semibold d-block">${empRole}</span>
                        <span class="text-muted fs-11">${empDept}</span>
                    </td>
                    <td><span class="fs-12 text-muted">${d.date}</span></td>
                    <td><span class="fs-13 fw-bold text-danger">${window.AlHusseiniHR.formatCurrency(d.amount)}</span></td>
                    <td><span class="fw-semibold text-dark fs-12">${d.reason}</span></td>
                    <td><span class="text-muted fs-12">${d.managerNotes || '-'}</span></td>
                    <td><span class="badge bg-success-subtle text-success fs-11"><i class="ri-checkbox-circle-line me-1"></i>معتمد ومخصوم</span></td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    // Modal for Single Employee Performance & Deductions History
    function openEmployeeCard(empId) {
        const emp = window.AlHusseiniHR.getEmployeeById(empId);
        if (!emp) return;

        const allAtt = window.AlHusseiniHR.getAllAttendance().filter(a => a.employeeId === empId);
        const allDeds = window.AlHusseiniHR.getDeductions().filter(d => d.employeeId === empId);

        const totalLate = allAtt.reduce((sum, a) => sum + (a.latenessMinutes || 0), 0);
        const totalDedAmount = allDeds.reduce((sum, d) => sum + Number(d.amount || 0), 0);

        document.getElementById('employeeModalTitle').textContent = `كشف انضباط: ${emp.name} (${emp.role})`;

        let dedsHtml = '';
        if (allDeds.length === 0) {
            dedsHtml = '<p class="text-muted text-center fs-12 mb-0 py-2">سجل الموظف نظيف من الخصومات الإدارية ✨</p>';
        } else {
            dedsHtml = `
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle fs-12 mb-0">
                        <thead class="table-light">
                            <tr><th>القرار</th><th>التاريخ</th><th>المبلغ</th><th>السبب</th><th>ملاحظات المدير</th></tr>
                        </thead>
                        <tbody>
                            ${allDeds.map(d => `
                                <tr>
                                    <td><span class="badge bg-danger-subtle text-danger">${d.decisionNo || d.id}</span></td>
                                    <td>${d.date}</td>
                                    <td class="text-danger fw-bold">${window.AlHusseiniHR.formatCurrency(d.amount)}</td>
                                    <td>${d.reason}</td>
                                    <td class="text-muted">${d.managerNotes || '-'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        const modalBody = document.getElementById('employeeModalBody');
        modalBody.innerHTML = `
            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded mb-3">
                <img src="${emp.avatar}" class="avatar-md rounded-circle border shadow-sm" alt="">
                <div>
                    <h5 class="fw-bold mb-1 text-dark">${emp.name}</h5>
                    <p class="text-muted mb-1 fs-13"><i class="ri-briefcase-line me-1"></i> ${emp.role} - <span class="text-primary">${emp.department}</span></p>
                    <div class="d-flex gap-2">
                        <span class="badge bg-primary-subtle text-primary">كود: ${emp.id}</span>
                        <span class="badge bg-success-subtle text-success">الوردية: ${emp.startTime} - ${emp.endTime}</span>
                        <span class="badge bg-info-subtle text-info">الراتب الأساسي: ${window.AlHusseiniHR.formatCurrency(emp.baseSalary)}</span>
                    </div>
                </div>
            </div>

            <div class="row g-2 mb-3 text-center">
                <div class="col-3">
                    <div class="p-2 border rounded">
                        <p class="text-muted fs-11 mb-1">أيام الحضور</p>
                        <h6 class="text-success fw-bold mb-0">${allAtt.filter(a => a.status === 'on_time' || a.status === 'late').length} يوم</h6>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 border rounded">
                        <p class="text-muted fs-11 mb-1">مرات التأخير</p>
                        <h6 class="text-warning fw-bold mb-0">${allAtt.filter(a => a.status === 'late').length} مرة</h6>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 border rounded">
                        <p class="text-muted fs-11 mb-1">إجمالي التأخير</p>
                        <h6 class="text-danger fw-bold mb-0">${totalLate} دقيقة</h6>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 border rounded">
                        <p class="text-muted fs-11 mb-1">إجمالي الخصومات</p>
                        <h6 class="text-danger fw-bold mb-0">${window.AlHusseiniHR.formatCurrency(totalDedAmount)}</h6>
                    </div>
                </div>
            </div>

            <h6 class="fw-bold fs-13 text-dark mb-2"><i class="ri-file-warning-line text-danger me-1"></i> سجل الخصومات والجزاءات المحررة:</h6>
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

    function exportReportCSV() {
        if (!currentReportData) return;

        let filename = `تقرير_مركز_الحسيني_${currentMode}_${new Date().toISOString().split('T')[0]}`;
        const headers = ['كود الموظف', 'اسم الموظف', 'الوظيفة', 'القسم', 'التاريخ', 'وقت الحضور الفعلي', 'وقت الانصراف', 'دقائق التأخير', 'الحالة', 'الخصومات المطبقة (ج.م)'];

        const rows = [];
        if (currentMode === 'daily') {
            currentReportData.employees.forEach(r => {
                const statusLabel = r.status === 'on_time' ? 'في الموعد' : (r.status === 'late' ? 'متأخر' : (r.status === 'on_leave' ? 'إجازة' : 'غياب'));
                rows.push([
                    r.employee.id,
                    r.employee.name,
                    r.employee.role,
                    r.employee.department,
                    r.date,
                    r.punchIn,
                    r.punchOut,
                    r.latenessMinutes,
                    statusLabel,
                    r.totalDeductions
                ]);
            });
        } else {
            currentReportData.staffReport.forEach(s => {
                s.attendanceDetails.forEach(att => {
                    const statusLabel = att.status === 'on_time' ? 'في الموعد' : (att.status === 'late' ? 'متأخر' : (att.status === 'on_leave' ? 'إجازة' : 'غياب'));
                    rows.push([
                        s.employee.id,
                        s.employee.name,
                        s.employee.role,
                        s.employee.department,
                        att.date,
                        att.punchIn || '-',
                        att.punchOut || '-',
                        att.latenessMinutes || 0,
                        statusLabel,
                        0
                    ]);
                });
            });
        }

        window.AlHusseiniHR.exportToCSV(filename, headers, rows);
    }
</script>
