<!-- Modal: Fast 1-Click Deduction for Late Arrival or Negligence -->
<div class="modal fade" id="quickDeductionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white p-3">
                <div class="d-flex align-items-center">
                    <div class="avatar-xs me-2">
                        <span class="avatar-title bg-white text-danger rounded-circle fs-16">
                            <i class="ri-scissors-cut-line"></i>
                        </span>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white fs-15 mb-0">تطبيق خصم وجزاء إداري فوري</h5>
                        <small class="text-white-50 fs-11">إدارة مركز الحسيني لبطاريات السيارات</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="quickDeductionForm">
                <input type="hidden" id="quickDedEmployeeId">
                <div class="modal-body p-4">
                    <!-- Target Employee Card -->
                    <div class="p-3 bg-light rounded-3 mb-3 d-flex align-items-center gap-3 border">
                        <div class="avatar-sm">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-16 fw-bold" id="quickDedAvatarText">م</span>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0 fs-14" id="quickDedEmployeeName">-</h6>
                            <span class="badge bg-primary-subtle text-primary fs-11" id="quickDedRole">-</span>
                            <span class="text-muted fs-11 ms-2" id="quickDedLatenessInfo"></span>
                        </div>
                    </div>

                    <!-- Fast Amount Presets -->
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-13 text-dark mb-1">
                            قيمة الخصم بالجنية المصري (اختر مبلغ سريع أو اكتبه):
                        </label>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <button type="button" class="btn btn-outline-danger btn-sm quick-amount-preset" onclick="setPresetAmount(150)">150 ج.م</button>
                            <button type="button" class="btn btn-outline-danger btn-sm quick-amount-preset" onclick="setPresetAmount(250)">250 ج.م</button>
                            <button type="button" class="btn btn-outline-danger btn-sm quick-amount-preset" onclick="setPresetAmount(350)">350 ج.م</button>
                            <button type="button" class="btn btn-outline-danger btn-sm quick-amount-preset" onclick="setPresetAmount(500)">500 ج.م</button>
                        </div>
                        <div class="input-group">
                            <input type="number" class="form-control form-control-lg fw-bold fs-16 border-danger text-center" id="quickDedAmount" required min="50" step="50" value="250">
                            <span class="input-group-text bg-danger-subtle text-danger fw-bold">جنيه مصري (EGP)</span>
                        </div>
                    </div>

                    <!-- Specific Reason -->
                    <div class="mb-3">
                        <label for="quickDedReason" class="form-label fw-bold fs-13 text-dark">سبب الجزاء والمخالفة:</label>
                        <select class="form-select form-select-lg fs-13" id="quickDedReason" required>
                            <option value="تأخير عن موعد فتح صالة المعرض واستقبال العملاء">تأخير عن موعد فتح صالة المعرض واستقبال العملاء</option>
                            <option value="تأخير عن موعد ورشة فحص وشحن البطاريات وتعبئة المحاليل">تأخير عن موعد ورشة فحص وشحن البطاريات وتعبئة المحاليل</option>
                            <option value="تأخر في الاستجابة لبلاغ طوارئ إنقاذ بطارية على الطريق">تأخر في الاستجابة لبلاغ طوارئ إنقاذ بطارية على الطريق</option>
                            <option value="إهمال في فحص كفاءة كابلات الدينامو لسيارة العميل">إهمال في فحص كفاءة كابلات الدينامو لسيارة العميل</option>
                            <option value="انصراف مبكر بدون إذن قبل إغلاق المعرض وجرد المخزن">انصراف مبكر بدون إذن قبل إغلاق المعرض وجرد المخزن</option>
                            <option value="عدم الالتزام بارتداء مهمات الوقاية بالورشة">عدم الالتزام بارتداء مهمات الوقاية بالورشة</option>
                            <option value="غياب كامل بدون إذن مسبق">غياب كامل بدون إذن مسبق</option>
                        </select>
                    </div>

                    <!-- Manager Notes -->
                    <div class="mb-0">
                        <label for="quickDedNotes" class="form-label fw-semibold fs-12 text-muted">ملاحظات إضافية من المدير (اختياري):</label>
                        <textarea class="form-control" id="quickDedNotes" rows="2" placeholder="اكتب أي توضيح للخصم ليظهر في كشف الموظف..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-light fw-semibold" data-bs-dismiss="modal">إلغاء الأمر</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4">
                        <i class="ri-check-line align-middle me-1"></i> تأكيد واعتماد الخصم فورياً
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
