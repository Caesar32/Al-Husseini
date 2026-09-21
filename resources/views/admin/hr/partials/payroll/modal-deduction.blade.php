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
                        <strong>تنبيه إداري:</strong> الخصم المعتمد سيتم استقطاعه فورياً من صافي راتب الموظف للشهر الحالي، وسيرسل النظام إشعاراً رسمياً للإدارة المالية.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="deductEmployeeSelect" class="form-label fw-semibold">اختر الموظف <span class="text-danger">*</span></label>
                            <select class="form-select" id="deductEmployeeSelect" required>
                                <option value="">-- اختر الموظف --</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_code }} - {{ $emp->branch?->name }})</option>
                                @endforeach
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
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger fw-bold">
                        <i class="ri-check-line align-middle me-1"></i> اعتماد الخصم فورياً
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
