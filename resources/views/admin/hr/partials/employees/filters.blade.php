<!-- Controls & Filter Toolbar -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card mb-0">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-4 col-md-6">
                        <div class="search-box">
                            <input type="text" class="form-control" id="searchEmployeeInput" placeholder="بحث بالاسم أو الكود أو رقم الهاتف...">
                            <i class="ri-search-line search-icon"></i>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <select class="form-select" id="branchFilter">
                            <option value="">جميع فروع المركز</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <select class="form-select" id="statusFilter">
                            <option value="">جميع الحالات</option>
                            <option value="active">على رأس العمل</option>
                            <option value="on_leave">في إجازة</option>
                            <option value="suspended">موقوف</option>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6 text-md-end">
                        <div class="d-flex gap-2 justify-content-lg-end">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-primary active" id="viewTableBtn" title="عرض جدول"><i class="ri-list-check"></i></button>
                                <button type="button" class="btn btn-outline-primary" id="viewGridBtn" title="عرض بطاقات"><i class="ri-grid-fill"></i></button>
                            </div>
                            <button type="button" class="btn btn-success" id="btnAddEmployee">
                                <i class="ri-user-add-line align-bottom me-1"></i> إضافة موظف
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
