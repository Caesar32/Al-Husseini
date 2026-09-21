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
