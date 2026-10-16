/**
 * Live diagnostics (resources/views/admin/diagnostics/index.blade.php), frontend F4.
 * Config (data-config on [data-page="diagnostics"]): { auditUrl, simulationUrl }
 * Moved from the page's inline script; check and step texts are now escaped before innerHTML.
 */
(function () {
    'use strict';

    Admin.page.register('diagnostics', function (root, config) {
        const esc = Admin.esc;
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
                const res = await fetch(config.auditUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (typeof window.getCsrfToken === 'function' ? window.getCsrfToken() : (document.querySelector('meta[name="csrf-token"]')?.content || ''))
                    }
                });
                const json = await res.json();
                if (json.status === 'success') {
                    const data = json.data;
                    document.getElementById('statHealthScore').innerText = data.health_score + '%';
                    document.getElementById('statPassedChecks').innerText = data.passed_count + ' / ' + data.total_checks;
                    document.getElementById('statDuration').innerHTML = esc(data.duration_ms) + ' <span class="fs-12 text-muted fw-normal">ms</span>';
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
                            <td><span class="fw-bold fs-13 text-dark">${esc(check.name)}</span></td>
                            <td><span class="text-muted fs-12">${esc(check.details)}</span></td>
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
                const res = await fetch(config.simulationUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (typeof window.getCsrfToken === 'function' ? window.getCsrfToken() : (document.querySelector('meta[name="csrf-token"]')?.content || ''))
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
                            <td class="fw-semibold text-dark">${esc(step.sector)}</td>
                            <td>${esc(step.name)}</td>
                            <td>${statusBadge}</td>
                            <td><span class="badge bg-light text-dark font-monospace">${esc(step.duration)} ms</span></td>
                            <td><small class="text-muted font-monospace">${esc(step.details)}</small></td>
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
                simulationTbody.innerHTML = `<tr><td colspan="6" class="text-danger py-3 text-center">حدث خطأ أثناء الاتصال: ${esc(err.message)}</td></tr>`;
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
})();
