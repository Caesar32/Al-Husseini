@extends('admin.layouts.master')

@section('title', 'شاشة البصمة ومتابعة الحضور اليومي | مركز الحسيني لبطاريات السيارات')

@section('css')
<style>
    /* Clean, intuitive styling tailored for easy shop management */
    .stat-filter-card {
        cursor: pointer;
        transition: all 0.25s ease;
        border-radius: 12px;
        border: 2px solid transparent;
    }
    .stat-filter-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    }
    .stat-filter-card.active-filter {
        border-color: #0ab39c !important;
        background-color: #f0fdf4 !important;
    }
    .stat-filter-card.active-filter-warning {
        border-color: #f7b84b !important;
        background-color: #fffbeb !important;
    }
    .stat-filter-card.active-filter-danger {
        border-color: #f06548 !important;
        background-color: #fef2f2 !important;
    }

    /* Live Shop Clock Card */
    .shop-clock-card {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border-radius: 14px;
        color: #fff;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.25);
    }
    .live-digital-time {
        font-family: 'Courier New', Courier, monospace;
        font-size: 2.2rem;
        font-weight: 800;
        letter-spacing: 2px;
        color: #38bdf8;
        text-shadow: 0 0 15px rgba(56, 189, 248, 0.4);
    }

    /* Action Buttons in Employee Table */
    .btn-punch-action {
        font-weight: 700;
        padding: 6px 14px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s ease;
    }
    .btn-punch-action:hover {
        transform: scale(1.04);
    }

    /* Status Pill Tabs */
    .filter-tab-pill {
        border-radius: 30px;
        padding: 8px 20px;
        font-size: 14px;
        font-weight: 700;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .filter-tab-pill:hover {
        background: #f1f5f9;
        color: #0f172a;
    }
    .filter-tab-pill.active {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    }

    /* Quick Preset Deduction Buttons */
    .quick-amount-preset {
        font-weight: 700;
        border-radius: 8px;
        padding: 8px 12px;
        cursor: pointer;
        transition: all 0.15s;
    }
    .quick-amount-preset:hover {
        background-color: #fee2e2;
        border-color: #ef4444;
        color: #b91c1c;
    }

    /* Pulse effect on fingerprint icon */
    .fingerprint-glow {
        animation: pulse-glow 2s infinite;
    }
    @keyframes pulse-glow {
        0% { box-shadow: 0 0 0 0 rgba(10, 179, 156, 0.5); }
        70% { box-shadow: 0 0 0 12px rgba(10, 179, 156, 0); }
        100% { box-shadow: 0 0 0 0 rgba(10, 179, 156, 0); }
    }
</style>
@endsection

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'الموارد البشرية والورشة', 'title' => 'شاشة متابعة حضور وبصمة العمال والفنيين اليومية'])

    <!-- Top Station: Shop Clock & 1-Click Punch Device -->
    <div class="row mb-4 g-3 align-items-stretch">
        <!-- Live Shop Clock -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shop-clock-card h-100 border-0 mb-0">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-primary text-white fs-11 px-2 py-1"><i class="ri-broadcast-line me-1"></i> مباشر من توقيت القاهرة</span>
                            <span class="text-white-50 fs-12" id="shopLiveDate">اليوم</span>
                        </div>
                        <div class="live-digital-time text-center my-2" id="liveDigitalClock">00:00:00</div>
                        <p class="text-white-50 fs-12 text-center mb-0">
                            مواعيد عمل مركز وبطاريات الحسيني: <strong>09:00 ص حتى 06:00 م</strong>
                        </p>
                    </div>
                    <div class="pt-3 border-top border-secondary mt-3 d-flex justify-content-between text-white fs-12">
                        <span><i class="ri-store-2-line me-1 text-info"></i> فرع المركز: الرئيسي</span>
                        <span><i class="ri-shield-check-line me-1 text-success"></i> البصمة: متصلة وجاهزة</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 1-Click Direct Biometric Punch Station -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow-sm border-0 h-100 mb-0">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="avatar-sm me-3">
                            <span class="avatar-title bg-success rounded-circle text-white fs-22 shadow-sm fingerprint-glow" id="punchSensorAvatar">
                                <i class="ri-fingerprint-fill"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1 text-dark fs-16">جهاز البصمة السريع (تسجيل فوري للعامل أو الفني)</h5>
                            <p class="text-muted mb-0 fs-12">اختر العامل واضغط زر "تسجيل حضور" أو "تسجيل انصراف" بضغطة واحدة:</p>
                        </div>
                    </div>

                    <div class="row g-3 align-items-end">
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-bold fs-13 text-dark mb-1">اختر الموظف:</label>
                            <select class="form-select form-select-lg border-2 border-primary fw-semibold fs-14" id="quickPunchSelect">
                                <option value="">-- اضغط هنا لاختيار الموظف --</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}" data-name="{{ $emp->full_name }}" data-role="{{ $emp->jobTitle?->title_name }}" data-branch="{{ $emp->branch?->name }}">
                                        {{ $emp->full_name }} — ({{ $emp->jobTitle?->title_name ?? 'موظف' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 col-6">
                            <button type="button" class="btn btn-success btn-lg w-100 fw-bold fs-14 shadow-sm" id="btnQuickIn">
                                <i class="ri-login-box-line me-1"></i> تسجيل حضور
                            </button>
                        </div>

                        <div class="col-md-3 col-6">
                            <button type="button" class="btn btn-primary btn-lg w-100 fw-bold fs-14 shadow-sm" id="btnQuickOut">
                                <i class="ri-logout-box-line me-1"></i> تسجيل انصراف
                            </button>
                        </div>
                    </div>

                    <!-- Custom time toggle for testing -->
                    <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                        <button class="btn btn-link btn-sm text-muted p-0 text-decoration-none fs-12" type="button" data-bs-toggle="collapse" data-bs-target="#customTimeCollapse" aria-expanded="false">
                            <i class="ri-settings-4-line me-1"></i> تجربة وقت يدوي مخصص (اختياري للاختبار)
                        </button>
                        <span class="text-muted fs-11">فترة السماح الصباحية: <strong>15 دقيقة</strong></span>
                    </div>

                    <div class="collapse mt-2" id="customTimeCollapse">
                        <div class="p-3 bg-light rounded-3 d-flex align-items-center gap-3">
                            <label class="form-label mb-0 fs-12 fw-bold text-muted text-nowrap">حدد وقتاً مخصصاً للبصمة:</label>
                            <input type="time" class="form-control form-control-sm" id="customSimTime" style="max-width: 140px;" placeholder="09:40">
                            <small class="text-muted fs-11">اتركه فارغاً ليتم اعتماد الوقت الفعلي التلقائي الحالي للساعة.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Big 4 Visual Stat Cards -->
    <div class="row mb-3">
        <!-- 1. Present On Time -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-filter-card border-start border-success border-4 shadow-sm mb-0 active-filter" id="card-filter-present" onclick="setQuickTabFilter('present')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">🟢 حاضرون في الموعد</p>
                            <h3 class="fs-24 fw-extrabold text-success mb-0" id="statCountOnTime">{{ $stats['present'] ?? 0 }}</h3>
                            <small class="text-muted fs-11">منضبطون في وردية اليوم</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-20">
                                <i class="ri-checkbox-circle-fill"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Late Staff -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-filter-card border-start border-warning border-4 shadow-sm mb-0" id="card-filter-late" onclick="setQuickTabFilter('late')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">🟡 متأخرون عن الوردية</p>
                            <h3 class="fs-24 fw-extrabold text-warning mb-0" id="statCountLate">{{ $stats['late'] ?? 0 }}</h3>
                            <small class="text-muted fs-11">تجاوزوا وقت الحضور</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-20">
                                <i class="ri-alarm-warning-fill"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Absent / Not Arrived -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-filter-card border-start border-danger border-4 shadow-sm mb-0" id="card-filter-absent" onclick="setQuickTabFilter('absent')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">🔴 غائبون / لم يسجلوا بعد</p>
                            <h3 class="fs-24 fw-extrabold text-danger mb-0" id="statCountAbsent">{{ $stats['absent'] ?? 0 }}</h3>
                            <small class="text-muted fs-11">لم يثبتوا بصمتهم اليوم</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-20">
                                <i class="ri-user-unfollow-fill"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Total Staff & Discipline Rate -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-filter-card border-start border-primary border-4 shadow-sm mb-0" id="card-filter-all" onclick="setQuickTabFilter('all')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">🔵 إجمالي فريق المركز</p>
                            <h3 class="fs-24 fw-extrabold text-primary mb-0" id="statCountTotal">{{ $stats['total_expected'] ?? count($employees) }} موظف</h3>
                            <small class="text-success fw-bold fs-11" id="statPunctualityText">نسبة الحضور: 0%</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20">
                                <i class="ri-team-fill"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Attendance Table & Filter Header -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom p-3">
                    <div class="row g-3 align-items-center justify-content-between">
                        <!-- Easy Filter Tabs -->
                        <div class="col-lg-7 col-12">
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <span class="fw-bold fs-13 text-muted me-1">عرض السجلات:</span>
                                <button type="button" class="filter-tab-pill active" id="tab-all" onclick="setQuickTabFilter('all')">
                                    الكل (<span id="pillCountAll">{{ count($employees) }}</span>)
                                </button>
                                <button type="button" class="filter-tab-pill" id="tab-present" onclick="setQuickTabFilter('present')">
                                    🟢 في الموعد (<span id="pillCountOnTime">{{ $stats['present'] ?? 0 }}</span>)
                                </button>
                                <button type="button" class="filter-tab-pill" id="tab-late" onclick="setQuickTabFilter('late')">
                                    🟡 متأخرين (<span id="pillCountLate">{{ $stats['late'] ?? 0 }}</span>)
                                </button>
                                <button type="button" class="filter-tab-pill" id="tab-absent" onclick="setQuickTabFilter('absent')">
                                    🔴 غياب / لم يسجل (<span id="pillCountAbsent">{{ $stats['absent'] ?? 0 }}</span>)
                                </button>
                            </div>
                        </div>

                        <!-- Date & Search Box -->
                        <div class="col-lg-5 col-12 d-flex gap-2">
                            <input type="date" class="form-control" id="attendanceDateFilter" value="{{ $date ?? date('Y-m-d') }}" style="max-width: 160px;">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="ri-search-line text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" id="searchEmployeeInput" placeholder="ابحث باسم الموظف أو الفني...">
                                <button class="btn btn-light border" type="button" onclick="clearSearch()" title="مسح البحث">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-nowrap mb-0" id="attendanceMainTable">
                            <thead class="table-light">
                                <tr class="text-muted fs-12 text-uppercase">
                                    <th style="min-width: 200px;">الموظف / الفني</th>
                                    <th>الوظيفة والفرع</th>
                                    <th>موعد الوردية الرسمي</th>
                                    <th>وقت البصمة الفعلي</th>
                                    <th>وقت الانصراف</th>
                                    <th>حالة الحضور اليوم</th>
                                    <th>التأخير المسجل</th>
                                    <th class="text-center" style="min-width: 190px;">تسجيل البصمة والإجراء المباشر</th>
                                </tr>
                            </thead>
                            <tbody id="attendanceTableBody">
                                <!-- Loaded dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Table Footer Helper -->
                <div class="card-footer bg-light p-3 border-top d-flex flex-wrap justify-content-between align-items-center fs-12 text-muted">
                    <div>
                        <i class="ri-information-line me-1 text-primary"></i> 
                        <strong>ملاحظة للمدير:</strong> تسجيل البصمة يتم بربط فوري مع قاعدة بيانات وسيرفر الموارد البشرية، ويتم احتساب دقائق التأخير تلقائياً.
                    </div>
                    <div>
                        تم التحديث تلقائياً: <span class="fw-bold text-dark" id="lastUpdatedTime">-</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Fast 1-Click Deduction for Late Arrival or Negligence -->
    <div class="modal fade" id="quickDeductionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white p-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar-xs me-2">
                            <span class="avatar-title bg-white text-danger rounded-circle fs-16">
                                <i class="ri-scissors-cut-line"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-white fs-15 mb-0">تطبيق خصم وجزاء إداري فوري</h5>
                            <small class="text-white-50 fs-11">إدارة مركز الحسيني لبطاريات السيارات</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="quickDeductionForm">
                    <input type="hidden" id="quickDedEmployeeId">
                    <div class="modal-body p-4">
                        <!-- Target Employee Card -->
                        <div class="p-3 bg-light rounded-3 mb-3 d-flex align-items-center gap-3 border">
                            <div class="avatar-sm">
                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-16 fw-bold" id="quickDedAvatarText">م</span>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-14" id="quickDedEmployeeName">-</h6>
                                <span class="badge bg-primary-subtle text-primary fs-11" id="quickDedRole">-</span>
                                <span class="text-muted fs-11 ms-2" id="quickDedLatenessInfo"></span>
                            </div>
                        </div>

                        <!-- Fast Amount Presets -->
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-13 text-dark mb-1">
                                قيمة الخصم بالجنية المصري (اختر مبلغ سريع أو اكتبه):
                            </label>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <button type="button" class="btn btn-outline-danger btn-sm quick-amount-preset" onclick="setPresetAmount(150)">150 ج.م</button>
                                <button type="button" class="btn btn-outline-danger btn-sm quick-amount-preset" onclick="setPresetAmount(250)">250 ج.م</button>
                                <button type="button" class="btn btn-outline-danger btn-sm quick-amount-preset" onclick="setPresetAmount(350)">350 ج.م</button>
                                <button type="button" class="btn btn-outline-danger btn-sm quick-amount-preset" onclick="setPresetAmount(500)">500 ج.م</button>
                            </div>
                            <div class="input-group">
                                <input type="number" class="form-control form-control-lg fw-bold fs-16 border-danger text-center" id="quickDedAmount" required min="50" step="50" value="250">
                                <span class="input-group-text bg-danger-subtle text-danger fw-bold">جنيه مصري (EGP)</span>
                            </div>
                        </div>

                        <!-- Specific Reason -->
                        <div class="mb-3">
                            <label for="quickDedReason" class="form-label fw-bold fs-13 text-dark">سبب الجزاء والمخالفة:</label>
                            <select class="form-select form-select-lg fs-13" id="quickDedReason" required>
                                <option value="تأخير عن موعد فتح صالة المعرض واستقبال العملاء">تأخير عن موعد فتح صالة المعرض واستقبال العملاء</option>
                                <option value="تأخير عن موعد ورشة فحص وشحن البطاريات وتعبئة المحاليل">تأخير عن موعد ورشة فحص وشحن البطاريات وتعبئة المحاليل</option>
                                <option value="تأخر في الاستجابة لبلاغ طوارئ إنقاذ بطارية على الطريق">تأخر في الاستجابة لبلاغ طوارئ إنقاذ بطارية على الطريق</option>
                                <option value="إهمال في فحص كفاءة كابلات الدينامو لسيارة العميل">إهمال في فحص كفاءة كابلات الدينامو لسيارة العميل</option>
                                <option value="انصراف مبكر بدون إذن قبل إغلاق المعرض وجرد المخزن">انصراف مبكر بدون إذن قبل إغلاق المعرض وجرد المخزن</option>
                                <option value="عدم الالتزام بارتداء مهمات الوقاية بالورشة">عدم الالتزام بارتداء مهمات الوقاية بالورشة</option>
                                <option value="غياب كامل بدون إذن مسبق">غياب كامل بدون إذن مسبق</option>
                            </select>
                        </div>

                        <!-- Manager Notes -->
                        <div class="mb-0">
                            <label for="quickDedNotes" class="form-label fw-semibold fs-12 text-muted">ملاحظات إضافية من المدير (اختياري):</label>
                            <textarea class="form-control" id="quickDedNotes" rows="2" placeholder="اكتب أي توضيح للخصم ليظهر في كشف الموظف..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer bg-light p-3">
                        <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal">إلغاء الأمر</button>
                        <button type="submit" class="btn btn-danger fw-bold px-4">
                            <i class="ri-check-line align-middle me-1"></i> تأكيد واعتماد الخصم فورياً
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
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
@endsection
