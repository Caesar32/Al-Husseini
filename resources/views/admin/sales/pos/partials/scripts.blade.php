<script>
'use strict';

// ─── Server data (single source of truth; real database ids) ─────────────────
const POS_DATA = {
    products: @json($products),
    customers: @json($customers),
    scrapTiers: @json($scrapTiers),
};
const POS_ROUTES = {
    store: @json(route('admin.pos.store')),
    customerStore: @json(route('admin.customers.store')),
    invoiceShow: @json(route('admin.invoices.show', ['invoice' => '__ID__'])),
};
const CATEGORY_LABELS = { batteries: 'بطاريات', oils: 'زيوت وفلاتر', greases: 'شحوم وسوائل', services: 'صيانة الورشة' };
const WALK_IN = 'WALK_IN';

let currentCategory = 'all';
let catalogViewMode = 'grid'; // 'grid' or 'list'
// Cart lines: { key, product, qty, serial }. Each battery unit is its own line (qty 1) because
// every battery carries its own serial number and warranty.
let cart = [];
let lineSeq = 0;
let currentPaymentMethod = 'cash';
let checkoutKey = null; // idempotency key, kept across retries of the same checkout
let audioCtx = null;

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

function findProduct(id) {
    return POS_DATA.products.find(p => p.id === Number(id)) || null;
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
    loadQuickAddDropdown();
    updateCategoryCounts();
    renderCatalog();
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
});

// ─── Customers & vehicles ────────────────────────────────────────────────────
function updateCategoryCounts() {
    const products = POS_DATA.products;
    const count = slug => products.filter(p => p.category === slug).length;
    document.getElementById('pillCountAll').textContent = products.length;
    document.getElementById('pillCountBatteries').textContent = count('batteries');
    document.getElementById('pillCountOils').textContent = count('oils');
    document.getElementById('pillCountGreases').textContent = count('greases');
    document.getElementById('pillCountServices').textContent = count('services');
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
        updateCreditBalance();
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
    updateCreditBalance();
}

// ─── Catalog ─────────────────────────────────────────────────────────────────
function loadQuickAddDropdown() {
    const select = document.getElementById('quickAddProductSelect');
    if (!select) return;

    let html = '<option value="">-- اضغط هنا للبحث السريع أو اختيار أي صنف لإضافته للسلة مباشرة --</option>';
    const groups = {};
    POS_DATA.products.forEach(p => {
        const label = CATEGORY_LABELS[p.category] || p.category_name || 'أخرى';
        (groups[label] = groups[label] || []).push(p);
    });

    Object.keys(groups).forEach(label => {
        html += `<optgroup label="=== ${esc(label)} ===">`;
        groups[label].forEach(p => {
            html += `<option value="${p.id}">${esc(p.name)} [${esc(p.brand || '')}] — ${esc(fmt(p.price))} (مخزن: ${p.stock})</option>`;
        });
        html += '</optgroup>';
    });

    select.innerHTML = html;
}

function onQuickSelectProduct(selectEl) {
    const prodId = selectEl.value;
    if (!prodId) return;
    addToCart(Number(prodId));
    selectEl.value = '';
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
    ['all', 'batteries', 'oils', 'greases', 'services'].forEach(t => {
        const el = document.getElementById(`cat-tab-${t}`);
        if (el) {
            el.className = (t === slug ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-soft-secondary') + ' pos-category-pill text-nowrap';
        }
    });
    renderCatalog();
}

function clearCatalogSearch() {
    const input = document.getElementById('catalogSearchInput');
    if (input) {
        input.value = '';
        input.focus();
        renderCatalog();
    }
}

function handleBarcodeEnter(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const input = document.getElementById('catalogSearchInput');
    const query = (input?.value || '').trim().toLowerCase();
    if (!query) return;

    // Exact match on barcode, SKU, supplier carton code, or full name.
    const matches = POS_DATA.products.filter(p =>
        (p.barcode && String(p.barcode).toLowerCase() === query) ||
        (p.sku && String(p.sku).toLowerCase() === query) ||
        (p.supplier_skus || []).some(s => String(s).toLowerCase() === query) ||
        p.name.toLowerCase() === query
    );

    if (matches.length === 1) {
        const prod = matches[0];
        if (addToCart(prod.id)) {
            playBeep(1050, 0.1);
            showBarcodeNotification(prod);
        }
        input.value = '';
        renderCatalog();
        return;
    }

    playBeep(350, 0.15);
    Swal.fire({
        icon: 'warning',
        title: matches.length > 1 ? 'الكود يطابق أكثر من صنف' : 'صنف غير مسجل بالباركود',
        text: matches.length > 1 ? 'يرجى اختيار الصنف من القائمة.' : `الكود "${query}" غير موجود بالمخزون.`,
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

function renderCatalog() {
    const searchVal = (document.getElementById('catalogSearchInput')?.value || '').trim().toLowerCase();
    const grid = document.getElementById('posCatalogGrid');
    if (!grid) return;

    const filtered = POS_DATA.products.filter(p => {
        if (currentCategory !== 'all' && p.category !== currentCategory) return false;
        if (!searchVal) return true;
        return [p.name, p.brand, p.category_name, p.barcode, p.sku, p.capacity_ah]
            .some(v => v && String(v).toLowerCase().includes(searchVal));
    });

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
        services: 'bg-primary-subtle text-primary'
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

    updateCreditBalance();
    renderQuickCashChips(t.total);
    calculateCashChange();
}

function setPaymentMethod(m) {
    currentPaymentMethod = m;
    ['cash', 'instapay', 'card', 'credit'].forEach(id => {
        document.getElementById(`pay-${id}`)?.classList.toggle('active', id === m);
    });
    document.getElementById('creditFieldsBox').classList.toggle('d-none', m !== 'credit');
    document.getElementById('cashPresetsBox').classList.toggle('d-none', m !== 'cash');
    updateCreditBalance();
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
    if (out) out.textContent = fmt(Math.max(0, received - cartTotals().total));
}

function updateCreditBalance() {
    if (currentPaymentMethod !== 'credit') return;
    const total = cartTotals().total;
    const depositInput = document.getElementById('creditDepositInput');
    let deposit = Math.max(0, Number(depositInput.value) || 0);
    if (deposit > total) {
        deposit = total;
        depositInput.value = total;
    }
    document.getElementById('creditBalanceOutput').value = fmt(Math.max(0, total - deposit));
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

    const payments = [];
    if (currentPaymentMethod === 'credit') {
        if (customerId === null) {
            return { error: 'لا يمكن البيع بالآجل لعميل نقدي مجهول! سجّل العميل أولاً.', newCustomer: true };
        }
        const deposit = Math.min(t.total, Math.max(0, Number(document.getElementById('creditDepositInput').value) || 0));
        const remaining = Math.max(0, t.total - deposit);
        if (deposit > 0) payments.push({ method: 'cash', amount: deposit });
        if (remaining > 0) payments.push({ method: 'credit', amount: remaining });
        if (payments.length === 0) payments.push({ method: 'credit', amount: t.total });
    } else {
        payments.push({ method: currentPaymentMethod === 'instapay' ? 'bank_transfer' : currentPaymentMethod, amount: t.total });
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
        notes: currentPaymentMethod === 'credit' ? 'مبيعات بالآجل من شاشة الكاشير' : 'مبيعات فورية بالمركز',
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
    renderCart();
    renderCatalog();
    loadQuickAddDropdown();
}
</script>
