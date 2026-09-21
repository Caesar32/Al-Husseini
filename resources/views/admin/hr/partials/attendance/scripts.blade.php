<script>
'use strict';

document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const quickDeductionModal = new bootstrap.Modal(document.getElementById('quickDeductionModal'));

    let currentFilterTab = 'all'; // 'all' | 'present' | 'late' | 'absent'
    let cachedAttendances = [];
    let allEmployees = @json($employees);

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
    setInterval(updateShopClock, 1000);
    updateShopClock();

    // 2. Fetch Attendance Data from Backend
    function fetchAttendance() {
        const date = document.getElementById('attendanceDateFilter').value || new Date().toISOString().split('T')[0];

        fetch(`/admin/hr/attendance?date=${date}`, {
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

    // 3. Render Table Rows
    window.renderTable = function() {
        const searchVal = (document.getElementById('searchEmployeeInput')?.value || '').trim().toLowerCase();
        const tbody = document.getElementById('attendanceTableBody');
        if (!tbody) return;

        let rowsHtml = '';

        allEmployees.forEach(emp => {
            const att = cachedAttendances.find(a => a.employee_id === emp.id);

            let status = att ? att.status : 'absent';
            let punchIn = att?.check_in_time ? att.check_in_time.substring(11, 16) : '-';
            let punchOut = att?.check_out_time ? att.check_out_time.substring(11, 16) : '-';
            let lateness = att?.lateness_minutes || 0;

            // Status Filter Tab Check
            if (currentFilterTab !== 'all') {
                if (currentFilterTab === 'present' && status !== 'present') return;
                if (currentFilterTab === 'late' && status !== 'late') return;
                if (currentFilterTab === 'absent' && status !== 'absent') return;
            }

            // Search Filter
            if (searchVal) {
                const matchName = (emp.full_name || '').toLowerCase().includes(searchVal);
                const matchCode = (emp.employee_code || '').toLowerCase().includes(searchVal);
                const matchRole = (emp.job_title?.title_name || '').toLowerCase().includes(searchVal);
                if (!matchName && !matchCode && !matchRole) return;
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
                        <button type="button" class="btn btn-sm btn-outline-primary btn-punch-action" onclick="punchDirect('${emp.id}', 'check_out')">
                            <i class="ri-logout-box-r-line"></i> تسجيل انصراف الآن
                        </button>
                    `;
                } else {
                    directActionBtn = `<span class="badge bg-light text-muted border px-2 py-1 fs-12"><i class="ri-check-double-line text-success me-1"></i>أتم الوردية وانصرف</span>`;
                }
            } else if (status === 'late') {
                statusBadge = '<span class="badge bg-warning text-dark fs-12 px-3 py-1 fw-bold shadow-sm"><i class="ri-alarm-warning-line me-1"></i>متأخر</span>';
                latenessBadge = `<span class="badge bg-danger text-white fs-12 fw-bold font-monospace shadow-sm">+${lateness} دقيقة تأخير</span>`;

                directActionBtn = `
                    <div class="d-inline-flex gap-1">
                        ${punchOut === '-' ? `
                            <button type="button" class="btn btn-sm btn-outline-primary btn-punch-action" onclick="punchDirect('${emp.id}', 'check_out')" title="تسجيل انصراف">
                                <i class="ri-logout-box-r-line"></i> انصراف
                            </button>
                        ` : ''}
                        <button type="button" class="btn btn-sm btn-danger btn-punch-action" onclick="openDeductionModal('${emp.id}')" title="تطبيق خصم فوري على التأخير">
                            <i class="ri-scissors-cut-line"></i> خصم تأخير
                        </button>
                    </div>
                `;
            } else if (emp.status === 'on_leave') {
                statusBadge = '<span class="badge bg-info-subtle text-info fs-12 px-3 py-1 fw-bold"><i class="ri-flight-takeoff-line me-1"></i>إجازة رسمية</span>';
                latenessBadge = '<span class="text-muted fs-11">إجازة معتمدة</span>';
                directActionBtn = `<span class="badge bg-light text-muted border px-2 py-1 fs-12">لا توجد إجراءات</span>`;
            } else {
                statusBadge = '<span class="badge bg-danger-subtle text-danger fs-12 px-3 py-1 fw-bold"><i class="ri-close-circle-line me-1"></i>لم يسجل بصمته</span>';
                latenessBadge = '<span class="text-danger fw-bold fs-11">غير حاضر بالمركز</span>';

                directActionBtn = `
                    <div class="d-inline-flex gap-1">
                        <button type="button" class="btn btn-sm btn-success btn-punch-action" onclick="punchDirect('${emp.id}', 'check_in')">
                            <i class="ri-fingerprint-line"></i> حضر الآن
                        </button>
                        <button type="button" class="btn btn-sm btn-soft-danger btn-punch-action" onclick="openDeductionModal('${emp.id}')" title="خصم غياب">
                            <i class="ri-hand-coin-line"></i> جزاء غياب
                        </button>
                    </div>
                `;
            }

            const punchInDisplay = punchIn !== '-' ? `<span class="fw-bold fs-13 font-monospace text-success">${punchIn}</span>` : '<span class="text-muted fs-12">لم يسجل</span>';
            const punchOutDisplay = punchOut !== '-' ? `<span class="fw-bold fs-13 font-monospace text-primary">${punchOut}</span>` : '<span class="text-muted fs-12">-</span>';

            rowsHtml += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar-xs me-2">
                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle fw-bold">
                                    ${emp.full_name.charAt(0)}
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-0 fs-14 fw-bold text-dark">${emp.full_name}</h6>
                                <span class="badge bg-light text-secondary font-monospace fs-11">${emp.employee_code}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="fw-bold fs-13 text-dark d-block">${emp.job_title?.title_name || 'فني'}</span>
                        <span class="text-muted fs-11"><i class="ri-tools-line me-1"></i>${emp.branch?.name || 'الفرع الرئيسي'}</span>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border fs-12 px-2 py-1">
                            ${emp.shift_start_time ? emp.shift_start_time.substring(0, 5) : '09:00'} - ${emp.shift_end_time ? emp.shift_end_time.substring(0, 5) : '17:00'}
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
    };

    // 4. Quick Tab Filtering
    window.setQuickTabFilter = function(tabName) {
        currentFilterTab = tabName;

        // update tabs
        document.querySelectorAll('.filter-tab-pill').forEach(btn => btn.classList.remove('active'));
        const activeTabEl = document.getElementById(`tab-${tabName}`);
        if (activeTabEl) activeTabEl.classList.add('active');

        // update cards
        document.querySelectorAll('.stat-filter-card').forEach(card => card.classList.remove('active-filter', 'active-filter-warning', 'active-filter-danger'));
        const activeCard = document.getElementById(`card-filter-${tabName}`);
        if (activeCard) {
            if (tabName === 'late') activeCard.classList.add('active-filter-warning');
            else if (tabName === 'absent') activeCard.classList.add('active-filter-danger');
            else activeCard.classList.add('active-filter');
        }

        renderTable();
    };

    window.clearSearch = function() {
        const input = document.getElementById('searchEmployeeInput');
        if (input) {
            input.value = '';
            renderTable();
        }
    };

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
    window.punchDirect = function(employeeId, type) {
        animatePunchSensor();

        const selectedDate = document.getElementById('attendanceDateFilter').value || new Date().toISOString().split('T')[0];
        const customTime = document.getElementById('customSimTime')?.value;
        let punchTimestamp;

        if (customTime) {
            punchTimestamp = `${selectedDate} ${customTime}:00`;
        } else {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            punchTimestamp = `${selectedDate} ${hours}:${minutes}:${seconds}`;
        }

        fetch('/admin/hr/attendance/punch', {
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
            const empName = att?.employee?.full_name || 'الموظف';

            if (type === 'check_in') {
                if (att.status === 'late') {
                    Swal.fire({
                        icon: 'warning',
                        title: '⚠️ تم تسجيل الحضور بتأخير!',
                        html: `
                            <div class="text-start fs-13">
                                <p class="mb-1">الموظف: <strong class="text-dark">${empName}</strong></p>
                                <p class="mb-1">وقت البصمة: <strong class="text-primary font-monospace">${att.check_in_time}</strong></p>
                                <p class="mb-2 text-danger fw-bold fs-14">مدة التأخير: ${att.lateness_minutes} دقيقة</p>
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
                    html: `تم تسجيل انصراف <strong>${empName}</strong> في تمام <strong>${att.check_out_time}</strong>. بالسلامة والتوفيق!`,
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
    };

    // Quick Punch Bar Event Handlers
    document.getElementById('btnQuickIn').onclick = function() {
        const empId = document.getElementById('quickPunchSelect').value;
        if (!empId) {
            Swal.fire('اختر الموظف أولاً', 'يرجى اختيار اسم الموظف من القائمة أولاً لتسجيل بصمة الحضور.', 'warning');
            return;
        }
        punchDirect(empId, 'check_in');
    };

    document.getElementById('btnQuickOut').onclick = function() {
        const empId = document.getElementById('quickPunchSelect').value;
        if (!empId) {
            Swal.fire('اختر الموظف أولاً', 'يرجى اختيار اسم الموظف من القائمة أولاً لتسجيل بصمة الانصراف.', 'warning');
            return;
        }
        punchDirect(empId, 'check_out');
    };

    // 7. Quick Deduction Management
    window.openDeductionModal = function(employeeId) {
        const emp = allEmployees.find(e => e.id == employeeId);
        if (!emp) return;

        const att = cachedAttendances.find(a => a.employee_id == employeeId);
        const lateness = att?.lateness_minutes || 0;

        document.getElementById('quickDedEmployeeId').value = emp.id;
        document.getElementById('quickDedEmployeeName').textContent = emp.full_name;
        document.getElementById('quickDedRole').textContent = `${emp.job_title?.title_name || 'موظف'} - ${emp.branch?.name || ''}`;
        document.getElementById('quickDedAvatarText').textContent = emp.full_name.charAt(0);

        const latenessInfoEl = document.getElementById('quickDedLatenessInfo');
        if (lateness > 0) {
            latenessInfoEl.innerHTML = `<span class="badge bg-danger-subtle text-danger">متأخر ${lateness} دقيقة</span>`;
        } else {
            latenessInfoEl.innerHTML = `<span class="badge bg-warning-subtle text-warning">غير حاضر اليوم</span>`;
        }

        let defaultAmount = 250;
        if (lateness > 60) defaultAmount = 450;
        else if (lateness > 30) defaultAmount = 300;
        document.getElementById('quickDedAmount').value = defaultAmount;

        quickDeductionModal.show();
    };

    window.setPresetAmount = function(amount) {
        document.getElementById('quickDedAmount').value = amount;
    };

    document.getElementById('quickDeductionForm').onsubmit = function(e) {
        e.preventDefault();
        const empId = document.getElementById('quickDedEmployeeId').value;
        const amount = Number(document.getElementById('quickDedAmount').value);
        const reason = document.getElementById('quickDedReason').value;
        const notes = document.getElementById('quickDedNotes').value;
        const date = document.getElementById('attendanceDateFilter').value || new Date().toISOString().split('T')[0];

        fetch('/admin/hr/deductions', {
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
                deduction_date: date
            })
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'تعذر اعتماد الجزاء.');
            return data;
        })
        .then(data => {
            quickDeductionModal.hide();
            Swal.fire({
                icon: 'success',
                title: 'تم اعتماد الخصم فورياً!',
                html: `تم اعتماد خصم مبلغ <strong>${amount} ج.م</strong> وإدراجه في كشف مسير الرواتب.`,
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
    };

    // Date filter change
    document.getElementById('attendanceDateFilter').addEventListener('change', fetchAttendance);
    document.getElementById('searchEmployeeInput').addEventListener('input', renderTable);

    // Initial Load
    fetchAttendance();
});
</script>
