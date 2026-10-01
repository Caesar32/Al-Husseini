<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const generatePayrollModal = new bootstrap.Modal(document.getElementById('generatePayrollModal'));
    const managerDeductionModal = new bootstrap.Modal(document.getElementById('managerDeductionModal'));
    const payslipModal = new bootstrap.Modal(document.getElementById('payslipModal'));

    // Open Generate Modal
    document.getElementById('btnOpenGenerateModal').onclick = () => generatePayrollModal.show();
    document.getElementById('btnOpenManagerDeduction').onclick = () => managerDeductionModal.show();

    // Direct Deduct button from table
    document.querySelectorAll('.btn-direct-deduct').forEach(btn => {
        btn.onclick = function() {
            const empId = this.getAttribute('data-id');
            document.getElementById('deductEmployeeSelect').value = empId;
            managerDeductionModal.show();
        };
    });

    // Generate Payroll Form Submission
    document.getElementById('generatePayrollForm').onsubmit = function(e) {
        e.preventDefault();

        const branchId = document.getElementById('genBranchSelect').value;
        const year = document.getElementById('genYearInput').value;
        const month = document.getElementById('genMonthInput').value;

        fetch('/admin/hr/payroll/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                branch_id: branchId,
                year: year,
                month: month
            })
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'تعذر احتساب المسير.');
            return data;
        })
        .then(data => {
            generatePayrollModal.hide();
            Swal.fire({
                icon: 'success',
                title: 'تم بنجاح!',
                text: data.message,
                confirmButtonText: 'تحديث الصفحة'
            }).then(() => window.location.reload());
        })
        .catch(err => {
            Swal.fire('خطأ في العملية', err.message, 'error');
        });
    };

    // Manager Deduction Form Submission
    document.getElementById('managerDeductionForm').onsubmit = function(e) {
        e.preventDefault();

        const empId = document.getElementById('deductEmployeeSelect').value;
        const amount = document.getElementById('deductAmountInput').value;
        const reason = document.getElementById('deductReasonSelect').value;
        const notes = document.getElementById('deductNotesInput').value;

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
                deduction_date: new Date().toISOString().split('T')[0]
            })
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'تعذر تسجيل الجزاء.');
            return data;
        })
        .then(data => {
            managerDeductionModal.hide();
            Swal.fire({
                icon: 'success',
                title: 'تم اعتماد الخصم!',
                text: data.message,
                confirmButtonText: 'حسناً'
            }).then(() => window.location.reload());
        })
        .catch(err => {
            Swal.fire('خطأ', err.message, 'error');
        });
    };

    // Approve Payroll Batch
    document.querySelectorAll('.btn-approve-payroll').forEach(btn => {
        btn.onclick = function() {
            const id = this.getAttribute('data-id');
            const batchRow = this.closest('tr.payroll-batch-row');
            const hasDebt = batchRow?.getAttribute('data-has-debt') === '1';

            Swal.fire({
                title: hasDebt ? 'اعتماد مسير يحتوي على أرصدة مرحّلة' : 'اعتماد مسير الرواتب',
                text: hasDebt
                    ? 'يوجد موظفون لديهم مبالغ غير مستردة بسبب تجاوز الخصومات للدخل. راجع القسائم والأرصدة المرحّلة قبل تأكيد الاعتماد.'
                    : 'هل أنت متأكد من اعتماد هذا المسير وإرساله للصرف النهائي؟',
                icon: hasDebt ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonText: hasDebt ? 'راجعت الأرصدة، اعتماد المسير' : 'نعم، اعتماد الآن',
                cancelButtonText: 'إلغاء'
            }).then((res) => {
                if (res.isConfirmed) {
                    fetch(`/admin/hr/payroll/${id}/approve`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            confirm_debt_review: hasDebt
                        })
                    })
                    .then(async r => {
                        const data = await r.json();
                        if (!r.ok) throw new Error(data.message || 'فشل الاعتماد.');
                        return data;
                    })
                    .then(data => {
                        Swal.fire('تم الاعتماد!', data.message, 'success').then(() => window.location.reload());
                    })
                    .catch(err => Swal.fire('خطأ', err.message, 'error'));
                }
            });
        };
    });

    // Disburse Payroll Batch
    document.querySelectorAll('.btn-disburse-payroll').forEach(btn => {
        btn.onclick = function() {
            const id = this.getAttribute('data-id');

            Swal.fire({
                title: 'تأكيد صرف المسير',
                text: 'سيتم تحويل حالة المسير إلى (مصروف) وإغلاق حسابات الشهر لهذا الفرع.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، تأكيد الصرف',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#0ab39c'
            }).then((res) => {
                if (res.isConfirmed) {
                    fetch(`/admin/hr/payroll/${id}/disburse`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    })
                    .then(async r => {
                        const data = await r.json();
                        if (!r.ok) throw new Error(data.message || 'فشل الصرف.');
                        return data;
                    })
                    .then(data => {
                        Swal.fire('تم الصرف بنجاح!', data.message, 'success').then(() => window.location.reload());
                    })
                    .catch(err => Swal.fire('خطأ', err.message, 'error'));
                }
            });
        };
    });

    // Cancel Deduction
    document.querySelectorAll('.btn-cancel-deduction').forEach(btn => {
        btn.onclick = function() {
            const id = this.getAttribute('data-id');

            Swal.fire({
                title: 'إلغاء قرار الخصم',
                text: 'هل ترغب بإلغاء هذا الجزاء واسترداده للموظف؟',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، إلغاء الخصم',
                cancelButtonText: 'تراجع'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch(`/admin/hr/deductions/${id}/status`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ status: 'cancelled' })
                    })
                    .then(async r => {
                        const data = await r.json();
                        if (!r.ok) throw new Error(data.message || 'تعذر الإلغاء');
                        return data;
                    })
                    .then(data => {
                        Swal.fire('تم الإلغاء!', data.message, 'success').then(() => window.location.reload());
                    })
                    .catch(err => Swal.fire('خطأ', err.message, 'error'));
                }
            });
        };
    });

    // View Payslip Modal — renders the STORED payroll item (from the payroll show JSON endpoint),
    // never a client-side estimate.
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, ch => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch]));

    document.querySelectorAll('.btn-view-payslip').forEach(btn => {
        btn.onclick = function() {
            const payrollId = this.getAttribute('data-payroll-id');
            const employeeId = Number(this.getAttribute('data-employee-id'));
            const branchName = this.getAttribute('data-branch') || '';
            if (!payrollId) return;

            fetch(`{{ url('/admin/hr/payroll') }}/${encodeURIComponent(payrollId)}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(async res => {
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'تعذر تحميل قسيمة الراتب.');
                return data;
            })
            .then(payroll => {
                const item = (payroll.items || []).find(i => Number(i.employee_id) === employeeId);
                if (!item) throw new Error('لا يوجد بند مسجل لهذا الموظف في المسير.');
                renderPayslip(payroll, item, branchName);
                payslipModal.show();
            })
            .catch(err => Swal.fire('خطأ', err.message, 'error'));
        };
    });

    function renderPayslip(payroll, item, branchName) {
            const emp = item.employee || {};
            const name = escapeHtml(emp.full_name);
            const code = escapeHtml(emp.employee_code);
            const role = escapeHtml(emp.job_title?.title_name || emp.job_title?.title || '');
            const branch = escapeHtml(payroll.branch?.name || branchName);
            const base = Number(item.basic_salary || 0);
            const allow = Number(item.total_allowance || 0);
            const overtime = Number(item.total_overtime || 0);
            const ded = Number(item.total_deduction || 0);
            const debtRepayment = Number(item.debt_repayment || 0);
            const net = Number(item.net_salary || 0);
            const shortfall = Number(item.carried_debt || 0);
            const period = `${Number(payroll.month)} / ${Number(payroll.year)}`;

            document.getElementById('payslipPrintArea').innerHTML = `
                <div class="border p-4 rounded-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                        <div>
                            <h4 class="fw-bold mb-0 text-primary">مركز الحسيني لبطاريات السيارات</h4>
                            <small class="text-muted">قسيمة استحقاق وصرف الراتب الشهري</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light text-body font-monospace fs-13">كود: ${code}</span>
                            <div class="text-muted fs-12 mt-1">تاريخ الإصدار: ${new Date().toLocaleDateString('ar-EG')}</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <span class="text-muted fs-12">اسم الموظف:</span>
                            <h6 class="fw-bold mb-0">${name}</h6>
                        </div>
                        <div class="col-6">
                            <span class="text-muted fs-12">المسمى الوظيفي:</span>
                            <h6 class="fw-bold mb-0">${role}</h6>
                        </div>
                        <div class="col-6">
                            <span class="text-muted fs-12">فرع المركز:</span>
                            <div class="fw-semibold">${branch}</div>
                        </div>
                        <div class="col-6">
                            <span class="text-muted fs-12">شهر الاستحقاق:</span>
                            <div class="fw-semibold font-monospace">${period}</div>
                        </div>
                    </div>

                    <table class="table table-bordered align-middle mb-4">
                        <thead class="table-light">
                            <tr>
                                <th>بيان الاستحقاقات</th>
                                <th class="text-end">المبلغ (ج.م)</th>
                                <th>بيان الاستقطاعات</th>
                                <th class="text-end">المبلغ (ج.م)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>الراتب الأساسي</td>
                                <td class="text-end fw-bold text-dark">${base.toLocaleString('ar-EG')}</td>
                                <td>الخصومات والجزاءات الإدارية</td>
                                <td class="text-end fw-bold text-danger">-${ded.toLocaleString('ar-EG')}</td>
                            </tr>
                            <tr>
                                <td>إجمالي البدلات والعمولات</td>
                                <td class="text-end fw-bold text-info">${allow.toLocaleString('ar-EG')}</td>
                                <td>منها سداد رصيد مرحّل سابق</td>
                                <td class="text-end fw-bold text-muted">${debtRepayment.toLocaleString('ar-EG')}</td>
                            </tr>
                            <tr>
                                <td>العمل الإضافي</td>
                                <td class="text-end fw-bold text-info">${overtime.toLocaleString('ar-EG')}</td>
                                <td>أيام الغياب المحتسبة</td>
                                <td class="text-end fw-bold text-muted">${Number(item.absent_days || 0)}</td>
                            </tr>
                            <tr class="table-light fw-bold">
                                <td>إجمالي الدخل</td>
                                <td class="text-end text-success">${(base + allow + overtime).toLocaleString('ar-EG')} ج.م</td>
                                <td>إجمالي الاستقطاع</td>
                                <td class="text-end text-danger">-${ded.toLocaleString('ar-EG')} ج.م</td>
                            </tr>
                        </tbody>
                    </table>

                    ${shortfall > 0 ? `
                    <div class="alert alert-warning text-start mb-4">
                        <strong><i class="ri-error-warning-line me-1"></i> يتطلب مراجعة قبل الصرف</strong>
                        <div class="mt-1">الخصومات تجاوزت إجمالي الدخل بمبلغ <strong>${shortfall.toLocaleString('ar-EG')} ج.م</strong>. تم تسجيل العجز كرصيد مستحق على الموظف ولا يُفقد عند إظهار صافي الراتب.</div>
                    </div>` : ''}
                    <div class="${shortfall > 0 ? 'p-3 bg-warning-subtle' : 'p-3 bg-success-subtle'} rounded text-center mb-4">
                        <span class="text-muted fs-13 d-block mb-1">صافي الراتب المستحق للصرف النهائي:</span>
                        <h3 class="fw-extrabold ${shortfall > 0 ? 'text-warning' : 'text-success'} mb-0">${net.toLocaleString('ar-EG')} ج.م</h3>
                        ${shortfall > 0 ? `<div class="text-danger fs-12 mt-2">الرصيد المرحّل المستحق: ${shortfall.toLocaleString('ar-EG')} ج.م</div>` : ''}
                    </div>

                    <div class="row pt-4 text-center fs-12 text-muted border-top">
                        <div class="col-4">توقيع المستلم: .....................</div>
                        <div class="col-4">توقيع الموارد البشرية: .....................</div>
                        <div class="col-4">اعتماد المدير المالي: .....................</div>
                    </div>
                </div>
            `;
    }

    // -------------------------------------------------------------
    // Universal Arabic Normalization & Fuzzy-Matching Engine
    // -------------------------------------------------------------
    function normalizeArabic(text) {
        if (!text) return '';
        return text.toString().toLowerCase()
            .replace(/[\u064B-\u065F\u0670]/g, '') // remove tashkeel/diacritics
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

        // Match without punctuation/dashes (e.g. EMP-0101 vs EMP0101 vs 0101)
        const strippedTarget = rawTarget.replace(/[\s\-_]+/g, '');
        const strippedQuery = rawQuery.replace(/[\s\-_]+/g, '');
        return strippedTarget.includes(strippedQuery) || strippedQuery.includes(strippedTarget);
    }

    // Client-side search and branch filter on the payroll tables
    const searchInput = document.getElementById('searchPayrollInput');
    const branchFilter = document.getElementById('payrollBranchFilter');
    const btnClearSearch = document.getElementById('btnClearPayrollSearch');

    function filterPayrollRows() {
        const query = (searchInput?.value || '').trim();
        const branch = branchFilter?.value || 'all';

        // 1. Filter Employee Monthly Breakdown Table
        const empRows = document.querySelectorAll('#payrollTableBody tr.payroll-emp-row');
        let visibleEmpCount = 0;

        empRows.forEach(row => {
            const rowBranch = row.getAttribute('data-branch');
            const rowName = row.getAttribute('data-name') || '';
            const rowCode = row.getAttribute('data-code') || '';
            const rowRole = row.getAttribute('data-role') || '';
            const rowDept = row.getAttribute('data-department') || '';
            const rowPhone = row.getAttribute('data-phone') || '';

            const matchesBranch = (branch === 'all' || rowBranch === branch);
            const matchesQuery = !query ||
                isMatch(rowName, query) ||
                isMatch(rowCode, query) ||
                isMatch(rowRole, query) ||
                isMatch(rowDept, query) ||
                isMatch(rowPhone, query);

            if (matchesBranch && matchesQuery) {
                row.style.display = '';
                visibleEmpCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const noEmpResultsRow = document.getElementById('payrollNoResultsRow');
        if (noEmpResultsRow) {
            noEmpResultsRow.style.display = (visibleEmpCount === 0 && empRows.length > 0) ? '' : 'none';
        }

        // 2. Filter Deductions Log Table
        const dedRows = document.querySelectorAll('#deductionsTableBody tr.deduction-log-row');
        let visibleDedCount = 0;

        dedRows.forEach(row => {
            const rowEmp = row.getAttribute('data-employee') || '';
            const rowCode = row.getAttribute('data-code') || '';
            const rowId = row.getAttribute('data-id') || '';
            const rowReason = row.getAttribute('data-reason') || '';

            const matchesQuery = !query ||
                isMatch(rowEmp, query) ||
                isMatch(rowCode, query) ||
                isMatch(rowId, query) ||
                isMatch(rowReason, query);

            if (matchesQuery) {
                row.style.display = '';
                visibleDedCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const noDedResultsRow = document.getElementById('deductionNoResultsRow');
        if (noDedResultsRow) {
            noDedResultsRow.style.display = (visibleDedCount === 0 && dedRows.length > 0) ? '' : 'none';
        }

        // 3. Filter Payroll Batches Table by Branch
        const batchRows = document.querySelectorAll('tr.payroll-batch-row');
        let visibleBatchCount = 0;

        batchRows.forEach(row => {
            const rowBranch = row.getAttribute('data-branch');
            const matchesBranch = (branch === 'all' || rowBranch === branch);

            if (matchesBranch) {
                row.style.display = '';
                visibleBatchCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const noBatchResultsRow = document.getElementById('batchNoResultsRow');
        if (noBatchResultsRow) {
            noBatchResultsRow.style.display = (visibleBatchCount === 0 && batchRows.length > 0) ? '' : 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterPayrollRows);
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                filterPayrollRows();
            }
        });
    }

    if (branchFilter) {
        branchFilter.addEventListener('change', filterPayrollRows);
    }

    if (btnClearSearch) {
        btnClearSearch.addEventListener('click', function() {
            if (searchInput) {
                searchInput.value = '';
                filterPayrollRows();
                searchInput.focus();
            }
        });
    }

    // Check URL parameters on page load (e.g. ?search=EMP-0101&branch_id=1)
    const urlParams = new URLSearchParams(window.location.search);
    const searchParam = urlParams.get('search');
    const branchParam = urlParams.get('branch_id');

    if (searchParam && searchInput) {
        searchInput.value = searchParam;
    }
    if (branchParam && branchFilter) {
        branchFilter.value = branchParam;
    }

    // Execute initial filtering if parameters are present
    if (searchParam || (branchParam && branchParam !== 'all')) {
        filterPayrollRows();
    }
});
</script>
