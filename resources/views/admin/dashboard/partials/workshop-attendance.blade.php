<div class="card dashboard-card h-100 d-flex flex-column overflow-hidden mb-0">
    <div class="card-header dashboard-header align-items-center d-flex justify-content-between p-3 flex-shrink-0">
        <div>
            <h5 class="card-title mb-0 fw-bold fs-14 text-dark">
                <i class="ri-team-line text-success me-1"></i> حضور فنيي وعمال الورشة لليوم
            </h5>
            <small class="text-muted fs-11">متابعة نوبات العمل وفنيي الصيانة والكهرباء</small>
        </div>
        <a href="{{ route('admin.hr.attendance') }}" class="btn btn-sm btn-soft-success rounded-pill px-3 fw-semibold">
            كشف الحضور <i class="ri-arrow-left-s-line align-middle"></i>
        </a>
    </div>

    <div class="card-body p-3 flex-grow-1 d-flex flex-column overflow-hidden">
        <!-- Stat counters -->
        <div class="row g-2 mb-3 text-center flex-shrink-0">
            <div class="col-4">
                <div class="p-2 bg-success-subtle border border-success-subtle dash-mini-tile">
                    <h5 class="mb-0 fw-bold text-success font-monospace" id="hrPresentCount">{{ $presentCount }}</h5>
                    <small class="text-muted fs-11">حاضرون بالورشة</small>
                </div>
            </div>
            <div class="col-4">
                <div class="p-2 bg-warning-subtle border border-warning-subtle dash-mini-tile">
                    <h5 class="mb-0 fw-bold text-warning font-monospace" id="hrLateCount">{{ $lateCount }}</h5>
                    <small class="text-muted fs-11">تأخير</small>
                </div>
            </div>
            <div class="col-4">
                <div class="p-2 bg-danger-subtle border border-danger-subtle dash-mini-tile">
                    <h5 class="mb-0 fw-bold text-danger font-monospace" id="hrAbsentCount">{{ $absentCount }}</h5>
                    <small class="text-muted fs-11">غياب / إجازة</small>
                </div>
            </div>
        </div>

        <!-- Technicians Quick List (Bounded, smooth scroll) -->
        <div class="d-flex flex-column gap-2 overflow-y-auto flex-grow-1 pe-1 custom-scroll-container" id="dashTechListContainer" style="max-height: 240px;">
            @forelse($workshopTechs as $tech)
                @php
                    $todayAtt = $tech->attendances->first();
                    $status = $todayAtt ? $todayAtt->status : 'present';
                @endphp
                <div class="d-flex justify-content-between align-items-center p-2 dash-mini-tile bg-light border fs-12 mb-1">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-xs flex-shrink-0">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-3 fw-bold fs-11">
                                {{ mb_substr($tech->full_name, 0, 1) }}
                            </span>
                        </div>
                        <div class="overflow-hidden">
                            <strong class="text-dark d-block fs-12 text-truncate">{{ $tech->full_name }}</strong>
                            <small class="text-muted fs-11 text-truncate d-block"><i class="ri-user-settings-line me-1"></i>{{ $tech->jobTitle->title ?? 'فني صيانة' }}</small>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-2">
                        @if($status === 'late')
                            <span class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">متأخر</span>
                        @elseif($status === 'absent' || $status === 'leave')
                            <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">غائب</span>
                        @else
                            <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2 py-1">حاضر بالوردية</span>
                        @endif
                        <small class="d-block text-muted font-monospace fs-10 mt-1">
                            {{ $tech->shift_start_time ? substr($tech->shift_start_time, 0, 5) : '09:00' }} - {{ $tech->shift_end_time ? substr($tech->shift_end_time, 0, 5) : '18:00' }}
                        </small>
                    </div>
                </div>
            @empty
                <div class="text-center py-3 text-muted fs-12">
                    لا يوجد فنيون مسجلون حالياً
                </div>
            @endforelse
        </div>
    </div>
</div>
