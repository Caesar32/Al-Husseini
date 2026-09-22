@extends('admin.layouts.master')

@section('title', 'إعدادات النظام | مجموعة الحسيني')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">إعدادات النظام والمنشأة</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">الإعدادات العامة</li>
                </ol>
            </div>
        </div>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success alert-border-left alert-dismissible fade show" role="alert">
        <i class="ri-check-double-line me-2 align-middle fs-16"></i> {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-border-left alert-dismissible fade show" role="alert">
        <i class="ri-error-warning-line me-2 align-middle fs-16"></i>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form action="{{ route('admin.settings.update') }}" method="POST">
    @csrf
    <div class="row">
        <div class="col-lg-3">
            <div class="card shadow-sm sticky-top" style="top: 85px; z-index: 10;">
                <div class="card-header bg-primary-subtle py-3">
                    <h6 class="card-title mb-0 fs-14 fw-bold text-primary">
                        <i class="ri-equalizer-line me-1 align-middle"></i> أقسام الإعدادات
                    </h6>
                </div>
                <div class="card-body p-2">
                    <div class="nav flex-column nav-pills" id="settings-tabs" role="tablist">
                        <button class="nav-link active text-start py-3 px-3 d-flex align-items-center" data-bs-toggle="pill" data-bs-target="#tab-company" type="button" role="tab">
                            <i class="ri-store-2-line fs-18 me-2 text-primary"></i>
                            <div>
                                <div class="fw-bold fs-13">بيانات المنشأة والمعرض</div>
                                <small class="text-muted fs-11">الاسم، السجل، الضريبة، والعناوين</small>
                            </div>
                        </button>
                        <button class="nav-link text-start py-3 px-3 d-flex align-items-center" data-bs-toggle="pill" data-bs-target="#tab-hr" type="button" role="tab">
                            <i class="ri-user-settings-line fs-18 me-2 text-success"></i>
                            <div>
                                <div class="fw-bold fs-13">الموارد البشرية والدوام</div>
                                <small class="text-muted fs-11">فترات السماح والشفتات وأيام العمل</small>
                            </div>
                        </button>
                        <button class="nav-link text-start py-3 px-3 d-flex align-items-center" data-bs-toggle="pill" data-bs-target="#tab-sales" type="button" role="tab">
                            <i class="ri-bill-line fs-18 me-2 text-warning"></i>
                            <div>
                                <div class="fw-bold fs-13">المبيعات والفوترة والضمان</div>
                                <small class="text-muted fs-11">الضريبة، تسلسل الفواتير، والضمانات</small>
                            </div>
                        </button>
                        <button class="nav-link text-start py-3 px-3 d-flex align-items-center" data-bs-toggle="pill" data-bs-target="#tab-security" type="button" role="tab">
                            <i class="ri-shield-check-line fs-18 me-2 text-info"></i>
                            <div>
                                <div class="fw-bold fs-13">الأمان والتنبيهات</div>
                                <small class="text-muted fs-11">مهلة الجلسة وإشعارات التأخير</small>
                            </div>
                        </button>
                    </div>

                    <div class="border-top mt-3 pt-3 p-2">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="ri-save-3-line align-middle me-1"></i> حفظ كافة الإعدادات
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="tab-content">
                <!-- 1. General Company Settings -->
                <div class="tab-pane fade show active" id="tab-company" role="tabpanel">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header border-bottom py-3">
                            <h5 class="card-title mb-0 fs-15 fw-bold text-dark">
                                <i class="ri-building-line text-primary me-1"></i> المعلومات الأساسية لمجموعة الحسيني
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">اسم الشركة / المجموعة الرسمي <span class="text-danger">*</span></label>
                                    <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $settings['company_name'] ?? 'مجموعة الحسيني لتجارة وتوزيع بطاريات السيارات') }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">الاسم التجاري المختصر</label>
                                    <input type="text" name="company_short_name" class="form-control" value="{{ old('company_short_name', $settings['company_short_name'] ?? 'الحسيني Al-Husseini') }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">الرقم الضريبي (Tax Identification No)</label>
                                    <input type="text" name="company_tax_id" class="form-control" value="{{ old('company_tax_id', $settings['company_tax_id'] ?? '456-789-123') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">رقم السجل التجاري (Commercial Registration)</label>
                                    <input type="text" name="company_cr_id" class="form-control" value="{{ old('company_cr_id', $settings['company_cr_id'] ?? '18920-دمياط') }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">الهاتف الأرضي الرئيسي <span class="text-danger">*</span></label>
                                    <input type="text" name="company_phone" class="form-control" value="{{ old('company_phone', $settings['company_phone'] ?? '0572400000') }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">موبايل وواتساب خدمة العملاء</label>
                                    <input type="text" name="company_mobile" class="form-control" value="{{ old('company_mobile', $settings['company_mobile'] ?? '01011111111') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">البريد الإلكتروني للإدارة</label>
                                    <input type="email" name="company_email" class="form-control" value="{{ old('company_email', $settings['company_email'] ?? 'info@alhusseini.com') }}">
                                </div>

                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">عنوان المقر والفرع الرئيسي</label>
                                    <input type="text" name="company_address" class="form-control" value="{{ old('company_address', $settings['company_address'] ?? 'شارع المحجوب، دمياط الجديدة، محافظة دمياط') }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">رمز العملة المعتمدة <span class="text-danger">*</span></label>
                                    <input type="text" name="currency" class="form-control" value="{{ old('currency', $settings['currency'] ?? 'ج.م') }}" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. HR & Work Schedule Settings -->
                <div class="tab-pane fade" id="tab-hr" role="tabpanel">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header border-bottom py-3">
                            <h5 class="card-title mb-0 fs-15 fw-bold text-dark">
                                <i class="ri-time-line text-success me-1"></i> معايير مواعيد العمل والبصمة واحتساب الرواتب
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">فترة السماح الافتراضية للتأخير (بالدقائق)</label>
                                    <div class="input-group">
                                        <input type="number" name="default_grace_period" class="form-control" value="{{ old('default_grace_period', $settings['default_grace_period'] ?? '15') }}" min="0" max="60">
                                        <span class="input-group-text">دقيقة</span>
                                    </div>
                                    <small class="text-muted fs-11">المدة المسموح بها للموظف بالدخول بعد بداية الوردية دون احتساب تأخير.</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">معامل احتساب ساعة العمل الإضافي (Overtime Multiplier)</label>
                                    <div class="input-group">
                                        <input type="number" step="0.1" name="overtime_rate_multiplier" class="form-control" value="{{ old('overtime_rate_multiplier', $settings['overtime_rate_multiplier'] ?? '1.5') }}" min="1.0" max="3.0">
                                        <span class="input-group-text">ضعف أجر الساعة</span>
                                    </div>
                                    <small class="text-muted fs-11">المعدل القانوني المعتمد 1.5 لمضاعفة قيمة الساعة الإضافية.</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">بداية الوردية الافتراضية</label>
                                    <input type="time" name="default_shift_start" class="form-control" value="{{ old('default_shift_start', $settings['default_shift_start'] ?? '09:00') }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">نهاية الوردية الافتراضية</label>
                                    <input type="time" name="default_shift_end" class="form-control" value="{{ old('default_shift_end', $settings['default_shift_end'] ?? '17:00') }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">معيار أيام العمل الشهرية</label>
                                    <div class="input-group">
                                        <input type="number" name="monthly_working_days" class="form-control" value="{{ old('monthly_working_days', $settings['monthly_working_days'] ?? '26') }}" min="20" max="31">
                                        <span class="input-group-text">يوم</span>
                                    </div>
                                    <small class="text-muted fs-11">يستخدم لحساب تكلفة يوم الغياب في مسير الرواتب (26 يوماً باستثناء الجمعة).</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">ساعات العمل اليومية الرسمية</label>
                                    <div class="input-group">
                                        <input type="number" name="daily_working_hours" class="form-control" value="{{ old('daily_working_hours', $settings['daily_working_hours'] ?? '8') }}" min="4" max="14">
                                        <span class="input-group-text">ساعات</span>
                                    </div>
                                    <small class="text-muted fs-11">تستخدم لحساب أجر الساعة من الراتب الأساسي.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Sales, Invoices & Warranty Settings -->
                <div class="tab-pane fade" id="tab-sales" role="tabpanel">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header border-bottom py-3">
                            <h5 class="card-title mb-0 fs-15 fw-bold text-dark">
                                <i class="ri-file-list-3-line text-warning me-1"></i> سياسات الفوترة والضريبة والبطاريات القديمة (السكراب)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">نسبة ضريبة القيمة المضافة (VAT)</label>
                                    <div class="input-group">
                                        <input type="number" step="0.5" name="vat_percentage" class="form-control" value="{{ old('vat_percentage', $settings['vat_percentage'] ?? '14') }}" min="0" max="30">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">فترة الضمان الافتراضية للبطاريات الجديدة</label>
                                    <div class="input-group">
                                        <input type="number" name="warranty_months_default" class="form-control" value="{{ old('warranty_months_default', $settings['warranty_months_default'] ?? '12') }}" min="1" max="36">
                                        <span class="input-group-text">شهراً</span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">بادئة ترقيم فواتير البيع (Invoice Prefix)</label>
                                    <input type="text" name="invoice_prefix" class="form-control" value="{{ old('invoice_prefix', $settings['invoice_prefix'] ?? 'INV-') }}">
                                    <small class="text-muted fs-11">مثال: INV-2026-0001</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">بادئة إيصالات استلام بطاريات السكراب والكهنة</label>
                                    <input type="text" name="scrap_prefix" class="form-control" value="{{ old('scrap_prefix', $settings['scrap_prefix'] ?? 'SCR-') }}">
                                    <small class="text-muted fs-11">مثال: SCR-2026-0001</small>
                                </div>

                                <div class="col-12 mt-4">
                                    <div class="form-check form-switch form-switch-md">
                                        <input class="form-check-input" type="checkbox" name="allow_negative_stock" id="allow_negative_stock" value="1" {{ ($settings['allow_negative_stock'] ?? '0') === '1' ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="allow_negative_stock">
                                            السماح بالبيع في حال نفاد المخزون (Negative Stock)
                                        </label>
                                        <div class="text-muted fs-11">عند التفعيل، يستطيع الكاشير إتمام الفاتورة حتى لو لم يتوفر رصيد كافي في مخزن الفرع مع تسجيل رصيد بالسالب لحين توريد الشحنة.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Security & Notifications Settings -->
                <div class="tab-pane fade" id="tab-security" role="tabpanel">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header border-bottom py-3">
                            <h5 class="card-title mb-0 fs-15 fw-bold text-dark">
                                <i class="ri-shield-keyhole-line text-info me-1"></i> الأمان والتنبيهات الإدارية التلقائية
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">مهلة انتهاء الجلسة التلقائية (Session Timeout)</label>
                                    <div class="input-group">
                                        <input type="number" name="session_timeout_minutes" class="form-control" value="{{ old('session_timeout_minutes', $settings['session_timeout_minutes'] ?? '120') }}" min="15" max="720">
                                        <span class="input-group-text">دقيقة</span>
                                    </div>
                                    <small class="text-muted fs-11">مدة عدم النشاط قبل إغلاق الجلسة أو قفل الشاشة تلقائياً.</small>
                                </div>

                                <div class="col-12 mt-3">
                                    <div class="form-check form-switch form-switch-md mb-3">
                                        <input class="form-check-input" type="checkbox" name="lateness_alert_enabled" id="lateness_alert_enabled" value="1" {{ ($settings['lateness_alert_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="lateness_alert_enabled">
                                            إرسال تنبيه فوري للإدارة عند تسجيل بصمة تأخير لأي فني أو بائع
                                        </label>
                                        <div class="text-muted fs-11">يتم إنشاء إشعار في قائمة التنبيهات العلوية فور تسجيل البصمة بعد فترة السماح.</div>
                                    </div>

                                    <div class="form-check form-switch form-switch-md">
                                        <input class="form-check-input" type="checkbox" name="email_notifications_enabled" id="email_notifications_enabled" value="1" {{ ($settings['email_notifications_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="email_notifications_enabled">
                                            تفعيل التنبيهات عبر البريد الإلكتروني للمسيرات وطلبات الإجازات
                                        </label>
                                        <div class="text-muted fs-11">إرسال تقرير ملخص عند اعتماد وصرف مسيرات الرواتب الشهرية.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
