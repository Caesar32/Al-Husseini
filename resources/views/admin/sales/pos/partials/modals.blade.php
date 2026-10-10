<!-- ============================================================== -->
<!-- Modal: Fast Add New Customer (5 Seconds)                       -->
<!-- ============================================================== -->
<div class="modal fade" id="newCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom p-3">
                <h5 class="modal-title fw-bold text-dark fs-15"><i class="ri-user-add-line text-primary me-1"></i> تسجيل عميل وسيارة جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="newCustomerForm" onsubmit="saveQuickCustomer(event)">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-13">اسم العميل بالكامل <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="newCustName" required placeholder="مثال: أحمد عبد الحميد">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-13">رقم الهاتف المحمول <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control font-monospace" id="newCustPhone" required placeholder="010XXXXXXXX">
                    </div>
                    <div class="row g-2 mb-1">
                        <div class="col-4">
                            <label class="form-label fw-bold text-dark fs-13">ماركة السيارة</label>
                            <input type="text" class="form-control" id="newCustCarBrand" maxlength="50" placeholder="تويوتا">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-bold text-dark fs-13">الموديل</label>
                            <input type="text" class="form-control" id="newCustCarModel" maxlength="50" placeholder="كورولا">
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-bold text-dark fs-13">رقم اللوحة</label>
                            <input type="text" class="form-control font-monospace text-center fw-bold" id="newCustPlate" maxlength="50" placeholder="أ ب ج 1234">
                        </div>
                    </div>
                    <small class="text-muted fs-11">بيانات المركبة اختيارية؛ عند إدخال رقم اللوحة تصبح الماركة والموديل مطلوبين.</small>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">حفظ وتعيين للفاتورة</button>
                </div>
            </form>
        </div>
    </div>
</div>
