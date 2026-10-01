<!-- 2. Search Bar, Category Ribbon & Instant Quick Add Bar -->
<div class="p-2 px-3 border-bottom bg-body">
    <!-- Row 1: High-Speed Full Search Bar + View Toggle -->
    <div class="d-flex align-items-center gap-2 mb-2">
        <div class="input-group input-group-sm flex-grow-1">
            <span class="input-group-text bg-light text-primary border-end-0">
                <i class="ri-barcode-box-line me-1"></i> <i class="ri-search-line"></i>
            </span>
            <input type="text" class="form-control fw-bold border-start-0 border-end-0" id="catalogSearchInput" placeholder="ابحث باسم الصنف، الماركة، أو امسح الباركود 📷 (Enter للإضافة)..." oninput="renderCatalog()" onkeydown="handleBarcodeEnter(event)">
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

    <!-- Row 2: Fluid Horizontal Scrollable Category Ribbon -->
    <div class="pos-category-scroll-container mb-2" id="categoryTabsContainer">
        <button type="button" class="btn btn-sm btn-primary pos-category-pill text-nowrap" id="cat-tab-all" onclick="filterByCategory('all')">
            الكل (<span id="pillCountAll">0</span>)
        </button>
        <button type="button" class="btn btn-sm btn-soft-secondary pos-category-pill text-nowrap" id="cat-tab-batteries" onclick="filterByCategory('batteries')">
            🔋 بطاريات (<span id="pillCountBatteries">0</span>)
        </button>
        <button type="button" class="btn btn-sm btn-soft-secondary pos-category-pill text-nowrap" id="cat-tab-oils" onclick="filterByCategory('oils')">
            🛢️ زيوت وفلاتر (<span id="pillCountOils">0</span>)
        </button>
        <button type="button" class="btn btn-sm btn-soft-secondary pos-category-pill text-nowrap" id="cat-tab-greases" onclick="filterByCategory('greases')">
            🧪 شحوم وسوائل (<span id="pillCountGreases">0</span>)
        </button>
        <button type="button" class="btn btn-sm btn-soft-secondary pos-category-pill text-nowrap" id="cat-tab-services" onclick="filterByCategory('services')">
            🔧 صيانة الورشة (<span id="pillCountServices">0</span>)
        </button>
    </div>

    <!-- Row 3: Instant Quick-Add Bar (One-Place Rapid Entry) -->
    <div class="p-2 bg-light rounded border d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2 flex-grow-1" style="min-width: 240px;">
            <span class="fs-11 fw-bold text-dark text-nowrap"><i class="ri-flashlight-line text-warning me-1"></i>إضافة سريعة:</span>
            <div class="flex-grow-1">
                <select class="form-select form-select-sm fw-bold fs-11" id="quickAddProductSelect" onchange="onQuickSelectProduct(this)">
                    <option value="">-- اضغط للبحث السريع أو اختر أي صنف لإضافته للسلة مباشرة --</option>
                </select>
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
