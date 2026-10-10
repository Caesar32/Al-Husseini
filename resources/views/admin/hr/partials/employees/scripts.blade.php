<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const employeeModal = new bootstrap.Modal(document.getElementById('employeeModal'));
    const employeeProfileModal = new bootstrap.Modal(document.getElementById('employeeProfileModal'));

    let currentEmployees = [];
    let currentPage = 1;

    // Load Employees via Backend API
    function fetchEmployees(page = 1) {
        currentPage = page;
        const search = document.getElementById('searchEmployeeInput').value.trim();
        const branchId = document.getElementById('branchFilter').value;
        const status = document.getElementById('statusFilter').value;

        const params = new URLSearchParams({
            page: page,
            ...(search && { search: search }),
            ...(branchId && { branch_id: branchId }),
            ...(status && { status: status })
        });

        fetch(`/admin/hr/employees?${params.toString()}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            currentEmployees = data.employees?.data || [];
            updateStats(data.stats);
            renderTable(currentEmployees);
            renderGrid(currentEmployees);
            renderPagination(data.employees);
        })
        .catch(err => {
            console.error('Error fetching employees:', err);
        });
    }

    function updateStats(stats) {
        if (!stats) return;
        document.getElementById('stat-total-employees').textContent = stats.total ?? 0;
        document.getElementById('stat-active-employees').textContent = stats.active ?? 0;
        document.getElementById('stat-leave-employees').textContent = stats.on_leave ?? 0;
        document.getElementById('stat-avg-salary').textContent = `${Number(stats.avg_salary || 0).toLocaleString('ar-EG')} ج.م`;
    }

    function renderTable(employees) {
        const tbody = document.getElementById('employeesTableBody');
        if (!employees || employees.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted fs-14">لا يوجد موظفون يطابقون خيارات البحث</td></tr>`;
            return;
        }

        let html = '';
        employees.forEach(emp => {
            const statusBadge = emp.status === 'active' 
                ? '<span class="badge bg-success-subtle text-success fs-12 px-2 py-1"><i class="ri-checkbox-circle-fill me-1"></i>على رأس العمل</span>'
                : (emp.status === 'on_leave' 
                    ? '<span class="badge bg-warning-subtle text-warning fs-12 px-2 py-1"><i class="ri-calendar-todo-fill me-1"></i>في إجازة</span>'
                    : '<span class="badge bg-danger-subtle text-danger fs-12 px-2 py-1"><i class="ri-close-circle-fill me-1"></i>موقوف</span>');

            const basicSalary = emp.current_salary?.basic_salary ? Number(emp.current_salary.basic_salary).toLocaleString('ar-EG') : '—';
            const deptName = emp.job_title?.department?.name || 'الورشة العامة';
            const titleName = emp.job_title?.title || emp.job_title?.title_name || 'موظف';
            const branchName = emp.branch?.name || 'الفرع الرئيسي';

            html += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar-xs flex-shrink-0 me-2">
                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle fw-bold">
                                    ${emp.full_name ? emp.full_name.charAt(0) : 'م'}
                                </span>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <h6 class="mb-0 fs-14 fw-bold text-dark text-truncate">${emp.full_name}</h6>
                                <small class="text-muted font-monospace text-truncate d-block">${emp.employee_code} | ${emp.phone}</small>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge bg-secondary-subtle text-secondary fs-12">${branchName}</span></td>
                    <td>
                        <div class="fw-semibold fs-13 text-dark">${titleName}</div>
                        <small class="text-muted">${deptName}</small>
                    </td>
                    <td>
                        <span class="badge bg-info-subtle text-info fs-12">
                            <i class="ri-time-line align-middle me-1"></i>${emp.shift_start_time ? emp.shift_start_time.substring(0, 5) : '09:00'} - ${emp.shift_end_time ? emp.shift_end_time.substring(0, 5) : '17:00'}
                        </span>
                        <small class="text-muted d-block fs-11">سماح: ${emp.grace_period_minutes || 15} د</small>
                    </td>
                    <td>
                        <span class="fw-bold fs-14 text-primary">${basicSalary} ج.م</span>
                    </td>
                    <td>${statusBadge}</td>
                    <td class="text-center">
                        <div class="dropdown">
                            <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">
                                <i class="ri-more-fill align-middle"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><a class="dropdown-item btn-view-profile" href="javascript:void(0);" data-id="${emp.id}"><i class="ri-eye-line me-2 text-primary"></i>عرض الملف التعريفي</a></li>
                                <li><a class="dropdown-item btn-edit-emp" href="javascript:void(0);" data-id="${emp.id}"><i class="ri-pencil-line me-2 text-warning"></i>تعديل البيانات</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item btn-delete-emp text-danger" href="javascript:void(0);" data-id="${emp.id}" data-name="${emp.full_name}"><i class="ri-delete-bin-line me-2"></i>حذف الموظف</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        attachTableEvents();
    }

    function renderGrid(employees) {
        const gridContainer = document.getElementById('gridViewContainer');
        if (!employees || employees.length === 0) {
            gridContainer.innerHTML = `<div class="col-12 text-center py-5 text-muted fs-14">لا يوجد موظفون يطابقون خيارات البحث</div>`;
            return;
        }

        let html = '';
        employees.forEach(emp => {
            const statusBadge = emp.status === 'active' 
                ? '<span class="badge bg-success-subtle text-success fs-11 px-2 py-1">على رأس العمل</span>'
                : '<span class="badge bg-warning-subtle text-warning fs-11 px-2 py-1">إجازة</span>';

            const basicSalary = emp.current_salary?.basic_salary ? Number(emp.current_salary.basic_salary).toLocaleString('ar-EG') : '—';
            const deptName = emp.job_title?.department?.name || 'الورشة';
            const titleName = emp.job_title?.title_name || 'موظف';

            html += `
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card h-100 border card-animate">
                        <div class="card-body text-center p-4">
                            <div class="avatar-lg mx-auto mb-3">
                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-24 fw-bold shadow-sm">
                                    ${emp.full_name.charAt(0)}
                                </span>
                            </div>
                            <h5 class="fs-16 fw-bold mb-1">${emp.full_name}</h5>
                            <p class="text-muted fs-13 mb-1">${titleName}</p>
                            <div class="badge bg-light text-primary fs-12 mb-3">${deptName}</div>
                            
                            <div class="d-flex justify-content-between border-top border-bottom py-2 my-2 text-start fs-12">
                                <span class="text-muted">الرقم الوظيفي:</span>
                                <span class="fw-bold font-monospace">${emp.employee_code}</span>
                            </div>
                            <div class="d-flex justify-content-between text-start fs-12 mb-3">
                                <span class="text-muted">الراتب الأساسي:</span>
                                <span class="fw-bold text-success">${basicSalary} ج.م</span>
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-soft-primary btn-sm flex-grow-1 btn-view-profile" data-id="${emp.id}"><i class="ri-user-line me-1"></i>الملف</button>
                                <button class="btn btn-soft-warning btn-sm btn-edit-emp" data-id="${emp.id}"><i class="ri-pencil-line"></i></button>
                                <button class="btn btn-soft-danger btn-sm btn-delete-emp" data-id="${emp.id}" data-name="${emp.full_name}"><i class="ri-delete-bin-line"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        gridContainer.innerHTML = html;
        attachTableEvents();
    }

    function renderPagination(paginator) {
        const container = document.getElementById('paginationContainer');
        if (!paginator || paginator.last_page <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = `
            <div class="text-muted fs-13">
                عرض <strong>${paginator.from || 0}</strong> إلى <strong>${paginator.to || 0}</strong> من أصل <strong>${paginator.total || 0}</strong> موظف
            </div>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item ${paginator.current_page === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="javascript:void(0);" onclick="fetchEmployees(${paginator.current_page - 1})">السابق</a>
                </li>
        `;

        for (let i = 1; i <= paginator.last_page; i++) {
            html += `
                <li class="page-item ${paginator.current_page === i ? 'active' : ''}">
                    <a class="page-link" href="javascript:void(0);" onclick="fetchEmployees(${i})">${i}</a>
                </li>
            `;
        }

        html += `
                <li class="page-item ${paginator.current_page === paginator.last_page ? 'disabled' : ''}">
                    <a class="page-link" href="javascript:void(0);" onclick="fetchEmployees(${paginator.current_page + 1})">التالي</a>
                </li>
            </ul>
        `;

        container.innerHTML = html;
    }

    window.fetchEmployees = fetchEmployees;

    function attachTableEvents() {
        // View Profile
        document.querySelectorAll('.btn-view-profile').forEach(btn => {
            btn.onclick = function() {
                const id = this.getAttribute('data-id');
                const modalBody = document.getElementById('profileModalBody');
                modalBody.innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">جاري تحميل السجل الكامل للموظف...</p>
                    </div>
                `;
                employeeProfileModal.show();

                fetch(`/admin/hr/employees/${id}`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(emp => {
                    const basic = emp.current_salary ? Number(emp.current_salary.basic_salary).toLocaleString('ar-EG') : '0';
                    const attendances = emp.attendances || [];
                    const deductions = emp.deductions || [];

                    let attRows = attendances.length > 0 
                        ? attendances.slice(0, 5).map(a => `
                            <tr>
                                <td>${a.work_date}</td>
                                <td><span class="badge ${a.status === 'present' ? 'bg-success-subtle text-success' : (a.status === 'late' ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger')}">${a.status}</span></td>
                                <td>${a.check_in ? a.check_in.substring(11, 16) : '—'}</td>
                                <td>${a.late_minutes > 0 ? `<span class="text-danger fw-bold">+${a.late_minutes} د</span>` : 'منضبط'}</td>
                            </tr>
                        `).join('')
                        : '<tr><td colspan="4" class="text-center text-muted">لا توجد حركات بصمة مسجلة مؤخراً</td></tr>';

                    modalBody.innerHTML = `
                        <div class="row align-items-center mb-4">
                            <div class="col-auto">
                                <div class="avatar-lg">
                                    <span class="avatar-title bg-primary text-white rounded-circle fs-28 fw-bold">
                                        ${emp.full_name.charAt(0)}
                                    </span>
                                </div>
                            </div>
                            <div class="col">
                                <h4 class="fw-bold mb-1">${emp.full_name}</h4>
                                <p class="text-muted mb-1 fs-14">${emp.job_title?.title_name || 'موظف'} | <span class="badge bg-primary-subtle text-primary">${emp.branch?.name || ''}</span></p>
                                <span class="badge bg-light text-body font-monospace">${emp.employee_code}</span>
                                <span class="badge bg-secondary-subtle text-secondary ms-1">الرقم القومي: ${emp.national_id || "-"}</span>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light-subtle text-center">
                                    <h6 class="text-muted fs-12 mb-1">الراتب الأساسي</h6>
                                    <h5 class="fw-bold text-success mb-0">${basic} ج.م</h5>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light-subtle text-center">
                                    <h6 class="text-muted fs-12 mb-1">بدلات (سكن + انتقالات)</h6>
                                    <h5 class="fw-bold text-info mb-0">${Number(Number(emp.current_salary?.housing_allowance || 0) + Number(emp.current_salary?.transport_allowance || 0)).toLocaleString('ar-EG')} ج.م</h5>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light-subtle text-center">
                                    <h6 class="text-muted fs-12 mb-1">إجمالي الخصومات النشطة</h6>
                                    <h5 class="fw-bold text-danger mb-0">${deductions.reduce((sum, d) => sum + Number(d.amount), 0).toLocaleString('ar-EG')} ج.م</h5>
                                </div>
                            </div>
                        </div>

                        <ul class="nav nav-tabs nav-tabs-custom mb-3" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#tab-profile-info" role="tab">البيانات الوظيفية</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tab-profile-attendance" role="tab">سجل الحضور (${attendances.length})</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tab-profile-deductions" role="tab">الجزاءات (${deductions.length})</a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane active" id="tab-profile-info" role="tabpanel">
                                <div class="row g-2 fs-13">
                                    <div class="col-md-6"><span class="text-muted me-2">الهاتف:</span> <span class="fw-semibold font-monospace">${emp.phone}</span></div>
                                    <div class="col-md-6"><span class="text-muted me-2">تاريخ التعيين:</span> <span class="fw-semibold">${emp.hire_date}</span></div>
                                    <div class="col-md-6"><span class="text-muted me-2">فترة الدوام:</span> <span class="badge bg-info-subtle text-info">${emp.shift_start_time.substring(0, 5)} - ${emp.shift_end_time.substring(0, 5)}</span></div>
                                    <div class="col-md-6"><span class="text-muted me-2">دقائق السماح:</span> <span class="fw-semibold">${emp.grace_period_minutes} دقيقة</span></div>
                                    <div class="col-md-6"><span class="text-muted me-2">كود البصمة PIN:</span> <span class="fw-semibold font-monospace">${emp.zkteco_pin || 'غير محدد'}</span></div>
                                    <div class="col-md-6"><span class="text-muted me-2">الحالة:</span> <span class="fw-semibold">${emp.status}</span></div>
                                </div>
                            </div>
                            <div class="tab-pane" id="tab-profile-attendance" role="tabpanel">
                                <table class="table table-sm table-bordered fs-12 mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>التاريخ</th>
                                            <th>الحالة</th>
                                            <th>وقت الحضور</th>
                                            <th>التأخير</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${attRows}
                                    </tbody>
                                </table>
                            </div>
                            <div class="tab-pane" id="tab-profile-deductions" role="tabpanel">
                                ${deductions.length > 0 ? deductions.map(d => `
                                    <div class="alert alert-danger p-2 mb-2 fs-12">
                                        <div class="d-flex justify-content-between fw-bold">
                                            <span>${d.reason}</span>
                                            <span>-${Number(d.amount).toLocaleString('ar-EG')} ج.م</span>
                                        </div>
                                        <small class="text-muted">${d.deduction_date} | الحالة: ${d.status}</small>
                                    </div>
                                `).join('') : '<p class="text-center text-muted py-3">لا توجد جزاءات مسجلة بحق هذا الموظف</p>'}
                            </div>
                        </div>
                    `;
                });
            };
        });

        // Edit Employee
        document.querySelectorAll('.btn-edit-emp').forEach(btn => {
            btn.onclick = function() {
                const id = this.getAttribute('data-id');
                fetch(`/admin/hr/employees/${id}`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(emp => {
                    document.getElementById('employeeModalLabel').textContent = `تعديل بيانات الموظف: ${emp.full_name}`;
                    document.getElementById('employeeId').value = emp.id;
                    document.getElementById('empName').value = emp.full_name;
                    document.getElementById('empCode').value = emp.employee_code;
                    document.getElementById('empBranch').value = emp.branch_id;
                    document.getElementById('empDepartment').value = emp.job_title?.department?.name || '';
                    document.getElementById('empJobTitle').value = emp.job_title?.title || '';
                    document.getElementById('empNationalId').value = emp.national_id || '';
                    document.getElementById('empPhone').value = emp.phone;
                    document.getElementById('empHireDate').value = emp.hire_date;
                    document.getElementById('empStatus').value = emp.status;
                    document.getElementById('empStartTime').value = emp.shift_start_time.substring(0, 5);
                    document.getElementById('empEndTime').value = emp.shift_end_time.substring(0, 5);
                    document.getElementById('empGracePeriod').value = emp.grace_period_minutes;
                    document.getElementById('empPin').value = emp.zkteco_pin || '';
                    document.getElementById('empBaseSalary').value = emp.current_salary?.basic_salary || '';
                    document.getElementById('empHousingAllowance').value = emp.current_salary?.housing_allowance || 0;
                    document.getElementById('empTransportAllowance').value = emp.current_salary?.transport_allowance || 0;
                    document.getElementById('empOtherAllowances').value = emp.current_salary?.other_allowances || 0;

                    employeeModal.show();
                });
            };
        });

        // Delete Employee
        document.querySelectorAll('.btn-delete-emp').forEach(btn => {
            btn.onclick = function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');

                Swal.fire({
                    title: 'تأكيد أرشفة الموظف',
                    text: `هل أنت متأكد من رغبتك في حذف/أرشفة بيانات الموظف [${name}]؟`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، أرشفة',
                    cancelButtonText: 'إلغاء',
                    confirmButtonColor: '#e63946'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch(`/admin/hr/employees/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            }
                        })
                        .then(res => res.json())
                        .then(resp => {
                            if (resp.success) {
                                Swal.fire('تم بنجاح!', resp.message, 'success');
                                fetchEmployees(currentPage);
                            } else {
                                Swal.fire('خطأ', resp.message || 'تعذر حذف الموظف.', 'error');
                            }
                        })
                        .catch(() => Swal.fire('خطأ', 'حدث خطأ أثناء تنفيذ الطلب.', 'error'));
                    }
                });
            };
        });
    }

    // Add Employee Button
    document.getElementById('btnAddEmployee').onclick = function() {
        document.getElementById('employeeForm').reset();
        document.getElementById('employeeId').value = '';
        document.getElementById('employeeModalLabel').textContent = 'إضافة موظف جديد لمجموعة الحسيني';
        document.getElementById('empCode').value = `EMP-${Math.floor(1000 + Math.random() * 9000)}`;
        document.getElementById('empStartTime').value = '09:00';
        document.getElementById('empEndTime').value = '17:00';
        document.getElementById('empGracePeriod').value = '15';
        document.getElementById('empHireDate').value = new Date().toISOString().split('T')[0];
        employeeModal.show();
    };

    // Form Submit (Store or Update)
    document.getElementById('employeeForm').onsubmit = function(e) {
        e.preventDefault();
        const id = document.getElementById('employeeId').value;
        const isEdit = Boolean(id);

        const payload = {
            branch_id: document.getElementById('empBranch').value,
            department: document.getElementById('empDepartment').value.trim(),
            job_title: document.getElementById('empJobTitle').value.trim(),
            employee_code: document.getElementById('empCode').value.trim(),
            full_name: document.getElementById('empName').value.trim(),
            national_id: document.getElementById('empNationalId').value.trim() || null,
            phone: document.getElementById('empPhone').value.trim(),
            hire_date: document.getElementById('empHireDate').value,
            shift_start_time: document.getElementById('empStartTime').value,
            shift_end_time: document.getElementById('empEndTime').value,
            grace_period_minutes: document.getElementById('empGracePeriod').value,
            zkteco_pin: document.getElementById('empPin').value.trim() || null,
            status: document.getElementById('empStatus').value,
            basic_salary: document.getElementById('empBaseSalary').value,
            housing_allowance: document.getElementById('empHousingAllowance').value || 0,
            transport_allowance: document.getElementById('empTransportAllowance').value || 0,
            other_allowances: document.getElementById('empOtherAllowances').value || 0,
        };

        const url = isEdit ? `/admin/hr/employees/${id}` : '/admin/hr/employees';
        const method = isEdit ? 'PUT' : 'POST';

        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        })
        .then(async res => {
            const data = await res.json();
            if (!res.ok) {
                let errorMsg = data.message || 'يرجى مراجعة الحقول المطلوبة';
                if (data.errors) {
                    errorMsg = Object.values(data.errors).flat().join('<br>');
                }
                throw new Error(errorMsg);
            }
            return data;
        })
        .then(data => {
            employeeModal.hide();
            Swal.fire('تمت العملية بنجاح!', data.message, 'success');
            fetchEmployees(currentPage);
        })
        .catch(err => {
            Swal.fire({
                icon: 'error',
                title: 'تعذر الحفظ',
                html: err.message
            });
        });
    };

    // Search and filter inputs
    let debounceTimer;
    document.getElementById('searchEmployeeInput').addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchEmployees(1), 350);
    });

    document.getElementById('branchFilter').addEventListener('change', () => fetchEmployees(1));
    document.getElementById('statusFilter').addEventListener('change', () => fetchEmployees(1));

    // View switch
    document.getElementById('viewTableBtn').onclick = function() {
        this.classList.add('active');
        document.getElementById('viewGridBtn').classList.remove('active');
        document.getElementById('tableViewContainer').classList.remove('d-none');
        document.getElementById('gridViewContainer').classList.add('d-none');
    };
    document.getElementById('viewGridBtn').onclick = function() {
        this.classList.add('active');
        document.getElementById('viewTableBtn').classList.remove('active');
        document.getElementById('tableViewContainer').classList.add('d-none');
        document.getElementById('gridViewContainer').classList.remove('d-none');
    };

    // Read query params from URL if present (e.g. ?search=EMP-0101)
    const urlParams = new URLSearchParams(window.location.search);
    const urlSearch = urlParams.get('search');
    const urlBranch = urlParams.get('branch_id');
    const urlStatus = urlParams.get('status');
    const openProfileId = urlParams.get('open_profile');

    if (urlSearch && document.getElementById('searchEmployeeInput')) {
        document.getElementById('searchEmployeeInput').value = urlSearch;
    }
    if (urlBranch && document.getElementById('branchFilter')) {
        document.getElementById('branchFilter').value = urlBranch;
    }
    if (urlStatus && document.getElementById('statusFilter')) {
        document.getElementById('statusFilter').value = urlStatus;
    }

    // Initial Load
    fetchEmployees(1);

    if (openProfileId) {
        setTimeout(() => {
            const profileBtn = document.querySelector(`.btn-view-profile[data-id="${openProfileId}"]`);
            if (profileBtn) {
                profileBtn.click();
            }
        }, 600);
    }
});
</script>
