/**
 * Al-Husseini Car Battery Center - HR & Staff Management Store (v4)
 * مخصص لمركز الحسيني لبيع وصيانة بطاريات السيارات
 */

(function () {
    'use strict';

    const STORAGE_KEYS = {
        EMPLOYEES: 'alhusseini_battery_employees_v4',
        ATTENDANCE: 'alhusseini_battery_attendance_v4',
        DEDUCTIONS: 'alhusseini_battery_deductions_v4',
        NOTIFICATIONS: 'alhusseini_battery_notifications_v4',
        INITIALIZED: 'alhusseini_battery_initialized_v4'
    };

    function resolveAsset(path) {
        if (!path) return '';
        if (path.startsWith('http') || path.startsWith('data:')) return path;
        const cleanPath = path.replace(/^\/+/, '');
        const meta = document.querySelector('meta[name="asset-url"]');
        const base = meta ? meta.getAttribute('content') : '';
        return base ? `${base.replace(/\/+$/, '')}/${cleanPath}` : `/${cleanPath}`;
    }

    // Realistic Car Battery Sales & Service Center Staff
    const SEED_EMPLOYEES = [
        {
            id: 'BAT-101',
            name: 'الحاج محمود الحسيني',
            role: 'مدير عام المركز والفرع',
            department: 'الإدارة والإشراف',
            email: 'm.husseini@alhusseini-batteries.com',
            phone: '+20 100 111 2233',
            avatar: '/assets/images/users/avatar-1.jpg',
            startTime: '08:30',
            endTime: '17:30',
            baseSalary: 28000,
            allowances: 4000,
            status: 'active',
            joinDate: '2020-01-10'
        },
        {
            id: 'BAT-102',
            name: 'إبراهيم حسن عبد العال',
            role: 'كبير بائعي بطاريات (مسؤول صالة البيع)',
            department: 'المبيعات والمعرض',
            email: 'ibrahim.sales@alhusseini-batteries.com',
            phone: '+20 111 222 3344',
            avatar: '/assets/images/users/avatar-2.jpg',
            startTime: '09:00',
            endTime: '18:00',
            baseSalary: 14000,
            allowances: 3500, // عمولات مبيعات
            status: 'active',
            joinDate: '2022-03-15'
        },
        {
            id: 'BAT-103',
            name: 'سيد ربيع الصعيدي',
            role: 'فني أول صيانة وإصلاح بطاريات وشواحن',
            department: 'ورشة الصيانة والشحن',
            email: 'sayed.repair@alhusseini-batteries.com',
            phone: '+20 122 333 4455',
            avatar: '/assets/images/users/avatar-3.jpg',
            startTime: '09:00',
            endTime: '18:00',
            baseSalary: 13500,
            allowances: 2500,
            status: 'active',
            joinDate: '2021-06-20'
        },
        {
            id: 'BAT-104',
            name: 'أحمد جابر كمال (حمادة كهربا)',
            role: 'كهربائي سيارات وفني فحص دينامو وتركيب',
            department: 'فنيو التركيب والكهرباء',
            email: 'ahmed.electric@alhusseini-batteries.com',
            phone: '+20 109 444 5566',
            avatar: '/assets/images/users/avatar-4.jpg',
            startTime: '08:30',
            endTime: '17:30',
            baseSalary: 12000,
            allowances: 2000,
            status: 'active',
            joinDate: '2022-11-01'
        },
        {
            id: 'BAT-105',
            name: 'مصطفى عادل الشافعي',
            role: 'بائع بطاريات تجزئة وجملة',
            department: 'المبيعات والمعرض',
            email: 'moustafa.sales@alhusseini-batteries.com',
            phone: '+20 106 555 6677',
            avatar: '/assets/images/users/avatar-5.jpg',
            startTime: '09:00',
            endTime: '18:00',
            baseSalary: 11000,
            allowances: 2800,
            status: 'active',
            joinDate: '2023-02-10'
        },
        {
            id: 'BAT-106',
            name: 'وليد صبحي الجزار',
            role: 'فني خدمة سريعة وإنقاذ بطاريات متنقل (طوارئ طريق)',
            department: 'خدمة الطوارئ والإنقاذ المتنقل',
            email: 'walid.rescue@alhusseini-batteries.com',
            phone: '+20 114 666 7788',
            avatar: '/assets/images/users/avatar-6.jpg',
            startTime: '10:00',
            endTime: '19:00',
            baseSalary: 12500,
            allowances: 3000, // بدل انتقالات ومخاطر طريق
            status: 'active',
            joinDate: '2023-07-15'
        },
        {
            id: 'BAT-107',
            name: 'عصام بدران مهران',
            role: 'أمين مخزن البطاريات الجديدة والكهنة (الخردة المسترجعة)',
            department: 'المخازن وسلاسل الإمداد',
            email: 'essam.stock@alhusseini-batteries.com',
            phone: '+20 120 777 8899',
            avatar: '/assets/images/users/avatar-7.jpg',
            startTime: '08:30',
            endTime: '17:00',
            baseSalary: 11500,
            allowances: 1500,
            status: 'active',
            joinDate: '2022-08-01'
        },
        {
            id: 'BAT-108',
            name: 'طارق عبد الباسط جودة',
            role: 'فني شحن بطاريات وتعبئة مياه نار ومحاليل',
            department: 'ورشة الصيانة والشحن',
            email: 'tarek.charging@alhusseini-batteries.com',
            phone: '+20 101 888 9900',
            avatar: '/assets/images/users/avatar-8.jpg',
            startTime: '09:00',
            endTime: '18:00',
            baseSalary: 10500,
            allowances: 1500,
            status: 'on_leave',
            joinDate: '2024-01-12'
        }
    ];

    function formatDate(d) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function getTodayString() {
        return formatDate(new Date());
    }

    // Generator for realistic multi-day records in current month
    function generateHistoricalData() {
        const today = new Date();
        const year = today.getFullYear();
        const month = today.getMonth(); // 0-indexed
        const dayOfMonth = today.getDate();

        const attendanceList = [];
        const deductionsList = [];
        let decCounter = 1;

        // Loop through each day of the current month up to today
        for (let d = 1; d <= dayOfMonth; d++) {
            const dateObj = new Date(year, month, d);
            const dayOfWeek = dateObj.getDay(); // 5 = Friday
            if (dayOfWeek === 5) continue; // Skip Fridays (الجمعة عطلة أسبوعية بالمركز)

            const dateStr = formatDate(dateObj);
            const isToday = (d === dayOfMonth);

            SEED_EMPLOYEES.forEach(emp => {
                let status = 'on_time';
                let punchIn = null;
                let punchOut = null;
                let lateness = 0;

                const [startH, startM] = (emp.startTime || '09:00').split(':').map(Number);
                const [endH, endM] = (emp.endTime || '18:00').split(':').map(Number);

                if (emp.id === 'BAT-108' && d > 12) {
                    // Tarek on leave during latter half
                    status = 'on_leave';
                } else if (emp.id === 'BAT-104' && (d === 4 || d === 15)) {
                    // Hamada absent without excuse
                    status = 'absent';
                } else if (emp.id === 'BAT-106' && d === 9) {
                    // Walid absent
                    status = 'absent';
                } else if ((emp.id === 'BAT-102' && (d === 3 || d === 12 || d === 17 || isToday)) ||
                           (emp.id === 'BAT-104' && (d === 7 || d === 16 || isToday)) ||
                           (emp.id === 'BAT-106' && d === 11)) {
                    // Late records
                    status = 'late';
                    let lateMins = 25;
                    if (emp.id === 'BAT-104') lateMins = (d === 16) ? 45 : 72;
                    if (emp.id === 'BAT-102') lateMins = (d === 3) ? 30 : (d === 17 ? 40 : 38);
                    if (emp.id === 'BAT-106') lateMins = 35;

                    lateness = lateMins;
                    const totalMins = (startH * 60) + startM + lateMins;
                    const h = Math.floor(totalMins / 60);
                    const m = totalMins % 60;
                    punchIn = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
                    punchOut = isToday ? null : `${String(endH).padStart(2, '0')}:${String(endM + 15).padStart(2, '0')}`;
                } else {
                    // On time records
                    status = 'on_time';
                    const earlyMins = Math.floor(Math.random() * 12) + 3; // 3-15 mins early
                    const totalMins = (startH * 60) + startM - earlyMins;
                    const h = Math.floor(totalMins / 60);
                    const m = totalMins % 60;
                    punchIn = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
                    punchOut = isToday ? null : `${String(endH).padStart(2, '0')}:${String(endM).padStart(2, '0')}`;
                }

                attendanceList.push({
                    id: `ATT-${dateStr}-${emp.id}`,
                    employeeId: emp.id,
                    date: dateStr,
                    punchIn: punchIn,
                    punchOut: punchOut,
                    status: status,
                    latenessMinutes: lateness
                });
            });

            // Specific Battery Center Deductions across the month
            if (d === 3) {
                deductionsList.push({
                    id: `DED-HIST-${decCounter++}`,
                    employeeId: 'BAT-104',
                    amount: 250,
                    reason: 'إهمال في ربط أقطاب بطارية سيارة عميل مما أدى لشرارة كهربائية',
                    date: dateStr,
                    managerNotes: 'تم التنبيه على ضرورة فحص الجهد وعزل الأقطاب بالشحم العازل.',
                    decisionNo: `BAT-DEC-${year}/00${decCounter}`
                });
            } else if (d === 7) {
                deductionsList.push({
                    id: `DED-HIST-${decCounter++}`,
                    employeeId: 'BAT-105',
                    amount: 200,
                    reason: 'تأخير تسليم فاتورة وضمان بطارية فارتا 70 أمبير لأحد العملاء',
                    date: dateStr,
                    managerNotes: 'شكوى من العميل لعدم استلام كارت الضمان المعتمد في حينه.',
                    decisionNo: `BAT-DEC-${year}/00${decCounter}`
                });
            } else if (d === 11) {
                deductionsList.push({
                    id: `DED-HIST-${decCounter++}`,
                    employeeId: 'BAT-106',
                    amount: 350,
                    reason: 'تأخر في الاستجابة لبلاغ طوارئ سيارة معطلة بطريق السويس',
                    date: dateStr,
                    managerNotes: 'تأخر تحرك ونش الطوارئ نصف ساعة مما أثار استياء العميل.',
                    decisionNo: `BAT-DEC-${year}/00${decCounter}`
                });
            } else if (d === 14) {
                deductionsList.push({
                    id: `DED-HIST-${decCounter++}`,
                    employeeId: 'BAT-103',
                    amount: 300,
                    reason: 'ترك شاحن البطاريات السريع يعمل دون معايرة أمبير الشحن ليلاً',
                    date: dateStr,
                    managerNotes: 'مخالفة معايير الأمان والسلامة المهنية بورشة شحن البطاريات.',
                    decisionNo: `BAT-DEC-${year}/00${decCounter}`
                });
            } else if (d === 17) {
                deductionsList.push({
                    id: `DED-HIST-${decCounter++}`,
                    employeeId: 'BAT-102',
                    amount: 250,
                    reason: 'تأخير 40 دقيقة عن موعد فتح المعرض صباحاً',
                    date: dateStr,
                    managerNotes: 'تكرار التأخير الصباحي عن فتح صالة بيع البطاريات.',
                    decisionNo: `BAT-DEC-${year}/00${decCounter}`
                });
            } else if (isToday) {
                // Today deductions
                deductionsList.push({
                    id: `DED-HIST-${decCounter++}`,
                    employeeId: 'BAT-102',
                    amount: 300,
                    reason: 'تأخير في فتح صالة المعرض واستقبال العملاء في موعدها',
                    date: dateStr,
                    managerNotes: 'تأخر 38 دقيقة مما تسبب في انتظار عملاء يريدون تركيب بطاريات صباحاً.',
                    decisionNo: `BAT-DEC-${year}/015`
                });
                deductionsList.push({
                    id: `DED-HIST-${decCounter++}`,
                    employeeId: 'BAT-104',
                    amount: 450,
                    reason: 'تأخير متكرر عن موعد الورشة + إهمال في فحص كابلات الدينامو',
                    date: dateStr,
                    managerNotes: 'تأخر 72 دقيقة عن بدء فحص سيارات العملاء بالورشة.',
                    decisionNo: `BAT-DEC-${year}/016`
                });
            }
        }

        return { attendanceList, deductionsList };
    }

    function initStore() {
        if (!localStorage.getItem(STORAGE_KEYS.INITIALIZED)) {
            localStorage.setItem(STORAGE_KEYS.EMPLOYEES, JSON.stringify(SEED_EMPLOYEES));

            const { attendanceList, deductionsList } = generateHistoricalData();
            localStorage.setItem(STORAGE_KEYS.ATTENDANCE, JSON.stringify(attendanceList));
            localStorage.setItem(STORAGE_KEYS.DEDUCTIONS, JSON.stringify(deductionsList));

            const today = getTodayString();
            const seedNotifications = [
                {
                    id: 'NOTIF-1',
                    type: 'lateness',
                    title: '⚠️ تأخير حضور - صالة بيع البطاريات',
                    message: 'سجل البائع [إبراهيم حسن عبد العال] حضوراً متأخراً بـ 38 دقيقة عن موعد فتح المعرض (09:00 ص).',
                    time: '09:38 ص',
                    date: today,
                    read: false,
                    employeeId: 'BAT-102'
                },
                {
                    id: 'NOTIF-2',
                    type: 'lateness',
                    title: '⚠️ تأخير حضور - ورشة كهرباء وتركيب البطاريات',
                    message: 'سجل فني الكهرباء [أحمد جابر (حمادة كهربا)] حضوراً متأخراً بـ 72 دقيقة عن موعد الورشة (08:30 ص).',
                    time: '09:42 ص',
                    date: today,
                    read: false,
                    employeeId: 'BAT-104'
                },
                {
                    id: 'NOTIF-3',
                    type: 'deduction',
                    title: '📋 قرار خصم إداري - تأخير المعرض',
                    message: 'اعتمد المدير خصم 300 ج.م على البائع إبراهيم حسن لتأخير فتح صالة العرض للعملاء.',
                    time: '10:15 ص',
                    date: today,
                    read: true,
                    employeeId: 'BAT-102'
                }
            ];
            localStorage.setItem(STORAGE_KEYS.NOTIFICATIONS, JSON.stringify(seedNotifications));

            localStorage.setItem(STORAGE_KEYS.INITIALIZED, 'true');
        }
    }

    initStore();

    function notifyChange() {
        window.dispatchEvent(new CustomEvent('alhusseini-hr-updated'));
    }

    window.AlHusseiniHR = {
        // Departments list specific to battery center
        getDepartments: function() {
            return [
                'المبيعات والمعرض',
                'ورشة الصيانة والشحن',
                'فنيو التركيب والكهرباء',
                'خدمة الطوارئ والإنقاذ المتنقل',
                'المخازن وسلاسل الإمداد',
                'الإدارة والإشراف'
            ];
        },

        // --- EMPLOYEES ---
        getEmployees: function () {
            try {
                const list = JSON.parse(localStorage.getItem(STORAGE_KEYS.EMPLOYEES)) || [];
                return list.map(emp => ({
                    ...emp,
                    avatar: resolveAsset(emp.avatar)
                }));
            } catch (e) {
                return [];
            }
        },

        getEmployeeById: function (id) {
            const list = this.getEmployees();
            return list.find(emp => emp.id === id) || null;
        },

        saveEmployee: function (employeeData) {
            let list = this.getEmployees();
            if (employeeData.id) {
                const idx = list.findIndex(e => e.id === employeeData.id);
                if (idx !== -1) {
                    list[idx] = { ...list[idx], ...employeeData };
                }
            } else {
                const newIdNum = 100 + list.length + 1;
                const newEmployee = {
                    id: `BAT-${newIdNum}`,
                    avatar: `/assets/images/users/avatar-${(list.length % 8) + 1}.jpg`,
                    joinDate: getTodayString(),
                    status: 'active',
                    ...employeeData
                };
                list.unshift(newEmployee);
            }
            localStorage.setItem(STORAGE_KEYS.EMPLOYEES, JSON.stringify(list));
            notifyChange();
            return true;
        },

        deleteEmployee: function (id) {
            let list = this.getEmployees();
            list = list.filter(e => e.id !== id);
            localStorage.setItem(STORAGE_KEYS.EMPLOYEES, JSON.stringify(list));
            notifyChange();
            return true;
        },

        // --- ATTENDANCE & BIOMETRICS ---
        getAttendance: function (date = null) {
            try {
                const list = JSON.parse(localStorage.getItem(STORAGE_KEYS.ATTENDANCE)) || [];
                const targetDate = date || getTodayString();
                return list.filter(att => att.date === targetDate);
            } catch (e) {
                return [];
            }
        },

        getAllAttendance: function () {
            try {
                return JSON.parse(localStorage.getItem(STORAGE_KEYS.ATTENDANCE)) || [];
            } catch (e) {
                return [];
            }
        },

        recordPunch: function (employeeId, type = 'in', customTimeStr = null) {
            const employee = this.getEmployeeById(employeeId);
            if (!employee) return { success: false, error: 'الموظف غير موجود' };

            const today = getTodayString();
            let allAtt = this.getAllAttendance();
            let record = allAtt.find(a => a.employeeId === employeeId && a.date === today);

            let now = new Date();
            let punchTime = customTimeStr || `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;

            if (type === 'in') {
                const [startH, startM] = (employee.startTime || '09:00').split(':').map(Number);
                const [punchH, punchM] = punchTime.split(':').map(Number);

                const officialMinutes = (startH * 60) + startM;
                const punchMinutes = (punchH * 60) + punchM;
                const diff = punchMinutes - officialMinutes;

                // 15 mins grace period
                const isLate = diff > 15;
                const latenessMinutes = isLate ? diff : 0;
                const status = isLate ? 'late' : 'on_time';

                if (record) {
                    record.punchIn = punchTime;
                    record.status = status;
                    record.latenessMinutes = latenessMinutes;
                } else {
                    record = {
                        id: `ATT-${Date.now()}`,
                        employeeId: employeeId,
                        date: today,
                        punchIn: punchTime,
                        punchOut: null,
                        status: status,
                        latenessMinutes: latenessMinutes
                    };
                    allAtt.unshift(record);
                }

                if (isLate) {
                    this.addNotification({
                        type: 'lateness',
                        title: `⚠️ تأخير في مركز البطاريات (${employee.department})`,
                        message: `سجل [${employee.name} - ${employee.role}] حضوراً متأخراً بـ ${latenessMinutes} دقيقة عن موعد فتح الوردية (${employee.startTime}).`,
                        employeeId: employeeId
                    });
                }

                localStorage.setItem(STORAGE_KEYS.ATTENDANCE, JSON.stringify(allAtt));
                notifyChange();

                return {
                    success: true,
                    type: 'in',
                    time: punchTime,
                    isLate: isLate,
                    latenessMinutes: latenessMinutes,
                    employee: employee
                };
            } else {
                // Punch out
                if (!record) {
                    record = {
                        id: `ATT-${Date.now()}`,
                        employeeId: employeeId,
                        date: today,
                        punchIn: null,
                        punchOut: punchTime,
                        status: 'on_time',
                        latenessMinutes: 0
                    };
                    allAtt.unshift(record);
                } else {
                    record.punchOut = punchTime;
                }

                localStorage.setItem(STORAGE_KEYS.ATTENDANCE, JSON.stringify(allAtt));
                notifyChange();

                return {
                    success: true,
                    type: 'out',
                    time: punchTime,
                    employee: employee
                };
            }
        },

        // --- DEDUCTIONS ---
        getDeductions: function () {
            try {
                return JSON.parse(localStorage.getItem(STORAGE_KEYS.DEDUCTIONS)) || [];
            } catch (e) {
                return [];
            }
        },

        addDeduction: function (deductionData) {
            const employee = this.getEmployeeById(deductionData.employeeId);
            if (!employee) return { success: false, error: 'الموظف غير موجود' };

            let list = this.getDeductions();
            const dateStr = deductionData.date || getTodayString();
            const yearStr = dateStr.split('-')[0] || new Date().getFullYear();
            const newDecNo = `BAT-DEC-${yearStr}/${String(list.length + 1).padStart(3, '0')}`;

            const newRecord = {
                id: `DED-${Date.now()}`,
                date: dateStr,
                decisionNo: newDecNo,
                ...deductionData
            };
            list.unshift(newRecord);
            localStorage.setItem(STORAGE_KEYS.DEDUCTIONS, JSON.stringify(list));

            this.addNotification({
                type: 'deduction',
                title: '📋 قرار خصم إداري لمركز البطاريات',
                message: `اعتمدت الإدارة خصماً بقيمة ${Number(deductionData.amount).toLocaleString('ar-EG')} ج.م على الموظف [${employee.name}]. السبب: ${deductionData.reason}`,
                employeeId: employee.id
            });

            notifyChange();
            return { success: true, record: newRecord };
        },

        deleteDeduction: function (id) {
            let list = this.getDeductions();
            list = list.filter(d => d.id !== id);
            localStorage.setItem(STORAGE_KEYS.DEDUCTIONS, JSON.stringify(list));
            notifyChange();
            return true;
        },

        // --- NOTIFICATIONS ---
        getNotifications: function () {
            try {
                return JSON.parse(localStorage.getItem(STORAGE_KEYS.NOTIFICATIONS)) || [];
            } catch (e) {
                return [];
            }
        },

        addNotification: function (notif) {
            let list = this.getNotifications();
            const now = new Date();
            const timeStr = now.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });

            const newNotif = {
                id: `NOTIF-${Date.now()}`,
                date: getTodayString(),
                time: timeStr,
                read: false,
                ...notif
            };
            list.unshift(newNotif);
            localStorage.setItem(STORAGE_KEYS.NOTIFICATIONS, JSON.stringify(list));
            notifyChange();

            if (typeof window.showHrToast === 'function') {
                window.showHrToast(newNotif.title, newNotif.message, newNotif.type);
            }
        },

        markAllAsRead: function () {
            let list = this.getNotifications();
            list.forEach(n => n.read = true);
            localStorage.setItem(STORAGE_KEYS.NOTIFICATIONS, JSON.stringify(list));
            notifyChange();
        },

        clearNotifications: function () {
            localStorage.setItem(STORAGE_KEYS.NOTIFICATIONS, JSON.stringify([]));
            notifyChange();
        },

        // ==========================================
        // --- REPORTS & ANALYTICS HELPER ENGINE ---
        // ==========================================

        /**
         * Get Daily Report for specific date
         */
        getDailyReport: function (targetDate = null) {
            const date = targetDate || getTodayString();
            const employees = this.getEmployees();
            const allAtt = this.getAllAttendance().filter(a => a.date === date);
            const allDeds = this.getDeductions().filter(d => d.date === date);

            const staffReport = employees.map(emp => {
                const att = allAtt.find(a => a.employeeId === emp.id);
                const empDeds = allDeds.filter(d => d.employeeId === emp.id);
                const totalDed = empDeds.reduce((acc, d) => acc + Number(d.amount || 0), 0);

                let status = 'absent';
                let punchIn = '-';
                let punchOut = '-';
                let lateness = 0;

                if (att) {
                    status = att.status || (att.punchIn ? 'on_time' : 'absent');
                    punchIn = att.punchIn || '-';
                    punchOut = att.punchOut || '-';
                    lateness = att.latenessMinutes || 0;
                } else if (emp.status === 'on_leave') {
                    status = 'on_leave';
                }

                return {
                    employee: emp,
                    date: date,
                    status: status,
                    punchIn: punchIn,
                    punchOut: punchOut,
                    latenessMinutes: lateness,
                    deductions: empDeds,
                    totalDeductions: totalDed
                };
            });

            const presentCount = staffReport.filter(r => r.status === 'on_time' || r.status === 'late').length;
            const lateCount = staffReport.filter(r => r.status === 'late').length;
            const onTimeCount = staffReport.filter(r => r.status === 'on_time').length;
            const absentCount = staffReport.filter(r => r.status === 'absent').length;
            const leaveCount = staffReport.filter(r => r.status === 'on_leave').length;
            const totalLateMins = staffReport.reduce((acc, r) => acc + r.latenessMinutes, 0);
            const totalDedsAmount = allDeds.reduce((acc, d) => acc + Number(d.amount || 0), 0);

            const attendanceRate = employees.length > 0 ? Math.round((presentCount / employees.length) * 100) : 0;
            const punctualityRate = presentCount > 0 ? Math.round((onTimeCount / presentCount) * 100) : 0;

            return {
                date: date,
                employees: staffReport,
                deductionsList: allDeds,
                summary: {
                    totalStaff: employees.length,
                    presentCount: presentCount,
                    onTimeCount: onTimeCount,
                    lateCount: lateCount,
                    absentCount: absentCount,
                    leaveCount: leaveCount,
                    totalLateMins: totalLateMins,
                    totalDeductionsAmount: totalDedsAmount,
                    deductionsCount: allDeds.length,
                    attendanceRate: attendanceRate,
                    punctualityRate: punctualityRate
                }
            };
        },

        /**
         * Get Report for a Date Range (or whole Month)
         */
        getRangeReport: function (startDate, endDate) {
            const employees = this.getEmployees();
            const allAtt = this.getAllAttendance().filter(a => a.date >= startDate && a.date <= endDate);
            const allDeds = this.getDeductions().filter(d => d.date >= startDate && d.date <= endDate);

            // Distinct dates recorded in range
            const distinctDates = [...new Set(allAtt.map(a => a.date))].sort();

            const staffAggregated = employees.map(emp => {
                const empAttList = allAtt.filter(a => a.employeeId === emp.id);
                const empDedsList = allDeds.filter(d => d.employeeId === emp.id);

                const presentDays = empAttList.filter(a => a.status === 'on_time' || a.status === 'late').length;
                const onTimeDays = empAttList.filter(a => a.status === 'on_time').length;
                const lateDays = empAttList.filter(a => a.status === 'late').length;
                const absentDays = empAttList.filter(a => a.status === 'absent').length;
                const leaveDays = empAttList.filter(a => a.status === 'on_leave').length;

                const totalLateMins = empAttList.reduce((acc, a) => acc + (a.latenessMinutes || 0), 0);
                const totalDeductionsAmount = empDedsList.reduce((acc, d) => acc + Number(d.amount || 0), 0);

                const recordedDays = distinctDates.length || 1;
                const attRate = Math.round((presentDays / recordedDays) * 100);

                return {
                    employee: emp,
                    totalWorkDays: recordedDays,
                    presentDays: presentDays,
                    onTimeDays: onTimeDays,
                    lateDays: lateDays,
                    absentDays: absentDays,
                    leaveDays: leaveDays,
                    totalLateMinutes: totalLateMins,
                    deductionsCount: empDedsList.length,
                    totalDeductionsAmount: totalDeductionsAmount,
                    attendanceRate: attRate,
                    deductions: empDedsList,
                    attendanceDetails: empAttList
                };
            });

            // Overall summary
            const totalPresents = staffAggregated.reduce((a, b) => a + b.presentDays, 0);
            const totalOnTimes = staffAggregated.reduce((a, b) => a + b.onTimeDays, 0);
            const totalLates = staffAggregated.reduce((a, b) => a + b.lateDays, 0);
            const totalAbsents = staffAggregated.reduce((a, b) => a + b.absentDays, 0);
            const totalLateMins = staffAggregated.reduce((a, b) => a + b.totalLateMinutes, 0);
            const totalDeds = staffAggregated.reduce((a, b) => a + b.totalDeductionsAmount, 0);

            // Daily chart timeline series
            const dailySeries = distinctDates.map(dStr => {
                const dayAtt = allAtt.filter(a => a.date === dStr);
                const dayPresent = dayAtt.filter(a => a.status === 'on_time' || a.status === 'late').length;
                const dayLate = dayAtt.filter(a => a.status === 'late').length;
                const dayAbsent = dayAtt.filter(a => a.status === 'absent').length;
                const dayLateMins = dayAtt.reduce((sum, a) => sum + (a.latenessMinutes || 0), 0);

                return {
                    date: dStr,
                    present: dayPresent,
                    late: dayLate,
                    absent: dayAbsent,
                    lateMinutes: dayLateMins
                };
            });

            return {
                startDate: startDate,
                endDate: endDate,
                distinctDatesCount: distinctDates.length,
                distinctDates: distinctDates,
                staffReport: staffAggregated,
                deductionsList: allDeds,
                dailySeries: dailySeries,
                summary: {
                    totalStaff: employees.length,
                    totalPresents: totalPresents,
                    totalOnTimes: totalOnTimes,
                    totalLates: totalLates,
                    totalAbsents: totalAbsents,
                    totalLateMins: totalLateMins,
                    totalDeductionsAmount: totalDeds,
                    deductionsCount: allDeds.length,
                    avgAttendanceRate: staffAggregated.length > 0 ? Math.round(staffAggregated.reduce((s, e) => s + e.attendanceRate, 0) / staffAggregated.length) : 0
                }
            };
        },

        /**
         * Get Monthly Report (convenience wrapper)
         */
        getMonthlyReport: function (year, month) {
            const y = year || new Date().getFullYear();
            const m = month || (new Date().getMonth() + 1);
            const mPad = String(m).padStart(2, '0');
            const lastDay = new Date(y, m, 0).getDate();

            const startDate = `${y}-${mPad}-01`;
            const endDate = `${y}-${mPad}-${String(lastDay).padStart(2, '0')}`;

            return this.getRangeReport(startDate, endDate);
        },

        /**
         * Export generated report data to Arabic UTF-8 CSV with BOM for Microsoft Excel
         */
        exportToCSV: function (filename, headers, rows) {
            let csvContent = '\uFEFF'; // UTF-8 BOM for Arabic text in Excel
            csvContent += headers.map(h => `"${h.replace(/"/g, '""')}"`).join(',') + '\r\n';

            rows.forEach(row => {
                csvContent += row.map(cell => {
                    const str = (cell === null || cell === undefined) ? '' : String(cell);
                    return `"${str.replace(/"/g, '""')}"`;
                }).join(',') + '\r\n';
            });

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', `${filename}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },

        formatCurrency: function (amount) {
            return `${Number(amount).toLocaleString('ar-EG')} ج.م`;
        }
    };
})();
