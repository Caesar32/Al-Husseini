<!-- Manager Control & Actions Bar -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card mb-0">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-4 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="ri-search-line text-muted"></i></span>
                            <input type="text" class="form-control border-start-0" id="searchPayrollInput" placeholder="بحث بالاسم، كود الموظف، الوظيفة، الهاتف..." value="{{ request('search', '') }}">
                            <button class="btn btn-light border" type="button" id="btnClearPayrollSearch" title="مسح البحث">
                                <i class="ri-close-line"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <select class="form-select" id="payrollBranchFilter">
                            <option value="all">جميع فروع المركز</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (string)request('branch_id') === (string)$branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-5 col-md-12 text-md-end">
                        <div class="d-flex gap-2 justify-content-lg-end">
                            <button type="button" class="btn btn-primary" id="btnOpenGenerateModal">
                                <i class="ri-calculator-line align-bottom me-1"></i> احتساب مسير الشهر
                            </button>
                            <button type="button" class="btn btn-danger" id="btnOpenManagerDeduction">
                                <i class="ri-hand-coin-fill align-bottom me-1"></i> تطبيق خصم إداري
                            </button>
                            <button type="button" class="btn btn-soft-secondary" onclick="window.print();">
                                <i class="ri-printer-line align-bottom me-1"></i> طباعة
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
