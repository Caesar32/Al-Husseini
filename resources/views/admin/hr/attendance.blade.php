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
        0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
        70% { box-shadow: 0 0 0 15px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
</style>
@endsection

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'الموارد البشرية', 'title' => 'تسجيل بصمة الحضور والانصراف اليومي'])

    <!-- Top Notice Banner for Friendly Clarity -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-primary bg-primary-subtle border-0 shadow-sm d-flex flex-wrap align-items-center justify-content-between p-3 rounded-3">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <div class="avatar-sm me-3 flex-shrink-0">
                        <span class="avatar-title bg-primary text-white rounded-circle fs-20 shadow-sm">
                            <i class="ri-user-follow-line"></i>
                        </span>
                    </div>
                    <div>
                        <h5 class="alert-heading fw-bold mb-1 fs-15 text-primary">مرحباً بك في شاشة متابعة حضور وبصمة فريق مركز البطاريات</h5>
                        <p class="mb-0 fs-13 text-muted">
                            يمكنك بنقرة زر واحدة تسجيل حضور أو انصراف أي عامل، أو النقر مباشرة على زر <strong>"حضر الآن"</strong> أمام اسم الموظف بالجدول.
                        </p>
                    </div>
                </div>
                <div>
                    <a href="{{ route('admin.hr.reports') }}" class="btn btn-outline-primary btn-sm fw-bold">
                        <i class="ri-file-chart-line me-1"></i> استعراض التقارير الشهرية واليومية
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Shop Station & 1-Click Biometric Punch Bar -->
    <div class="row mb-4">
        <!-- Live Clock & Center Shift Status -->
        <div class="col-xl-4 col-lg-5 mb-3 mb-lg-0">
            <div class="card shop-clock-card h-100 border-0 mb-0">
                <div class="card-body p-4 d-flex flex-column justify-content-between text-center text-lg-start">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-success-subtle text-success fs-12 px-3 py-1 fw-bold">
                                <i class="ri-checkbox-blank-circle-fill me-1 fs-10 text-success"></i> وردية المركز تعمل الآن
                            </span>
                            <span class="text-white-50 fs-12" id="shopLiveDate">اليوم</span>
                        </div>
                        <div class="live-digital-time text-center my-2" id="liveDigitalClock">00:00:00</div>
                        <p class="text-white-50 fs-12 text-center mb-0">
                            مواعيد فتح معرض وورشة البطاريات: <strong>08:30 ص حتى 06:00 م</strong>
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

                    <!-- Optional simulation drawer / toggle for custom time testing -->
                    <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                        <button class="btn btn-link btn-sm text-muted p-0 text-decoration-none fs-12" type="button" data-bs-toggle="collapse" data-bs-target="#customTimeCollapse" aria-expanded="false">
                            <i class="ri-settings-4-line me-1"></i> تجربة وقت يدوي مخصص (اختياري للاختبار والتجربة)
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

    <!-- Big 4 Visual Stat Cards (Clickable to Filter instantly!) -->
    <div class="row mb-3">
        <!-- 1. Present On Time -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-filter-card border-start border-success border-4 shadow-sm mb-0 active-filter" id="card-filter-on_time" onclick="setQuickTabFilter('on_time')">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">🟢 حاضرون في الموعد</p>
                            <h3 class="fs-24 fw-extrabold text-success mb-0" id="statCountOnTime">0</h3>
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
                            <h3 class="fs-24 fw-extrabold text-warning mb-0" id="statCountLate">0</h3>
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
                            <h3 class="fs-24 fw-extrabold text-danger mb-0" id="statCountAbsent">0</h3>
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
                            <h3 class="fs-24 fw-extrabold text-primary mb-0" id="statCountTotal">8 موظفين</h3>
                            <small class="text-success fw-bold fs-11" id="statPunctualityText">نسبة الالتزام: 0%</small>
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
                                    الكل (<span id="pillCountAll">8</span>)
                                </button>
                                <button type="button" class="filter-tab-pill" id="tab-on_time" onclick="setQuickTabFilter('on_time')">
                                    🟢 في الموعد (<span id="pillCountOnTime">0</span>)
                                </button>
                                <button type="button" class="filter-tab-pill" id="tab-late" onclick="setQuickTabFilter('late')">
                                    🟡 متأخرين (<span id="pillCountLate">0</span>)
                                </button>
                                <button type="button" class="filter-tab-pill" id="tab-absent" onclick="setQuickTabFilter('absent')">
                                    🔴 غياب / لم يسجل (<span id="pillCountAbsent">0</span>)
                                </button>
                                <button type="button" class="filter-tab-pill" id="tab-on_leave" onclick="setQuickTabFilter('on_leave')">
                                    ✈️ إجازات (<span id="pillCountLeave">0</span>)
                                </button>
                            </div>
                        </div>

                        <!-- Search Box -->
                        <div class="col-lg-4 col-12">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="ri-search-line text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" id="searchEmployeeInput" placeholder="ابحث باسم الموظف أو الفني..." oninput="renderTable()">
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
                                    <th>الوظيفة والقسم بالمركز</th>
                                    <th>موعد الوردية الرسمي</th>
                                    <th>وقت البصمة الفعلي</th>
                                    <th>وقت الانصراف</th>
                                    <th>حالة الحضور اليوم</th>
                                    <th>التأخير المسجل</th>
                                    <th class="text-center" style="min-width: 190px;">تسجيل البصمة والإجراء المباشر</th>
                                </tr>
                            </thead>
                            <tbody id="attendanceTableBody">
                                <!-- Rendered dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Table Footer Helper -->
                <div class="card-footer bg-light p-3 border-top d-flex flex-wrap justify-content-between align-items-center fs-12 text-muted">
                    <div>
                        <i class="ri-information-line me-1 text-primary"></i> 
                        <strong>ملاحظة للمدير:</strong> يمكنك الضغط مباشرة على زر <strong>"حضر الآن"</strong> أو <strong>"انصرف الآن"</strong> أمام أي اسم لتحديث سجله فورياً.
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
                            <img src="/assets/images/users/avatar-1.jpg" id="quickDedAvatar" class="avatar-sm rounded-circle border" alt="">
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
    let currentFilterTab = 'all'; // 'all' | 'on_time' | 'late' | 'absent' | 'on_leave'
    const quickDeductionModal = new bootstrap.Modal(document.getElementById('quickDeductionModal'));

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

    // 2. Populate Dropdown for 1-Click Biometric Punch Bar
    function populateDropdown() {
        if (!window.AlHusseiniHR) return;
        const employees = window.AlHusseiniHR.getEmployees();
        const select = document.getElementById('quickPunchSelect');
        if (!select) return;

        const currentVal = select.value;
        let html = '<option value="">-- اضغط هنا لاختيار الموظف --</option>';

        employees.forEach(emp => {
            html += `<option value="${emp.id}">${emp.name} — (${emp.role})</option>`;
        });

        select.innerHTML = html;
        if (currentVal) select.value = currentVal;
    }

    // 3. Render Attendance Table and Update Stats
    window.renderTable = function() {
        if (!window.AlHusseiniHR) return;

        const employees = window.AlHusseiniHR.getEmployees();
        const todayAttendance = window.AlHusseiniHR.getAttendance();
        const searchVal = (document.getElementById('searchEmployeeInput')?.value || '').trim().toLowerCase();

        let countOnTime = 0;
        let countLate = 0;
        let countAbsent = 0;
        let countLeave = 0;

        const tbody = document.getElementById('attendanceTableBody');
        if (!tbody) return;

        let rowsHtml = '';

        employees.forEach(emp => {
            const att = todayAttendance.find(a => a.employeeId === emp.id);

            let status = 'absent';
            let punchIn = '-';
            let punchOut = '-';
            let lateness = 0;

            if (emp.status === 'on_leave') {
                status = 'on_leave';
                countLeave++;
            } else if (att) {
                status = att.status || (att.punchIn ? 'on_time' : 'absent');
                punchIn = att.punchIn ? `${att.punchIn}` : '-';
                punchOut = att.punchOut ? `${att.punchOut}` : '-';
                lateness = att.latenessMinutes || 0;

                if (status === 'on_time') countOnTime++;
                else if (status === 'late') countLate++;
                else countAbsent++;
            } else {
                countAbsent++;
            }

            // Status Filter Tab Check
            if (currentFilterTab !== 'all' && status !== currentFilterTab) {
                return;
            }

            // Search Keyword Check
            if (searchVal) {
                const matchName = emp.name.toLowerCase().includes(searchVal);
                const matchRole = emp.role.toLowerCase().includes(searchVal);
                const matchDept = emp.department.toLowerCase().includes(searchVal);
                const matchId = emp.id.toLowerCase().includes(searchVal);
                if (!matchName && !matchRole && !matchDept && !matchId) {
                    return;
                }
            }

            // Visual Status Badges & Text
            let statusBadge = '';
            let latenessBadge = '<span class="text-muted fs-12">-</span>';
            let directActionBtn = '';

            if (status === 'on_time') {
                statusBadge = '<span class="badge bg-success text-white fs-12 px-3 py-1 fw-bold shadow-sm"><i class="ri-checkbox-circle-line me-1"></i>حاضر في الموعد</span>';
                latenessBadge = '<span class="badge bg-success-subtle text-success fs-11 fw-bold">منضبط (0 دقيقة)</span>';

                if (punchOut === '-') {
                    directActionBtn = `
                        <button type="button" class="btn btn-sm btn-outline-primary btn-punch-action" onclick="punchDirect('${emp.id}', 'out')">
                            <i class="ri-logout-box-r-line"></i> تسجيل انصراف الآن
                        </button>
                    `;
                } else {
                    directActionBtn = `<span class="badge bg-light text-muted border px-2 py-1 fs-12"><i class="ri-check-double-line text-success me-1"></i>أتم وريديته وانصرف</span>`;
                }
            } else if (status === 'late') {
                statusBadge = '<span class="badge bg-warning text-dark fs-12 px-3 py-1 fw-bold shadow-sm"><i class="ri-alarm-warning-line me-1"></i>متأخر</span>';
                latenessBadge = `<span class="badge bg-danger text-white fs-12 fw-bold font-monospace shadow-sm">+${lateness} دقيقة تأخير</span>`;

                directActionBtn = `
                    <div class="d-inline-flex gap-1">
                        ${punchOut === '-' ? `
                            <button type="button" class="btn btn-sm btn-outline-primary btn-punch-action" onclick="punchDirect('${emp.id}', 'out')" title="تسجيل انصراف">
                                <i class="ri-logout-box-r-line"></i> انصراف
                            </button>
                        ` : ''}
                        <button type="button" class="btn btn-sm btn-danger btn-punch-action" onclick="openDeductionModal('${emp.id}')" title="تطبيق خصم فوري على التأخير">
                            <i class="ri-scissors-cut-line"></i> خصم تأخير
                        </button>
                    </div>
                `;
            } else if (status === 'on_leave') {
                statusBadge = '<span class="badge bg-info-subtle text-info fs-12 px-3 py-1 fw-bold"><i class="ri-flight-takeoff-line me-1"></i>إجازة رسمية / مرضية</span>';
                latenessBadge = '<span class="text-muted fs-11">معتمد بإذن</span>';
                directActionBtn = `<span class="badge bg-light text-muted border px-2 py-1 fs-12">لا توجد إجراءات</span>`;
            } else {
                statusBadge = '<span class="badge bg-danger-subtle text-danger fs-12 px-3 py-1 fw-bold"><i class="ri-close-circle-line me-1"></i>لم يسجل بصمته بعد</span>';
                latenessBadge = '<span class="text-danger fw-bold fs-11">غير حاضر بالمركز</span>';

                directActionBtn = `
                    <div class="d-inline-flex gap-1">
                        <button type="button" class="btn btn-sm btn-success btn-punch-action" onclick="punchDirect('${emp.id}', 'in')">
                            <i class="ri-fingerprint-line"></i> حضر الآن
                        </button>
                        <button type="button" class="btn btn-sm btn-soft-danger btn-punch-action" onclick="openDeductionModal('${emp.id}')" title="خصم غياب">
                            <i class="ri-hand-coin-line"></i> جزاء غياب
                        </button>
                    </div>
                `;
            }

            const punchInDisplay = punchIn !== '-' ? `<span class="fw-bold fs-13 font-monospace text-success">${punchIn} ص</span>` : '<span class="text-muted fs-12">لم يسجل</span>';
            const punchOutDisplay = punchOut !== '-' ? `<span class="fw-bold fs-13 font-monospace text-primary">${punchOut} م</span>` : '<span class="text-muted fs-12">-</span>';

            rowsHtml += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="${emp.avatar || '/assets/images/users/avatar-1.jpg'}" alt="" class="avatar-sm rounded-circle me-3 border shadow-sm">
                            <div>
                                <h6 class="mb-0 fs-14 fw-bold text-dark">${emp.name}</h6>
                                <span class="badge bg-light text-secondary font-monospace fs-11">كود: ${emp.id}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="fw-bold fs-13 text-dark d-block">${emp.role}</span>
                        <span class="text-muted fs-11"><i class="ri-tools-line me-1"></i>${emp.department}</span>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border fs-12 px-2 py-1">
                            ${emp.startTime || '09:00'} ص - ${emp.endTime || '18:00'} م
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

        tbody.innerHTML = rowsHtml || `<tr><td colspan="8" class="text-center py-5 text-muted fs-14"><i class="ri-user-search-line fs-24 d-block mb-1"></i>لا توجد نتائج مطابقة لبحثك في هذا القسم</td></tr>`;

        // Update Top Stat Numbers
        document.getElementById('statCountOnTime').textContent = `${countOnTime} موظف`;
        document.getElementById('statCountLate').textContent = `${countLate} متأخر`;
        document.getElementById('statCountAbsent').textContent = `${countAbsent} غائب`;
        document.getElementById('statCountTotal').textContent = `${employees.length} موظف بالمركز`;

        // Update Pill Badges
        document.getElementById('pillCountAll').textContent = employees.length;
        document.getElementById('pillCountOnTime').textContent = countOnTime;
        document.getElementById('pillCountLate').textContent = countLate;
        document.getElementById('pillCountAbsent').textContent = countAbsent;
        document.getElementById('pillCountLeave').textContent = countLeave;

        const totalActive = countOnTime + countLate;
        const punctualityRate = totalActive > 0 ? Math.round((countOnTime / totalActive) * 100) : 0;
        document.getElementById('statPunctualityText').textContent = `نسبة الالتزام بالموعد: ${punctualityRate}%`;

        // Update Last Updated Timestamp
        const now = new Date();
        document.getElementById('lastUpdatedTime').textContent = now.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    };

    // 4. Easy Quick Filter Tab Switcher
    window.setQuickTabFilter = function(tabName) {
        currentFilterTab = tabName;

        // Sync pills
        ['all', 'on_time', 'late', 'absent', 'on_leave'].forEach(tab => {
            const pill = document.getElementById(`tab-${tab}`);
            if (pill) {
                if (tab === tabName) pill.classList.add('active');
                else pill.classList.remove('active');
            }
        });

        // Sync top stat cards visual active state
        ['on_time', 'late', 'absent', 'all'].forEach(c => {
            const card = document.getElementById(`card-filter-${c}`);
            if (card) {
                card.classList.remove('active-filter', 'active-filter-warning', 'active-filter-danger');
                if (c === tabName) {
                    if (c === 'late') card.classList.add('active-filter-warning');
                    else if (c === 'absent') card.classList.add('active-filter-danger');
                    else card.classList.add('active-filter');
                }
            }
        });

        renderTable();
    };

    window.clearSearch = function() {
        const input = document.getElementById('searchEmployeeInput');
        if (input) {
            input.value = '';
            renderTable();
        }
    };

    // 5. Punch Animation & Sensor Effect
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

    // 6. Direct 1-Click Punch In or Out from Table or Bar
    window.punchDirect = function(employeeId, type) {
        if (!window.AlHusseiniHR) return;
        animatePunchSensor();

        const customTime = document.getElementById('customSimTime')?.value || null;
        const result = window.AlHusseiniHR.recordPunch(employeeId, type, customTime);

        if (result.success) {
            if (type === 'in') {
                if (result.isLate) {
                    Swal.fire({
                        icon: 'warning',
                        title: '⚠️ تم تسجيل الحضور بتأخير!',
                        html: `
                            <div class="text-start fs-13">
                                <p class="mb-1">الموظف: <strong class="text-dark">${result.employee.name}</strong> (${result.employee.role})</p>
                                <p class="mb-1">وقت البصمة: <strong class="text-primary font-monospace">${result.time} ص</strong></p>
                                <p class="mb-2 text-danger fw-bold fs-14">مدة التأخير: ${result.latenessMinutes} دقيقة عن موعد الوردية (${result.employee.startTime})</p>
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
                        html: `تم تسجيل بصمة الفني <strong>${result.employee.name}</strong> في تمام الساعة <strong>${result.time}</strong> بنجاح. منضبط وممتاز!`,
                        timer: 3000,
                        timerProgressBar: true,
                        confirmButtonText: 'حسناً',
                        confirmButtonColor: '#198754'
                    });
                }
            } else {
                Swal.fire({
                    icon: 'info',
                    title: '👋 تم تسجيل بصمة الانصراف',
                    html: `تم تسجيل انصراف الموظف <strong>${result.employee.name}</strong> في تمام الساعة <strong>${result.time}</strong>. بالسلامة والتوفيق!`,
                    timer: 3000,
                    timerProgressBar: true,
                    confirmButtonText: 'حسناً',
                    confirmButtonColor: '#0d6efd'
                });
            }

            renderTable();
        }
    };

    // Quick Punch Bar Event Handlers
    document.getElementById('btnQuickIn').onclick = function() {
        const empId = document.getElementById('quickPunchSelect').value;
        if (!empId) {
            Swal.fire('اختر الموظف أولاً', 'يرجى اختيار اسم الموظف من القائمة أولاً لتسجيل بصمة الحضور.', 'warning');
            return;
        }
        punchDirect(empId, 'in');
    };

    document.getElementById('btnQuickOut').onclick = function() {
        const empId = document.getElementById('quickPunchSelect').value;
        if (!empId) {
            Swal.fire('اختر الموظف أولاً', 'يرجى اختيار اسم الموظف من القائمة أولاً لتسجيل بصمة الانصراف.', 'warning');
            return;
        }
        punchDirect(empId, 'out');
    };

    // 7. Quick Deduction Management
    window.openDeductionModal = function(employeeId) {
        if (!window.AlHusseiniHR) return;
        const emp = window.AlHusseiniHR.getEmployeeById(employeeId);
        if (!emp) return;

        const allAtt = window.AlHusseiniHR.getAttendance();
        const empAtt = allAtt.find(a => a.employeeId === employeeId);
        const lateness = empAtt?.latenessMinutes || 0;

        document.getElementById('quickDedEmployeeId').value = emp.id;
        document.getElementById('quickDedEmployeeName').textContent = emp.name;
        document.getElementById('quickDedRole').textContent = `${emp.role} - ${emp.department}`;
        document.getElementById('quickDedAvatar').src = emp.avatar || '/assets/images/users/avatar-1.jpg';

        const latenessInfoEl = document.getElementById('quickDedLatenessInfo');
        if (lateness > 0) {
            latenessInfoEl.innerHTML = `<span class="badge bg-danger-subtle text-danger">متأخر ${lateness} دقيقة</span>`;
        } else {
            latenessInfoEl.innerHTML = `<span class="badge bg-warning-subtle text-warning">غير حاضر اليوم</span>`;
        }

        // Suggest amount based on lateness
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

        window.AlHusseiniHR.addDeduction({
            employeeId: empId,
            amount: amount,
            reason: reason,
            managerNotes: notes
        });

        quickDeductionModal.hide();
        Swal.fire({
            icon: 'success',
            title: 'تم اعتماد الخصم فورياً!',
            html: `تم اعتماد خصم مبلغ <strong>${amount} ج.م</strong> وتحديث مسير الرواتب تلقائياً.`,
            confirmButtonText: 'ممتاز'
        });

        renderTable();
    };

    // Initial Loading
    populateDropdown();
    renderTable();

    // Listen for cross-window / global updates
    window.addEventListener('alhusseini-hr-updated', function() {
        populateDropdown();
        renderTable();
    });
});
</script>
@endsection
