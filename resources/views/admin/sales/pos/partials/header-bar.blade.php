<!-- 1. Top Customer, Technician & Vehicle Header Bar -->
<div class="card-header bg-light border-bottom p-2 px-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <!-- Customer Selection -->
        <div class="d-flex align-items-center gap-1 flex-grow-1" style="min-width: 220px; max-width: 380px;">
            <span class="fs-12 fw-bold text-muted text-nowrap"><i class="ri-user-smile-line text-primary fs-14"></i> العميل:</span>
            <select class="form-select form-select-sm fw-bold fs-12 border-primary" id="posCustomerSelect" onchange="onCustomerSelected()">
                <option value="WALK_IN">عميل نقدي مباشر بالمعرض / الورشة</option>
            </select>
        </div>

        <!-- Technician Selection -->
        <div class="d-flex align-items-center gap-1 flex-grow-1" style="min-width: 180px; max-width: 270px;">
            <span class="fs-11 fw-bold text-danger text-nowrap"><i class="ri-user-settings-line text-primary fs-13"></i> الفني <span class="text-danger">*</span>:</span>
            <select class="form-select form-select-sm fw-bold fs-11 border-danger" id="posTechnicianSelect" required onchange="if(document.getElementById('posTechnicianSelectCart')) document.getElementById('posTechnicianSelectCart').value = this.value">
                <option value="">-- اختر الفني المسؤول * --</option>
                @foreach($technicians as $tech)
                    <option value="{{ $tech->id }}">{{ $tech->full_name }} ({{ $tech->employee_code }})</option>
                @endforeach
            </select>
        </div>

        <!-- Vehicle Details Display Badge -->
        <div class="px-2 py-1 bg-body rounded border d-flex align-items-center gap-2 fs-11 flex-grow-1" style="min-width: 170px; max-width: 260px;">
            <span class="text-muted text-nowrap"><i class="ri-car-line me-1"></i>المركبة:</span>
            <span class="fw-bold text-dark text-truncate" id="posCarDetailsDisplay" title="غير محدد">غير محدد</span>
            <select class="form-select form-select-sm fs-11 py-0 d-none" id="posVehicleSelect" aria-label="مركبة العميل"></select>
        </div>

        <!-- Status Indicators & New Customer Modal Trigger -->
        <div class="d-flex align-items-center gap-1 ms-auto shrink-0">
            @auth
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle d-none d-xl-inline-flex align-items-center gap-1" title="الكاشير الحالي">
                <i class="ri-user-line"></i> {{ auth()->user()->name }}
            </span>
            @endauth
            <span class="badge bg-success-subtle text-success border border-success-subtle d-none d-sm-inline-flex align-items-center gap-1" title="قارئ الباركود جاهز">
                <span class="pulse-dot"></span> باركود نشط
            </span>
            <button type="button" class="btn btn-sm btn-soft-primary fw-bold fs-11 text-nowrap" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                <i class="ri-user-add-line me-1"></i> عميل جديد
            </button>
        </div>
    </div>
</div>
