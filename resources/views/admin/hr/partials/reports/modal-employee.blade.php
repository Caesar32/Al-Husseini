<!-- Individual Employee Detail Modal -->
<div class="modal fade" id="employeeDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="employeeModalTitle">كشف حساب وانضباط الموظف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="employeeModalBody">
                <!-- Populated dynamically via JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                <button type="button" class="btn btn-primary" onclick="printModalEmployeeCard()">
                    <i class="ri-printer-line me-1"></i> طباعة كشف الموظف
                </button>
            </div>
        </div>
    </div>
</div>
