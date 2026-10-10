<!-- 2. Search Bar, Category Ribbon & Instant Quick Add Bar -->
<div class="p-2 px-3 border-bottom bg-body">
    <!-- Row 1: High-Speed Full Search Bar + View Toggle -->
    <div class="d-flex align-items-center gap-2 mb-2">
        <div class="input-group input-group-sm flex-grow-1">
            <span class="input-group-text bg-light text-primary border-end-0">
                <i class="ri-barcode-box-line me-1"></i> <i class="ri-search-line"></i>
            </span>
            <input type="text" class="form-control fw-bold border-start-0 border-end-0" id="catalogSearchInput" placeholder="ابحث باسم الصنف، الماركة، أو امسح الباركود 📷 (Enter للإضافة)..." oninput="onCatalogSearchInput()" onkeydown="handleBarcodeEnter(event)">
            <button class="btn btn-light border border-start-0 text-muted" type="button" onclick="clearCatalogSearch()" title="مسح البحث">
                <i class="ri-close-line"></i>
            </button>
        </div>
        <!-- View Mode Toggle: Grid vs Table List -->
        <div class="btn-group btn-group-sm shrink-0" role="group">
            <button type="button" class="btn btn-light border active px-2" id="viewModeGridBtn" onclick="setViewMode('grid')" title="عرض بطاقات كروت">
                <i class="ri-grid-fill"></i>
            </button>
            <button type="button" class="btn btn-light border px-2" id="viewModeListBtn" onclick="setViewMode('list')" title="عرض جدول مدمج سريع">
                <i class="ri-list-check"></i>
            </button>
        </div>
    </div>

    <!-- Row 2: Fluid Horizontal Scrollable Category Ribbon (server-driven: real categories + cached counts) -->
    <div class="pos-category-nav-row mb-2">
        <button type="button" class="btn btn-sm btn-light border pos-category-nav-arrow" id="catScrollLeftBtn" onclick="scrollCategoryRibbon(-1)" title="للخلف" aria-label="تمرير القائمة للخلف">
            <i class="ri-arrow-left-s-line"></i>
        </button>
        <div class="pos-category-scroll-container" id="categoryTabsContainer">
            <button type="button" class="btn btn-sm btn-primary pos-category-pill text-nowrap" id="cat-tab-all" data-slug="all" onclick="filterByCategory('all')">
                الكل (<span id="pillCount-all">{{ $categories->sum('count') }}</span>)
            </button>
            @foreach ($categories as $cat)
                <button type="button" class="btn btn-sm btn-soft-secondary pos-category-pill text-nowrap" id="cat-tab-{{ $cat['slug'] }}" data-slug="{{ $cat['slug'] }}" onclick="filterByCategory('{{ $cat['slug'] }}')">
                    {{ $cat['icon'] }} {{ $cat['name'] }} (<span id="pillCount-{{ $cat['slug'] }}">{{ $cat['count'] }}</span>)
                </button>
            @endforeach
        </div>
        <button type="button" class="btn btn-sm btn-light border pos-category-nav-arrow" id="catScrollRightBtn" onclick="scrollCategoryRibbon(1)" title="للأمام" aria-label="تمرير القائمة للأمام">
            <i class="ri-arrow-right-s-line"></i>
        </button>
    </div>

    <!-- Row 3: Instant Quick-Add Bar (One-Place Rapid Entry) — lightweight async autocomplete, not a 7,340-option <select> -->
    <div class="p-2 bg-light rounded border d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2 flex-grow-1 position-relative" style="min-width: 240px;">
            <span class="fs-11 fw-bold text-dark text-nowrap"><i class="ri-flashlight-line text-warning me-1"></i>إضافة سريعة:</span>
            <div class="flex-grow-1 position-relative">
                <input type="text" class="form-control form-select-sm fw-bold fs-11" id="quickAddSearchInput" autocomplete="off"
                       placeholder="-- اكتب للبحث السريع أو امسح الباركود لإضافة صنف مباشرة --"
                       oninput="onQuickAddInput()" onkeydown="onQuickAddKeydown(event)" onblur="onQuickAddBlur()">
                <div id="quickAddResultsDropdown" class="d-none"></div>
            </div>
        </div>
        <div class="d-none d-sm-flex align-items-center gap-1 text-muted fs-11 shrink-0">
            <span>اختصار:</span>
            <kbd class="bg-dark text-white px-1 rounded">F2</kbd> <span class="fs-10">بحث</span>
            <span class="mx-1 text-black-50">|</span>
            <kbd class="bg-dark text-white px-1 rounded">F4</kbd> <span class="fs-10">تفريغ</span>
        </div>
    </div>
</div>
