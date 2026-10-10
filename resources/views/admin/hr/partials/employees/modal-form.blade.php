<!-- Modal: Add/Edit Employee -->
<div class="modal fade" id="employeeModal" tabindex="-1" aria-labelledby="employeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header p-3 bg-primary text-white">
                <h5 class="modal-title fw-bold text-white" id="employeeModalLabel">إضافة موظف جديد</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="employeeForm">
                <input type="hidden" id="employeeId">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="empName" class="form-label fw-semibold">اسم الموظف بالكامل <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="empName" required placeholder="مثال: محمود أحمد الحسيني">
                        </div>

                        <div class="col-md-3">
                            <label for="empCode" class="form-label fw-semibold">كود الموظف <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="empCode" required placeholder="EMP-1001">
                        </div>

                        <div class="col-md-3">
                            <label for="empBranch" class="form-label fw-semibold">الفرع التابع له <span class="text-danger">*</span></label>
                            <select class="form-select" id="empBranch" required>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="empDepartment" class="form-label fw-semibold">القسم <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="empDepartment" required maxlength="100">
                        </div>

                        <div class="col-md-6">
                            <label for="empJobTitle" class="form-label fw-semibold">المسمى الوظيفي <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="empJobTitle" required maxlength="100">
                        </div>

                        <div class="col-md-6">
                            <label for="empNationalId" class="form-label fw-semibold">الرقم القومي (14 رقم)</label>
                            <input type="text" class="form-control font-monospace" id="empNationalId" maxlength="14" placeholder="29501011234567">
                        </div>

                        <div class="col-md-6">
                            <label for="empPhone" class="form-label fw-semibold">رقم الهاتف للتواصل <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="empPhone" required placeholder="01012345678">
                        </div>

                        <div class="col-md-3">
                            <label for="empHireDate" class="form-label fw-semibold">تاريخ التعيين <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="empHireDate" required value="{{ date('Y-m-d') }}">
                        </div>

                        <div class="col-md-3">
                            <label for="empStatus" class="form-label fw-semibold">الحالة الوظيفية</label>
                            <select class="form-select" id="empStatus">
                                <option value="active">على رأس العمل (نشط)</option>
                                <option value="on_leave">في إجازة</option>
                                <option value="suspended">موقوف مؤقتاً</option>
                            </select>
                        </div>

                        <div class="col-12"><hr class="my-2 text-muted"></div>

                        <div class="col-md-4">
                            <label for="empStartTime" class="form-label fw-semibold">بداية الوردية <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="empStartTime" required value="09:00">
                        </div>

                        <div class="col-md-4">
                            <label for="empEndTime" class="form-label fw-semibold">نهاية الوردية <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="empEndTime" required value="17:00">
                        </div>

                        <div class="col-md-4">
                            <label for="empGracePeriod" class="form-label fw-semibold">فترة السماح (بالدقائق) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="empGracePeriod" required min="0" max="60" value="15">
                        </div>

                        <div class="col-md-6">
                            <label for="empPin" class="form-label fw-semibold">رقم PIN جهاز البصمة (ZKTeco) <small class="text-muted">(اختياري)</small></label>
                            <input type="text" class="form-control font-monospace" id="empPin" placeholder="مثال: 101">
                        </div>

                        <div class="col-md-6">
                            <label for="empBaseSalary" class="form-label fw-semibold">الراتب الأساسي الشهري (ج.م) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="empBaseSalary" required min="1000" step="500" placeholder="10000">
                        </div>

                        <div class="col-md-4">
                            <label for="empHousingAllowance" class="form-label fw-semibold">بدل السكن (ج.م)</label>
                            <input type="number" class="form-control" id="empHousingAllowance" min="0" step="100" value="0">
                        </div>

                        <div class="col-md-4">
                            <label for="empTransportAllowance" class="form-label fw-semibold">بدل الانتقالات (ج.م)</label>
                            <input type="number" class="form-control" id="empTransportAllowance" min="0" step="100" value="0">
                        </div>

                        <div class="col-md-4">
                            <label for="empOtherAllowances" class="form-label fw-semibold">بدلات أخرى (ج.م)</label>
                            <input type="number" class="form-control" id="empOtherAllowances" min="0" step="100" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary" id="saveEmployeeBtn">
                        <i class="ri-save-line align-bottom me-1"></i> حفظ البيانات في النظام
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
