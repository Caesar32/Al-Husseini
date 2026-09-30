<script>
'use strict';

let currentCategory = 'all';
let catalogViewMode = 'grid'; // 'grid' or 'list'
let cart = [];
let currentPaymentMethod = 'cash';
let audioCtx = null;

// Audio Beep for Cashier Feedback
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

    window.addEventListener('alhusseini-sales-updated', function () {
        loadCustomersDropdown();
        loadQuickAddDropdown();
        updateCategoryCounts();
        renderCatalog();
    });

    // Keyboard Shortcuts
    document.addEventListener('keydown', function (e) {
        // F2: Focus Search / Scanner
        if (e.key === 'F2') {
            e.preventDefault();
            const input = document.getElementById('catalogSearchInput');
            if (input) {
                input.focus();
                input.select();
            }
        }
        // F4: Clear Cart
        if (e.key === 'F4') {
            e.preventDefault();
            clearCart();
        }
    });
});

function updateCategoryCounts() {
    if (!window.AlHusseiniSales) return;
    const products = window.AlHusseiniSales.getProducts();

    document.getElementById('pillCountAll').textContent = products.length;
    document.getElementById('pillCountBatteries').textContent = products.filter(p => p.category === 'بطاريات').length;
    document.getElementById('pillCountOils').textContent = products.filter(p => p.category === 'زيوت').length;
    document.getElementById('pillCountGreases').textContent = products.filter(p => p.category === 'شحوم وسوائل').length;
    document.getElementById('pillCountServices').textContent = products.filter(p => p.category === 'خدمات وصيانة').length;
}

function loadCustomersDropdown() {
    if (!window.AlHusseiniSales) return;
    const customers = window.AlHusseiniSales.getCustomers();
    const select = document.getElementById('posCustomerSelect');
    if (!select) return;

    const prevVal = select.value;
    let html = '<option value="CUST-CASH">عميل نقدي مباشر بالمعرض / الورشة</option>';

    customers.forEach(c => {
        const creditTag = c.creditBalance > 0 ? ` [عليه آجل: ${c.creditBalance} ج.م]` : '';
        html += `<option value="${c.id}">${c.name} — ${c.carModel} (${c.carPlate})${creditTag}</option>`;
    });

    select.innerHTML = html;
    if (prevVal) select.value = prevVal;
    onCustomerSelected();
}

function onCustomerSelected() {
    const select = document.getElementById('posCustomerSelect');
    const custId = select.value;
    const display = document.getElementById('posCarDetailsDisplay');

    if (custId === 'CUST-CASH') {
        display.textContent = 'عميل نقدي فوري بالمركز';
    } else {
        const cust = window.AlHusseiniSales.getCustomerById(custId);
        if (cust) {
            display.textContent = `${cust.carModel} | لوحة: ${cust.carPlate}`;
        }
    }
}

function loadQuickAddDropdown() {
    if (!window.AlHusseiniSales) return;
    const select = document.getElementById('quickAddProductSelect');
    if (!select) return;

    const products = window.AlHusseiniSales.getProducts();
    let html = '<option value="">-- اضغط هنا للبحث السريع أو اختيار أي صنف لإضافته للسلة مباشرة --</option>';

    // Group by Category for exceptional UX
    const categories = ['بطاريات', 'زيوت', 'شحوم وسوائل', 'خدمات وصيانة'];
    categories.forEach(cat => {
        const catProds = products.filter(p => p.category === cat);
        if (catProds.length > 0) {
            html += `<optgroup label="=== ${cat} ===">`;
            catProds.forEach(p => {
                const stockText = p.category === 'خدمات وصيانة' ? 'خدمة ورشة' : `مخزن: ${p.stock}`;
                const price = p.priceNew;
                html += `<option value="${p.id}">${p.name} [${p.brand || ''}] — ${window.AlHusseiniSales.formatCurrency(price)} (${stockText})</option>`;
            });
            html += `</optgroup>`;
        }
    });

    select.innerHTML = html;
}

function onQuickSelectProduct(selectEl) {
    const prodId = selectEl.value;
    if (!prodId) return;

    addToCart(prodId);
    selectEl.value = ''; // Reset for rapid continuous scanning/picking
}

function setViewMode(mode) {
    catalogViewMode = mode;
    const gridBtn = document.getElementById('viewModeGridBtn');
    const listBtn = document.getElementById('viewModeListBtn');

    if (mode === 'grid') {
        gridBtn.classList.add('active', 'btn-primary');
        gridBtn.classList.remove('btn-light');
        listBtn.classList.remove('active', 'btn-primary');
        listBtn.classList.add('btn-light');
    } else {
        listBtn.classList.add('active', 'btn-primary');
        listBtn.classList.remove('btn-light');
        gridBtn.classList.remove('active', 'btn-primary');
        gridBtn.classList.add('btn-light');
    }
    renderCatalog();
}

function filterByCategory(cat) {
    currentCategory = cat;

    const tabs = ['all', 'batteries', 'oils', 'greases', 'services'];
    const map = { 'all': 'all', 'بطاريات': 'batteries', 'زيوت': 'oils', 'شحوم وسوائل': 'greases', 'خدمات وصيانة': 'services' };

    tabs.forEach(t => {
        const el = document.getElementById(`cat-tab-${t}`);
        if (el) {
            if (map[cat] === t) {
                el.className = 'btn btn-sm btn-primary pos-category-pill';
            } else {
                el.className = 'btn btn-sm btn-soft-secondary pos-category-pill';
            }
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
    if (e.key === 'Enter') {
        e.preventDefault();
        const input = document.getElementById('catalogSearchInput');
        const query = (input?.value || '').trim();
        if (!query) return;

        // 1. Check exact barcode match first
        let prod = window.AlHusseiniSales.getProductByBarcode(query);

        // 2. If not found by exact barcode, check single search match
        if (!prod) {
            const all = window.AlHusseiniSales.getProducts();
            const matches = all.filter(p => 
                (p.barcode && p.barcode.toString().toLowerCase() === query.toLowerCase()) ||
                p.name.toLowerCase() === query.toLowerCase()
            );
            if (matches.length === 1) {
                prod = matches[0];
            }
        }

        if (prod) {
            addToCart(prod.id);
            playBeep(1050, 0.1);
            showBarcodeNotification(prod);
            input.value = '';
            renderCatalog();
        } else {
            playBeep(350, 0.15);
            Swal.fire({
                icon: 'warning',
                title: 'صنف غير مسجل بالباركود',
                text: `كود الباركود "${query}" غير موجود بالمخزون.`,
                confirmButtonText: 'حسناً',
                customClass: { confirmButton: 'btn btn-warning fw-bold' }
            });
        }
    }
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
            <span class="fs-11 text-warning fw-bold font-monospace">${prod.name} (${prod.barcode || ''})</span>
        </div>
    `;
    document.body.appendChild(toast);
    setTimeout(() => {
        if (toast) toast.remove();
    }, 1800);
}

function renderCatalog() {
    if (!window.AlHusseiniSales) return;
    const products = window.AlHusseiniSales.getProducts();
    const searchVal = (document.getElementById('catalogSearchInput')?.value || '').trim().toLowerCase();
    const grid = document.getElementById('posCatalogGrid');
    if (!grid) return;

    const filtered = products.filter(p => {
        if (currentCategory !== 'all' && p.category !== currentCategory) return false;
        if (searchVal) {
            const matchName = p.name.toLowerCase().includes(searchVal);
            const matchBrand = p.brand.toLowerCase().includes(searchVal);
            const matchCat = p.category.toLowerCase().includes(searchVal);
            const matchBarcode = p.barcode ? p.barcode.toString().toLowerCase().includes(searchVal) : false;
            if (!matchName && !matchBrand && !matchCat && !matchBarcode) return false;
        }
        return true;
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
        'بطاريات': 'bg-success-subtle text-success',
        'زيوت': 'bg-warning-subtle text-warning',
        'شحوم وسوائل': 'bg-info-subtle text-info',
        'خدمات وصيانة': 'bg-primary-subtle text-primary'
    };

    if (catalogViewMode === 'list') {
        // High-Speed Compact Table List View (1-Click Addition)
        let tableHtml = `
            <div class="col-12">
                <div class="table-responsive rounded border shadow-sm" style="background-color: var(--vz-card-bg, #ffffff);">
                    <table class="table table-hover pos-catalog-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>الصنف والماركة</th>
                                <th>النوع / السعة</th>
                                <th class="text-center">المخزن</th>
                                <th class="text-end">السعر قطاعي</th>
                                <th class="text-end">مع الكهنة</th>
                                <th class="text-center" style="width: 100px;">إضافة للسلة</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        filtered.forEach(p => {
            const isBattery = p.category === 'بطاريات';
            const isService = p.category === 'خدمات وصيانة';
            const cartItem = cart.find(it => it.product.id === p.id);
            const isOutOfStock = !isService && (parseInt(p.stock) || 0) <= 0;
            const currentInCartQty = cartItem ? cartItem.qty : 0;
            const isMaxedInCart = !isService && currentInCartQty >= (parseInt(p.stock) || 0);

            let stockBadge;
            if (isService) {
                stockBadge = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-11 fw-bold px-2 py-1"><i class="ri-flashlight-line me-1"></i>خدمة ورشة</span>`;
            } else if (isOutOfStock) {
                stockBadge = `<span class="badge bg-danger text-white fs-11 fw-bold px-2 py-1">نفذ (0)</span>`;
            } else if (p.stock <= 5) {
                stockBadge = `<span class="badge bg-warning-subtle text-danger border border-warning fs-11 fw-bold px-2 py-1">${p.stock} قطعة</span>`;
            } else {
                stockBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle fs-11 fw-bold font-monospace px-2 py-1">${p.stock} قطعة</span>`;
            }

            tableHtml += `
                <tr onclick="addToCart('${p.id}')" class="${isOutOfStock ? 'opacity-75' : ''}">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-light text-dark border fs-10 font-monospace px-2 py-1">${p.brand || 'عام'}</span>
                            <strong class="text-dark fs-13">${p.name}</strong>
                            ${cartItem ? `<span class="badge bg-success text-white fs-10"><i class="ri-shopping-cart-fill"></i> ${cartItem.qty}</span>` : ''}
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-light text-secondary border fs-11 font-monospace">${p.amp || p.unit || p.type || '-'}</span>
                    </td>
                    <td class="text-center">${stockBadge}</td>
                    <td class="text-end">
                        <strong class="text-dark font-monospace fs-13">${window.AlHusseiniSales.formatCurrency(p.priceNew)}</strong>
                    </td>
                    <td class="text-end">
                        <strong class="text-success font-monospace fs-14 fw-extrabold">${isBattery ? window.AlHusseiniSales.formatCurrency(p.priceWithOld) : '-'}</strong>
                    </td>
                    <td class="text-center" onclick="event.stopPropagation()">
                        <button type="button" class="btn btn-sm ${isOutOfStock || isMaxedInCart ? 'btn-soft-secondary' : 'btn-primary'} py-1 px-3 fs-11 fw-bold shadow-sm" onclick="addToCart('${p.id}')" ${isOutOfStock ? 'disabled' : ''}>
                            <i class="ri-${isOutOfStock ? 'close-line' : (isMaxedInCart ? 'check-line' : 'shopping-cart-2-line')}"></i> ${isOutOfStock ? 'نفذ' : (isMaxedInCart ? 'الحد' : 'إضافة')}
                        </button>
                    </td>
                </tr>
            `;
        });

        tableHtml += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        grid.innerHTML = tableHtml;
        return;
    }

    // Default Card Grid View
    let html = '';
    filtered.forEach(p => {
        const catBadge = categoryBadges[p.category] || 'bg-light text-dark';
        const isBattery = p.category === 'بطاريات';
        const isService = p.category === 'خدمات وصيانة';
        const price = isBattery ? p.priceWithOld : p.priceNew;

        // In-cart badge
        const cartItem = cart.find(it => it.product.id === p.id);
        const inCartBadge = cartItem ? 
            `<span class="in-cart-indicator"><i class="ri-shopping-cart-fill"></i> ${cartItem.qty} بالسلة</span>` : '';

        const isOutOfStock = !isService && (parseInt(p.stock) || 0) <= 0;
        const currentInCartQty = cartItem ? cartItem.qty : 0;
        const isMaxedInCart = !isService && currentInCartQty >= (parseInt(p.stock) || 0);

        // High-Visibility Stock Badge
        let stockIndicator;
        if (isService) {
            stockIndicator = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle pos-stock-badge"><i class="ri-flashlight-line me-1"></i>خدمة ورشة</span>`;
        } else if (isOutOfStock) {
            stockIndicator = `<span class="badge bg-danger text-white pos-stock-badge"><i class="ri-error-warning-line me-1"></i>نفذ من المخزن (0)</span>`;
        } else if (p.stock <= 5) {
            stockIndicator = `<span class="badge bg-warning-subtle text-danger border border-warning pos-stock-badge"><i class="ri-alarm-warning-line me-1"></i>متبقي ${p.stock} فقط!</span>`;
        } else {
            stockIndicator = `<span class="badge bg-success-subtle text-success border border-success-subtle pos-stock-badge"><i class="ri-archive-line me-1"></i>المخزون: <strong>${p.stock}</strong></span>`;
        }

        html += `
            <div class="col-6 col-sm-6 col-md-6 col-xl-4 col-xxl-3">
                <div class="card pos-product-card ${isOutOfStock ? 'opacity-75' : ''}" onclick="addToCart('${p.id}')">
                    ${inCartBadge}
                    <div>
                        <!-- Header: Brand & Capacity/Spec -->
                        <div class="d-flex justify-content-between align-items-center mb-1 mb-sm-2">
                            <span class="badge ${catBadge} fs-10 fs-sm-11 fw-bold font-monospace px-1 px-sm-2 py-0.5">${p.brand || 'عام'}</span>
                            <span class="badge bg-dark-subtle text-dark border font-monospace fs-10 fs-sm-11 fw-bold px-1 px-sm-2 py-0.5">${p.amp || p.unit || ''}</span>
                        </div>

                        <!-- Product Title -->
                        <h6 class="fw-extrabold text-dark fs-12 fs-sm-13 mb-1 mb-sm-2 lh-base text-truncate" title="${p.name}">
                            ${p.name}
                        </h6>

                        <!-- Barcode & Stock Pill -->
                        <div class="d-flex justify-content-between align-items-center mb-1 mb-sm-2 bg-light p-1 px-1 px-sm-2 rounded border">
                            <span class="text-secondary font-monospace fs-9 fs-sm-10 text-truncate" style="max-width: 50%;" title="باركود الصنف">
                                <i class="ri-barcode-line text-dark me-1"></i>${p.barcode || 'بدون'}
                            </span>
                            ${stockIndicator}
                        </div>

                        <div class="d-flex justify-content-between text-muted fs-10 fs-sm-11 mb-1 mb-sm-2">
                            <span class="text-truncate">${p.type || ''}</span>
                            ${p.warrantyMonths > 0 ? `<span class="text-primary fw-bold text-nowrap"><i class="ri-shield-check-line me-1"></i>${p.warrantyMonths} شهر</span>` : ''}
                        </div>
                    </div>

                    <!-- Price & Quick Add Button -->
                    <div class="d-flex justify-content-between align-items-center pt-1 pt-sm-2 border-top mt-auto">
                        <div>
                            ${isBattery ? `
                                <div class="fs-9 fs-sm-10 text-muted text-decoration-line-through">${window.AlHusseiniSales.formatCurrency(p.priceNew)} (جديد)</div>
                                <div class="text-success font-monospace fw-extrabold fs-13 fs-sm-15">
                                    ${window.AlHusseiniSales.formatCurrency(price)}
                                    <span class="fs-9 fs-sm-10 fw-bold text-secondary">(مع الكهنة)</span>
                                </div>
                            ` : `
                                <div class="fs-9 fs-sm-10 text-muted">السعر:</div>
                                <div class="text-success font-monospace fw-extrabold fs-13 fs-sm-15">
                                    ${window.AlHusseiniSales.formatCurrency(price)}
                                </div>
                            `}
                        </div>
                        <button type="button" class="btn btn-sm ${isOutOfStock || isMaxedInCart ? 'btn-soft-secondary' : 'btn-primary'} px-2 px-sm-3 py-1 fs-11 fs-sm-12 fw-bold shadow-sm text-nowrap" ${isOutOfStock ? 'disabled' : ''}>
                            <i class="ri-${isOutOfStock ? 'close-line' : (isMaxedInCart ? 'check-line' : 'shopping-cart-2-line')} me-1"></i> ${isOutOfStock ? 'غير متاح' : (isMaxedInCart ? 'الحد' : 'إضافة')}
                        </button>
                    </div>
                </div>
            </div>
        `;
    });

    grid.innerHTML = html;
}

// Cart Logic with Strict Stock Boundary Enforcement
function addToCart(productId) {
    if (!window.AlHusseiniSales) return;
    const prod = window.AlHusseiniSales.getProductById(productId);
    if (!prod) return;

    const isService = prod.category === 'خدمات وصيانة';
    const maxStock = isService ? 9999 : (parseInt(prod.stock) || 0);

    const existing = cart.find(item => item.product.id === productId);
    const currentQtyInCart = existing ? existing.qty : 0;

    if (!isService && currentQtyInCart >= maxStock) {
        playBeep(440, 0.15); // low error beep
        Swal.fire({
            icon: 'warning',
            title: 'تنبيه نفاذ المخزون!',
            text: `الرصيد المتاح من الصنف (${prod.name}) هو ${maxStock} قطعة فقط! لا يمكن إضافة المزيد إلى السلة.`,
            confirmButtonText: 'حسناً',
            timer: 2500
        });
        return;
    }

    if (existing) {
        existing.qty += 1;
    } else {
        cart.push({ product: prod, qty: 1 });
    }

    playBeep(920, 0.06);
    renderCart();
    renderCatalog(); // Update in-cart badges
}

function updateCartQty(productId, delta) {
    const idx = cart.findIndex(item => item.product.id === productId);
    if (idx !== -1) {
        const item = cart[idx];
        const isService = item.product.category === 'خدمات وصيانة';
        const maxStock = isService ? 9999 : (parseInt(item.product.stock) || 0);

        if (delta > 0 && !isService && item.qty >= maxStock) {
            playBeep(440, 0.15);
            Swal.fire({
                icon: 'warning',
                title: 'تجاوز رصيد المخزون!',
                text: `الحد الأقصى المتاح في المخزن هو ${maxStock} قطعة فقط.`,
                confirmButtonText: 'حسناً',
                timer: 2000
            });
            return;
        }

        item.qty += delta;
        if (item.qty <= 0) {
            cart.splice(idx, 1);
        }
    }
    renderCart();
    renderCatalog();
}

function removeCartItem(productId) {
    cart = cart.filter(item => item.product.id !== productId);
    renderCart();
    renderCatalog();
}

function clearCart() {
    if (cart.length === 0) return;
    cart = [];
    renderCart();
    renderCatalog();
}

function renderCart() {
    const container = document.getElementById('cartItemsContainer');
    const badge = document.getElementById('cartCountBadge');
    const totalCount = cart.reduce((sum, it) => sum + it.qty, 0);
    badge.textContent = totalCount;

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
    cart.forEach(item => {
        const p = item.product;
        const lineTotal = p.priceNew * item.qty;

        html += `
            <div class="cart-item-row d-flex justify-content-between align-items-center py-1 px-2 mb-1">
                <div class="overflow-hidden me-2" style="max-width: 54%;">
                    <div class="d-flex align-items-center gap-1">
                        <span class="badge bg-light text-dark border fs-9 py-0 px-1 font-monospace">${p.brand || 'عام'}</span>
                        <span class="fs-11 fw-bold text-dark text-truncate" title="${p.name}">${p.name}</span>
                    </div>
                    <small class="text-muted fs-10 font-monospace d-block">${window.AlHusseiniSales.formatCurrency(p.priceNew)} × ${item.qty}</small>
                </div>
                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                    <div class="btn-group btn-group-sm border rounded">
                        <button type="button" class="btn btn-sm btn-light py-0 px-1 fs-11 text-muted" onclick="updateCartQty('${p.id}', -1)" title="تقليل">-</button>
                        <span class="font-monospace fw-bold fs-11 px-2 py-0 d-flex align-items-center bg-white">${item.qty}</span>
                        <button type="button" class="btn btn-sm btn-light py-0 px-1 fs-11 text-muted" onclick="updateCartQty('${p.id}', 1)" title="زيادة">+</button>
                    </div>
                    <span class="font-monospace fw-bold text-success fs-12 ms-1 text-end" style="min-width: 65px;">${window.AlHusseiniSales.formatCurrency(lineTotal)}</span>
                    <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" onclick="removeCartItem('${p.id}')" title="حذف">
                        <i class="ri-delete-bin-line fs-13"></i>
                    </button>
                </div>
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

// Scrap Trade-in Interactive Helpers
function toggleTradeInFields() {
    const check = document.getElementById('tradeInCheck');
    const detailsBox = document.getElementById('tradeInDetailsBox');
    if (!check || !detailsBox) return;

    if (check.checked) {
        detailsBox.classList.remove('d-none');
        // If price is empty, suggest default from capacity & count
        const priceInput = document.getElementById('scrapPriceInput');
        if (!priceInput.value || Number(priceInput.value) <= 0) {
            updateSuggestedScrapPrice();
        }
    } else {
        detailsBox.classList.add('d-none');
    }
    calculateCartTotal(false);
}

function adjustScrapCount(delta) {
    const input = document.getElementById('scrapCountInput');
    if (!input) return;
    let count = Math.max(1, (parseInt(input.value) || 1) + delta);
    input.value = count;
    updateSuggestedScrapPrice();
    calculateCartTotal(false);
}

function onScrapCapacityChange() {
    updateSuggestedScrapPrice();
    calculateCartTotal(false);
}

function updateSuggestedScrapPrice() {
    const ahSelect = document.getElementById('scrapCapacitySelect');
    const countInput = document.getElementById('scrapCountInput');
    const priceInput = document.getElementById('scrapPriceInput');
    if (!ahSelect || !countInput || !priceInput) return;

    const ah = parseInt(ahSelect.value) || 70;
    const count = Math.max(1, parseInt(countInput.value) || 1);

    // Standard benchmark estimates per capacity
    const benchmarkRates = {
        45: 500,
        60: 700,
        70: 800,
        90: 1050,
        100: 1200,
        150: 1800
    };

    const unitPrice = benchmarkRates[ah] || 800;
    priceInput.value = unitPrice * count;
}

function calculateCartTotal(autoSyncScrapPrice = true) {
    const subtotal = cart.reduce((sum, it) => sum + (it.product.priceNew * it.qty), 0);
    const tradeInCheck = document.getElementById('tradeInCheck');
    const scrapPriceInput = document.getElementById('scrapPriceInput');
    const scrapCountInput = document.getElementById('scrapCountInput');

    let scrapDiscount = 0;
    if (tradeInCheck && tradeInCheck.checked) {
        const manualPrice = scrapPriceInput ? parseFloat(scrapPriceInput.value) : 0;
        scrapDiscount = Math.max(0, isNaN(manualPrice) ? 0 : manualPrice);
    }

    const total = Math.max(0, subtotal - scrapDiscount);

    document.getElementById('cartSubtotal').textContent = window.AlHusseiniSales.formatCurrency(subtotal);
    
    const scrapRow = document.getElementById('cartScrapDiscountRow');
    const tradeInDisplay = document.getElementById('tradeInDiscountDisplay');
    if (scrapDiscount > 0) {
        if (scrapRow) scrapRow.classList.remove('d-none');
        const scrapDisplayEl = document.getElementById('cartScrapDiscountDisplay');
        if (scrapDisplayEl) scrapDisplayEl.textContent = `- ${window.AlHusseiniSales.formatCurrency(scrapDiscount)}`;
        if (tradeInDisplay) tradeInDisplay.textContent = `- ${window.AlHusseiniSales.formatCurrency(scrapDiscount)}`;
    } else {
        if (scrapRow) scrapRow.classList.add('d-none');
        if (tradeInDisplay) tradeInDisplay.textContent = '- 0 ج.م';
    }

    document.getElementById('cartTotalAmount').textContent = window.AlHusseiniSales.formatCurrency(total);
    const mobileTotal = document.getElementById('mobileCartTotalAmount');
    if (mobileTotal) mobileTotal.textContent = window.AlHusseiniSales.formatCurrency(total);

    updateCreditBalance();
    renderQuickCashChips(total);
    calculateCashChange();
}

function setPaymentMethod(m) {
    currentPaymentMethod = m;
    ['cash', 'instapay', 'card', 'credit'].forEach(id => {
        const el = document.getElementById(`pay-${id}`);
        if (el) {
            if (id === m) el.classList.add('active');
            else el.classList.remove('active');
        }
    });

    const creditBox = document.getElementById('creditFieldsBox');
    const cashBox = document.getElementById('cashPresetsBox');

    if (m === 'credit') {
        creditBox.classList.remove('d-none');
        cashBox.classList.add('d-none');
        updateCreditBalance();
    } else if (m === 'cash') {
        creditBox.classList.add('d-none');
        cashBox.classList.remove('d-none');
    } else {
        creditBox.classList.add('d-none');
        cashBox.classList.add('d-none');
    }
}

function renderQuickCashChips(total) {
    const container = document.getElementById('quickCashChips');
    if (!container) return;

    if (total <= 0) {
        container.innerHTML = '';
        return;
    }

    const next500 = Math.ceil(total / 500) * 500;
    const next1000 = Math.ceil(total / 1000) * 1000;

    let chips = [
        { label: 'المبلغ بالضبط', val: total }
    ];

    if (next500 > total && !chips.some(c => c.val === next500)) {
        chips.push({ label: `${next500} ج.م`, val: next500 });
    }
    if (next1000 > total && !chips.some(c => c.val === next1000)) {
        chips.push({ label: `${next1000} ج.م`, val: next1000 });
    }

    let html = '';
    chips.forEach(c => {
        html += `<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 fs-11" onclick="setReceivedCash(${c.val})">${c.label}</button>`;
    });
    container.innerHTML = html;
}

function setReceivedCash(amount) {
    const input = document.getElementById('cashReceivedInput');
    if (input) {
        input.value = amount;
        calculateCashChange();
    }
}

function calculateCashChange() {
    const subtotal = cart.reduce((sum, it) => sum + (it.product.priceNew * it.qty), 0);
    const hasBattery = cart.some(it => it.product.category === 'بطاريات');
    const scrapDiscount = (hasBattery && document.getElementById('tradeInCheck').checked) ?
        cart.filter(it => it.product.category === 'بطاريات').reduce((sum, it) => sum + (it.product.scrapValue * it.qty), 0) : 0;
    const total = Math.max(0, subtotal - scrapDiscount);

    const received = Number(document.getElementById('cashReceivedInput')?.value) || 0;
    const change = Math.max(0, received - total);
    const out = document.getElementById('cashChangeOutput');
    if (out) {
        out.textContent = window.AlHusseiniSales.formatCurrency(change);
    }
}

function updateCreditBalance() {
    if (currentPaymentMethod !== 'credit') return;

    const subtotal = cart.reduce((sum, it) => sum + (it.product.priceNew * it.qty), 0);
    const hasBattery = cart.some(it => it.product.category === 'بطاريات');
    const scrapDiscount = (hasBattery && document.getElementById('tradeInCheck').checked) ?
        cart.filter(it => it.product.category === 'بطاريات').reduce((sum, it) => sum + (it.product.scrapValue * it.qty), 0) : 0;
    const total = Math.max(0, subtotal - scrapDiscount);

    const depositInput = document.getElementById('creditDepositInput');
    let deposit = Number(depositInput.value) || 0;

    if (deposit > total) {
        deposit = total;
        depositInput.value = total;
    }

    const remaining = Math.max(0, total - deposit);
    document.getElementById('creditBalanceOutput').value = window.AlHusseiniSales.formatCurrency(remaining);
}

// Quick Add Customer Modal
function saveQuickCustomer(e) {
    e.preventDefault();
    const name = document.getElementById('newCustName').value.trim();
    const phone = document.getElementById('newCustPhone').value.trim();
    const car = document.getElementById('newCustCar').value.trim() || 'سيارة ملاكي';
    const plate = document.getElementById('newCustPlate').value.trim() || 'غير محدد';

    const newCust = window.AlHusseiniSales.saveCustomer({
        name: name,
        phone: phone,
        carModel: car,
        carPlate: plate,
        creditLimit: 5000,
        creditBalance: 0
    });

    const modalEl = document.getElementById('newCustomerModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    loadCustomersDropdown();
    document.getElementById('posCustomerSelect').value = newCust.id;
    onCustomerSelected();

    Swal.fire({
        icon: 'success',
        title: 'تم تسجيل العميل بنجاح!',
        text: `تم تعيين [${name}] للفاتورة الحالية.`,
        timer: 1500,
        showConfirmButton: false
    });
}

function submitFullInvoice() {
    if (cart.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'السلة فارغة',
            text: 'يرجى اختيار صنف واحد على الأقل لإصدار الفاتورة.',
            confirmButtonText: 'حسناً',
            customClass: { confirmButton: 'btn btn-primary fw-bold' }
        });
        return;
    }

    const techEl = document.getElementById('posTechnicianSelectCart')?.value 
        ? document.getElementById('posTechnicianSelectCart') 
        : document.getElementById('posTechnicianSelect');
    const techId = techEl ? techEl.value : '';

    if (!techId) {
        Swal.fire({
            icon: 'warning',
            title: 'اسم العامل / الفني إجباري!',
            text: 'يرجى اختيار اسم الفني أو العامل المسؤول عن التركيب قبل إصدار الفاتورة.',
            confirmButtonText: 'اختيار الفني الآن',
            customClass: { confirmButton: 'btn btn-danger fw-bold' }
        }).then(() => {
            if (techEl) {
                techEl.focus();
                techEl.classList.add('is-invalid');
            }
        });
        return;
    }

    const selectEl = document.getElementById('posCustomerSelect');
    const custId = selectEl.value;
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    const customerVehicleId = selectedOption?.dataset?.vehicleId ? parseInt(selectedOption.dataset.vehicleId) : null;

    let custName = 'عميل نقدي مباشر';
    let custPhone = '-';
    let carModel = 'ملاكي';
    let carPlate = '-';

    if (custId !== 'CUST-CASH') {
        const cust = window.AlHusseiniSales ? window.AlHusseiniSales.getCustomerById(custId) : null;
        if (cust) {
            custName = cust.name;
            custPhone = cust.phone;
            carModel = cust.carModel;
            carPlate = cust.carPlate;
        } else if (selectedOption) {
            custName = selectedOption.text.split('—')[0].trim();
        }
    }

    const subtotal = cart.reduce((sum, it) => sum + (it.product.priceNew * it.qty), 0);
    const tradeInCheck = document.getElementById('tradeInCheck');
    const hasScrapTradeIn = tradeInCheck ? tradeInCheck.checked : false;

    let scrapDiscount = 0;
    let scrapCountVal = 1;
    let scrapAhVal = 70;

    if (hasScrapTradeIn) {
        const scrapPriceInput = document.getElementById('scrapPriceInput');
        const scrapCountInput = document.getElementById('scrapCountInput');
        const scrapCapacitySelect = document.getElementById('scrapCapacitySelect');

        scrapCountVal = scrapCountInput ? Math.max(1, parseInt(scrapCountInput.value) || 1) : 1;
        scrapAhVal = scrapCapacitySelect ? (parseInt(scrapCapacitySelect.value) || 70) : 70;
        scrapDiscount = scrapPriceInput ? Math.max(0, parseFloat(scrapPriceInput.value) || 0) : 0;
    }

    const totalAmount = Math.max(0, subtotal - scrapDiscount);

    let paidAmount = totalAmount;
    let remainingCredit = 0;

    if (currentPaymentMethod === 'credit') {
        if (custId === 'CUST-CASH') {
            Swal.fire({
                icon: 'error',
                title: 'تنبيه بيع بالآجل',
                text: 'لا يمكن البيع بالآجل لعميل نقدي مجهول! اضغط على "عميل جديد" أولاً لتسجيل اسمه ورقم هاتفه.',
                confirmButtonText: 'تسجيل عميل جديد الآن'
            }).then(res => {
                if (res.isConfirmed) {
                    const modal = new bootstrap.Modal(document.getElementById('newCustomerModal'));
                    modal.show();
                }
            });
            return;
        }
        const depositVal = Number(document.getElementById('creditDepositInput').value) || 0;
        paidAmount = Math.min(totalAmount, Math.max(0, depositVal));
        remainingCredit = Math.max(0, totalAmount - paidAmount);
    }

    // Build backend payments array
    let paymentsPayload = [];
    if (currentPaymentMethod === 'credit') {
        if (paidAmount > 0) {
            paymentsPayload.push({ method: 'cash', amount: paidAmount });
        }
        if (remainingCredit > 0) {
            paymentsPayload.push({ method: 'credit', amount: remainingCredit });
        }
        if (paymentsPayload.length === 0) {
            paymentsPayload.push({ method: 'credit', amount: totalAmount });
        }
    } else {
        paymentsPayload.push({
            method: (currentPaymentMethod === 'instapay' ? 'bank_transfer' : currentPaymentMethod),
            amount: totalAmount
        });
    }

    const numericCustomerId = (custId !== 'CUST-CASH' && !isNaN(parseInt(custId))) ? parseInt(custId) : null;

    const payload = {
        technician_id: parseInt(techId),
        customer_id: numericCustomerId,
        customer_vehicle_id: customerVehicleId,
        items: cart.map((it, idx) => ({
            product_id: !isNaN(parseInt(it.product.id)) ? parseInt(it.product.id) : 1,
            quantity: it.qty,
            unit_price: it.product.priceNew,
            battery_serial: (it.product.category === 'بطاريات' || it.product.is_battery) 
                ? (it.batterySerial || `BAT-${Date.now().toString().slice(-6)}-${idx + 1}`) 
                : null
        })),
        has_scrap: hasScrapTradeIn,
        scrap_capacity_ah: hasScrapTradeIn ? scrapAhVal : null,
        scrap_count: hasScrapTradeIn ? scrapCountVal : null,
        scrap_deduction_amount: hasScrapTradeIn ? scrapDiscount : 0,
        discount_amount: 0,
        tax_amount: 0,
        payments: paymentsPayload,
        notes: currentPaymentMethod === 'credit' ? 'مبيعات بالآجل من شاشة الكاشير' : 'مبيعات فورية بالمركز'
    };

    // UI Loading state
    const submitBtn = document.querySelector('button[onclick="submitFullInvoice()"]');
    const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جاري إصدار الفاتورة...';
    }

    const postInvoice = (invoicePayload) => {
        return fetch("{{ route('admin.pos.store') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (typeof window.getCsrfToken === 'function' ? window.getCsrfToken() : '{{ csrf_token() }}')
            },
            body: JSON.stringify(invoicePayload)
        });
    };

    postInvoice(payload)
    .then(async res => {
        const data = await res.json();
        if (!res.ok || !data.success) {
            // Check if manager override needed for credit limit or discounts
            if (data.errors && (data.errors.manager_override_code || data.errors.discount_amount || (data.errors['items.0.unit_price'] && data.errors['items.0.unit_price'][0].includes('المشرف')))) {
                const overrideMsg = data.errors.discount_amount 
                    ? data.errors.discount_amount[0] 
                    : (data.errors.manager_override_code ? data.errors.manager_override_code[0] : (data.errors['items.0.unit_price'] ? data.errors['items.0.unit_price'][0] : 'هذه العملية تتطلب إدخال كود موافقة المشرف.'));
                const { value: managerPin } = await Swal.fire({
                    title: 'مطلوب إذن وموافقة المشرف',
                    text: overrideMsg,
                    input: 'password',
                    inputPlaceholder: 'أدخل كود موافقة المشرف',
                    showCancelButton: true,
                    confirmButtonText: 'تأكيد الصلاحية والمتابعة',
                    cancelButtonText: 'إلغاء'
                });
                if (managerPin) {
                    payload.manager_override_code = managerPin;
                    const retryRes = await postInvoice(payload);
                    const retryData = await retryRes.json();
                    if (retryRes.ok && retryData.success) {
                        return retryData;
                    }
                    throw new Error(retryData.message || (retryData.errors ? Object.values(retryData.errors).flat().join('<br>') : 'فشل اعتماد كود المشرف.'));
                }
            }
            const errDetail = data.errors ? Object.values(data.errors).flat().join('<br>') : data.message;
            throw new Error(errDetail || 'حدث خطأ أثناء اعتماد الفاتورة على الخادم.');
        }
        return data;
    })
    .then(data => {
        playBeep(1200, 0.2);

        // Fallback local invoice sync
        const invoiceItems = cart.map(it => ({
            productId: it.product.id,
            barcode: it.product.barcode || '',
            brand: it.product.brand,
            name: it.product.name,
            category: it.product.category,
            amp: it.product.amp || it.product.unit || '',
            unitPrice: it.product.priceNew,
            qty: it.qty,
            hasTradeIn: hasScrapTradeIn,
            scrapDiscount: it.product.scrapValue || 0,
            finalPrice: it.product.priceNew * it.qty
        }));

        const localInvData = {
            id: data.invoice_id,
            invoiceNo: data.invoice_number || 'INV-TEMP',
            customerId: custId === 'CUST-CASH' ? null : custId,
            customerName: custName,
            customerPhone: custPhone,
            carModel: carModel,
            carPlate: carPlate,
            items: invoiceItems,
            subtotal: subtotal,
            scrapDiscountTotal: scrapDiscount,
            extraDiscount: 0,
            totalAmount: totalAmount,
            paymentMethod: currentPaymentMethod,
            paidAmount: paidAmount,
            remainingCredit: remainingCredit,
            creditDueDate: currentPaymentMethod === 'credit' ? '2026-10-05' : null,
            sellerName: 'كاشير الفرع',
            notes: payload.notes
        };

        const newInv = window.AlHusseiniSales ? window.AlHusseiniSales.createInvoice(localInvData) : localInvData;
        newInv.invoiceNo = data.invoice_number || newInv.invoiceNo;

        // Show Printable Official Invoice
        renderPrintableInvoice(newInv);
        const printModal = new bootstrap.Modal(document.getElementById('invoicePrintModal'));
        printModal.show();

        Swal.fire({
            icon: 'success',
            title: 'تم إصدار الفاتورة وتثبيتها بنجاح!',
            html: `
                <div class="p-2 mb-2 bg-light rounded text-center">
                    <strong class="text-primary font-monospace fs-16">${data.invoice_number}</strong>
                    <div class="text-muted fs-12 mt-1">تم حفظ العملية في قاعدة البيانات وخصم الكميات من المخزون فورياً.</div>
                </div>
                <div class="d-flex justify-content-center flex-wrap gap-2 mt-3">
                    <a href="${data.receipt_url}" target="_blank" class="btn btn-sm btn-primary">
                        <i class="ri-printer-line me-1"></i> طباعة إيصال كاشير
                    </a>
                    <a href="${data.warranty_cert_url}" target="_blank" class="btn btn-sm btn-info text-white">
                        <i class="ri-shield-check-line me-1"></i> شهادة الضمان
                    </a>
                    <a href="/admin/invoices/${data.invoice_id}" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="ri-file-list-3-line me-1"></i> تفاصيل الفاتورة
                    </a>
                </div>
            `,
            showConfirmButton: true,
            confirmButtonText: 'حسناً - بدء فاتورة جديدة',
            confirmButtonColor: '#0ab39c'
        });

        // Reset Cart
        cart = [];
        document.getElementById('tradeInCheck').checked = false;
        document.getElementById('cashReceivedInput').value = '';
        renderCart();
        renderCatalog();
    })
    .catch(err => {
        console.error('POS Checkout Error:', err);
        Swal.fire({
            icon: 'error',
            title: 'تعذر إتمام الفاتورة',
            html: err.message,
            confirmButtonText: 'موافق'
        });
    })
    .finally(() => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });
}

function renderPrintableInvoice(inv) {
    const container = document.getElementById('printableInvoiceContent');
    const payLabels = { 'cash': 'نقدي (كاش)', 'instapay': 'إنستاباي / فوري', 'card': 'فيزا / بطاقة بنكية', 'credit': 'الآجل (مستحق)' };

    let itemsHtml = '';
    inv.items.forEach((it, i) => {
        itemsHtml += `
            <tr>
                <td class="text-center font-monospace">${i + 1}</td>
                <td>
                    <strong class="text-dark fs-13 d-block">${it.name}</strong>
                    <small class="text-muted font-monospace">${it.barcode ? `باركود: ${it.barcode}` : it.brand}</small>
                </td>
                <td class="text-center font-monospace fw-bold">${it.qty}</td>
                <td class="text-end font-monospace">${window.AlHusseiniSales.formatCurrency(it.unitPrice)}</td>
                <td class="text-end font-monospace fw-bold">${window.AlHusseiniSales.formatCurrency(it.finalPrice)}</td>
            </tr>
        `;
    });

    container.innerHTML = `
        <div class="print-invoice-sheet text-dark" style="direction: rtl;">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="48" class="rounded-circle shadow-sm">
                    <div>
                        <h4 class="fw-extrabold text-primary mb-0">مركز الحسيني لبطاريات وزيوت السيارات</h4>
                        <small class="text-muted">صيانة متكاملة - بطاريات جافة وسائلة - زيوت معتمدة - فحص كمبيوتر دينامو</small>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-dark font-monospace fs-13 mb-1">فاتورة #${inv.invoiceNo}</span>
                    <div class="text-muted fs-11">${inv.date} | ${inv.time || ''}</div>
                </div>
            </div>

            <div class="row g-2 mb-3 p-3 bg-light rounded border">
                <div class="col-6"><strong>اسم العميل:</strong> ${inv.customerName}</div>
                <div class="col-6"><strong>رقم الهاتف:</strong> <span class="font-monospace">${inv.customerPhone}</span></div>
                <div class="col-6"><strong>السيارة:</strong> ${inv.carModel}</div>
                <div class="col-6"><strong>رقم اللوحة:</strong> <span class="font-monospace fw-bold">${inv.carPlate}</span></div>
            </div>

            <table class="table table-bordered align-middle mb-3">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 50px;">#</th>
                        <th>الصنف / الخدمة</th>
                        <th class="text-center" style="width: 70px;">الكمية</th>
                        <th class="text-end" style="width: 120px;">السعر</th>
                        <th class="text-end" style="width: 130px;">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml}
                </tbody>
            </table>

            <div class="row justify-content-end mb-3">
                <div class="col-md-6 col-12">
                    <div class="p-2 border rounded bg-light fs-12">
                        <div class="d-flex justify-content-between mb-1">
                            <span>المجموع:</span>
                            <span class="font-monospace fw-bold">${window.AlHusseiniSales.formatCurrency(inv.subtotal)}</span>
                        </div>
                        ${inv.scrapDiscountTotal > 0 ? `
                            <div class="d-flex justify-content-between mb-1 text-success">
                                <span>خصم البطارية القديمة (الكهنة):</span>
                                <span class="font-monospace fw-bold">- ${window.AlHusseiniSales.formatCurrency(inv.scrapDiscountTotal)}</span>
                            </div>
                        ` : ''}
                        <div class="d-flex justify-content-between align-items-center border-top pt-1 mt-1 fs-14">
                            <strong class="text-dark">الصافي المطلوب:</strong>
                            <strong class="text-success font-monospace fs-16">${window.AlHusseiniSales.formatCurrency(inv.totalAmount)}</strong>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-1 mt-1 text-muted fs-11">
                            <span>طريقة السداد:</span>
                            <span class="fw-bold">${payLabels[inv.paymentMethod] || inv.paymentMethod}</span>
                        </div>
                        ${inv.remainingCredit > 0 ? `
                            <div class="d-flex justify-content-between text-danger fw-bold border-top pt-1 mt-1">
                                <span>المتبقي على الآجل:</span>
                                <span class="font-monospace">${window.AlHusseiniSales.formatCurrency(inv.remainingCredit)}</span>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>

            <div class="border-top pt-2 text-center text-muted fs-11">
                <p class="mb-1"><strong>سيريال الضمان المعتمد:</strong> <span class="font-monospace text-primary fw-bold">${inv.serialNumber}</span> | ينتهي في: <span class="font-monospace">${inv.warrantyExpiry}</span></p>
                <small>شكراً لتعاملكم مع مركز الحسيني - خدمة الدعم الفني والطوارئ: 01000000000</small>
            </div>
        </div>
    `;
}
</script>
