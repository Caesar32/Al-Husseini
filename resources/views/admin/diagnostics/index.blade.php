@extends('admin.layouts.master')

@section('title', 'فحص وتشخيص النظام الحي | مجموعة الحسيني')

@section('content')
<x-page-header title="منظومة الفحص والتشخيص والمحاكاة الذاتية (Live Diagnostic Suite)" icon="pulse-line" icon-class="text-success fs-20"
    :breadcrumbs="['الرئيسية' => route('admin.dashboard'), 'فحص النظام الحي' => null]" />

<!-- بطاقات المؤشرات العامة لسلامة النظام -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <x-stat-card label="مؤشر سلامة البيانات" :value="$auditData['health_score'].'%'" value-id="statHealthScore" icon="shield-check-line" variant="success" tint="success">
            <x-slot:footer>
                <div class="mt-3">
                    <div class="progress progress-sm">
                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $auditData['health_score'] }}%;" aria-valuenow="{{ $auditData['health_score'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </x-slot:footer>
        </x-stat-card>
    </div>

    <div class="col-xl-3 col-md-6">
        <x-stat-card label="معايير التدقيق المنجزة" :value="$auditData['passed_count'].' / '.$auditData['total_checks']" value-id="statPassedChecks" icon="checkbox-multiple-line" variant="primary" tint="primary">
            <x-slot:footer><p class="text-muted fs-11 mt-3 mb-0">جميع المحاور المحاسبية والمخزنية مفحوصة</p></x-slot:footer>
        </x-stat-card>
    </div>

    <div class="col-xl-3 col-md-6">
        <x-stat-card label="زمن الفحص اللحظي" value-id="statDuration" icon="speed-up-line" variant="warning" tint="warning">
            <x-slot:value-slot>{{ $auditData['duration_ms'] }} <span class="fs-12 text-muted fw-normal">ms</span></x-slot:value-slot>
            <x-slot:footer><p class="text-muted fs-11 mt-3 mb-0">استجابة فائقة السرعة بدون N+1</p></x-slot:footer>
        </x-stat-card>
    </div>

    <div class="col-xl-3 col-md-6">
        <x-stat-card label="جاهزية المحاكاة" value="8 قطاعات" icon="cpu-line" variant="info" value-class="text-indigo" tint="indigo">
            <x-slot:footer><p class="text-muted fs-11 mt-3 mb-0">WAC، POS، كود المدير، الضمان، الكهنة، الرواتب</p></x-slot:footer>
        </x-stat-card>
    </div>
</div>

<!-- شريط الإجراءات والتحكم بالمحاكاة -->
<div class="card border-0 shadow-sm mb-4 ah-panel">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2">
                <div class="form-check form-switch form-switch-success m-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="chkRollback" checked>
                    <label class="form-check-label fw-semibold fs-13" for="chkRollback">
                        التراجع التلقائي عن البيانات التجريبية (Atomic Rollback)
                    </label>
                </div>
                <i class="ri-information-line text-muted fs-15" data-bs-toggle="tooltip" title="يقوم بتشغيل كافة العمليات الحسابية داخل Transaction والتراجع عنها فوراً لحماية قاعدة البيانات"></i>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm px-3" id="btnRefreshAudit">
                    <i class="ri-refresh-line me-1 align-middle"></i>
                    <span>تحديث فحص البيانات</span>
                </button>
                <button type="button" class="btn btn-success btn-sm px-4 fw-bold shadow-sm d-flex align-items-center gap-2" id="btnRunSimulation">
                    <i class="ri-rocket-line fs-16"></i>
                    <span>تشغيل المحاكاة الشاملة الحية</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- منطقة نتائج المحاكاة التفاعلية (Interactive Simulation Terminal) -->
<div class="card border-0 shadow-sm mb-4 d-none ah-panel ah-panel--success-outline" id="simulationCard">
    <div class="card-header bg-success-subtle d-flex align-items-center justify-content-between py-2 px-3">
        <h5 class="card-title mb-0 fs-14 fw-bold text-success d-flex align-items-center gap-2">
            <x-spinner class="text-success d-none" id="simSpinner" label="جاري تشغيل المحاكاة..." />
            <i class="ri-terminal-box-line fs-16" id="simIcon"></i>
            <span>نتائج محاكاة العمليات التشغيلية (End-to-End Simulation Results)</span>
        </h5>
        <span class="badge bg-success text-white fs-11" id="simBadge">جاهز للتنفيذ</span>
    </div>
    <div class="card-body p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle table-nowrap mb-0" id="simulationTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>القطاع الوظيفي</th>
                        <th>العملية / السيناريو</th>
                        <th>الحالة</th>
                        <th>زمن المعالجة</th>
                        <th>المعادلات والتحقق المنطقي</th>
                    </tr>
                </thead>
                <tbody id="simulationTbody">
                    <!-- تُملأ ديناميكياً عبر AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- جدول فحوصات سلامة قاعدة البيانات والاتساق المحاسبي -->
<div class="card border-0 shadow-sm ah-panel">
    <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0 fs-15 fw-bold d-flex align-items-center gap-2">
            <i class="ri-shield-keyhole-line text-primary fs-18"></i>
            <span>سجل تدقيق سلامة البيانات والاتساق المحاسبي والمخزني</span>
        </h5>
        <span class="badge bg-light text-muted fs-11" id="lastAuditedAt">آخر فحص: {{ $auditData['audited_at'] }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="auditTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">الحالة</th>
                        <th style="width: 280px;">معيار الفحص</th>
                        <th>النتيجة والتشخيص التقني</th>
                        <th style="width: 140px;" class="text-center">مستوى الأهمية</th>
                    </tr>
                </thead>
                <tbody id="auditTbody">
                    @foreach($auditData['checks'] as $check)
                    <tr>
                        <td class="text-center">
                            @if($check['status'] === 'passed')
                                <span class="badge bg-success-subtle text-success rounded-circle p-2">
                                    <i class="ri-check-line fs-14"></i>
                                </span>
                            @elseif($check['status'] === 'warning')
                                <span class="badge bg-warning-subtle text-warning rounded-circle p-2">
                                    <i class="ri-alert-line fs-14"></i>
                                </span>
                            @else
                                <span class="badge bg-danger-subtle text-danger rounded-circle p-2">
                                    <i class="ri-close-line fs-14"></i>
                                </span>
                            @endif
                        </td>
                        <td>
                            <span class="fw-bold fs-13 text-dark">{{ $check['name'] }}</span>
                        </td>
                        <td>
                            <span class="text-muted fs-12">{{ $check['details'] }}</span>
                        </td>
                        <td class="text-center">
                            @if($check['severity'] === 'critical')
                                <span class="badge bg-danger-subtle text-danger fs-11 px-2 py-1">حرج (Critical)</span>
                            @elseif($check['severity'] === 'high')
                                <span class="badge bg-warning-subtle text-warning fs-11 px-2 py-1">عالي (High)</span>
                            @elseif($check['severity'] === 'medium')
                                <span class="badge bg-info-subtle text-info fs-11 px-2 py-1">متوسط (Medium)</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary fs-11 px-2 py-1">اعتيادي (Low)</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btnRefreshAudit = document.getElementById('btnRefreshAudit');
    const btnRunSimulation = document.getElementById('btnRunSimulation');
    const chkRollback = document.getElementById('chkRollback');
    const simulationCard = document.getElementById('simulationCard');
    const simulationTbody = document.getElementById('simulationTbody');
    const simSpinner = document.getElementById('simSpinner');
    const simIcon = document.getElementById('simIcon');
    const simBadge = document.getElementById('simBadge');

    // ─── 1. تشغيل فحص البيانات الحي ─────────────────────────
    btnRefreshAudit.addEventListener('click', async function () {
        btnRefreshAudit.disabled = true;
        btnRefreshAudit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جاري الفحص...';

        try {
            const res = await fetch("{{ route('admin.diagnostics.run_audit') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (typeof window.getCsrfToken === 'function' ? window.getCsrfToken() : '{{ csrf_token() }}')
                }
            });
            const json = await res.json();
            if (json.status === 'success') {
                const data = json.data;
                document.getElementById('statHealthScore').innerText = data.health_score + '%';
                document.getElementById('statPassedChecks').innerText = data.passed_count + ' / ' + data.total_checks;
                document.getElementById('statDuration').innerHTML = data.duration_ms + ' <span class="fs-12 text-muted fw-normal">ms</span>';
                document.getElementById('lastAuditedAt').innerText = 'آخر فحص: ' + data.audited_at;

                // تحديث الجدول
                let html = '';
                data.checks.forEach(check => {
                    const icon = check.status === 'passed'
                        ? '<span class="badge bg-success-subtle text-success rounded-circle p-2"><i class="ri-check-line fs-14"></i></span>'
                        : (check.status === 'warning'
                            ? '<span class="badge bg-warning-subtle text-warning rounded-circle p-2"><i class="ri-alert-line fs-14"></i></span>'
                            : '<span class="badge bg-danger-subtle text-danger rounded-circle p-2"><i class="ri-close-line fs-14"></i></span>');

                    const sevBadge = check.severity === 'critical'
                        ? '<span class="badge bg-danger-subtle text-danger fs-11 px-2 py-1">حرج (Critical)</span>'
                        : (check.severity === 'high'
                            ? '<span class="badge bg-warning-subtle text-warning fs-11 px-2 py-1">عالي (High)</span>'
                            : (check.severity === 'medium'
                                ? '<span class="badge bg-info-subtle text-info fs-11 px-2 py-1">متوسط (Medium)</span>'
                                : '<span class="badge bg-secondary-subtle text-secondary fs-11 px-2 py-1">اعتيادي (Low)</span>'));

                    html += `<tr>
                        <td class="text-center">${icon}</td>
                        <td><span class="fw-bold fs-13 text-dark">${check.name}</span></td>
                        <td><span class="text-muted fs-12">${check.details}</span></td>
                        <td class="text-center">${sevBadge}</td>
                    </tr>`;
                });
                document.getElementById('auditTbody').innerHTML = html;
            }
        } catch (err) {
            alert('حدث خطأ أثناء تشغيل الفحص: ' + err.message);
        } finally {
            btnRefreshAudit.disabled = false;
            btnRefreshAudit.innerHTML = '<i class="ri-refresh-line me-1 align-middle"></i> <span>تحديث فحص البيانات</span>';
        }
    });

    // ─── 2. تشغيل المحاكاة الشاملة ─────────────────────────
    btnRunSimulation.addEventListener('click', async function () {
        btnRunSimulation.disabled = true;
        btnRunSimulation.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جاري المحاكاة...';
        simulationCard.classList.remove('d-none');
        simSpinner.classList.remove('d-none');
        simIcon.classList.add('d-none');
        simBadge.className = 'badge bg-warning text-dark fs-11';
        simBadge.innerText = 'جاري التنفيذ الحي لكافة السيناريوهات...';

        simulationTbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span> جاري إنشاء بيانات المحاكاة الذرية وفحص WAC ونقاط البيع والرواتب...</td></tr>`;

        try {
            const rollback = chkRollback.checked;
            const res = await fetch("{{ route('admin.diagnostics.run_simulation') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (typeof window.getCsrfToken === 'function' ? window.getCsrfToken() : '{{ csrf_token() }}')
                },
                body: JSON.stringify({ rollback: rollback })
            });
            const json = await res.json();
            if (json.status === 'success') {
                const data = json.data;
                let rowsHtml = '';
                data.steps.forEach((step, idx) => {
                    const statusBadge = step.status === 'passed'
                        ? '<span class="badge bg-success-subtle text-success fs-12 px-2 py-1 fw-bold"><i class="ri-checkbox-circle-fill me-1 align-middle"></i> اجتياز بنجاح</span>'
                        : '<span class="badge bg-danger-subtle text-danger fs-12 px-2 py-1 fw-bold"><i class="ri-close-circle-fill me-1 align-middle"></i> فشل</span>';

                    rowsHtml += `<tr>
                        <td class="fw-bold text-muted">${idx + 1}</td>
                        <td class="fw-semibold text-dark">${step.sector}</td>
                        <td>${step.name}</td>
                        <td>${statusBadge}</td>
                        <td><span class="badge bg-light text-dark font-monospace">${step.duration} ms</span></td>
                        <td><small class="text-muted font-monospace">${step.details}</small></td>
                    </tr>`;
                });
                simulationTbody.innerHTML = rowsHtml;

                if (data.all_passed) {
                    simBadge.className = 'badge bg-success text-white fs-11';
                    simBadge.innerText = `نجاح 100% (${data.passed_count}/${data.total_steps}) في ${data.execution_time} ms`;
                } else {
                    simBadge.className = 'badge bg-danger text-white fs-11';
                    simBadge.innerText = `خلل (${data.passed_count}/${data.total_steps}) اجتازت فقط`;
                }
            }
        } catch (err) {
            simulationTbody.innerHTML = `<tr><td colspan="6" class="text-danger py-3 text-center">حدث خطأ أثناء الاتصال: ${err.message}</td></tr>`;
            simBadge.className = 'badge bg-danger text-white fs-11';
            simBadge.innerText = 'فشل الاتصال';
        } finally {
            simSpinner.classList.add('d-none');
            simIcon.classList.remove('d-none');
            btnRunSimulation.disabled = false;
            btnRunSimulation.innerHTML = '<i class="ri-rocket-line fs-16"></i> <span>تشغيل المحاكاة الشاملة الحية</span>';
        }
    });
});
</script>
@endpush
@endsection
