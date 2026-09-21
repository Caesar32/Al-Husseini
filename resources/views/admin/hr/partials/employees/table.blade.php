<!-- Table View Container -->
<div class="row" id="tableViewContainer">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle table-nowrap mb-0 table-hover">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">الموظف</th>
                                <th scope="col">الفرع</th>
                                <th scope="col">القسم والمسمى الوظيفي</th>
                                <th scope="col">مواعيد العمل الرسمية</th>
                                <th scope="col">الراتب الأساسي</th>
                                <th scope="col">الحالة</th>
                                <th scope="col" class="text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="employeesTableBody">
                            <!-- Loaded dynamically via JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3" id="paginationContainer">
                    <!-- Pagination controls loaded dynamically -->
                </div>
            </div>
        </div>
    </div>
</div>
