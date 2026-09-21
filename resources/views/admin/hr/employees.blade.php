@extends('admin.layouts.master')

@section('title', 'دليل الموظفين | نظام الحسيني الإداري')

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'الموارد البشرية', 'title' => 'دليل وشؤون الموظفين'])

    <!-- Stat Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-primary border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">إجمالي الموظفين</p>
                            <h4 class="fs-22 fw-bold ff-secondary mb-0 mt-2" id="stat-total-employees">0</h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20">
                                    <i class="ri-team-line"></i>
                                </span>
                            </div>
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
                            <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">على رأس العمل</p>
                            <h4 class="fs-22 fw-bold ff-secondary text-success mb-0 mt-2" id="stat-active-employees">0</h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle text-success rounded-circle fs-20">
                                    <i class="ri-user-follow-line"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-animate border-start border-warning border-3">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1 overflow-hidden">
                            <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">في إجازة رسمية</p>
                            <h4 class="fs-22 fw-bold ff-secondary text-warning mb-0 mt-2" id="stat-leave-employees">0</h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-20">
                                    <i class="ri-calendar-event-line"></i>
                                </span>
                            </div>
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
                            <p class="text-uppercase fw-semibold text-muted text-truncate mb-0">متوسط الرواتب الأساسية</p>
                            <h4 class="fs-20 fw-bold ff-secondary text-info mb-0 mt-2" id="stat-avg-salary">0 ج.م</h4>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle text-info rounded-circle fs-20">
                                    <i class="ri-money-dollar-box-line"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Controls Row -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card mb-0">
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-4 col-md-6">
                            <div class="search-box">
                                <input type="text" class="form-control" id="searchEmployeeInput" placeholder="بحث بالاسم أو الرقم الوظيفي أو الوظيفة...">
                                <i class="ri-search-line search-icon"></i>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <select class="form-select" id="departmentFilter">
                                <option value="all">جميع أقسام المركز</option>
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
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-outline-primary active" id="viewTableBtn" title="عرض جدول"><i class="ri-list-check"></i></button>
                                    <button type="button" class="btn btn-outline-primary" id="viewGridBtn" title="عرض بطاقات"><i class="ri-grid-fill"></i></button>
                                </div>
                                <button type="button" class="btn btn-success" id="btnAddEmployee">
                                    <i class="ri-user-add-line align-bottom me-1"></i> إضافة موظف جديد
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table View Container -->
    <div class="row" id="tableViewContainer">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle table-nowrap mb-0 table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">الموظف</th>
                                    <th scope="col">الرقم الوظيفي</th>
                                    <th scope="col">القسم والوظيفة</th>
                                    <th scope="col">مواعيد العمل الرسمية</th>
                                    <th scope="col">الراتب الأساسي</th>
                                    <th scope="col">الحالة</th>
                                    <th scope="col" class="text-center">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody id="employeesTableBody">
                                <!-- Rendered dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Grid View Container -->
    <div class="row d-none" id="gridViewContainer">
        <!-- Rendered dynamically -->
    </div>

    <!-- Modal: Add/Edit Employee -->
    <div class="modal fade" id="employeeModal" tabindex="-1" aria-labelledby="employeeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0">
                <div class="modal-header p-3 bg-primary-subtle">
                    <h5 class="modal-title fw-bold text-primary" id="employeeModalLabel">إضافة موظف جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="employeeForm">
                    <input type="hidden" id="employeeId">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="empName" class="form-label fw-semibold">اسم الموظف بالكامل <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="empName" required placeholder="مثال: حسام علي إبراهيم">
                            </div>

                            <div class="col-md-6">
                                <label for="empRole" class="form-label fw-semibold">المسمى الوظيفي <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="empRole" required placeholder="مثال: فني صيانة بطاريات / مسؤول صالة بيع">
                            </div>

                            <div class="col-md-6">
                                <label for="empDepartment" class="form-label fw-semibold">القسم / ورشة المركز <span class="text-danger">*</span></label>
                                <select class="form-select" id="empDepartment" required>
                                    <option value="المبيعات والمعرض">المبيعات والمعرض (صالة البيع)</option>
                                    <option value="ورشة الصيانة والشحن">ورشة الصيانة والشحن والإصلاح</option>
                                    <option value="فنيو التركيب والكهرباء">فنيو التركيب والكهرباء</option>
                                    <option value="خدمة الطوارئ والإنقاذ المتنقل">خدمة الطوارئ والإنقاذ المتنقل</option>
                                    <option value="المخازن وسلاسل الإمداد">المخازن والبطاريات المسترجعة</option>
                                    <option value="الإدارة والإشراف">الإدارة والإشراف</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="empStatus" class="form-label fw-semibold">الحالة الوظيفية</label>
                                <select class="form-select" id="empStatus">
                                    <option value="active">على رأس العمل (نشط)</option>
                                    <option value="on_leave">في إجازة</option>
                                    <option value="suspended">موقوف مؤقتاً</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="empEmail" class="form-label fw-semibold">البريد الإلكتروني <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="empEmail" required placeholder="user@alhusseini.com">
                            </div>

                            <div class="col-md-6">
                                <label for="empPhone" class="form-label fw-semibold">رقم الهاتف <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="empPhone" required placeholder="+20 100 000 0000">
                            </div>

                            <div class="col-12"><hr class="my-2"></div>

                            <div class="col-md-6">
                                <label for="empStartTime" class="form-label fw-semibold">موعد الحضور الرسمي <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="empStartTime" required value="09:00">
                                <div class="form-text fs-11 text-muted">الحد الأقصى للسماح هو 15 دقيقة بعد هذا التوقيت.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="empEndTime" class="form-label fw-semibold">موعد الانصراف الرسمي <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="empEndTime" required value="17:00">
                            </div>

                            <div class="col-md-6">
                                <label for="empBaseSalary" class="form-label fw-semibold">الراتب الأساسي (ج.م) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="empBaseSalary" required min="1000" step="500" placeholder="مثال: 15000">
                            </div>

                            <div class="col-md-6">
                                <label for="empAllowances" class="form-label fw-semibold">إجمالي البدلات (ج.م)</label>
                                <input type="number" class="form-control" id="empAllowances" min="0" step="250" value="0" placeholder="مثال: 2000">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary" id="saveEmployeeBtn">
                            <i class="ri-save-line align-bottom me-1"></i> حفظ البيانات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Offcanvas / Modal: Employee Detailed Profile -->
    <div class="modal fade" id="employeeProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0">
                <div class="modal-header p-3 bg-dark text-white">
                    <h5 class="modal-title text-white fw-bold"><i class="ri-profile-line me-2"></i> الملف التعريفي للموظف</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="profileModalBody">
                    <!-- Loaded dynamically -->
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    let currentView = 'table';
    const employeeModal = new bootstrap.Modal(document.getElementById('employeeModal'));
    const employeeProfileModal = new bootstrap.Modal(document.getElementById('employeeProfileModal'));

    function renderEmployees() {
        if (!window.AlHusseiniHR) return;
        const employees = window.AlHusseiniHR.getEmployees();
        const searchTerm = document.getElementById('searchEmployeeInput').value.trim().toLowerCase();
        const selectedDept = document.getElementById('departmentFilter').value;

        const filtered = employees.filter(emp => {
            const matchesSearch = emp.name.toLowerCase().includes(searchTerm) ||
                                  emp.id.toLowerCase().includes(searchTerm) ||
                                  emp.role.toLowerCase().includes(searchTerm);
            const matchesDept = selectedDept === 'all' || emp.department === selectedDept;
            return matchesSearch && matchesDept;
        });

        // Update Stat Cards
        document.getElementById('stat-total-employees').textContent = employees.length;
        document.getElementById('stat-active-employees').textContent = employees.filter(e => e.status === 'active').length;
        document.getElementById('stat-leave-employees').textContent = employees.filter(e => e.status === 'on_leave').length;
        
        const totalBase = employees.reduce((sum, e) => sum + Number(e.baseSalary || 0), 0);
        const avg = employees.length > 0 ? Math.round(totalBase / employees.length) : 0;
        document.getElementById('stat-avg-salary').textContent = `${avg.toLocaleString('ar-EG')} ج.م`;

        // Render Table View
        const tbody = document.getElementById('employeesTableBody');
        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted fs-14">لا يوجد موظفون يطابقون خيارات البحث</td></tr>`;
        } else {
            let rowsHtml = '';
            filtered.forEach(emp => {
                const statusBadge = emp.status === 'active' 
                    ? '<span class="badge bg-success-subtle text-success fs-12 px-2 py-1">على رأس العمل</span>'
                    : (emp.status === 'on_leave' 
                        ? '<span class="badge bg-warning-subtle text-warning fs-12 px-2 py-1">إجازة</span>'
                        : '<span class="badge bg-danger-subtle text-danger fs-12 px-2 py-1">موقوف</span>');

                rowsHtml += `
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="${emp.avatar}" alt="" class="avatar-xs rounded-circle me-2 shadow-sm">
                                <div>
                                    <h6 class="mb-0 fs-14 fw-bold">${emp.name}</h6>
                                    <small class="text-muted">${emp.email}</small>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge bg-light text-body fs-12 fw-bold font-monospace">${emp.id}</span></td>
                        <td>
                            <div class="fw-semibold fs-13 text-dark">${emp.role}</div>
                            <small class="text-muted">${emp.department}</small>
                        </td>
                        <td>
                            <span class="badge bg-info-subtle text-info fs-12"><i class="ri-time-line align-middle me-1"></i>${emp.startTime} - ${emp.endTime}</span>
                        </td>
                        <td>
                            <span class="fw-bold fs-14 text-primary">${Number(emp.baseSalary).toLocaleString('ar-EG')} ج.م</span>
                        </td>
                        <td>${statusBadge}</td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="ri-more-fill align-middle"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item btn-view-profile" href="javascript:void(0);" data-id="${emp.id}"><i class="ri-eye-line me-2 text-primary"></i>عرض الملف التعريفي</a></li>
                                    <li><a class="dropdown-item btn-edit-emp" href="javascript:void(0);" data-id="${emp.id}"><i class="ri-pencil-line me-2 text-warning"></i>تعديل البيانات</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item btn-delete-emp text-danger" href="javascript:void(0);" data-id="${emp.id}"><i class="ri-delete-bin-line me-2"></i>حذف الموظف</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = rowsHtml;
        }

        // Render Grid View
        const gridContainer = document.getElementById('gridViewContainer');
        if (filtered.length === 0) {
            gridContainer.innerHTML = `<div class="col-12 text-center py-5 text-muted fs-14">لا يوجد موظفون يطابقون خيارات البحث</div>`;
        } else {
            let cardsHtml = '';
            filtered.forEach(emp => {
                const statusBadge = emp.status === 'active' 
                    ? '<span class="badge bg-success-subtle text-success fs-11 px-2 py-1">على رأس العمل</span>'
                    : '<span class="badge bg-warning-subtle text-warning fs-11 px-2 py-1">إجازة</span>';

                cardsHtml += `
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card h-100 border card-animate">
                            <div class="card-body text-center p-4">
                                <div class="position-relative d-inline-block mb-3">
                                    <img src="${emp.avatar}" alt="" class="avatar-lg rounded-circle shadow border border-2 border-primary">
                                    <span class="position-absolute bottom-0 start-0 p-1 bg-success border border-light rounded-circle"></span>
                                </div>
                                <h5 class="fs-16 fw-bold mb-1">${emp.name}</h5>
                                <p class="text-muted fs-13 mb-2">${emp.role}</p>
                                <div class="badge bg-light text-primary fs-12 mb-3">${emp.department}</div>
                                
                                <div class="d-flex justify-content-between border-top border-bottom py-2 my-2 text-start fs-12">
                                    <span class="text-muted">الرقم الوظيفي:</span>
                                    <span class="fw-bold font-monospace">${emp.id}</span>
                                </div>
                                <div class="d-flex justify-content-between text-start fs-12 mb-3">
                                    <span class="text-muted">الراتب الأساسي:</span>
                                    <span class="fw-bold text-success">${Number(emp.baseSalary).toLocaleString('ar-EG')} ج.م</span>
                                </div>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-soft-primary btn-sm flex-grow-1 btn-view-profile" data-id="${emp.id}"><i class="ri-user-line me-1"></i>الملف</button>
                                    <button class="btn btn-soft-warning btn-sm btn-edit-emp" data-id="${emp.id}"><i class="ri-pencil-line"></i></button>
                                    <button class="btn btn-soft-danger btn-sm btn-delete-emp" data-id="${emp.id}"><i class="ri-delete-bin-line"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            gridContainer.innerHTML = cardsHtml;
        }

        attachEventListeners();
    }

    function attachEventListeners() {
        // View Profile
        document.querySelectorAll('.btn-view-profile').forEach(btn => {
            btn.onclick = function() {
                const id = this.getAttribute('data-id');
                const emp = window.AlHusseiniHR.getEmployeeById(id);
                if (!emp) return;

                const deductions = window.AlHusseiniHR.getDeductions().filter(d => d.employeeId === id);
                const totalDeductions = deductions.reduce((sum, d) => sum + Number(d.amount), 0);
                const attendanceRecords = window.AlHusseiniHR.getAllAttendance().filter(a => a.employeeId === id);
                const lateDays = attendanceRecords.filter(a => a.status === 'late').length;

                document.getElementById('profileModalBody').innerHTML = `
                    <div class="row align-items-center mb-4">
                        <div class="col-auto">
                            <img src="${emp.avatar}" alt="" class="avatar-lg rounded-circle shadow border border-3 border-primary">
                        </div>
                        <div class="col">
                            <h4 class="fw-bold mb-1">${emp.name}</h4>
                            <p class="text-muted mb-1 fs-14">${emp.role} | <span class="badge bg-primary-subtle text-primary">${emp.department}</span></p>
                            <span class="badge bg-light text-body font-monospace">${emp.id}</span>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="p-3 border rounded bg-light-subtle text-center">
                                <h6 class="text-muted fs-12 mb-1">الراتب الأساسي</h6>
                                <h5 class="fw-bold text-success mb-0">${Number(emp.baseSalary).toLocaleString('ar-EG')} ج.م</h5>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 border rounded bg-light-subtle text-center">
                                <h6 class="text-muted fs-12 mb-1">مرات التأخير المرصودة</h6>
                                <h5 class="fw-bold text-warning mb-0">${lateDays} يوم</h5>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 border rounded bg-light-subtle text-center">
                                <h6 class="text-muted fs-12 mb-1">إجمالي الخصومات</h6>
                                <h5 class="fw-bold text-danger mb-0">${totalDeductions.toLocaleString('ar-EG')} ج.م</h5>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-3 border-bottom pb-2">بيانات الاتصال ومواعيد الدوام</h6>
                    <div class="row g-2 fs-13">
                        <div class="col-md-6"><span class="text-muted me-2">البريد الإلكتروني:</span> <span class="fw-semibold">${emp.email}</span></div>
                        <div class="col-md-6"><span class="text-muted me-2">رقم الهاتف:</span> <span class="fw-semibold">${emp.phone}</span></div>
                        <div class="col-md-6"><span class="text-muted me-2">تاريخ التعيين:</span> <span class="fw-semibold">${emp.joinDate}</span></div>
                        <div class="col-md-6"><span class="text-muted me-2">فترة الدوام:</span> <span class="badge bg-info-subtle text-info">${emp.startTime} - ${emp.endTime}</span></div>
                    </div>
                `;

                employeeProfileModal.show();
            };
        });

        // Edit Employee
        document.querySelectorAll('.btn-edit-emp').forEach(btn => {
            btn.onclick = function() {
                const id = this.getAttribute('data-id');
                const emp = window.AlHusseiniHR.getEmployeeById(id);
                if (!emp) return;

                document.getElementById('employeeModalLabel').textContent = `تعديل بيانات الموظف: ${emp.name}`;
                document.getElementById('employeeId').value = emp.id;
                document.getElementById('empName').value = emp.name;
                document.getElementById('empRole').value = emp.role;
                document.getElementById('empDepartment').value = emp.department;
                document.getElementById('empStatus').value = emp.status || 'active';
                document.getElementById('empEmail').value = emp.email;
                document.getElementById('empPhone').value = emp.phone;
                document.getElementById('empStartTime').value = emp.startTime || '09:00';
                document.getElementById('empEndTime').value = emp.endTime || '17:00';
                document.getElementById('empBaseSalary').value = emp.baseSalary;
                document.getElementById('empAllowances').value = emp.allowances || 0;

                employeeModal.show();
            };
        });

        // Delete Employee
        document.querySelectorAll('.btn-delete-emp').forEach(btn => {
            btn.onclick = function() {
                const id = this.getAttribute('data-id');
                const emp = window.AlHusseiniHR.getEmployeeById(id);
                if (!emp) return;

                Swal.fire({
                    title: 'تأكيد حذف الموظف',
                    text: `هل أنت متأكد من رغبتك في حذف بيانات الموظف [${emp.name}] نهائياً؟`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، حذف',
                    cancelButtonText: 'إلغاء',
                    confirmButtonColor: '#e63946'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.AlHusseiniHR.deleteEmployee(id);
                        Swal.fire('تم الحذف!', 'تم حذف الموظف بنجاح.', 'success');
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
        document.getElementById('empStartTime').value = '09:00';
        document.getElementById('empEndTime').value = '17:00';
        employeeModal.show();
    };

    // Save Form Submission
    document.getElementById('employeeForm').onsubmit = function(e) {
        e.preventDefault();
        const id = document.getElementById('employeeId').value;
        const employeeData = {
            id: id || undefined,
            name: document.getElementById('empName').value.trim(),
            role: document.getElementById('empRole').value.trim(),
            department: document.getElementById('empDepartment').value,
            status: document.getElementById('empStatus').value,
            email: document.getElementById('empEmail').value.trim(),
            phone: document.getElementById('empPhone').value.trim(),
            startTime: document.getElementById('empStartTime').value,
            endTime: document.getElementById('empEndTime').value,
            baseSalary: Number(document.getElementById('empBaseSalary').value),
            allowances: Number(document.getElementById('empAllowances').value || 0)
        };

        window.AlHusseiniHR.saveEmployee(employeeData);
        employeeModal.hide();
        Swal.fire('تم الحفظ بنجاح!', 'تم تحديث بيانات الموظف في قاعدة بيانات الحسيني.', 'success');
    };

    // Filter & Search listeners
    document.getElementById('searchEmployeeInput').addEventListener('input', renderEmployees);
    document.getElementById('departmentFilter').addEventListener('change', renderEmployees);

    // Toggle Table / Grid
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

    // Initial render and listen to global updates
    renderEmployees();
    window.addEventListener('alhusseini-hr-updated', renderEmployees);
});
</script>
@endsection
