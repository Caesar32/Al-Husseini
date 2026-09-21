<!-- Modal: Digital Printable Payslip -->
<div class="modal fade" id="payslipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-dark text-white p-3">
                <h5 class="modal-title fw-bold text-white"><i class="ri-file-paper-2-line me-2"></i> قسيمة الراتب الرسمية (Payslip)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="payslipPrintArea">
                <!-- Populated dynamically via JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="window.print();">
                    <i class="ri-printer-line me-1"></i> طباعة القسيمة
                </button>
            </div>
        </div>
    </div>
</div>
