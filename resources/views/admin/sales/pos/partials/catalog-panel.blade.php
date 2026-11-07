<!-- ============================================================== -->
<!-- RIGHT (RTL): Products & Services Catalog Panel (65% width)     -->
<!-- ============================================================== -->
<div class="col-xl-8 col-lg-7 pos-catalog-panel">
    <div class="card shadow-sm border-0 h-100 d-flex flex-column pos-catalog-card mb-0">
        
        <!-- 1. Customer & Vehicle Header -->
        @include('admin.sales.pos.partials.header-bar')

        <!-- 2. Search Bar, Category Ribbon & Quick Add -->
        @include('admin.sales.pos.partials.search-and-categories')

        <!-- 3. Product Grid (Smooth Internal Scroll) -->
        <div class="pos-catalog-scroll" id="posCatalogScrollContainer">
            <div class="row g-2" id="posCatalogGrid">
                <!-- Rendered dynamically by JavaScript -->
            </div>
            <!-- Infinite scroll trigger, observed by IntersectionObserver: a status line, no button -->
            <div id="catalogLoadMoreSentinel" class="pos-catalog-sentinel d-none">
                <span id="catalogLoadMoreStatus" role="status"></span>
            </div>
        </div>

    </div>
</div>
