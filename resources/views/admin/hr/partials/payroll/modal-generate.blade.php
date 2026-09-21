<!-- Modal: Generate Monthly Payroll -->
<div class="modal fade" id="generatePayrollModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-primary text-white p-3">
                <h5 class="modal-title fw-bold text-white"><i class="ri-calculator-line me-2"></i> احتساب وتوليد مسير الرواتب الشهري</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="generatePayrollForm">
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 p-3 mb-3 fs-13">
                        <i class="ri-information-line me-2 fs-16 align-middle"></i>
                        سيقوم النظام تلقائياً باحتساب الرواتب الأساسية، والبدلات، والخصومات المستحقة على البصمة خلال الشهر، وتوليد مسودة المسير للاعتماد المالي.
                    </div>

                    <div class="mb-3">
                        <label for="genBranchSelect" class="form-label fw-semibold">الفرع المراد احتساب رواتبه <span class="text-danger">*</span></label>
                        <select class="form-select" id="genBranchSelect" required>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label for="genYearInput" class="form-label fw-semibold">السنة <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="genYearInput" required value="{{ date('Y') }}" min="2024" max="2030">
                        </div>
                        <div class="col-6">
                            <label for="genMonthInput" class="form-label fw-semibold">الشهر <span class="text-danger">*</span></label>
                            <select class="form-select" id="genMonthInput" required>
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ date('n') == $m ? 'selected' : '' }}>{{ $m }} - {{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="ri-magic-line me-1"></i> بدء الاحتساب التلقائي
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
