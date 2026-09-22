<!-- Easy Filter Tabs & Date/Search Bar -->
<div class="card-header bg-transparent border-bottom p-3">
    <div class="row g-3 align-items-center justify-content-between">
        <!-- Filter Tabs -->
        <div class="col-lg-7 col-12">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="fw-bold fs-13 text-muted me-1">عرض السجلات:</span>
                <button type="button" class="filter-tab-pill active" id="tab-all" onclick="setQuickTabFilter('all')">
                    الكل (<span id="pillCountAll">{{ count($employees) }}</span>)
                </button>
                <button type="button" class="filter-tab-pill" id="tab-present" onclick="setQuickTabFilter('present')">
                    🟢 في الموعد (<span id="pillCountOnTime">{{ $stats['present'] ?? 0 }}</span>)
                </button>
                <button type="button" class="filter-tab-pill" id="tab-late" onclick="setQuickTabFilter('late')">
                    🟡 متأخرين (<span id="pillCountLate">{{ $stats['late'] ?? 0 }}</span>)
                </button>
                <button type="button" class="filter-tab-pill" id="tab-absent" onclick="setQuickTabFilter('absent')">
                    🔴 غياب / لم يسجل (<span id="pillCountAbsent">{{ $stats['absent'] ?? 0 }}</span>)
                </button>
            </div>
        </div>

        <!-- Date & Search Box -->
        <div class="col-lg-5 col-12 d-flex gap-2">
            <input type="date" class="form-control" id="attendanceDateFilter" value="{{ $date ?? date('Y-m-d') }}" style="max-width: 160px;">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="ri-search-line text-muted"></i></span>
                <input type="text" class="form-control border-start-0" id="searchEmployeeInput" placeholder="ابحث بالاسم، كود الموظف، الوظيفة، أو الهاتف..." value="{{ request('search', '') }}">
                <button class="btn btn-light border" type="button" onclick="clearSearch()" title="مسح البحث">
                    <i class="ri-close-line"></i>
                </button>
            </div>
        </div>
    </div>
</div>
