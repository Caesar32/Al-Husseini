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

            Swal.fire({
                title: 'اعتماد مسير الرواتب',
                text: 'هل أنت متأكد من اعتماد هذا المسير وإرساله للصرف النهائي؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، اعتماد الآن',
                cancelButtonText: 'إلغاء'
            }).then((res) => {
                if (res.isConfirmed) {
                    fetch(`/admin/hr/payroll/${id}/approve`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
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

    // View Payslip Modal
    document.querySelectorAll('.btn-view-payslip').forEach(btn => {
        btn.onclick = function() {
            const name = this.getAttribute('data-name');
            const code = this.getAttribute('data-code');
            const role = this.getAttribute('data-role');
            const branch = this.getAttribute('data-branch');
            const base = Number(this.getAttribute('data-base') || 0);
            const allow = Number(this.getAttribute('data-allow') || 0);
            const ded = Number(this.getAttribute('data-ded') || 0);
            const net = Number(this.getAttribute('data-net') || 0);

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
                            <div class="fw-semibold font-monospace">${new Date().getMonth() + 1} / ${new Date().getFullYear()}</div>
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
                                <td>إجمالي البدلات والمكافآت</td>
                                <td class="text-end fw-bold text-info">+${allow.toLocaleString('ar-EG')}</td>
                                <td>تأمينات واستقطاعات أخرى</td>
                                <td class="text-end fw-bold text-muted">0</td>
                            </tr>
                            <tr class="table-light fw-bold">
                                <td>إجمالي الدخل</td>
                                <td class="text-end text-success">${(base + allow).toLocaleString('ar-EG')} ج.م</td>
                                <td>إجمالي الاستقطاع</td>
                                <td class="text-end text-danger">-${ded.toLocaleString('ar-EG')} ج.م</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="p-3 bg-success-subtle rounded text-center mb-4">
                        <span class="text-muted fs-13 d-block mb-1">صافي الراتب المستحق للصرف النهائي:</span>
                        <h3 class="fw-extrabold text-success mb-0">${net.toLocaleString('ar-EG')} جنيه مصري</h3>
                    </div>

                    <div class="row pt-4 text-center fs-12 text-muted border-top">
                        <div class="col-4">توقيع المستلم: .....................</div>
                        <div class="col-4">توقيع الموارد البشرية: .....................</div>
                        <div class="col-4">اعتماد المدير المالي: .....................</div>
                    </div>
                </div>
            `;

            payslipModal.show();
        };
    });

    // Client-side search and branch filter on the table
    const searchInput = document.getElementById('searchPayrollInput');
    const branchFilter = document.getElementById('payrollBranchFilter');

    function filterPayrollRows() {
        const query = searchInput.value.trim().toLowerCase();
        const branch = branchFilter.value;

        document.querySelectorAll('#payrollTableBody tr').forEach(row => {
            const rowBranch = row.getAttribute('data-branch');
            const rowName = row.getAttribute('data-name') || '';
            const rowRole = row.getAttribute('data-role') || '';

            const matchesBranch = (branch === 'all' || rowBranch === branch);
            const matchesQuery = (!query || rowName.includes(query) || rowRole.includes(query));

            row.style.display = (matchesBranch && matchesQuery) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterPayrollRows);
    branchFilter.addEventListener('change', filterPayrollRows);
});
</script>
