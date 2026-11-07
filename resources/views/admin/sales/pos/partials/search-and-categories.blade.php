<!-- Product search and catalog navigation -->
<div class="pos-catalog-navigation">
    <div class="pos-product-search-row">
        <div class="pos-product-search-wrap">
            <i class="ri-barcode-box-line pos-product-search-icon" aria-hidden="true"></i>
            <input type="text" class="form-control" id="catalogSearchInput" placeholder="امسح الباركود مباشرة، أو اكتب اسم الصنف/الماركة..." autocomplete="off" aria-label="البحث عن صنف أو مسح الباركود" data-action="pos.search">
            <span class="pos-search-shortcuts" aria-hidden="true">
                <kbd>F2</kbd><span>للتركيز</span>
                <kbd>Enter</kbd><span>للإضافة</span>
                <kbd>F4</kbd><span>لتفريغ الفاتورة</span>
            </span>
            <button class="btn btn-light pos-search-clear-button" type="button" data-action="pos.clear-search" title="مسح البحث" aria-label="مسح البحث">
                <i class="ri-close-line" aria-hidden="true"></i>
            </button>
            <div id="quickAddResultsDropdown" class="d-none" role="listbox" aria-label="اقتراحات الأصناف"></div>
        </div>
        <div class="btn-group pos-view-toggle" role="group" aria-label="طريقة عرض الأصناف">
            <button type="button" class="btn btn-light border" id="viewModeGridBtn" data-action="pos.view" data-mode="grid" title="عرض بطاقات">
                <i class="ri-grid-fill" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-primary border active" id="viewModeListBtn" data-action="pos.view" data-mode="list" title="عرض قائمة">
                <i class="ri-list-check" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="pos-category-nav-row">
        <button type="button" class="btn btn-sm btn-light border pos-category-nav-arrow" id="catScrollLeftBtn" data-action="pos.ribbon" data-direction="1" title="للخلف" aria-label="تمرير القائمة للخلف">
            <i class="ri-arrow-left-s-line"></i>
        </button>
        <div class="pos-category-scroll-container" id="categoryTabsContainer">
            <button type="button" class="btn btn-primary pos-category-pill" id="cat-tab-all" data-slug="all" data-action="pos.category" data-category="all">
                الكل <span class="pos-pill-count">(<span id="pillCount-all">{{ $categories->sum('count') }}</span>)</span>
            </button>
            @foreach ($categories as $cat)
                <button type="button" class="btn btn-soft-secondary pos-category-pill" id="cat-tab-{{ $cat['slug'] }}" data-slug="{{ $cat['slug'] }}" data-action="pos.category" data-category="{{ $cat['slug'] }}">
                    @if ($cat['icon'])
                        <span aria-hidden="true">{{ $cat['icon'] }}</span>
                    @endif
                    {{ $cat['name'] }} <span class="pos-pill-count">(<span id="pillCount-{{ $cat['slug'] }}">{{ $cat['count'] }}</span>)</span>
                </button>
            @endforeach
        </div>
        <button type="button" class="btn btn-sm btn-light border pos-category-nav-arrow" id="catScrollRightBtn" data-action="pos.ribbon" data-direction="-1" title="للأمام" aria-label="تمرير القائمة للأمام">
            <i class="ri-arrow-right-s-line"></i>
        </button>
    </div>
</div>
