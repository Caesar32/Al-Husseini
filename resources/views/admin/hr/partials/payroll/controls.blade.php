<!-- Manager Control & Actions Bar -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card mb-0">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-4 col-md-6">
                        <div class="search-box">
                            <input type="text" class="form-control" id="searchPayrollInput" placeholder="بحث باسم الموظف أو الوظيفة...">
                            <i class="ri-search-line search-icon"></i>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <select class="form-select" id="payrollBranchFilter">
                            <option value="all">جميع فروع المركز</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
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
