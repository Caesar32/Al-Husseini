<!-- Main Attendance Table -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            {{-- Tabs & Filters Bar --}}
            @include('admin.hr.partials.attendance.filters')

            <!-- Table Content -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-nowrap mb-0" id="attendanceMainTable">
                        <thead class="table-light">
                            <tr class="text-muted fs-12 text-uppercase">
                                <th style="min-width: 200px;">الموظف / الفني</th>
                                <th>الوظيفة والفرع</th>
                                <th>موعد الوردية الرسمي</th>
                                <th>وقت البصمة الفعلي</th>
                                <th>وقت الانصراف</th>
                                <th>حالة الحضور اليوم</th>
                                <th>التأخير المسجل</th>
                                <th class="text-center" style="min-width: 190px;">تسجيل البصمة والإجراء المباشر</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
                            <!-- Loaded dynamically via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Table Footer Helper -->
            <div class="card-footer bg-light p-3 border-top d-flex flex-wrap justify-content-between align-items-center fs-12 text-muted">
                <div>
                    <i class="ri-information-line me-1 text-primary"></i> 
                    <strong>ملاحظة للمدير:</strong> تسجيل البصمة يتم بربط فوري مع قاعدة بيانات وسيرفر الموارد البشرية، ويتم احتساب دقائق التأخير تلقائياً.
                </div>
                <div>
                    تم التحديث تلقائياً: <span class="fw-bold text-dark" id="lastUpdatedTime">-</span>
                </div>
            </div>
        </div>
    </div>
</div>
