/**
 * HR attendance screen (resources/views/admin/hr/attendance.blade.php), frontend F4.
 * Config (data-config on [data-page="hr.attendance"]): { routes: {attendance, punch, markAbsent, deductions}, employees: [...] }
 * Moved from hr/partials/attendance/scripts.blade.php; behaviour unchanged except that server
 * values are now escaped before they reach innerHTML and the clock timer stops on navigation.
 */
(function () {
    'use strict';

    Admin.page.register('hr.attendance', function (root, config) {
        const esc = Admin.esc;
        const ROUTES = config.routes;
        const allEmployees = config.employees || [];
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const quickDeductionModal = new bootstrap.Modal(document.getElementById('quickDeductionModal'));

        let currentFilterTab = 'all'; // 'all' | 'present' | 'late' | 'absent'
        let cachedAttendances = [];

        // 1. Live Digital Clock in Cairo Time
        function updateShopClock() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
            const dateStr = now.toLocaleDateString('ar-EG', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

            const clockEl = document.getElementById('liveDigitalClock');
            const dateEl = document.getElementById('shopLiveDate');
            if (clockEl) clockEl.textContent = timeStr;
            if (dateEl) dateEl.textContent = dateStr;
        }
        const clockTimer = setInterval(updateShopClock, 1000);
        Admin.page.onLeave(() => clearInterval(clockTimer));
        updateShopClock();

        function selectedDate() {
            return document.getElementById('attendanceDateFilter').value || new Date().toISOString().split('T')[0];
        }

        // 2. Fetch Attendance Data from Backend
        function fetchAttendance() {
            fetch(`${ROUTES.attendance}?date=${selectedDate()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                cachedAttendances = data.attendances || [];
                updateStatsUI(data.stats);
                renderTable();
                const timeNow = new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                document.getElementById('lastUpdatedTime').textContent = timeNow;
            })
            .catch(err => console.error('Error fetching attendance:', err));
        }

        function updateStatsUI(stats) {
            if (!stats) return;
            const present = stats.present || 0;
            const late = stats.late || 0;
            const absent = stats.absent || 0;
            const total = stats.total_expected || allEmployees.length;

            document.getElementById('statCountOnTime').textContent = present;
            document.getElementById('statCountLate').textContent = late;
            document.getElementById('statCountAbsent').textContent = absent;
            document.getElementById('statCountTotal').textContent = `${total} موظف`;

            document.getElementById('pillCountAll').textContent = total;
            document.getElementById('pillCountOnTime').textContent = present;
            document.getElementById('pillCountLate').textContent = late;
            document.getElementById('pillCountAbsent').textContent = absent;

            const attended = present + late;
            const punctuality = total > 0 ? Math.round((present / total) * 100) : 0;
            document.getElementById('statPunctualityText').textContent = `نسبة الانضباط: ${punctuality}% (${attended}/${total} حضروا)`;
        }

        // 3. Universal Arabic Normalization & Fuzzy-Matching Engine
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

        // Row action buttons: data-action + escaped data-* (no inline JS).
        function punchButton(emp, type, cls, icon, label, title) {
            return `<button type="button" class="btn btn-sm ${cls} btn-punch-action" data-action="hr.attendance.punch" data-employee-id="${esc(emp.id)}" data-punch-type="${type}"${title ? ` title="${title}"` : ''}>
                <i class="${icon}"></i> ${label}
            </button>`;
        }
        function absentButton(emp, title) {
            return `<button type="button" class="btn btn-sm btn-soft-danger btn-punch-action" data-action="hr.attendance.absent" data-employee-id="${esc(emp.id)}" data-employee-name="${esc(emp.full_name)}" title="${title}">
                <i class="ri-user-unfollow-line"></i> غياب
            </button>`;
        }
        function deductButton(emp, cls, icon, label, title) {
            return `<button type="button" class="btn btn-sm ${cls} btn-punch-action" data-action="hr.attendance.deduct" data-employee-id="${esc(emp.id)}" title="${title}">
                <i class="${icon}"></i> ${label}
            </button>`;
        }

        function renderTable() {
            const searchVal = (document.getElementById('searchEmployeeInput')?.value || '').trim();
            const tbody = document.getElementById('attendanceTableBody');
            if (!tbody) return;

            let rowsHtml = '';

            allEmployees.forEach(emp => {
                const att = cachedAttendances.find(a => a.employee_id === emp.id);

                const status = att ? att.status : 'absent';
                const punchIn = att?.check_in ? att.check_in.substring(11, 16) : '-';
                const punchOut = att?.check_out ? att.check_out.substring(11, 16) : '-';
                const lateness = att?.late_minutes || 0;

                // Status Filter Tab Check
                if (currentFilterTab !== 'all') {
                    if (currentFilterTab === 'present' && status !== 'present') return;
                    if (currentFilterTab === 'late' && status !== 'late') return;
                    if (currentFilterTab === 'absent' && status !== 'absent') return;
                }

                // Search Filter with Arabic Normalization & Multi-field
                if (searchVal) {
                    const roleName = emp.job_title?.title || emp.job_title?.title_name || '';
                    const branchName = emp.branch?.name || '';
                    const phone = emp.phone || '';

                    const matches = isMatch(emp.full_name, searchVal) ||
                        isMatch(emp.employee_code, searchVal) ||
                        isMatch(roleName, searchVal) ||
                        isMatch(branchName, searchVal) ||
                        isMatch(phone, searchVal);

                    if (!matches) return;
                }

                // Badges
                let statusBadge = '';
                let latenessBadge = '<span class="text-muted fs-12">-</span>';
                let directActionBtn = '';

                if (status === 'present') {
                    statusBadge = '<span class="badge bg-success text-white fs-12 px-3 py-1 fw-bold shadow-sm"><i class="ri-checkbox-circle-line me-1"></i>حاضر في الموعد</span>';
                    latenessBadge = '<span class="badge bg-success-subtle text-success fs-11 fw-bold">منضبط (0 د)</span>';

                    if (punchOut === '-') {
                        directActionBtn = `
                            <div class="d-inline-flex gap-1">
                                ${punchButton(emp, 'check_out', 'btn-outline-primary', 'ri-logout-box-r-line', 'تسجيل انصراف')}
                                ${absentButton(emp, 'تسجيل كغائب')}
                            </div>
                        `;
                    } else {
                        directActionBtn = `
                            <div class="d-inline-flex align-items-center gap-1">
                                <span class="badge bg-light text-muted border px-2 py-1 fs-12"><i class="ri-check-double-line text-success me-1"></i>أتم الوردية</span>
                                ${absentButton(emp, 'إلغاء الحضور وتسجيل غياب')}
                            </div>
                        `;
                    }
                } else if (status === 'late') {
                    statusBadge = '<span class="badge bg-warning text-dark fs-12 px-3 py-1 fw-bold shadow-sm"><i class="ri-alarm-warning-line me-1"></i>متأخر</span>';
                    latenessBadge = `<span class="badge bg-danger text-white fs-12 fw-bold font-monospace shadow-sm">+${esc(lateness)} دقيقة تأخير</span>`;

                    directActionBtn = `
                        <div class="d-inline-flex gap-1">
                            ${punchOut === '-' ? punchButton(emp, 'check_out', 'btn-outline-primary', 'ri-logout-box-r-line', 'انصراف', 'تسجيل انصراف') : ''}
                            ${deductButton(emp, 'btn-danger', 'ri-scissors-cut-line', 'خصم تأخير', 'تطبيق خصم فوري على التأخير')}
                            ${absentButton(emp, 'تسجيل كغائب')}
                        </div>
                    `;
                } else if (status === 'holiday') {
                    statusBadge = '<span class="badge bg-purple text-white fs-12 px-3 py-1 fw-bold shadow-sm" style="background-color: #8b5cf6;"><i class="ri-calendar-check-line me-1"></i>عمل في يوم إجازة (أوفر تايم)</span>';
                    latenessBadge = `<span class="badge bg-purple-subtle text-purple fs-11 fw-bold">+${esc(att?.overtime_hours || 0)} س إضافي</span>`;
                    directActionBtn = punchOut === '-'
                        ? punchButton(emp, 'check_out', 'btn-outline-primary', 'ri-logout-box-r-line', 'انصراف إضافي')
                        : '<span class="badge bg-light text-muted border px-2 py-1 fs-12">أتم العمل الإضافي</span>';
                } else if (emp.status === 'on_leave') {
                    statusBadge = '<span class="badge bg-info-subtle text-info fs-12 px-3 py-1 fw-bold"><i class="ri-flight-takeoff-line me-1"></i>إجازة رسمية</span>';
                    latenessBadge = '<span class="text-muted fs-11">إجازة معتمدة</span>';
                    directActionBtn = punchButton(emp, 'check_in', 'btn-outline-warning', 'ri-briefcase-line', 'حضور كإضافي', 'تسجيل حضور كعمل إضافي دون إلغاء الإجازة');
                } else {
                    statusBadge = '<span class="badge bg-danger-subtle text-danger fs-12 px-3 py-1 fw-bold"><i class="ri-close-circle-line me-1"></i>لم يسجل بصمته</span>';
                    latenessBadge = '<span class="text-danger fw-bold fs-11">غير حاضر بالمركز</span>';

                    directActionBtn = `
                        <div class="d-inline-flex gap-1">
                            ${punchButton(emp, 'check_in', 'btn-success', 'ri-fingerprint-line', 'حضر الآن')}
                            ${deductButton(emp, 'btn-soft-danger', 'ri-hand-coin-line', 'جزاء غياب', 'خصم غياب')}
                        </div>
                    `;
                }

                const punchInDisplay = punchIn !== '-' ? `<span class="fw-bold fs-13 font-monospace text-success">${esc(punchIn)}</span>` : '<span class="text-muted fs-12">لم يسجل</span>';
                const punchOutDisplay = punchOut !== '-' ? `<span class="fw-bold fs-13 font-monospace text-primary">${esc(punchOut)}</span>` : '<span class="text-muted fs-12">-</span>';

                rowsHtml += `
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-xs me-2">
                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle fw-bold">
                                        ${esc(emp.full_name.charAt(0))}
                                    </span>
                                </div>
                                <div>
                                    <h6 class="mb-0 fs-14 fw-bold text-dark">${esc(emp.full_name)}</h6>
                                    <span class="badge bg-light text-secondary font-monospace fs-11">${esc(emp.employee_code)}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="fw-bold fs-13 text-dark d-block">${esc(emp.job_title?.title_name || 'فني')}</span>
                            <span class="text-muted fs-11"><i class="ri-tools-line me-1"></i>${esc(emp.branch?.name || 'الفرع الرئيسي')}</span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border fs-12 px-2 py-1">
                                ${esc(emp.shift_start_time ? emp.shift_start_time.substring(0, 5) : '09:00')} - ${esc(emp.shift_end_time ? emp.shift_end_time.substring(0, 5) : '17:00')}
                            </span>
                        </td>
                        <td>${punchInDisplay}</td>
                        <td>${punchOutDisplay}</td>
                        <td>${statusBadge}</td>
                        <td>${latenessBadge}</td>
                        <td class="text-center">${directActionBtn}</td>
                    </tr>
                `;
            });

            tbody.innerHTML = rowsHtml || `<tr><td colspan="8" class="text-center py-5 text-muted fs-14"><i class="ri-user-search-line fs-24 d-block mb-1"></i>لا توجد نتائج مطابقة لبحثك</td></tr>`;
        }

        // 4. Quick Tab Filtering
        function setQuickTabFilter(tabName) {
            currentFilterTab = tabName;

            document.querySelectorAll('.filter-tab-pill').forEach(btn => btn.classList.remove('active'));
            const activeTabEl = document.getElementById(`tab-${tabName}`);
            if (activeTabEl) activeTabEl.classList.add('active');

            document.querySelectorAll('.stat-filter-card').forEach(card => card.classList.remove('active-filter', 'active-filter-warning', 'active-filter-danger'));
            const activeCard = document.getElementById(`card-filter-${tabName}`);
            if (activeCard) {
                if (tabName === 'late') activeCard.classList.add('active-filter-warning');
                else if (tabName === 'absent') activeCard.classList.add('active-filter-danger');
                else activeCard.classList.add('active-filter');
            }

            renderTable();
        }

        function clearSearch() {
            const input = document.getElementById('searchEmployeeInput');
            if (input) {
                input.value = '';
                renderTable();
            }
        }

        // 5. Sensor Animation
        function animatePunchSensor() {
            const sensor = document.getElementById('punchSensorAvatar');
            if (!sensor) return;
            sensor.classList.add('bg-warning');
            sensor.style.transform = 'scale(1.25)';
            setTimeout(() => {
                sensor.classList.remove('bg-warning');
                sensor.style.transform = 'scale(1)';
            }, 500);
        }

        // 6. Punch Direct
        function punchDirect(employeeId, type) {
            animatePunchSensor();

            const customTime = document.getElementById('customSimTime')?.value;
            let punchTimestamp;

            if (customTime) {
                punchTimestamp = `${selectedDate()} ${customTime}:00`;
            } else {
                const now = new Date();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');
                punchTimestamp = `${selectedDate()} ${hours}:${minutes}:${seconds}`;
            }

            fetch(ROUTES.punch, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    employee_id: employeeId,
                    timestamp: punchTimestamp,
                    punch_state: type
                })
            })
            .then(async res => {
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'تعذر تسجيل حركة البصمة.');
                return data;
            })
            .then(data => {
                const att = data.attendance;
                const empName = esc(att?.employee?.full_name || 'الموظف');
                // The API returns the Attendance row (check_in / check_out / late_minutes as datetimes);
                // show only HH:MM, never "undefined".
                const clock = (value) => value ? esc(String(value).substring(11, 16)) : '—';

                if (type === 'check_in') {
                    if (att.status === 'late') {
                        Swal.fire({
                            icon: 'warning',
                            title: '⚠️ تم تسجيل الحضور بتأخير!',
                            html: `
                                <div class="text-start fs-13">
                                    <p class="mb-1">الموظف: <strong class="text-dark">${empName}</strong></p>
                                    <p class="mb-1">وقت البصمة: <strong class="text-primary font-monospace">${clock(att.check_in)}</strong></p>
                                    <p class="mb-2 text-danger fw-bold fs-14">مدة التأخير: ${esc(att.late_minutes ?? 0)} دقيقة</p>
                                    <hr class="my-2">
                                    <p class="mb-0 text-muted fs-12">هل تريد تطبيق خصم إداري فوري على هذا الموظف الآن؟</p>
                                </div>
                            `,
                            showCancelButton: true,
                            confirmButtonText: 'نعم، تطبيق خصم الآن',
                            cancelButtonText: 'إغلاق بدون خصم',
                            confirmButtonColor: '#dc3545',
                            cancelButtonColor: '#6c757d'
                        }).then((swalRes) => {
                            if (swalRes.isConfirmed) {
                                openDeductionModal(employeeId);
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: '✅ تم تسجيل الحضور في الموعد!',
                            html: `تم تسجيل بصمة <strong>${empName}</strong> بنجاح. حضور منضبط وممتاز!`,
                            timer: 2500,
                            timerProgressBar: true
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: '👋 تم تسجيل بصمة الانصراف',
                        html: `تم تسجيل انصراف <strong>${empName}</strong> في تمام <strong>${clock(att.check_out)}</strong>. بالسلامة والتوفيق!`,
                        timer: 2500,
                        timerProgressBar: true
                    });
                }

                fetchAttendance();
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'تعذر التسجيل',
                    text: err.message
                });
            });
        }

        // Quick Punch Bar
        function quickPunch(type, missingText) {
            const empId = document.getElementById('quickPunchSelect').value;
            if (!empId) {
                Swal.fire('اختر الموظف أولاً', missingText, 'warning');
                return;
            }
            punchDirect(empId, type);
        }
        document.getElementById('btnQuickIn')?.addEventListener('click', () => quickPunch('check_in', 'يرجى اختيار اسم الموظف من القائمة أولاً لتسجيل بصمة الحضور.'));
        document.getElementById('btnQuickOut')?.addEventListener('click', () => quickPunch('check_out', 'يرجى اختيار اسم الموظف من القائمة أولاً لتسجيل بصمة الانصراف.'));

        // 7. Quick Deduction Management
        function openDeductionModal(employeeId) {
            const emp = allEmployees.find(e => e.id == employeeId);
            if (!emp) return;

            const att = cachedAttendances.find(a => a.employee_id == employeeId);
            const lateness = att?.late_minutes || 0;

            document.getElementById('quickDedEmployeeId').value = emp.id;
            document.getElementById('quickDedEmployeeName').textContent = emp.full_name;
            document.getElementById('quickDedRole').textContent = `${emp.job_title?.title_name || 'موظف'} - ${emp.branch?.name || ''}`;
            document.getElementById('quickDedAvatarText').textContent = emp.full_name.charAt(0);

            const latenessInfoEl = document.getElementById('quickDedLatenessInfo');
            if (lateness > 0) {
                latenessInfoEl.innerHTML = `<span class="badge bg-danger-subtle text-danger">متأخر ${esc(lateness)} دقيقة</span>`;
            } else {
                latenessInfoEl.innerHTML = '<span class="badge bg-warning-subtle text-warning">غير حاضر اليوم</span>';
            }

            let defaultAmount = 250;
            if (lateness > 60) defaultAmount = 450;
            else if (lateness > 30) defaultAmount = 300;
            document.getElementById('quickDedAmount').value = defaultAmount;

            quickDeductionModal.show();
        }

        document.getElementById('quickDeductionForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const empId = document.getElementById('quickDedEmployeeId').value;
            const amount = Number(document.getElementById('quickDedAmount').value);
            const reason = document.getElementById('quickDedReason').value;
            const notes = document.getElementById('quickDedNotes').value;

            fetch(ROUTES.deductions, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    employee_id: empId,
                    amount: amount,
                    reason: notes ? `${reason} - ${notes}` : reason,
                    deduction_date: selectedDate()
                })
            })
            .then(async res => {
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'تعذر اعتماد الجزاء.');
                return data;
            })
            .then(() => {
                quickDeductionModal.hide();
                Swal.fire({
                    icon: 'success',
                    title: 'تم اعتماد الخصم فورياً!',
                    html: `تم اعتماد خصم مبلغ <strong>${esc(amount)} ج.م</strong> وإدراجه في كشف مسير الرواتب.`,
                    confirmButtonText: 'ممتاز'
                });
                fetchAttendance();
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: err.message
                });
            });
        });

        // Mark Absent (Admin Action)
        function markAbsent(employeeId, employeeName) {
            const date = selectedDate();

            Swal.fire({
                title: `تسجيل غياب: ${esc(employeeName)}`, // SweetAlert2 renders title as HTML
                text: 'هل أنت متأكد من تسجيل الموظف كغائب لليوم وإلغاء أي بصمة مسجلة؟',
                icon: 'warning',
                input: 'text',
                inputPlaceholder: 'سبب تسجيل الغياب (اختياري)...',
                showCancelButton: true,
                confirmButtonText: 'نعم، تسجيل غياب',
                cancelButtonText: 'تراجع',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                showLoaderOnConfirm: true,
                preConfirm: (reason) => {
                    return fetch(ROUTES.markAbsent, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            employee_id: employeeId,
                            date: date,
                            reason: reason
                        })
                    })
                    .then(async res => {
                        const data = await res.json();
                        if (!res.ok) throw new Error(data.message || 'تعذر تسجيل الغياب.');
                        return data;
                    })
                    .catch(err => {
                        Swal.showValidationMessage(err.message);
                    });
                }
            }).then(result => {
                if (result.isConfirmed && result.value) {
                    Swal.fire({
                        icon: 'success',
                        title: 'تم تسجيل الغياب',
                        text: `تم تسجيل الموظف (${employeeName}) كغائب لليوم بنجاح.`,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    fetchAttendance();
                }
            });
        }

        // Actions used by the markup (filters, stats cards, deduction presets) and the table rows.
        Admin.actions.registerAll({
            'hr.attendance.tab': (el) => setQuickTabFilter(el.dataset.tab),
            'hr.attendance.clear-search': () => clearSearch(),
            'hr.attendance.preset': (el) => { document.getElementById('quickDedAmount').value = el.dataset.amount; },
            'hr.attendance.punch': (el) => punchDirect(el.dataset.employeeId, el.dataset.punchType),
            'hr.attendance.absent': (el) => markAbsent(el.dataset.employeeId, el.dataset.employeeName),
            'hr.attendance.deduct': (el) => openDeductionModal(el.dataset.employeeId),
        });

        // Date filter change, live search
        document.getElementById('attendanceDateFilter')?.addEventListener('change', fetchAttendance);
        document.getElementById('searchEmployeeInput')?.addEventListener('input', renderTable);
        document.getElementById('searchEmployeeInput')?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                renderTable();
            }
        });

        // Read URL query parameters on load (e.g. ?search=EMP-0101&status=late)
        const urlParams = new URLSearchParams(window.location.search);
        const searchParam = urlParams.get('search');
        const statusParam = urlParams.get('status');

        if (searchParam) {
            const searchEl = document.getElementById('searchEmployeeInput');
            if (searchEl) searchEl.value = searchParam;
        }
        if (statusParam && ['all', 'present', 'late', 'absent'].includes(statusParam)) {
            currentFilterTab = statusParam;
            document.querySelectorAll('.filter-tab-pill').forEach(btn => btn.classList.remove('active'));
            const tabEl = document.getElementById(`tab-${statusParam}`);
            if (tabEl) tabEl.classList.add('active');
        }

        // Initial Load
        fetchAttendance();
    });
})();
