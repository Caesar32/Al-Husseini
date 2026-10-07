<script>
'use strict';

// ─── Server data (single source of truth; real database ids) ─────────────────
// Only an initial ~36-product "fast movers" shortlist is embedded for instant paint — the full
// catalog (7,340+ items) is never sent to the browser; it loads on demand from the search API.
const POS_DATA = {
    products: @json($products),
    customers: @json($customers),
    scrapTiers: @json($scrapTiers),
};
const POS_ROUTES = {
    store: @json(route('admin.pos.store')),
    customerStore: @json(route('admin.customers.store')),
    invoiceShow: @json(route('admin.invoices.show', ['invoice' => '__ID__'])),
    productsSearch: @json(route('admin.pos.products.search')),
};
const WALK_IN = 'WALK_IN';

let currentCategory = 'all';
let catalogViewMode = 'grid'; // 'grid' or 'list'
// Cart lines: { key, product, qty, serial }. Each battery unit is its own line (qty 1) because
// every battery carries its own serial number and warranty.
let cart = [];
let lineSeq = 0;
let currentPaymentMethod = 'cash'; // how the amount paid now is paid: cash | instapay | card
let paymentMode = 'full';          // full | partial | remaining
let isSubmittingInvoice = false;   // blocks double clicks / double Enter while a checkout is in progress
let checkoutKey = null; // idempotency key, kept across retries of the same checkout
let audioCtx = null;

// ─── Catalog data layer: server-paginated search, client-side result caching ──
const productCache = new Map();       // id -> product object; shared by rendered cards AND cart lines
const searchResultsCache = new Map(); // "category|query|page" -> API response; repeat queries resolve from memory
let currentResults = [];              // the product set currently painted in the grid (accumulates across pages)
let currentQuery = '';
let currentPage = 1;
let currentLastPage = 1;
let isFetchingCatalog = false;
let isShowingFeatured = true;   // true until the user searches, picks a category, or scrolls past the shortlist
let catalogSearchDebounceTimer = null;
let scannerSettleTimer = null;
let lastKeystrokeAt = 0;
let keystrokeIntervals = [];    // recent inter-keystroke gaps (ms), used to tell a scanner from typing
let catalogLoadMoreObserver = null;

// ─── Helpers ─────────────────────────────────────────────────────────────────
function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

function fmt(amount) {
    const n = Number(amount) || 0;
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(n) + ' ج.م';
}

function newCheckoutKey() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID();
    }
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    return Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
}

function cacheProduct(p) {
    p.id = Number(p.id);
    productCache.set(p.id, p);
    return p;
}

function findProduct(id) {
    return productCache.get(Number(id)) || null;
}

function findCustomer(id) {
    return POS_DATA.customers.find(c => c.id === Number(id)) || null;
}

function qtyInCart(productId) {
    return cart.filter(l => l.product.id === productId).reduce((sum, l) => sum + l.qty, 0);
}

function playBeep(freq = 880, duration = 0.08) {
    try {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, audioCtx.currentTime);
        gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + duration);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + duration);
    } catch (e) {
        // Audio not allowed or unavailable
    }
}

document.addEventListener('DOMContentLoaded', function () {
    loadCustomersDropdown();

    POS_DATA.products.forEach(cacheProduct);
    currentResults = POS_DATA.products.slice();
    renderCatalog();
    setupInfiniteScroll();
    renderCart();

    document.addEventListener('keydown', function (e) {
        if (e.key === 'F2') {
            e.preventDefault();
            const input = document.getElementById('catalogSearchInput');
            if (input) {
                input.focus();
                input.select();
            }
        }
        if (e.key === 'F4') {
            e.preventDefault();
            clearCart();
        }
    });

    setupCategoryRibbonScroll();
});

// ─── Category ribbon: wheel, drag-to-scroll, and nav arrows ───────────────────
function setupCategoryRibbonScroll() {
    const ribbon = document.getElementById('categoryTabsContainer');
    if (!ribbon) return;

    // Mouse wheel -> horizontal scroll (deltaY is normally vertical-only on a horizontal strip).
    // If this feels reversed in testing, flip the two signs below — RTL scrollLeft direction is
    // a genuine per-browser judgment call, not something that can be verified without a browser.
    ribbon.addEventListener('wheel', (e) => {
        if (e.deltaY === 0) return;
        e.preventDefault();
        ribbon.scrollLeft += (e.deltaY > 0 ? 120 : -120);
    }, { passive: false });

    // Click-and-drag panning (desktop): content follows the cursor, like a touch drag.
    let isDragging = false;
    let dragMoved = false;
    let dragStartX = 0;
    let dragStartScrollLeft = 0;

    ribbon.addEventListener('mousedown', (e) => {
        isDragging = true;
        dragMoved = false;
        dragStartX = e.pageX;
        dragStartScrollLeft = ribbon.scrollLeft;
        ribbon.classList.add('is-dragging');
    });

    window.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        const delta = e.pageX - dragStartX;
        if (Math.abs(delta) > 3) dragMoved = true;
        ribbon.scrollLeft = dragStartScrollLeft - delta;
    });

    function endDrag() {
        if (!isDragging) return;
        isDragging = false;
        ribbon.classList.remove('is-dragging');
        if (dragMoved) {
            // A real drag just happened: swallow the click it would otherwise trigger on
            // whichever category pill is under the cursor (mousedown -> drag -> mouseup
            // normally fires a click too, which would wrongly switch category).
            const suppressClick = (evt) => { evt.preventDefault(); evt.stopPropagation(); };
            ribbon.addEventListener('click', suppressClick, { capture: true, once: true });
        }
    }
    window.addEventListener('mouseup', endDrag);
    ribbon.addEventListener('mouseleave', () => { if (isDragging && !dragMoved) endDrag(); });

    ribbon.addEventListener('scroll', () => updateCategoryNavArrows(ribbon));
    window.addEventListener('resize', () => updateCategoryNavArrows(ribbon));
    updateCategoryNavArrows(ribbon);
}

/** Nav arrow buttons: ‹ always pans physically left, › always pans physically right. */
function scrollCategoryRibbon(direction) {
    const ribbon = document.getElementById('categoryTabsContainer');
    if (!ribbon) return;
    ribbon.scrollBy({ left: direction * 160, behavior: 'smooth' });
}

/** Arrows are only shown when the ribbon actually overflows its container. */
function updateCategoryNavArrows(ribbon) {
    const leftBtn = document.getElementById('catScrollLeftBtn');
    const rightBtn = document.getElementById('catScrollRightBtn');
    if (!leftBtn || !rightBtn) return;

    const hasOverflow = ribbon.scrollWidth > ribbon.clientWidth + 1;
    leftBtn.classList.toggle('d-none', !hasOverflow);
    rightBtn.classList.toggle('d-none', !hasOverflow);
}

// ─── Customers & vehicles ────────────────────────────────────────────────────
function applyCategoryCounts(counts) {
    if (!counts) return;
    Object.keys(counts).forEach(slug => {
        const el = document.getElementById('pillCount-' + slug);
        if (el) el.textContent = counts[slug];
    });
}

function loadCustomersDropdown(selectedId = null) {
    const select = document.getElementById('posCustomerSelect');
    if (!select) return;

    const prevVal = selectedId !== null ? String(selectedId) : select.value;
    let html = `<option value="${WALK_IN}">عميل نقدي مباشر بالمعرض / الورشة</option>`;

    POS_DATA.customers.forEach(c => {
        const creditTag = c.credit_balance > 0 ? ` [عليه آجل: ${fmt(c.credit_balance)}]` : '';
        html += `<option value="${c.id}">${esc(c.name)} — ${esc(c.phone)}${esc(creditTag)}</option>`;
    });

    select.innerHTML = html;
    if (prevVal && select.querySelector(`option[value="${CSS.escape(prevVal)}"]`)) {
        select.value = prevVal;
    }
    onCustomerSelected();
}

function onCustomerSelected() {
    const custId = document.getElementById('posCustomerSelect').value;
    const display = document.getElementById('posCarDetailsDisplay');
    const vehicleSelect = document.getElementById('posVehicleSelect');

    vehicleSelect.innerHTML = '';
    vehicleSelect.classList.add('d-none');
    display.classList.remove('d-none');

    if (custId === WALK_IN) {
        display.textContent = 'عميل نقدي فوري بالمركز';
        renderPaymentSection();
        return;
    }

    const cust = findCustomer(custId);
    const vehicles = cust ? cust.vehicles : [];

    if (vehicles.length === 0) {
        display.textContent = 'لا توجد مركبة مسجلة';
    } else {
        let html = '<option value="">-- بدون تحديد مركبة --</option>';
        vehicles.forEach(v => {
            html += `<option value="${v.id}">${esc(v.car)} (${esc(v.plate_number)})</option>`;
        });
        vehicleSelect.innerHTML = html;
        // A single registered vehicle is preselected; with several the cashier chooses.
        if (vehicles.length === 1) vehicleSelect.value = String(vehicles[0].id);
        vehicleSelect.classList.remove('d-none');
        display.classList.add('d-none');
    }
    renderPaymentSection();
}

// ─── Catalog: server search + pagination ───────────────────────────────────────
/**
 * Fetches one page of the catalog from the server. Non-barcode queries are cached in memory
 * (Map) so repeat searches/category switches within the session resolve instantly with no
 * network round-trip. Every response also carries fresh cached category counts for the pills.
 */
async function fetchCatalogPage({ query = '', category = 'all', page = 1, perPage = 36, exactBarcode = false } = {}) {
    const cacheKey = exactBarcode ? null : `${category}|${query.toLowerCase()}|${page}|${perPage}`;
    if (cacheKey && searchResultsCache.has(cacheKey)) {
        return searchResultsCache.get(cacheKey);
    }

    const params = new URLSearchParams();
    if (query) params.set('q', query);
    if (category && category !== 'all') params.set('category', category);
    params.set('page', String(page));
    params.set('per_page', String(perPage));
    if (exactBarcode) params.set('exact_barcode', '1');

    const res = await fetch(`${POS_ROUTES.productsSearch}?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
    });
    if (!res.ok) throw new Error('تعذر الاتصال بالخادم لجلب الأصناف.');
    const json = await res.json();

    json.data.forEach(cacheProduct);
    applyCategoryCounts(json.category_counts);
    if (cacheKey) searchResultsCache.set(cacheKey, json);
    return json;
}

function renderSkeletonCards(count = 8) {
    const grid = document.getElementById('posCatalogGrid');
    if (!grid) return;
    let html = '';
    for (let i = 0; i < count; i++) {
        html += `
            <div class="col-6 col-sm-6 col-md-6 col-xl-4 col-xxl-3">
                <div class="pos-product-card pos-skeleton-card">
                    <div class="pos-skeleton-line w-50 mb-2"></div>
                    <div class="pos-skeleton-line w-100 mb-2" style="height:14px;"></div>
                    <div class="pos-skeleton-line w-75 mb-3"></div>
                    <div class="pos-skeleton-line w-25" style="height:16px;"></div>
                </div>
            </div>
        `;
    }
    grid.innerHTML = html;
}

function toggleLoadMore(hasMore) {
    document.getElementById('catalogLoadMoreSentinel')?.classList.toggle('d-none', !hasMore);
}

function setLoadMoreLoading(loading) {
    const btn = document.getElementById('catalogLoadMoreBtn');
    if (!btn) return;
    btn.disabled = loading;
    btn.innerHTML = loading
        ? '<span class="spinner-border spinner-border-sm me-1"></span> جاري التحميل...'
        : '<i class="ri-add-line me-1"></i> تحميل المزيد';
}

/**
 * Loads a catalog page. reset=true starts a fresh search/category/page-1 and replaces the grid;
 * reset=false appends the next page onto what's already shown (infinite scroll / "تحميل المزيد").
 */
async function loadCatalogPage({ reset = false } = {}) {
    if (isFetchingCatalog) return;
    isFetchingCatalog = true;

    if (reset) {
        currentQuery = (document.getElementById('catalogSearchInput')?.value || '').trim();
        currentPage = 1;
        isShowingFeatured = false;
        renderSkeletonCards(8);
        toggleLoadMore(false);
    } else {
        currentPage += 1;
        setLoadMoreLoading(true);
    }

    try {
        const json = await fetchCatalogPage({ query: currentQuery, category: currentCategory, page: currentPage, perPage: 36 });
        currentLastPage = json.meta.last_page;
        currentResults = reset ? json.data : currentResults.concat(json.data);
        renderCatalog();
        toggleLoadMore(json.meta.has_more);
    } catch (e) {
        console.error('POS catalog fetch error:', e);
        if (reset) {
            document.getElementById('posCatalogGrid').innerHTML = `
                <div class="col-12 text-center py-5 text-danger fs-13">
                    <i class="ri-error-warning-line fs-24 d-block mb-1"></i>
                    تعذر تحميل الأصناف من الخادم. تحقق من الاتصال وحاول مرة أخرى.
                </div>
            `;
        }
    } finally {
        isFetchingCatalog = false;
        setLoadMoreLoading(false);
    }
}

function loadNextCatalogPage() {
    if (isShowingFeatured) {
        // The initial screen is a curated "fast movers" shortlist ordered by popularity, not
        // page 1 of the real catalog — scrolling past it switches to the real, consistently
        // ordered, paginated catalog instead of appending onto an unrelated set.
        loadCatalogPage({ reset: true });
        return;
    }
    if (isFetchingCatalog || currentPage >= currentLastPage) return;
    loadCatalogPage({ reset: false });
}

function setupInfiniteScroll() {
    const sentinel = document.getElementById('catalogLoadMoreSentinel');
    const root = document.getElementById('posCatalogScrollContainer');
    if (!sentinel || !root || !('IntersectionObserver' in window)) return;
    catalogLoadMoreObserver = new IntersectionObserver((entries) => {
        if (entries[0]?.isIntersecting) loadNextCatalogPage();
    }, { root, rootMargin: '200px' });
    catalogLoadMoreObserver.observe(sentinel);
}

function setViewMode(mode) {
    catalogViewMode = mode;
    const gridBtn = document.getElementById('viewModeGridBtn');
    const listBtn = document.getElementById('viewModeListBtn');
    const [on, off] = mode === 'grid' ? [gridBtn, listBtn] : [listBtn, gridBtn];
    on.classList.add('active', 'btn-primary');
    on.classList.remove('btn-light');
    off.classList.remove('active', 'btn-primary');
    off.classList.add('btn-light');
    renderCatalog();
}

function filterByCategory(slug) {
    currentCategory = slug;
    document.querySelectorAll('.pos-category-pill').forEach(el => {
        const isActive = el.dataset.slug === slug;
        el.className = (isActive ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-soft-secondary') + ' pos-category-pill text-nowrap';
    });
    loadCatalogPage({ reset: true });
}

function clearCatalogSearch() {
    const input = document.getElementById('catalogSearchInput');
    if (input) {
        input.value = '';
        input.focus();
        keystrokeIntervals = [];
        loadCatalogPage({ reset: true });
    }
}

/**
 * Live search input: debounces normal typing (250ms), but detects hardware-scanner-speed input
 * (consecutive keystrokes averaging under 50ms apart, the signature of a barcode gun rather than
 * a human) and resolves it as a barcode almost immediately instead of waiting for the debounce.
 */
function onCatalogSearchInput() {
    const input = document.getElementById('catalogSearchInput');
    const now = performance.now();
    if (lastKeystrokeAt) {
        keystrokeIntervals.push(now - lastKeystrokeAt);
        if (keystrokeIntervals.length > 10) keystrokeIntervals.shift();
    }
    lastKeystrokeAt = now;

    clearTimeout(catalogSearchDebounceTimer);
    clearTimeout(scannerSettleTimer);

    const value = input.value.trim();
    if (!value) {
        loadCatalogPage({ reset: true });
        return;
    }

    const avgInterval = keystrokeIntervals.length
        ? keystrokeIntervals.reduce((a, b) => a + b, 0) / keystrokeIntervals.length
        : Infinity;
    const looksLikeScanner = value.length >= 6 && keystrokeIntervals.length >= 4 && avgInterval < 50;

    if (looksLikeScanner) {
        scannerSettleTimer = setTimeout(() => tryAutoAddByBarcode(value), 60);
        return;
    }

    catalogSearchDebounceTimer = setTimeout(() => loadCatalogPage({ reset: true }), 250);
}

/** Scanner-speed auto-detect path: silently tries an exact barcode match; falls back to a normal search. */
async function tryAutoAddByBarcode(code) {
    try {
        const json = await fetchCatalogPage({ query: code, exactBarcode: true, perPage: 5 });
        if (json.data.length === 1) {
            const prod = json.data[0];
            if (addToCart(prod.id)) {
                playBeep(1050, 0.1);
                showBarcodeNotification(prod);
                const input = document.getElementById('catalogSearchInput');
                if (input) input.value = '';
                keystrokeIntervals = [];
            }
            return;
        }
    } catch (e) {
        console.error('Barcode auto-detect error:', e);
    }
    loadCatalogPage({ reset: true });
}

/** Enter key: the reliable, primary barcode path (virtually every scanner sends a trailing Enter). */
async function handleBarcodeEnter(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const input = document.getElementById('catalogSearchInput');
    const query = (input?.value || '').trim();
    if (!query) return;

    clearTimeout(catalogSearchDebounceTimer);
    clearTimeout(scannerSettleTimer);

    let json;
    try {
        json = await fetchCatalogPage({ query, exactBarcode: true, perPage: 5 });
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'خطأ في الاتصال', text: 'تعذر الاتصال بالخادم. حاول مرة أخرى.', confirmButtonText: 'حسناً' });
        return;
    }

    if (json.data.length === 1) {
        const prod = json.data[0];
        if (addToCart(prod.id)) {
            playBeep(1050, 0.1);
            showBarcodeNotification(prod);
        }
        input.value = '';
        keystrokeIntervals = [];
        return;
    }

    playBeep(350, 0.15);
    Swal.fire({
        icon: 'warning',
        title: json.data.length > 1 ? 'الكود يطابق أكثر من صنف' : 'صنف غير مسجل بالباركود',
        text: json.data.length > 1 ? 'يرجى البحث بالاسم لاختيار الصنف المطلوب.' : `الكود "${query}" غير موجود بالمخزون.`,
        confirmButtonText: 'حسناً',
        customClass: { confirmButton: 'btn btn-warning fw-bold' }
    });
}

function showBarcodeNotification(prod) {
    const existing = document.getElementById('posBarcodeToast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'posBarcodeToast';
    toast.className = 'position-fixed bottom-0 start-50 translate-middle-x mb-4 p-2 px-3 bg-dark text-white rounded shadow-lg d-flex align-items-center gap-2';
    toast.style.zIndex = '99999';
    toast.innerHTML = `
        <i class="ri-checkbox-circle-fill text-success fs-18"></i>
        <div>
            <strong class="fs-12 text-white d-block">تمت الإضافة للسلة عبر الباركود:</strong>
            <span class="fs-11 text-warning fw-bold font-monospace">${esc(prod.name)} (${esc(prod.barcode || prod.sku || '')})</span>
        </div>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 1800);
}

// ─── Quick-add bar: lightweight async autocomplete (replaces the old 7,340-option <select>) ───
let quickAddDebounceTimer = null;
let quickAddResults = [];
let quickAddActiveIndex = -1;

function onQuickAddInput() {
    clearTimeout(quickAddDebounceTimer);
    const value = (document.getElementById('quickAddSearchInput')?.value || '').trim();
    if (!value) {
        hideQuickAddResults();
        return;
    }
    quickAddDebounceTimer = setTimeout(() => runQuickAddSearch(value), 250);
}

async function runQuickAddSearch(value) {
    try {
        const json = await fetchCatalogPage({ query: value, perPage: 8 });
        quickAddResults = json.data;
        quickAddActiveIndex = -1;
        renderQuickAddResults();
    } catch (e) {
        hideQuickAddResults();
    }
}

function renderQuickAddResults() {
    const dropdown = document.getElementById('quickAddResultsDropdown');
    if (!dropdown) return;

    if (quickAddResults.length === 0) {
        dropdown.innerHTML = `<div class="quick-add-result-empty">لا توجد نتائج مطابقة</div>`;
        dropdown.classList.remove('d-none');
        return;
    }

    dropdown.innerHTML = quickAddResults.map((p, i) => `
        <div class="quick-add-result-item ${i === quickAddActiveIndex ? 'active' : ''}" onmousedown="selectQuickAddResult(${p.id})">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <span class="fw-bold fs-12 text-dark text-truncate">${esc(p.name)}</span>
                <span class="fs-11 text-success fw-bold font-monospace text-nowrap">${esc(fmt(p.price))}</span>
            </div>
            <div class="fs-10 text-muted">${esc(p.brand || '')} ${p.stock <= 0 ? '— نفذ من المخزن' : '— مخزون: ' + p.stock}</div>
        </div>
    `).join('');
    dropdown.classList.remove('d-none');
}

function selectQuickAddResult(productId) {
    addToCart(productId);
    const input = document.getElementById('quickAddSearchInput');
    if (input) input.value = '';
    hideQuickAddResults();
    input?.focus();
}

function hideQuickAddResults() {
    const dropdown = document.getElementById('quickAddResultsDropdown');
    if (dropdown) {
        dropdown.classList.add('d-none');
        dropdown.innerHTML = '';
    }
    quickAddResults = [];
    quickAddActiveIndex = -1;
}

function onQuickAddBlur() {
    // Delayed so a click on a result (onmousedown fires before blur) still registers.
    setTimeout(hideQuickAddResults, 150);
}

function onQuickAddKeydown(e) {
    if (quickAddResults.length === 0) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        quickAddActiveIndex = Math.min(quickAddResults.length - 1, quickAddActiveIndex + 1);
        renderQuickAddResults();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        quickAddActiveIndex = Math.max(0, quickAddActiveIndex - 1);
        renderQuickAddResults();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const target = quickAddActiveIndex >= 0 ? quickAddResults[quickAddActiveIndex] : quickAddResults[0];
        if (target) selectQuickAddResult(target.id);
    } else if (e.key === 'Escape') {
        hideQuickAddResults();
    }
}

function stockBadgeHtml(p, compact) {
    const cls = compact ? 'fs-11 fw-bold px-2 py-1' : 'pos-stock-badge';
    if (p.stock <= 0) {
        return `<span class="badge bg-danger text-white ${cls}">نفذ من المخزن (0)</span>`;
    }
    if (p.stock <= 5) {
        return `<span class="badge bg-warning-subtle text-danger border border-warning ${cls}">متبقي ${p.stock} فقط!</span>`;
    }
    return `<span class="badge bg-success-subtle text-success border border-success-subtle ${cls}">المخزون: <strong>${p.stock}</strong></span>`;
}

/** Renders whatever is currently loaded in `currentResults` (the active search/category/page set). */
function renderCatalog() {
    const grid = document.getElementById('posCatalogGrid');
    if (!grid) return;

    const filtered = currentResults;

    if (filtered.length === 0) {
        grid.innerHTML = `
            <div class="col-12 text-center py-5 text-muted fs-13">
                <i class="ri-search-line fs-24 d-block mb-1"></i>
                <strong class="text-dark d-block mb-1">لا توجد أصناف أو خدمات مطابقة للبحث أو الباركود</strong>
                <small>تأكد من كتابة الاسم أو مسح الباركود بشكل سليم</small>
            </div>
        `;
        return;
    }

    const categoryBadges = {
        batteries: 'bg-success-subtle text-success',
        oils: 'bg-warning-subtle text-warning',
        greases: 'bg-info-subtle text-info',
        services: 'bg-primary-subtle text-primary',
        filters: 'bg-info-subtle text-info',
        brakes: 'bg-danger-subtle text-danger',
        suspension: 'bg-secondary-subtle text-secondary',
        belts: 'bg-dark-subtle text-dark',
        electrical: 'bg-warning-subtle text-warning',
        engine: 'bg-primary-subtle text-primary',
        'cooling-ac': 'bg-info-subtle text-info',
        general: 'bg-light text-dark',
    };

    if (catalogViewMode === 'list') {
        let rows = '';
        filtered.forEach(p => {
            const inCart = qtyInCart(p.id);
            const isOut = p.stock <= 0;
            const isMaxed = inCart >= p.stock;
            rows += `
                <tr onclick="addToCart(${p.id})" class="${isOut ? 'opacity-75' : ''}">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-light text-dark border fs-10 font-monospace px-2 py-1">${esc(p.brand || 'عام')}</span>
                            <strong class="text-dark fs-13">${esc(p.name)}</strong>
                            ${inCart ? `<span class="badge bg-success text-white fs-10"><i class="ri-shopping-cart-fill"></i> ${inCart}</span>` : ''}
                        </div>
                    </td>
                    <td><span class="badge bg-light text-secondary border fs-11 font-monospace">${esc(p.capacity_ah || p.category_name || '-')}</span></td>
                    <td class="text-center">${stockBadgeHtml(p, true)}</td>
                    <td class="text-end"><strong class="text-dark font-monospace fs-13">${esc(fmt(p.price))}</strong></td>
                    <td class="text-center" onclick="event.stopPropagation()">
                        <button type="button" class="btn btn-sm ${isOut || isMaxed ? 'btn-soft-secondary' : 'btn-primary'} py-1 px-3 fs-11 fw-bold shadow-sm" onclick="addToCart(${p.id})" ${isOut ? 'disabled' : ''}>
                            ${isOut ? 'نفذ' : (isMaxed ? 'الحد' : 'إضافة')}
                        </button>
                    </td>
                </tr>
            `;
        });

        grid.innerHTML = `
            <div class="col-12">
                <div class="table-responsive rounded border shadow-sm" style="background-color: var(--vz-card-bg, #ffffff);">
                    <table class="table table-hover pos-catalog-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>الصنف والماركة</th>
                                <th>النوع / السعة</th>
                                <th class="text-center">المخزن</th>
                                <th class="text-end">السعر قطاعي</th>
                                <th class="text-center" style="width: 100px;">إضافة للسلة</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </div>
        `;
        return;
    }

    let html = '';
    filtered.forEach(p => {
        const inCart = qtyInCart(p.id);
        const isOut = p.stock <= 0;
        const isMaxed = inCart >= p.stock;
        const catBadge = categoryBadges[p.category] || 'bg-light text-dark';

        html += `
            <div class="col-6 col-sm-6 col-md-6 col-xl-4 col-xxl-3">
                <div class="card pos-product-card ${isOut ? 'opacity-75' : ''}" onclick="addToCart(${p.id})">
                    ${inCart ? `<span class="in-cart-indicator"><i class="ri-shopping-cart-fill"></i> ${inCart} بالسلة</span>` : ''}
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1 mb-sm-2">
                            <span class="badge ${catBadge} fs-10 fs-sm-11 fw-bold font-monospace px-1 px-sm-2 py-0.5">${esc(p.brand || 'عام')}</span>
                            <span class="badge bg-dark-subtle text-dark border font-monospace fs-10 fs-sm-11 fw-bold px-1 px-sm-2 py-0.5">${esc(p.capacity_ah || '')}</span>
                        </div>
                        <h6 class="fw-extrabold text-dark fs-12 fs-sm-13 mb-1 mb-sm-2 lh-base text-truncate" title="${esc(p.name)}">${esc(p.name)}</h6>
                        <div class="d-flex justify-content-between align-items-center mb-1 mb-sm-2 bg-light p-1 px-1 px-sm-2 rounded border">
                            <span class="text-secondary font-monospace fs-9 fs-sm-10 text-truncate" style="max-width: 50%;" title="باركود الصنف">
                                <i class="ri-barcode-line text-dark me-1"></i>${esc(p.barcode || p.sku || 'بدون')}
                            </span>
                            ${stockBadgeHtml(p, false)}
                        </div>
                        <div class="d-flex justify-content-between text-muted fs-10 fs-sm-11 mb-1 mb-sm-2">
                            <span class="text-truncate">${esc(p.category_name || '')}</span>
                            ${p.is_battery && p.warranty_months > 0 ? `<span class="text-primary fw-bold text-nowrap"><i class="ri-shield-check-line me-1"></i>${p.warranty_months} شهر</span>` : ''}
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-1 pt-sm-2 border-top mt-auto">
                        <div>
                            <div class="fs-9 fs-sm-10 text-muted">السعر:</div>
                            <div class="text-success font-monospace fw-extrabold fs-13 fs-sm-15">${esc(fmt(p.price))}</div>
                        </div>
                        <button type="button" class="btn btn-sm ${isOut || isMaxed ? 'btn-soft-secondary' : 'btn-primary'} px-2 px-sm-3 py-1 fs-11 fs-sm-12 fw-bold shadow-sm text-nowrap" ${isOut ? 'disabled' : ''}>
                            ${isOut ? 'غير متاح' : (isMaxed ? 'الحد' : 'إضافة')}
                        </button>
                    </div>
                </div>
            </div>
        `;
    });

    grid.innerHTML = html;
}

// ─── Cart (stock limits mirror the server: total per product ≤ current stock) ─
function addToCart(productId) {
    const prod = findProduct(productId);
    if (!prod) return false;

    if (qtyInCart(prod.id) >= prod.stock) {
        playBeep(440, 0.15);
        Swal.fire({
            icon: 'warning',
            title: 'تنبيه نفاذ المخزون!',
            text: `الرصيد المتاح من الصنف (${prod.name}) هو ${prod.stock} قطعة فقط! لا يمكن إضافة المزيد إلى السلة.`,
            confirmButtonText: 'حسناً',
            timer: 2500
        });
        return false;
    }

    let focusKey = null;
    if (prod.is_battery) {
        focusKey = ++lineSeq;
        cart.push({ key: focusKey, product: prod, qty: 1, serial: '' });
    } else {
        const existing = cart.find(l => l.product.id === prod.id);
        if (existing) {
            existing.qty += 1;
        } else {
            cart.push({ key: ++lineSeq, product: prod, qty: 1, serial: '' });
        }
    }

    playBeep(920, 0.06);
    renderCart();
    renderCatalog();
    if (focusKey !== null) {
        document.querySelector(`input[data-serial-line="${focusKey}"]`)?.focus();
    }
    return true;
}

function updateCartQty(lineKey, delta) {
    const line = cart.find(l => l.key === lineKey);
    if (!line) return;

    if (delta > 0) {
        if (line.product.is_battery) {
            addToCart(line.product.id);
            return;
        }
        if (qtyInCart(line.product.id) >= line.product.stock) {
            playBeep(440, 0.15);
            Swal.fire({
                icon: 'warning',
                title: 'تجاوز رصيد المخزون!',
                text: `الحد الأقصى المتاح في المخزن هو ${line.product.stock} قطعة فقط.`,
                confirmButtonText: 'حسناً',
                timer: 2000
            });
            return;
        }
    }

    line.qty += delta;
    if (line.qty <= 0) {
        cart = cart.filter(l => l.key !== lineKey);
    }
    renderCart();
    renderCatalog();
}

function removeCartItem(lineKey) {
    cart = cart.filter(l => l.key !== lineKey);
    renderCart();
    renderCatalog();
}

function setLineSerial(lineKey, value) {
    const line = cart.find(l => l.key === lineKey);
    if (line) line.serial = value.trim();
}

function clearCart() {
    if (cart.length === 0) return;
    cart = [];
    renderCart();
    renderCatalog();
}

function renderCart() {
    const container = document.getElementById('cartItemsContainer');
    const totalCount = cart.reduce((sum, l) => sum + l.qty, 0);
    document.getElementById('cartCountBadge').textContent = totalCount;
    const mobileBadge = document.getElementById('mobileCartCountBadge');
    if (mobileBadge) mobileBadge.textContent = totalCount;

    if (cart.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5 text-muted fs-13" id="cartEmptyState">
                <i class="ri-shopping-basket-2-line fs-26 d-block mb-1 text-secondary"></i>
                السلة فارغة.. اضغط على أي بطارية أو زيت أو خدمة لإضافتها
            </div>
        `;
        calculateCartTotal();
        return;
    }

    let html = '';
    cart.forEach(line => {
        const p = line.product;
        html += `
            <div class="cart-item-row py-1 px-2 mb-1">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="overflow-hidden me-2" style="max-width: 54%;">
                        <div class="d-flex align-items-center gap-1">
                            <span class="badge bg-light text-dark border fs-9 py-0 px-1 font-monospace">${esc(p.brand || 'عام')}</span>
                            <span class="fs-11 fw-bold text-dark text-truncate" title="${esc(p.name)}">${esc(p.name)}</span>
                        </div>
                        <small class="text-muted fs-10 font-monospace d-block">${esc(fmt(p.price))} × ${line.qty}</small>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        ${p.is_battery ? '' : `
                        <div class="btn-group btn-group-sm border rounded">
                            <button type="button" class="btn btn-sm btn-light py-0 px-1 fs-11 text-muted" onclick="updateCartQty(${line.key}, -1)" title="تقليل">-</button>
                            <span class="font-monospace fw-bold fs-11 px-2 py-0 d-flex align-items-center bg-white">${line.qty}</span>
                            <button type="button" class="btn btn-sm btn-light py-0 px-1 fs-11 text-muted" onclick="updateCartQty(${line.key}, 1)" title="زيادة">+</button>
                        </div>`}
                        <span class="font-monospace fw-bold text-success fs-12 ms-1 text-end" style="min-width: 65px;">${esc(fmt(p.price * line.qty))}</span>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" onclick="removeCartItem(${line.key})" title="حذف">
                            <i class="ri-delete-bin-line fs-13"></i>
                        </button>
                    </div>
                </div>
                ${p.is_battery ? `
                <div class="input-group input-group-sm mt-1">
                    <span class="input-group-text py-0 fs-10"><i class="ri-barcode-line me-1"></i>سيريال *</span>
                    <input type="text" class="form-control form-control-sm font-monospace fs-11 py-0" data-serial-line="${line.key}"
                           value="${esc(line.serial)}" placeholder="امسح أو اكتب سيريال البطارية" maxlength="100"
                           oninput="setLineSerial(${line.key}, this.value)">
                </div>` : ''}
            </div>
        `;
    });

    container.innerHTML = html;
    calculateCartTotal();
}

function scrollToCartOrSubmit() {
    const cartPanel = document.querySelector('.pos-cart-panel');
    if (cartPanel) {
        cartPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        cartPanel.classList.add('border', 'border-primary');
        setTimeout(() => cartPanel.classList.remove('border', 'border-primary'), 1200);
    }
}

// ─── Scrap trade-in: estimate from server tiers; the server computes the deduction ─
function tierPriceFor(ah) {
    const tier = POS_DATA.scrapTiers.find(t => ah >= t.min_ah && ah <= t.max_ah);
    return tier ? tier.price : null;
}

function scrapState() {
    const check = document.getElementById('tradeInCheck');
    if (!check || !check.checked) {
        return { active: false, deduction: 0, ah: null, count: null, manual: null };
    }
    const ah = parseInt(document.getElementById('scrapCapacityInput').value, 10);
    const count = Math.max(1, parseInt(document.getElementById('scrapCountInput').value, 10) || 1);
    const manualRaw = document.getElementById('scrapPriceInput').value.trim();
    const manual = manualRaw === '' ? null : Math.max(0, parseFloat(manualRaw) || 0);
    const unit = Number.isFinite(ah) ? tierPriceFor(ah) : null;
    const estimate = unit === null ? 0 : unit * count;
    return { active: true, deduction: manual !== null ? manual : estimate, ah, count, manual, unit };
}

function toggleTradeInFields() {
    const check = document.getElementById('tradeInCheck');
    document.getElementById('tradeInDetailsBox').classList.toggle('d-none', !check.checked);
    updateScrapHint();
    calculateCartTotal();
}

function adjustScrapCount(delta) {
    const input = document.getElementById('scrapCountInput');
    input.value = Math.max(1, (parseInt(input.value, 10) || 1) + delta);
    updateScrapHint();
    calculateCartTotal();
}

function onScrapCapacityChange() {
    updateScrapHint();
    calculateCartTotal();
}

function updateScrapHint() {
    const hint = document.getElementById('scrapTierHint');
    const priceInput = document.getElementById('scrapPriceInput');
    if (!hint || !priceInput) return;
    const s = scrapState();
    if (!s.active) {
        hint.textContent = '';
        return;
    }
    if (s.unit === null) {
        hint.textContent = 'لا توجد شريحة تسعير لهذه السعة — أدخل السعر يدوياً.';
        priceInput.placeholder = 'السعر';
    } else {
        hint.textContent = `سعر الشريحة: ${fmt(s.unit)} × ${s.count}`;
        priceInput.placeholder = String(s.unit * s.count);
    }
}

// ─── Totals ──────────────────────────────────────────────────────────────────
function discountValue() {
    const raw = parseFloat(document.getElementById('cartDiscountInput')?.value);
    return Number.isFinite(raw) && raw > 0 ? raw : 0;
}

function cartTotals() {
    const subtotal = cart.reduce((sum, l) => sum + l.product.price * l.qty, 0);
    const scrap = scrapState();
    const discount = discountValue();
    // Tax: no tax rule is configured in the system (VAT setting unused), so none is applied.
    const total = Math.max(0, subtotal - discount - scrap.deduction);
    return { subtotal, scrap, discount, total };
}

function calculateCartTotal() {
    const t = cartTotals();
    document.getElementById('cartSubtotal').textContent = fmt(t.subtotal);

    const scrapRow = document.getElementById('cartScrapDiscountRow');
    const tradeInDisplay = document.getElementById('tradeInDiscountDisplay');
    if (t.scrap.deduction > 0) {
        scrapRow?.classList.remove('d-none');
        document.getElementById('cartScrapDiscountDisplay').textContent = `- ${fmt(t.scrap.deduction)}`;
        if (tradeInDisplay) tradeInDisplay.textContent = `- ${fmt(t.scrap.deduction)}`;
    } else {
        scrapRow?.classList.add('d-none');
        if (tradeInDisplay) tradeInDisplay.textContent = '- 0 ج.م';
    }

    document.getElementById('cartTotalAmount').textContent = fmt(t.total);
    const mobileTotal = document.getElementById('mobileCartTotalAmount');
    if (mobileTotal) mobileTotal.textContent = fmt(t.total);

    renderPaymentSection();
}

// ─── Payment: mode + method + amount paid now ────────────────────────────────
// Modes: full (pay exactly the amount due), partial (pay > 0 and <= due now, the rest goes on
// credit), remaining (pay nothing now, the whole amount goes on credit). Amounts are handled in
// integer piasters so decimals never drift (0.1 + 0.2). The server validates everything again.
const toCents = (value) => Math.round((Number(value) || 0) * 100);
const fromCents = (cents) => Math.round(cents) / 100;
const moneyText = (cents) => fmt(fromCents(cents));
const PAYMENT_MODE_HINTS = {
    full: 'سيتم دفع كامل المبلغ المستحق الآن.',
    partial: 'ادفع جزءاً من المبلغ الآن (أكبر من صفر وحتى المستحق)، ويُسجَّل المتبقي على الآجل.',
    remaining: 'لا يُدفع شيء الآن: يُسجَّل كامل المبلغ المستحق على الآجل.',
};
let paidNowNotice = null; // { type, text } shown under the amount (e.g. value clamped to the maximum)

function blockNonNumericKeys(e) {
    if (['e', 'E', '+', '-'].includes(e.key)) e.preventDefault();
}

function selectedCustomerId() {
    const value = document.getElementById('posCustomerSelect')?.value;
    return !value || value === WALK_IN ? null : Number(value);
}

function setPaymentMode(mode) {
    if (!PAYMENT_MODE_HINTS[mode] || mode === paymentMode) return;
    paymentMode = mode;
    paidNowNotice = null;
    const input = document.getElementById('paidNowInput');
    if (input) input.value = '';
    renderPaymentSection();
    if (mode === 'partial' && input) input.focus();
}

function setPaymentMethod(m) {
    if (!['cash', 'instapay', 'card'].includes(m)) return;
    currentPaymentMethod = m;
    renderPaymentSection();
}

// What is paid now / what remains. A pure function of the amount due, the mode and the typed amount.
function paymentState() {
    const due = toCents(cartTotals().total);
    const raw = (document.getElementById('paidNowInput')?.value ?? '').trim();
    let paid = 0;
    let error = null;

    if (paymentMode === 'full') {
        paid = due;
    } else if (paymentMode === 'partial') {
        const entered = raw === '' ? null : toCents(raw);
        if (entered === null) {
            error = 'أدخل المبلغ المدفوع الآن (أكبر من صفر وحتى ' + moneyText(due) + ').';
        } else if (entered <= 0) {
            error = 'المبلغ المدفوع يجب أن يكون أكبر من الصفر.';
        } else if (entered > due) {
            error = 'لا يمكن أن يتجاوز المبلغ المدفوع المبلغ المستحق (' + moneyText(due) + ').';
        } else {
            paid = entered;
        }
    }
    if (due <= 0 && cart.length > 0) {
        error = 'المبلغ المستحق صفر؛ لا يمكن إصدار فاتورة بدون مبلغ مستحق. راجع الخصم.';
    }

    return { due, paid, remaining: Math.max(0, due - paid), error, typed: raw !== '' };
}

function onPaidNowInput() {
    const input = document.getElementById('paidNowInput');
    if (!input) return;
    paidNowNotice = null;

    // currency: at most two decimals
    if (/\.\d{3,}/.test(input.value)) input.value = input.value.replace(/(\.\d{2})\d+/, '$1');

    // never above the amount due: clamp and tell the cashier
    const due = toCents(cartTotals().total);
    if (input.value !== '' && toCents(input.value) > due) {
        input.value = String(fromCents(due));
        paidNowNotice = { type: 'warning', text: 'تم تعديل المبلغ إلى الحد الأقصى المسموح وهو المبلغ المستحق (' + moneyText(due) + ').' };
    }
    renderPaymentSection();
}

function renderPaymentSection() {
    const input = document.getElementById('paidNowInput');
    if (!input) return;
    const st = paymentState();

    ['full', 'partial', 'remaining'].forEach(m => {
        const button = document.getElementById('mode-' + m);
        button?.classList.toggle('active', m === paymentMode);
        button?.setAttribute('aria-checked', String(m === paymentMode));
    });
    document.getElementById('paymentModeHint').textContent = PAYMENT_MODE_HINTS[paymentMode];

    ['cash', 'instapay', 'card'].forEach(id => {
        document.getElementById('pay-' + id)?.classList.toggle('active', id === currentPaymentMethod);
    });
    const paysNow = paymentMode !== 'remaining';
    document.getElementById('paymentMethodBox')?.classList.toggle('d-none', !paysNow);

    // amount paid now: fixed in full mode, empty in remaining mode, free (0.01..due) in partial mode
    input.max = String(fromCents(st.due));
    if (paymentMode === 'full') {
        input.value = st.due > 0 ? fromCents(st.due).toFixed(2) : '';
        input.readOnly = true;
        input.disabled = false;
    } else if (paymentMode === 'remaining') {
        input.value = '';
        input.readOnly = false;
        input.disabled = true;
    } else {
        input.readOnly = false;
        input.disabled = st.due <= 0;
    }

    // remaining balance: always shown, never negative
    const remainingOutput = document.getElementById('paymentRemainingOutput');
    remainingOutput.value = moneyText(st.remaining);
    remainingOutput.classList.toggle('text-danger', st.remaining > 0);
    remainingOutput.classList.toggle('text-success', st.remaining === 0);
    document.getElementById('paymentRemainingLabel').className =
        'form-label fs-9 fw-bold mb-0 ' + (st.remaining > 0 ? 'text-danger' : 'text-success');

    // inline message
    let message = null;
    let type = 'danger';
    if (st.due <= 0 && cart.length > 0) {
        message = st.error;
    } else if (paymentMode === 'partial' && paidNowNotice) {
        message = paidNowNotice.text;
        type = paidNowNotice.type;
    } else if (paymentMode === 'partial' && st.typed && st.error) {
        message = st.error;
    } else if (st.remaining > 0 && cart.length > 0 && selectedCustomerId() === null) {
        message = 'المتبقي على الآجل يتطلب اختيار عميل مسجل (أو اختر كامل الدفع).';
        type = 'warning';
    }
    const messageEl = document.getElementById('paidNowMessage');
    messageEl.textContent = message ?? '';
    messageEl.classList.toggle('d-none', !message);
    messageEl.classList.toggle('text-danger', type === 'danger');
    messageEl.classList.toggle('text-warning', type === 'warning');
    input.classList.toggle('is-invalid', !!message && type === 'danger' && paymentMode === 'partial');

    // cash helper (change for the cash received) only when cash is paid now
    document.getElementById('cashPresetsBox')?.classList.toggle('d-none', !(paysNow && currentPaymentMethod === 'cash' && st.paid > 0));
    renderQuickCashChips(fromCents(st.paid));
    calculateCashChange();
}

function resetPaymentSection() {
    paymentMode = 'full';
    currentPaymentMethod = 'cash';
    paidNowNotice = null;
    const input = document.getElementById('paidNowInput');
    if (input) input.value = '';
    renderPaymentSection();
}

function renderQuickCashChips(total) {
    const container = document.getElementById('quickCashChips');
    if (!container) return;
    if (total <= 0) {
        container.innerHTML = '';
        return;
    }
    const chips = [{ label: 'المبلغ بالضبط', val: total }];
    [500, 1000].forEach(step => {
        const next = Math.ceil(total / step) * step;
        if (next > total && !chips.some(c => c.val === next)) chips.push({ label: fmt(next), val: next });
    });
    container.innerHTML = chips.map(c =>
        `<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 fs-11" onclick="setReceivedCash(${c.val})">${esc(c.label)}</button>`
    ).join('');
}

function setReceivedCash(amount) {
    const input = document.getElementById('cashReceivedInput');
    if (input) {
        input.value = amount;
        calculateCashChange();
    }
}

function calculateCashChange() {
    const received = Number(document.getElementById('cashReceivedInput')?.value) || 0;
    const out = document.getElementById('cashChangeOutput');
    if (out) out.textContent = fmt(Math.max(0, received - fromCents(paymentState().paid)));
}

// ─── Quick customer registration (server) ────────────────────────────────────
async function saveQuickCustomer(e) {
    e.preventDefault();
    const name = document.getElementById('newCustName').value.trim();
    const phone = document.getElementById('newCustPhone').value.trim();
    const brand = document.getElementById('newCustCarBrand').value.trim();
    const model = document.getElementById('newCustCarModel').value.trim();
    const plate = document.getElementById('newCustPlate').value.trim();

    if (plate && (!brand || !model)) {
        Swal.fire({ icon: 'warning', title: 'بيانات المركبة ناقصة', text: 'عند إدخال رقم اللوحة يجب إدخال الماركة والموديل.', confirmButtonText: 'حسناً' });
        return;
    }

    const body = { name, phone, tier: 'standard' };
    if (plate) body.vehicle = { plate_number: plate, car_brand: brand, car_model: model };

    try {
        const res = await fetch(POS_ROUTES.customerStore, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (typeof window.getCsrfToken === 'function' ? window.getCsrfToken() : '{{ csrf_token() }}')
            },
            body: JSON.stringify(body)
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) {
            const detail = data.errors ? Object.values(data.errors).flat().join(' - ') : (data.message || 'تعذر تسجيل العميل.');
            throw new Error(detail);
        }

        const c = data.data;
        POS_DATA.customers.push({
            id: c.id,
            name: c.name,
            phone: c.phone,
            credit_limit: Number(c.credit_limit) || 0,
            credit_balance: Number(c.current_credit_balance) || 0,
            vehicles: (c.vehicles || []).map(v => ({ id: v.id, plate_number: v.plate_number, car: `${v.car_brand} ${v.car_model}`.trim() })),
        });
        POS_DATA.customers.sort((a, b) => a.name.localeCompare(b.name, 'ar'));

        bootstrap.Modal.getInstance(document.getElementById('newCustomerModal'))?.hide();
        document.getElementById('newCustomerForm').reset();
        loadCustomersDropdown(c.id);

        Swal.fire({ icon: 'success', title: 'تم تسجيل العميل بنجاح!', text: `تم تعيين [${c.name}] للفاتورة الحالية.`, timer: 1500, showConfirmButton: false });
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'تعذر تسجيل العميل', text: err.message, confirmButtonText: 'موافق' });
    }
}

// ─── Checkout ────────────────────────────────────────────────────────────────
function buildPayload() {
    const techEl = document.getElementById('posTechnicianSelectCart')?.value
        ? document.getElementById('posTechnicianSelectCart')
        : document.getElementById('posTechnicianSelect');
    const techId = Number(techEl?.value);
    if (!Number.isInteger(techId) || techId <= 0) {
        return { error: 'يرجى اختيار اسم الفني أو العامل المسؤول عن التركيب قبل إصدار الفاتورة.', focus: techEl };
    }

    const custVal = document.getElementById('posCustomerSelect').value;
    let customerId = null;
    let vehicleId = null;
    if (custVal !== WALK_IN) {
        customerId = Number(custVal);
        if (!Number.isInteger(customerId) || !findCustomer(customerId)) {
            return { error: 'العميل المختار غير صالح. أعد تحميل الشاشة.' };
        }
        const vehicleVal = document.getElementById('posVehicleSelect').value;
        vehicleId = vehicleVal ? Number(vehicleVal) : null;
    }

    const items = [];
    for (const line of cart) {
        if (!Number.isInteger(line.product.id) || line.product.id <= 0) {
            return { error: 'صنف غير صالح في السلة. أعد تحميل الشاشة.' };
        }
        if (line.product.is_battery && !line.serial) {
            const input = document.querySelector(`input[data-serial-line="${line.key}"]`);
            return { error: `يرجى إدخال سيريال البطارية (${line.product.name}).`, focus: input };
        }
        items.push({
            product_id: line.product.id,
            quantity: line.qty,
            unit_price: line.product.price,
            battery_serial: line.product.is_battery ? line.serial : null,
        });
    }

    const t = cartTotals();
    if (t.scrap.active && !Number.isFinite(t.scrap.ah)) {
        return { error: 'يرجى إدخال سعة البطارية الكهنة (أمبير).', focus: document.getElementById('scrapCapacityInput') };
    }

    const pay = paymentState();
    if (pay.error) {
        return { error: pay.error, focus: document.getElementById('paidNowInput') };
    }

    const payments = [];
    if (pay.paid > 0) {
        payments.push({ method: currentPaymentMethod === 'instapay' ? 'bank_transfer' : currentPaymentMethod, amount: fromCents(pay.paid) });
    }
    if (pay.remaining > 0) {
        if (customerId === null) {
            return { error: 'لا يمكن ترك مبلغ متبقٍ على الآجل لعميل نقدي مجهول! سجّل العميل أولاً أو اختر كامل الدفع.', newCustomer: true };
        }
        payments.push({ method: 'credit', amount: fromCents(pay.remaining) });
    }

    if (!checkoutKey) checkoutKey = newCheckoutKey();

    const payload = {
        idempotency_key: checkoutKey,
        technician_id: techId,
        customer_id: customerId,
        customer_vehicle_id: vehicleId,
        items,
        has_scrap: t.scrap.active,
        discount_amount: t.discount,
        tax_amount: 0,
        payments,
        notes: pay.remaining > 0 ? 'مبيعات بالآجل من شاشة الكاشير' : 'مبيعات فورية بالمركز',
    };
    if (t.scrap.active) {
        payload.scrap_capacity_ah = t.scrap.ah;
        payload.scrap_count = t.scrap.count;
        // Without a manual amount the server prices the trade-in from the active tier.
        if (t.scrap.manual !== null) payload.scrap_deduction_amount = t.scrap.manual;
    }
    return { payload };
}

function postInvoice(payload) {
    return fetch(POS_ROUTES.store, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': (typeof window.getCsrfToken === 'function' ? window.getCsrfToken() : '{{ csrf_token() }}')
        },
        body: JSON.stringify(payload)
    });
}

function errorText(data, fallback) {
    if (data && data.errors) return Object.values(data.errors).flat().join('\n');
    return (data && data.message) || fallback;
}

async function submitFullInvoice() {
    if (isSubmittingInvoice) return; // double click / double Enter
    isSubmittingInvoice = true;
    try {
        await processInvoiceSubmission();
    } finally {
        isSubmittingInvoice = false;
    }
}

async function processInvoiceSubmission() {
    if (cart.length === 0) {
        Swal.fire({ icon: 'warning', title: 'السلة فارغة', text: 'يرجى اختيار صنف واحد على الأقل لإصدار الفاتورة.', confirmButtonText: 'حسناً' });
        return;
    }

    const built = buildPayload();
    if (built.error) {
        const res = await Swal.fire({
            icon: 'warning',
            title: 'لا يمكن إصدار الفاتورة',
            text: built.error,
            confirmButtonText: built.newCustomer ? 'تسجيل عميل جديد الآن' : 'حسناً',
            showCancelButton: !!built.newCustomer,
            cancelButtonText: 'إلغاء'
        });
        if (built.newCustomer && res.isConfirmed) {
            new bootstrap.Modal(document.getElementById('newCustomerModal')).show();
        }
        if (built.focus) {
            built.focus.focus();
            built.focus.classList.add('is-invalid');
        }
        return;
    }
    const payload = built.payload;

    const submitBtn = document.getElementById('btnSubmitInvoice');
    const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جاري إصدار الفاتورة...';
    }

    try {
        let res = await postInvoice(payload);
        let data = await res.json().catch(() => ({}));

        if (!res.ok || !data.success) {
            const needsOverride = data.errors && (
                data.errors.manager_override_code || data.errors.discount_amount ||
                Object.keys(data.errors).some(k => /^items\.\d+\.unit_price$/.test(k))
            );
            if (!needsOverride) {
                throw new Error(errorText(data, 'حدث خطأ أثناء اعتماد الفاتورة على الخادم.'));
            }

            const overrideMsg = errorText(data, 'هذه العملية تتطلب إدخال كود موافقة المشرف.');
            const { value: managerCode } = await Swal.fire({
                title: 'مطلوب إذن وموافقة المشرف',
                text: overrideMsg,
                input: 'password',
                inputPlaceholder: 'أدخل كود موافقة المشرف',
                showCancelButton: true,
                confirmButtonText: 'تأكيد الصلاحية والمتابعة',
                cancelButtonText: 'إلغاء'
            });
            if (!managerCode) {
                return;
            }
            payload.manager_override_code = managerCode;
            res = await postInvoice(payload);
            data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(errorText(data, 'فشل اعتماد كود المشرف.'));
            }
        }

        onInvoiceCreated(data);
    } catch (err) {
        console.error('POS Checkout Error:', err);
        Swal.fire({ icon: 'error', title: 'تعذر إتمام الفاتورة', text: err.message, confirmButtonText: 'موافق' });
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
        }
    }
}

function onInvoiceCreated(data) {
    playBeep(1200, 0.2);

    // Mirror the stock the server just consumed so the catalog stays accurate until reload.
    // line.product is the same object reference held in productCache, so this also keeps the
    // cache (and therefore every cached search page referencing this product) in sync.
    cart.forEach(line => {
        line.product.stock = Math.max(0, line.product.stock - line.qty);
    });

    const invoiceUrl = POS_ROUTES.invoiceShow.replace('__ID__', encodeURIComponent(data.invoice_id));
    Swal.fire({
        icon: 'success',
        title: 'تم إصدار الفاتورة وتثبيتها بنجاح!',
        html: `
            <div class="p-2 mb-2 bg-light rounded text-center">
                <strong class="text-primary font-monospace fs-16">${esc(data.invoice_number)}</strong>
                <div class="text-muted fs-12 mt-1">تم حفظ العملية في قاعدة البيانات وخصم الكميات من المخزون.</div>
            </div>
            <div class="d-flex justify-content-center flex-wrap gap-2 mt-3">
                <a href="${esc(data.receipt_url)}" target="_blank" class="btn btn-sm btn-primary"><i class="ri-printer-line me-1"></i> طباعة إيصال كاشير</a>
                <a href="${esc(data.warranty_cert_url)}" target="_blank" class="btn btn-sm btn-info text-white"><i class="ri-shield-check-line me-1"></i> شهادة الضمان</a>
                <a href="${esc(invoiceUrl)}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="ri-file-list-3-line me-1"></i> تفاصيل الفاتورة</a>
            </div>
        `,
        showConfirmButton: true,
        confirmButtonText: 'حسناً - بدء فاتورة جديدة',
        confirmButtonColor: '#0ab39c'
    });

    // New checkout: fresh cart and a fresh idempotency key.
    cart = [];
    checkoutKey = null;
    document.getElementById('tradeInCheck').checked = false;
    document.getElementById('tradeInDetailsBox').classList.add('d-none');
    document.getElementById('scrapPriceInput').value = '';
    document.getElementById('cartDiscountInput').value = '';
    document.getElementById('cashReceivedInput').value = '';
    resetPaymentSection();
    renderCart();
    renderCatalog();
    document.getElementById('quickAddSearchInput').value = '';
    hideQuickAddResults();
}
</script>
