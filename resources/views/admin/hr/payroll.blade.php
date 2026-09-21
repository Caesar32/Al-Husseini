@extends('admin.layouts.master')

@section('title', 'مسير الرواتب والخصومات | مجموعة الحسيني')

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'الموارد البشرية', 'title' => 'مسير الرواتب والخصومات الإدارية'])

    <!-- Top Stats -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-primary border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">إجمالي الرواتب الأساسية</p>
                            <h4 class="fs-22 fw-bold text-primary mb-0 mt-2" id="stat-total-base">0 ج.م</h4>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20">
                                <i class="ri-bank-card-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-info border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">إجمالي البدلات والمكافآت</p>
                            <h4 class="fs-22 fw-bold text-info mb-0 mt-2" id="stat-total-allowances">0 ج.م</h4>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-info-subtle text-info rounded-circle fs-20">
                                <i class="ri-gift-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-danger border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">إجمالي الخصومات المطبقة</p>
                            <h4 class="fs-22 fw-bold text-danger mb-0 mt-2" id="stat-total-deductions">0 ج.م</h4>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-20">
                                <i class="ri-scissors-cut-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-success border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">صافي المستحق للصرف</p>
                            <h4 class="fs-22 fw-bold text-success mb-0 mt-2" id="stat-total-net">0 ج.م</h4>
                        </div>
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-20">
                                <i class="ri-hand-coin-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manager Control & Actions Bar -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card mb-0">
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-4 col-md-6">
                            <div class="search-box">
                                <input type="text" class="form-control" id="searchPayrollInput" placeholder="بحث باسم الموظف أو الوظيفة...">
                                <i class="ri-search-line search-icon"></i>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <select class="form-select" id="payrollDepartmentFilter">
                                <option value="all">جميع أقسام وورش المركز</option>
                                <option value="المبيعات والمعرض">المبيعات والمعرض (صالة البيع)</option>
                                <option value="ورشة الصيانة والشحن">ورشة الصيانة والشحن والإصلاح</option>
                                <option value="فنيو التركيب والكهرباء">فنيو التركيب والكهرباء</option>
                                <option value="خدمة الطوارئ والإنقاذ المتنقل">خدمة الطوارئ والإنقاذ المتنقل</option>
                                <option value="المخازن وسلاسل الإمداد">المخازن والبطاريات المسترجعة</option>
                                <option value="الإدارة والإشراف">الإدارة والإشراف</option>
                            </select>
                        </div>

                        <div class="col-lg-5 col-md-12 text-md-end">
                            <div class="d-flex gap-2 justify-content-lg-end">
                                <button type="button" class="btn btn-danger" id="btnOpenManagerDeduction">
                                    <i class="ri-hand-coin-fill align-bottom me-1"></i> تطبيق خصم إداري
                                </button>
                                <button type="button" class="btn btn-soft-secondary" onclick="window.print();">
                                    <i class="ri-printer-line align-bottom me-1"></i> طباعة المسير
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payroll Table Card -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h5 class="card-title mb-0 flex-grow-1"><i class="ri-file-list-3-line text-primary me-2"></i> كشف مسير الرواتب لشهر {{ date('F Y') }}</h5>
                    <div class="flex-shrink-0">
                        <span class="badge bg-success-subtle text-success fs-12 px-3 py-2">
                            <i class="ri-shield-check-fill me-1"></i> معتمد من الإدارة المالية
                        </span>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle table-nowrap table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">الموظف</th>
                                    <th scope="col">القسم</th>
                                    <th scope="col">الراتب الأساسي</th>
                                    <th scope="col">إجمالي البدلات</th>
                                    <th scope="col">الخصومات المطبقة</th>
                                    <th scope="col">صافي الراتب المستحق</th>
                                    <th scope="col">الحالة</th>
                                    <th scope="col" class="text-center">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody id="payrollTableBody">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Deductions History Log Card -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h5 class="card-title mb-0 flex-grow-1"><i class="ri-history-line text-danger me-2"></i> سجل قرارات الخصم الإدارية المعتمدة</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle table-nowrap mb-0 table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>رقم القرار</th>
                                    <th>الموظف</th>
                                    <th>المبلغ المخصوم</th>
                                    <th>سبب الخصم</th>
                                    <th>تاريخ القرار</th>
                                    <th>ملاحظات المدير</th>
                                    <th class="text-center">إلغاء الخصم</th>
                                </tr>
                            </thead>
                            <tbody id="deductionsHistoryBody">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Manager Apply Deduction -->
    <div class="modal fade" id="managerDeductionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0">
                <div class="modal-header bg-danger text-white p-3">
                    <h5 class="modal-title fw-bold text-white"><i class="ri-hand-coin-line me-2"></i> قرار إداري: تطبيق خصم على موظف</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="managerDeductionForm">
                    <div class="modal-body p-4">
                        <div class="alert alert-danger-subtle text-danger border-0 p-3 mb-3">
                            <i class="ri-error-warning-line me-2 fs-16 align-middle"></i>
                            <strong>تنبيه إداري:</strong> الخصم المعتمد سيتم استقطاعه فورياً من صافي راتب الموظف للشهر الحالي، وسيرسل النظام إشعاراً رسمياً للمدير والموظف.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="deductEmployeeSelect" class="form-label fw-semibold">اختر الموظف <span class="text-danger">*</span></label>
                                <select class="form-select" id="deductEmployeeSelect" required>
                                    <option value="">-- اختر الموظف --</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="deductAmountInput" class="form-label fw-semibold">مبلغ الخصم (جنيه مصري EGP) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="deductAmountInput" required min="50" step="50" placeholder="مثال: 500">
                            </div>

                            <div class="col-md-12">
                                <label for="deductReasonSelect" class="form-label fw-semibold">سبب الخصم <span class="text-danger">*</span></label>
                                <select class="form-select" id="deductReasonSelect" required>
                                    <option value="تأخير عن موعد فتح صالة المعرض واستقبال العملاء">تأخير عن موعد فتح صالة المعرض واستقبال العملاء</option>
                                    <option value="تأخير عن بدء وردية ورشة فحص وشحن البطاريات">تأخير عن بدء وردية ورشة فحص وشحن البطاريات</option>
                                    <option value="تأخير في الاستجابة لبلاغ طوارئ إنقاذ بطارية طريق">تأخير في الاستجابة لبلاغ طوارئ إنقاذ بطارية طريق</option>
                                    <option value="غياب كامل بدون إذن مسبق في يوم ذروة بيع">غياب كامل بدون إذن مسبق في يوم ذروة بيع</option>
                                    <option value="إهمال في فحص كفاءة البطارية ودينامو سيارة العميل">إهمال في فحص كفاءة البطارية ودينامو سيارة العميل</option>
                                    <option value="خطأ أو إهمال في تسجيل بطاقة ضمان البطارية">خطأ أو إهمال في تسجيل بطاقة ضمان البطارية</option>
                                    <option value="تلف كابلات أو معدات أثناء الفحص والشحن">تلف كابلات أو معدات أثناء الفحص والشحن</option>
                                    <option value="مخالفة لوائح وسياسات مركز الحسيني للبطاريات">مخالفة لوائح وسياسات مركز الحسيني للبطاريات</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label for="deductNotesInput" class="form-label fw-semibold">تفاصيل وسند القرار الإداري</label>
                                <textarea class="form-control" id="deductNotesInput" rows="3" placeholder="اكتب أسباب وحيثيات القرار وتاريخ المخالفة..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-danger fw-bold">
                            <i class="ri-check-line align-middle me-1"></i> اعتماد الخصم فورياً
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Digital Printable Payslip -->
    <div class="modal fade" id="payslipModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0">
                <div class="modal-header bg-dark text-white p-3">
                    <h5 class="modal-title fw-bold text-white"><i class="ri-file-paper-2-line me-2"></i> قسيمة الراتب الرسمية (Payslip)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="payslipPrintArea">
                    <!-- Populated dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-primary" onclick="window.print();">
                        <i class="ri-printer-line me-1"></i> طباعة القسيمة
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const managerDeductionModal = new bootstrap.Modal(document.getElementById('managerDeductionModal'));
    const payslipModal = new bootstrap.Modal(document.getElementById('payslipModal'));

    function populateEmployeeDropdown() {
        if (!window.AlHusseiniHR) return;
        const employees = window.AlHusseiniHR.getEmployees();
        const select = document.getElementById('deductEmployeeSelect');
        const prevVal = select.value;

        select.innerHTML = '<option value="">-- اختر الموظف --</option>';
        employees.forEach(emp => {
            select.innerHTML += `<option value="${emp.id}">${emp.name} (${emp.id} - ${emp.department})</option>`;
        });
        if (prevVal) select.value = prevVal;
    }

    function renderPayroll() {
        if (!window.AlHusseiniHR) return;
        const employees = window.AlHusseiniHR.getEmployees();
        const allDeductions = window.AlHusseiniHR.getDeductions();
        const searchTerm = document.getElementById('searchPayrollInput').value.trim().toLowerCase();
        const selectedDept = document.getElementById('payrollDepartmentFilter').value;

        const filtered = employees.filter(emp => {
            const matchesSearch = emp.name.toLowerCase().includes(searchTerm) || emp.role.toLowerCase().includes(searchTerm);
            const matchesDept = selectedDept === 'all' || emp.department === selectedDept;
            return matchesSearch && matchesDept;
        });

        let totalBase = 0;
        let totalAllowances = 0;
        let totalDeductions = 0;
        let totalNet = 0;

        const tbody = document.getElementById('payrollTableBody');
        let html = '';

        filtered.forEach(emp => {
            const base = Number(emp.baseSalary || 0);
            const allow = Number(emp.allowances || 0);
            const empDeductionsList = allDeductions.filter(d => d.employeeId === emp.id);
            const ded = empDeductionsList.reduce((sum, d) => sum + Number(d.amount), 0);
            const net = Math.max(0, (base + allow) - ded);

            totalBase += base;
            totalAllowances += allow;
            totalDeductions += ded;
            totalNet += net;

            html += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="${emp.avatar}" alt="" class="avatar-xs rounded-circle me-2 shadow-sm">
                            <div>
                                <h6 class="mb-0 fs-13 fw-bold">${emp.name}</h6>
                                <small class="text-muted font-monospace">${emp.id}</small>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge bg-light text-body fs-12">${emp.department}</span></td>
                    <td><span class="fw-bold fs-13 text-dark">${base.toLocaleString('ar-EG')} ج.م</span></td>
                    <td><span class="fw-bold fs-13 text-info">+${allow.toLocaleString('ar-EG')} ج.م</span></td>
                    <td>
                        ${ded > 0 
                            ? `<span class="badge bg-danger-subtle text-danger fs-12 fw-bold font-monospace">-${ded.toLocaleString('ar-EG')} ج.م</span>` 
                            : `<span class="badge bg-light text-muted fs-12">0 ج.م</span>`}
                    </td>
                    <td>
                        <span class="fw-bold fs-14 text-success">${net.toLocaleString('ar-EG')} ج.م</span>
                    </td>
                    <td>
                        <span class="badge bg-success-subtle text-success fs-12 px-2 py-1"><i class="ri-check-double-line me-1"></i>جاهز للصرف</span>
                    </td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                            <button type="button" class="btn btn-sm btn-soft-danger btn-direct-deduct" data-id="${emp.id}" title="تطبيق خصم">
                                <i class="ri-hand-coin-line"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-soft-primary btn-view-payslip" data-id="${emp.id}" title="قسيمة الراتب">
                                <i class="ri-file-text-line me-1"></i> القسيمة
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html || `<tr><td colspan="8" class="text-center py-4 text-muted">لا يوجد موظفون يطابقون البحث</td></tr>`;

        // Update Stat Cards
        document.getElementById('stat-total-base').textContent = `${totalBase.toLocaleString('ar-EG')} ج.م`;
        document.getElementById('stat-total-allowances').textContent = `${totalAllowances.toLocaleString('ar-EG')} ج.م`;
        document.getElementById('stat-total-deductions').textContent = `${totalDeductions.toLocaleString('ar-EG')} ج.م`;
        document.getElementById('stat-total-net').textContent = `${totalNet.toLocaleString('ar-EG')} ج.م`;

        // Render Deductions History
        const historyBody = document.getElementById('deductionsHistoryBody');
        if (allDeductions.length === 0) {
            historyBody.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-muted">لا توجد خصومات مطبقة حتى الآن</td></tr>`;
        } else {
            let histHtml = '';
            allDeductions.forEach(d => {
                const emp = employees.find(e => e.id === d.employeeId);
                const empName = emp ? emp.name : d.employeeId;

                histHtml += `
                    <tr>
                        <td><span class="badge bg-light text-body font-monospace">${d.decisionNo || d.id}</span></td>
                        <td><span class="fw-bold text-dark">${empName}</span></td>
                        <td><span class="badge bg-danger-subtle text-danger fw-bold fs-12">-${Number(d.amount).toLocaleString('ar-EG')} ج.م</span></td>
                        <td><span class="text-muted fs-12">${d.reason}</span></td>
                        <td><span class="fs-12">${d.date}</span></td>
                        <td><small class="text-muted">${d.managerNotes || '-'}</small></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-ghost-danger btn-delete-deduction" data-id="${d.id}" title="إلغاء هذا الخصم">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            historyBody.innerHTML = histHtml;
        }

        attachPayrollEvents();
    }

    function attachPayrollEvents() {
        // Direct Deduct Button in Table
        document.querySelectorAll('.btn-direct-deduct').forEach(btn => {
            btn.onclick = function() {
                const id = this.getAttribute('data-id');
                document.getElementById('deductEmployeeSelect').value = id;
                document.getElementById('deductAmountInput').value = '300';
                managerDeductionModal.show();
            };
        });

        // Delete Deduction
        document.querySelectorAll('.btn-delete-deduction').forEach(btn => {
            btn.onclick = function() {
                const id = this.getAttribute('data-id');
                Swal.fire({
                    title: 'إلغاء الخصم المعتمد',
                    text: 'هل تريد التراجع عن قرار الخصم هذا وإعادة المبلغ إلى صافي راتب الموظف؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، إلغاء الخصم',
                    cancelButtonText: 'إبقاء الخصم',
                    confirmButtonColor: '#2a9d8f'
                }).then((res) => {
                    if (res.isConfirmed) {
                        window.AlHusseiniHR.deleteDeduction(id);
                        Swal.fire('تم الإلغاء!', 'تمت تسوية الراتب بنجاح.', 'success');
                    }
                });
            };
        });

        // View Payslip Modal
        document.querySelectorAll('.btn-view-payslip').forEach(btn => {
            btn.onclick = function() {
                const id = this.getAttribute('data-id');
                const emp = window.AlHusseiniHR.getEmployeeById(id);
                if (!emp) return;

                const allDeds = window.AlHusseiniHR.getDeductions().filter(d => d.employeeId === id);
                const totalDed = allDeds.reduce((sum, d) => sum + Number(d.amount), 0);
                const base = Number(emp.baseSalary || 0);
                const allow = Number(emp.allowances || 0);
                const net = Math.max(0, (base + allow) - totalDed);

                let dedListHtml = '';
                if (allDeds.length === 0) {
                    dedListHtml = '<tr><td colspan="2" class="text-muted text-center py-2">لا توجد استقطاعات أو خصومات إدارية</td></tr>';
                } else {
                    allDeds.forEach(d => {
                        dedListHtml += `
                            <tr>
                                <td>${d.reason} <small class="text-muted font-monospace">(${d.decisionNo || d.id})</small></td>
                                <td class="text-end text-danger fw-bold">-${Number(d.amount).toLocaleString('ar-EG')} ج.م</td>
                            </tr>
                        `;
                    });
                }

                document.getElementById('payslipPrintArea').innerHTML = `
                    <div class="border p-4 rounded bg-white" style="color: #212529;">
                        <!-- Payslip Header -->
                        <div class="row align-items-center border-bottom pb-3 mb-3">
                            <div class="col-8">
                                <div class="d-flex align-items-center gap-2">
                                    <img src="/assets/images/alhusseini-icon.jpg" alt="Al-Husseini" height="42" class="rounded-circle">
                                    <div>
                                        <h4 class="fw-bold mb-0 text-dark">مركز الحسيني لبيع وصيانة بطاريات السيارات</h4>
                                        <small class="text-muted">إدارة المعرض والورش والمبيعات</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4 text-end">
                                <h6 class="fw-bold text-uppercase mb-1">قسيمة راتب شهرية</h6>
                                <span class="badge bg-light text-dark font-monospace border">${emp.id} / ${new Date().getFullYear()}</span>
                            </div>
                        </div>

                        <!-- Employee Meta -->
                        <div class="row g-2 mb-3 fs-13 bg-light-subtle p-3 rounded">
                            <div class="col-md-6"><span class="text-muted">اسم الموظف:</span> <strong class="text-dark">${emp.name}</strong></div>
                            <div class="col-md-6"><span class="text-muted">المسمى الوظيفي:</span> <strong>${emp.role}</strong></div>
                            <div class="col-md-6"><span class="text-muted">القسم / الإدارة:</span> <strong>${emp.department}</strong></div>
                            <div class="col-md-6"><span class="text-muted">تاريخ التعيين:</span> <strong>${emp.joinDate}</strong></div>
                        </div>

                        <!-- Earnings & Deductions Tables -->
                        <div class="row g-3 mb-3">
                            <!-- Earnings -->
                            <div class="col-md-6">
                                <div class="border rounded p-2">
                                    <h6 class="fw-bold text-success border-bottom pb-2 mb-2"><i class="ri-add-circle-line me-1"></i> الاستحقاقات (Earnings)</h6>
                                    <table class="table table-sm mb-0 fs-13">
                                        <tr>
                                            <td>الراتب الأساسي</td>
                                            <td class="text-end fw-bold">${base.toLocaleString('ar-EG')} ج.م</td>
                                        </tr>
                                        <tr>
                                            <td>البدلات والمكافآت</td>
                                            <td class="text-end fw-bold text-info">+${allow.toLocaleString('ar-EG')} ج.م</td>
                                        </tr>
                                        <tr class="table-light fw-bold">
                                            <td>إجمالي الاستحقاق</td>
                                            <td class="text-end text-success">${(base + allow).toLocaleString('ar-EG')} ج.م</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Deductions -->
                            <div class="col-md-6">
                                <div class="border rounded p-2">
                                    <h6 class="fw-bold text-danger border-bottom pb-2 mb-2"><i class="ri-indeterminate-circle-line me-1"></i> الاستقطاعات والخصومات (Deductions)</h6>
                                    <table class="table table-sm mb-0 fs-13">
                                        ${dedListHtml}
                                        <tr class="table-light fw-bold">
                                            <td>إجمالي الخصومات</td>
                                            <td class="text-end text-danger">-${totalDed.toLocaleString('ar-EG')} ج.م</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Net Pay Box -->
                        <div class="p-3 bg-success-subtle border border-success rounded text-center my-3">
                            <span class="fs-13 text-uppercase fw-semibold text-muted">صافي الراتب المستحق للصرف (Net Payable Salary)</span>
                            <h2 class="fw-bold text-success mb-0 mt-1">${net.toLocaleString('ar-EG')} جنيه مصري</h2>
                        </div>

                        <!-- Signatures -->
                        <div class="row pt-4 mt-2 border-top text-center fs-12 text-muted">
                            <div class="col-4">
                                <p class="mb-4">توقيع الموظف المستلم</p>
                                <span>............................</span>
                            </div>
                            <div class="col-4">
                                <p class="mb-4">مسؤول الحسابات</p>
                                <span>............................</span>
                            </div>
                            <div class="col-4">
                                <p class="mb-4">اعتماد المشرف العام - الحسيني</p>
                                <span class="fw-bold text-dark">Al-Husseini Management ✓</span>
                            </div>
                        </div>
                    </div>
                `;

                payslipModal.show();
            };
        });
    }

    // Open Manager Deduction Modal
    document.getElementById('btnOpenManagerDeduction').onclick = function() {
        document.getElementById('managerDeductionForm').reset();
        managerDeductionModal.show();
    };

    // Manager Deduction Form Submit
    document.getElementById('managerDeductionForm').onsubmit = function(e) {
        e.preventDefault();
        const empId = document.getElementById('deductEmployeeSelect').value;
        const amount = Number(document.getElementById('deductAmountInput').value);
        const reason = document.getElementById('deductReasonSelect').value;
        const notes = document.getElementById('deductNotesInput').value;

        window.AlHusseiniHR.addDeduction({
            employeeId: empId,
            amount: amount,
            reason: reason,
            managerNotes: notes
        });

        managerDeductionModal.hide();
        Swal.fire({
            icon: 'success',
            title: 'تم اعتماد الخصم الإداري!',
            text: `تم استقطاع ${amount.toLocaleString('ar-EG')} ج.م بنجاح من مسير راتب الموظف للشهر الحالي.`,
            confirmButtonText: 'حسناً'
        });
    };

    // Filters
    document.getElementById('searchPayrollInput').addEventListener('input', renderPayroll);
    document.getElementById('payrollDepartmentFilter').addEventListener('change', renderPayroll);

    // Initial setup
    populateEmployeeDropdown();
    renderPayroll();
    window.addEventListener('alhusseini-hr-updated', function() {
        populateEmployeeDropdown();
        renderPayroll();
    });
});
</script>
@endsection
