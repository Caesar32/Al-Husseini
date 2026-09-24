<!-- Modal: Printable Official Invoice & Warranty Receipt -->
<div class="modal fade" id="dashInvoicePrintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom p-3 no-print">
                <div class="d-flex align-items-center gap-2">
                    <i class="ri-printer-line text-primary fs-18"></i>
                    <h5 class="modal-title fw-bold text-dark mb-0 fs-15">فاتورة معتمدة وشهادة ضمان رسمية</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="dashPrintableInvoiceContent">
                <!-- Rendered dynamically -->
            </div>
            <div class="modal-footer bg-light p-3 no-print d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إغلاق</button>
                <button type="button" class="btn btn-primary fw-bold px-4" onclick="window.print()">
                    <i class="ri-printer-fill me-1"></i> طباعة الإيصال (A4 / حراري)
                </button>
            </div>
        </div>
    </div>
</div>
