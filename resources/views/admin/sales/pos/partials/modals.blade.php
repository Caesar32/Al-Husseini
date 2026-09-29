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
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label fw-bold text-dark fs-13">نوع وموديل السيارة</label>
                            <input type="text" class="form-control" id="newCustCar" placeholder="تويوتا كورولا 2021">
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-bold text-dark fs-13">رقم اللوحة</label>
                            <input type="text" class="form-control font-monospace text-center fw-bold" id="newCustPlate" placeholder="أ ب ج 1234">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">حفظ وتعيين للفاتورة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- Modal: Printable Official Invoice & Warranty Receipt           -->
<!-- ============================================================== -->
<div class="modal fade" id="invoicePrintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom p-3 no-print">
                <div class="d-flex align-items-center gap-2">
                    <i class="ri-printer-line text-primary fs-18"></i>
                    <h5 class="modal-title fw-bold text-dark mb-0 fs-15">فاتورة معتمدة وشهادة ضمان رسمية</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="printableInvoiceContent">
                <!-- Rendered dynamically -->
            </div>
            <div class="modal-footer bg-light p-3 no-print d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إإغلاق</button>
                <button type="button" class="btn btn-primary fw-bold px-4" onclick="window.print()">
                    <i class="ri-printer-fill me-1"></i> طباعة الإيصال (A4 / حراري)
                </button>
            </div>
        </div>
    </div>
</div>
