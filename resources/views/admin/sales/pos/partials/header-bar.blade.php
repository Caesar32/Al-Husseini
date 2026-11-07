<!-- Transaction context: the native customer select remains the JavaScript source of truth. -->
<div class="card-header pos-transaction-context">
    <div class="pos-transaction-grid">
        <div class="pos-context-field pos-customer-field">
            <span class="pos-context-label"><i class="ri-user-smile-line" aria-hidden="true"></i> العميل</span>
            <div class="pos-customer-combobox">
                <input type="search" class="form-control" id="posCustomerSearch" placeholder="ابحث باسم العميل أو هاتفه" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="posCustomerOptions" aria-label="بحث واختيار العميل" data-action="pos.customer-search">
                <div class="pos-customer-options d-none" id="posCustomerOptions" role="listbox" aria-label="نتائج العملاء"></div>
            </div>
            <select class="pos-source-select" id="posCustomerSelect" data-action="pos.customer" aria-hidden="true" tabindex="-1">
                <option value="WALK_IN">عميل نقدي مباشر بالمعرض / الورشة</option>
            </select>
            <button type="button" class="btn btn-soft-primary pos-new-customer-button" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                <i class="ri-user-add-line" aria-hidden="true"></i>
                <span>عميل جديد</span>
            </button>
        </div>

        <div class="pos-context-field pos-vehicle-field">
            <span class="pos-context-label"><i class="ri-car-line" aria-hidden="true"></i> المركبة</span>
            <span class="pos-vehicle-display" id="posCarDetailsDisplay" title="غير محدد">غير محدد</span>
            <select class="form-select form-select-sm d-none" id="posVehicleSelect" aria-label="مركبة العميل"></select>
        </div>

        <label class="pos-context-field pos-technician-field" for="posTechnicianSelect">
            <span class="pos-context-label">الفني <span class="pos-required-marker" aria-label="مطلوب">*</span></span>
            <select class="form-select form-select-sm" id="posTechnicianSelect" required data-action="pos.sync-tech" data-target="posTechnicianSelectCart">
                <option value="">اختر الفني</option>
                @foreach($technicians as $tech)
                    <option value="{{ $tech->id }}">{{ $tech->full_name }} ({{ $tech->employee_code }})</option>
                @endforeach
            </select>
        </label>

        <div class="pos-context-meta">
            @auth
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" title="الكاشير الحالي: {{ auth()->user()->name }}">
                    <i class="ri-user-line" aria-hidden="true"></i> <span class="pos-cashier-name">{{ auth()->user()->name }}</span>
                </span>
            @endauth
            <span class="badge bg-success-subtle text-success border border-success-subtle" title="جاهز لاستقبال قارئ الباركود">
                <span class="pulse-dot"></span> قارئ نشط
            </span>
        </div>
    </div>
</div>
