@extends('admin.layouts.master')

@section('title', 'نقطة البيع وفاتورة الصيانة والزيوت والبطاريات | مركز الحسيني')

@section('css')
<style>
    /* Global POS Viewport Lock - Full Height Cashier Terminal */
    .footer {
        display: none !important;
    }

    @media (min-width: 992px) {
        html, body {
            overflow: hidden !important;
            height: 100vh !important;
        }
        #layout-wrapper {
            height: 100vh !important;
            max-height: 100vh !important;
            overflow: hidden !important;
            min-height: 0 !important;
        }
        .main-content {
            height: 100vh !important;
            max-height: 100vh !important;
            overflow: hidden !important;
            padding-bottom: 0 !important;
            min-height: 0 !important;
        }
        .page-content {
            padding-top: 74px !important;
            padding-bottom: 6px !important;
            height: 100vh !important;
            overflow: hidden !important;
        }
        .pos-container-fluid {
            height: calc(100vh - 82px) !important;
            display: flex;
            flex-direction: column;
        }
        .pos-grid-row {
            height: 100% !important;
        }
        .pos-catalog-panel {
            height: 100% !important;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .pos-catalog-scroll {
            flex: 1;
            overflow-y: auto !important;
            min-height: 0 !important;
            padding: 10px 14px !important;
        }
        .pos-cart-panel {
            height: 100% !important;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .pos-cart-items-scroll {
            flex: 1;
            overflow-y: auto !important;
            min-height: 90px;
            padding: 8px 10px;
            background: var(--vz-light, #f3f6f9);
            border-radius: 6px;
            border: 1px solid var(--vz-border-color, #e9ebec);
        }
    }

    /* Strict Alignment with Dashboard Theme Colors */
    .pos-product-card {
        cursor: pointer;
        position: relative;
        background: var(--vz-card-bg, #ffffff);
        border: 1px solid var(--vz-border-color, #e9ebec);
        border-radius: 8px;
        padding: 10px 12px;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: all 0.16s ease;
        user-select: none;
    }

    .pos-product-card:hover {
        border-color: var(--vz-primary);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(var(--vz-primary-rgb, 64, 81, 137), 0.12);
    }

    .pos-product-card:active {
        transform: scale(0.99);
    }

    .in-cart-indicator {
        position: absolute;
        top: -6px;
        left: -6px;
        background: var(--vz-success);
        color: #ffffff;
        font-size: 10px;
        font-weight: 800;
        padding: 2px 7px;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(var(--vz-success-rgb, 10, 179, 156), 0.35);
        display: inline-flex;
        align-items: center;
        gap: 3px;
        z-index: 2;
    }

    .pulse-dot {
        width: 7px;
        height: 7px;
        background-color: var(--vz-success);
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 0 rgba(var(--vz-success-rgb, 10, 179, 156), 0.7);
        animation: pulseAnimation 1.6s infinite;
    }

    @keyframes pulseAnimation {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(var(--vz-success-rgb, 10, 179, 156), 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(var(--vz-success-rgb, 10, 179, 156), 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(var(--vz-success-rgb, 10, 179, 156), 0); }
    }

    .pos-category-pill {
        border-radius: 20px;
        padding: 5px 14px;
        font-weight: 700;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .cart-item-row {
        background: var(--vz-card-bg, #ffffff);
        border-radius: 6px;
        border: 1px solid var(--vz-border-color, #e9ebec);
        padding: 8px 10px;
        margin-bottom: 6px;
        transition: all 0.12s ease;
    }

    .cart-item-row:hover {
        border-color: var(--vz-primary);
        background: var(--vz-card-bg, #ffffff);
    }

    .payment-method-card {
        cursor: pointer;
        border: 2px solid var(--vz-border-color, #e9ebec);
        border-radius: 8px;
        padding: 7px 4px;
        text-align: center;
        background: var(--vz-card-bg, #ffffff);
        transition: all 0.14s ease;
        user-select: none;
    }

    .payment-method-card:hover {
        border-color: var(--vz-border-color-translucent, #cbd5e1);
    }

    .payment-method-card.active.pay-cash {
        border-color: var(--vz-success);
        background-color: var(--vz-success-bg-subtle, rgba(10, 179, 156, 0.1));
        color: var(--vz-success);
        font-weight: 800;
    }

    .payment-method-card.active.pay-instapay {
        border-color: var(--vz-primary);
        background-color: var(--vz-primary-bg-subtle, rgba(64, 81, 137, 0.1));
        color: var(--vz-primary);
        font-weight: 800;
    }

    .payment-method-card.active.pay-card {
        border-color: var(--vz-info);
        background-color: var(--vz-info-bg-subtle, rgba(41, 156, 219, 0.1));
        color: var(--vz-info);
        font-weight: 800;
    }

    .payment-method-card.active.pay-credit {
        border-color: var(--vz-warning);
        background-color: var(--vz-warning-bg-subtle, rgba(247, 184, 75, 0.12));
        color: var(--vz-warning-text-emphasis, #b45309);
        font-weight: 800;
    }

    /* Print styles */
    @media print {
        body { background: #fff !important; }
        .app-menu, .topbar, .footer, .btn, .no-print { display: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; }
        .print-invoice-sheet {
            display: block !important;
            width: 100% !important;
            border: 2px solid #000;
            padding: 20px;
            font-size: 12pt;
        }
    }
</style>
@endsection

@section('content')
<div class="pos-container-fluid">
    <div class="row g-2 pos-grid-row">

        <!-- ============================================================== -->
        <!-- RIGHT (RTL): Products & Services Catalog Panel (65% width)     -->
        <!-- ============================================================== -->
        <div class="col-xl-8 col-lg-7 pos-catalog-panel">
            <div class="card shadow-sm border-0 h-100 d-flex flex-column overflow-hidden mb-0">
                
                <!-- 1. Top Customer & Vehicle Header -->
                <div class="card-header bg-light border-bottom p-2 px-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-5 col-12">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fs-12 fw-bold text-muted text-nowrap"><i class="ri-user-smile-line text-primary fs-14"></i> العميل:</span>
                                <select class="form-select form-select-sm fw-bold fs-12 border-primary" id="posCustomerSelect" onchange="onCustomerSelected()">
                                    <option value="CUST-CASH">عميل نقدي مباشر بالمعرض / الورشة</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-7">
                            <div class="px-2 py-1 bg-white rounded border d-flex justify-content-between align-items-center fs-11">
                                <span class="text-muted"><i class="ri-car-line me-1"></i>المركبة:</span>
                                <span class="fw-bold text-dark text-truncate" id="posCarDetailsDisplay">سيارة غير محددة</span>
                            </div>
                        </div>

                        <div class="col-md-3 col-5 text-end d-flex gap-1 justify-content-end align-items-center">
                            <span class="badge bg-success-subtle text-success border border-success-subtle d-none d-sm-inline-flex align-items-center gap-1" title="قارئ الباركود جاهز">
                                <span class="pulse-dot"></span> باركود نشط
                            </span>
                            <button type="button" class="btn btn-sm btn-soft-primary fw-bold fs-11" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                                <i class="ri-user-add-line me-1"></i> عميل جديد
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 2. Category Tabs & Search Bar (Aligned with Dashboard Theme) -->
                <div class="p-2 px-3 border-bottom bg-white">
                    <div class="row g-2 align-items-center mb-1">
                        <!-- Category Navigation -->
                        <div class="col-xl-7 col-12">
                            <div class="d-flex flex-wrap gap-1 align-items-center" id="categoryTabsContainer">
                                <button type="button" class="btn btn-sm btn-primary pos-category-pill" id="cat-tab-all" onclick="filterByCategory('all')">
                                    الكل (<span id="pillCountAll">0</span>)
                                </button>
                                <button type="button" class="btn btn-sm btn-soft-secondary pos-category-pill" id="cat-tab-batteries" onclick="filterByCategory('بطاريات')">
                                    🔋 بطاريات (<span id="pillCountBatteries">0</span>)
                                </button>
                                <button type="button" class="btn btn-sm btn-soft-secondary pos-category-pill" id="cat-tab-oils" onclick="filterByCategory('زيوت')">
                                    🛢️ زيوت وفلاتر (<span id="pillCountOils">0</span>)
                                </button>
                                <button type="button" class="btn btn-sm btn-soft-secondary pos-category-pill" id="cat-tab-greases" onclick="filterByCategory('شحوم وسوائل')">
                                    🧪 شحوم وسوائل (<span id="pillCountGreases">0</span>)
                                </button>
                                <button type="button" class="btn btn-sm btn-soft-secondary pos-category-pill" id="cat-tab-services" onclick="filterByCategory('خدمات وصيانة')">
                                    🔧 صيانة الورشة (<span id="pillCountServices">0</span>)
                                </button>
                            </div>
                        </div>

                        <!-- Instant Barcode Search Input -->
                        <div class="col-xl-5 col-12">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light text-primary"><i class="ri-barcode-box-line me-1"></i> <i class="ri-search-line"></i></span>
                                <input type="text" class="form-control fw-bold" id="catalogSearchInput" placeholder="ابحث باسم الصنف، الماركة، أو امسح الباركود 📷 (Enter للإضافة)..." oninput="renderCatalog()" onkeydown="handleBarcodeEnter(event)">
                                <button class="btn btn-light border" type="button" onclick="clearCatalogSearch()" title="مسح البحث">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Product Grid (Smooth Internal Scroll) -->
                <div class="pos-catalog-scroll">
                    <div class="row g-2" id="posCatalogGrid">
                        <!-- Rendered dynamically -->
                    </div>
                </div>

            </div>
        </div>

        <!-- ============================================================== -->
        <!-- LEFT (RTL): Live Cart & Checkout Terminal (35% width)           -->
        <!-- ============================================================== -->
        <div class="col-xl-4 col-lg-5 pos-cart-panel">
            <div class="card shadow-sm border-0 h-100 d-flex flex-column overflow-hidden mb-0">
                
                <!-- Cart Header (Uses Dashboard Theme Style) -->
                <div class="card-header bg-light border-bottom p-2 px-3 d-flex justify-content-between align-items-center">
                    <h6 class="card-title fw-bold text-dark mb-0 fs-13">
                        <i class="ri-shopping-cart-2-line text-primary me-1"></i> سلة الفاتورة (<span id="cartCountBadge" class="badge bg-primary">0</span>)
                    </h6>
                    <button type="button" class="btn btn-sm btn-soft-danger py-0 px-2 fs-11" onclick="clearCart()" title="إفراغ السلة بالكامل">
                        <i class="ri-delete-bin-line me-1"></i> تفريغ السلة
                    </button>
                </div>

                <!-- Scrollable Cart Items List -->
                <div class="pos-cart-items-scroll" id="cartItemsContainer">
                    <div class="text-center py-5 text-muted fs-13" id="cartEmptyState">
                        <i class="ri-shopping-basket-2-line fs-26 d-block mb-1 text-secondary"></i>
                        السلة فارغة.. اضغط على أي بطارية أو زيت أو امسح الباركود لإضافتها
                    </div>
                </div>

                <!-- Bottom Calculations & Dues Terminal -->
                <div class="p-3 bg-white border-top flex-shrink-0">
                    
                    <!-- Old Battery Trade-in Scrap Switch -->
                    <div class="p-2 bg-success-subtle rounded border border-success-subtle mb-2" id="tradeInContainer">
                        <div class="form-check form-switch p-0 d-flex justify-content-between align-items-center mb-0">
                            <label class="form-check-label fw-bold text-dark fs-12 mb-0 d-flex align-items-center gap-1" for="tradeInCheck">
                                <i class="ri-recycle-line text-success fs-15"></i>
                                تسليم بطارية قديمة (كهنة / استبدال)
                            </label>
                            <input class="form-check-input me-0" type="checkbox" role="switch" id="tradeInCheck" onchange="calculateCartTotal()">
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1 pt-1 border-top border-success-subtle fs-11 text-muted">
                            <span>خصم قيمة البطارية القديمة:</span>
                            <span class="fw-bold text-success font-monospace" id="tradeInDiscountDisplay">- 0 ج.م</span>
                        </div>
                    </div>

                    <!-- Financial Breakdown -->
                    <div class="border-top pt-2 mb-2 fs-12">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">المجموع قبل الخصم:</span>
                            <span class="fw-bold text-dark font-monospace" id="cartSubtotal">0 ج.م</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 text-danger d-none" id="cartScrapDiscountRow">
                            <span>خصم الكهنة المسترجعة:</span>
                            <span class="fw-bold font-monospace" id="cartScrapDiscountDisplay">- 0 ج.م</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded border mt-1">
                            <strong class="text-dark fs-13">المبلغ الإجمالي المستحق:</strong>
                            <h4 class="fw-extrabold text-success mb-0 fs-18 font-monospace" id="cartTotalAmount">0 ج.م</h4>
                        </div>
                    </div>

                    <!-- Payment Method Selector (Aligned with Velzon Dashboard Palette) -->
                    <div class="mb-2">
                        <label class="form-label fw-bold fs-11 text-muted mb-1">طريقة الدفع:</label>
                        <div class="row g-1 text-center">
                            <div class="col-3">
                                <div class="payment-method-card active pay-cash" id="pay-cash" onclick="setPaymentMethod('cash')">
                                    <i class="ri-money-dollar-circle-line fs-15 d-block mb-0 text-success"></i>
                                    <span class="fs-11">كاش</span>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="payment-method-card pay-instapay" id="pay-instapay" onclick="setPaymentMethod('instapay')">
                                    <i class="ri-smartphone-line fs-15 d-block mb-0 text-primary"></i>
                                    <span class="fs-11">إنستاباي</span>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="payment-method-card pay-card" id="pay-card" onclick="setPaymentMethod('card')">
                                    <i class="ri-bank-card-line fs-15 d-block mb-0 text-info"></i>
                                    <span class="fs-11">فيزا</span>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="payment-method-card pay-credit" id="pay-credit" onclick="setPaymentMethod('credit')">
                                    <i class="ri-hand-coin-line fs-15 d-block mb-0 text-warning"></i>
                                    <span class="fs-11 fw-bold">الآجل ⏱️</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Cash Calculation Helper (When Cash is chosen) -->
                    <div class="p-2 bg-light rounded border mb-2" id="cashPresetsBox">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fs-11 text-muted fw-bold">حاسبة النقدية (المستلم والباقي):</span>
                            <div class="d-flex gap-1" id="quickCashChips">
                                <!-- Rendered dynamically -->
                            </div>
                        </div>
                        <div class="row g-1 align-items-center">
                            <div class="col-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white fs-10">المستلم:</span>
                                    <input type="number" class="form-control font-monospace fw-bold" id="cashReceivedInput" placeholder="0" oninput="calculateCashChange()">
                                </div>
                            </div>
                            <div class="col-6 text-end">
                                <span class="fs-11 text-muted">الباقي للعميل: </span>
                                <strong class="text-success font-monospace fs-13" id="cashChangeOutput">0 ج.م</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Credit Fields (If payment is "الآجل") -->
                    <div class="p-2 bg-warning-subtle rounded border border-warning mb-2 d-none" id="creditFieldsBox">
                        <div class="row g-1">
                            <div class="col-6">
                                <label class="form-label fs-10 fw-bold text-dark mb-0">المقدم المدفوع الآن:</label>
                                <input type="number" class="form-control form-control-sm font-monospace fw-bold" id="creditDepositInput" value="500" oninput="updateCreditBalance()">
                            </div>
                            <div class="col-6">
                                <label class="form-label fs-10 fw-bold text-danger mb-0">المتبقي على الآجل:</label>
                                <input type="text" class="form-control form-control-sm font-monospace text-danger fw-bold bg-light" id="creditBalanceOutput" readonly value="0 ج.م">
                            </div>
                        </div>
                    </div>

                    <!-- Submit Invoice Button (Uses Dashboard Success Emerald Color) -->
                    <button type="button" class="btn btn-success btn-lg w-100 fw-bold fs-14 shadow-sm" id="btnSubmitInvoice" onclick="submitFullInvoice()">
                        <i class="ri-check-double-line me-1"></i> اعتماد الفاتورة والضمان (Enter ↵)
                    </button>

                </div>

            </div>
        </div>

    </div>
</div>

<!-- Modal: Fast Add New Customer (5 Seconds) -->
<div class="modal fade" id="newCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom p-3">
                <h5 class="modal-title fw-bold text-dark fs-15"><i class="ri-user-add-line text-primary me-1"></i> تسجيل عميل وسيارة جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="newCustomerForm" onsubmit="saveQuickCustomer(event)">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-13">اسم العميل بالكامل <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="newCustName" required placeholder="مثال: أحمد عبد الحميد">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-13">رقم الهاتف المحمول <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control font-monospace" id="newCustPhone" required placeholder="010XXXXXXXX">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label fw-bold text-dark fs-13">نوع وموديل السيارة</label>
                            <input type="text" class="form-control" id="newCustCar" placeholder="تويوتا كورولا 2021">
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-bold text-dark fs-13">رقم اللوحة</label>
                            <input type="text" class="form-control font-monospace text-center fw-bold" id="newCustPlate" placeholder="أ ب ج 1234">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">حفظ وتعيين للفاتورة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Printable Official Invoice & Warranty Receipt -->
<div class="modal fade" id="invoicePrintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom p-3 no-print">
                <div class="d-flex align-items-center gap-2">
                    <i class="ri-printer-line text-primary fs-18"></i>
                    <h5 class="modal-title fw-bold text-dark mb-0 fs-15">فاتورة معتمدة وشهادة ضمان رسمية</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="printableInvoiceContent">
                <!-- Rendered dynamically -->
            </div>
            <div class="modal-footer bg-light p-3 no-print d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إغلاق</button>
                <button type="button" class="btn btn-primary fw-bold px-4" onclick="window.print()">
                    <i class="ri-printer-fill me-1"></i> طباعة الإيصال (A4 / حراري)
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
'use strict';

let currentCategory = 'all';
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
    updateCategoryCounts();
    renderCatalog();

    window.addEventListener('alhusseini-sales-updated', function () {
        loadCustomersDropdown();
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

        // Stock Tag
        const stockIndicator = isService ? 
            `<span class="fs-10 text-primary fw-bold"><i class="ri-flashlight-line me-1"></i>خدمة ورشة</span>` :
            `<span class="fs-10 text-muted"><span class="badge bg-success-subtle text-success p-1 rounded-circle me-1">●</span>مخزن: ${p.stock}</span>`;

        html += `
            <div class="col-xxl-4 col-md-6 col-12">
                <div class="pos-product-card" onclick="addToCart('${p.id}')">
                    ${inCartBadge}
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge ${catBadge} fs-10 font-monospace">${p.brand}</span>
                            <span class="badge bg-light text-dark border font-monospace fs-10">${p.amp || p.unit || ''}</span>
                        </div>
                        <h6 class="fw-bold text-dark fs-12 mb-1 text-truncate" title="${p.name}">${p.name}</h6>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-light text-secondary border font-monospace fs-9" title="باركود الصنف"><i class="ri-barcode-line text-dark me-1"></i>${p.barcode || ''}</span>
                            ${stockIndicator}
                        </div>
                        <small class="text-muted fs-10 d-block mb-1 text-truncate">${p.type} ${p.warrantyMonths > 0 ? `| ضمان ${p.warrantyMonths} شهر` : ''}</small>
                    </div>

                    <div class="d-flex justify-content-between align-items-end pt-2 border-top">
                        <div>
                            ${isBattery ? `
                                <small class="fs-10 text-muted d-block text-decoration-line-through">${window.AlHusseiniSales.formatCurrency(p.priceNew)} بدون استبدال</small>
                                <strong class="text-success font-monospace fs-14">${window.AlHusseiniSales.formatCurrency(price)} <span class="fs-9 fw-normal text-muted">(مع القديمة)</span></strong>
                            ` : `
                                <small class="fs-10 text-muted d-block">السعر:</small>
                                <strong class="text-success font-monospace fs-14">${window.AlHusseiniSales.formatCurrency(price)}</strong>
                            `}
                        </div>
                        <button type="button" class="btn btn-sm btn-soft-primary px-2 py-1 fs-11 fw-bold">
                            <i class="ri-add-line"></i> إضافة
                        </button>
                    </div>
                </div>
            </div>
        `;
    });

    grid.innerHTML = html;
}

// Cart Logic
function addToCart(productId) {
    if (!window.AlHusseiniSales) return;
    const prod = window.AlHusseiniSales.getProductById(productId);
    if (!prod) return;

    const existing = cart.find(item => item.product.id === productId);
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
        cart[idx].qty += delta;
        if (cart[idx].qty <= 0) {
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
    badge.textContent = cart.reduce((sum, it) => sum + it.qty, 0);

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
            <div class="cart-item-row d-flex justify-content-between align-items-center">
                <div class="overflow-hidden me-2" style="max-width: 56%;">
                    <strong class="fs-12 text-dark text-truncate d-block" title="${p.name}">${p.name}</strong>
                    <div class="d-flex align-items-center gap-1">
                        <span class="badge bg-light text-muted border fs-9 font-monospace">${p.brand}</span>
                        <small class="text-muted fs-10 font-monospace">${window.AlHusseiniSales.formatCurrency(p.priceNew)} × ${item.qty}</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <button class="btn btn-sm btn-light border p-0 px-2 fs-11" onclick="updateCartQty('${p.id}', -1)">-</button>
                    <span class="font-monospace fw-bold fs-11 px-1 text-center" style="min-width: 20px;">${item.qty}</span>
                    <button class="btn btn-sm btn-light border p-0 px-2 fs-11" onclick="updateCartQty('${p.id}', 1)">+</button>
                    <strong class="font-monospace text-success fs-11 ms-1">${window.AlHusseiniSales.formatCurrency(lineTotal)}</strong>
                    <button class="btn btn-sm btn-link text-danger p-0 ms-1" onclick="removeCartItem('${p.id}')" title="حذف"><i class="ri-delete-bin-line"></i></button>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
    calculateCartTotal();
}

function calculateCartTotal() {
    const subtotal = cart.reduce((sum, it) => sum + (it.product.priceNew * it.qty), 0);
    const hasBattery = cart.some(it => it.product.category === 'بطاريات');
    const tradeInCheck = document.getElementById('tradeInCheck');

    let scrapDiscount = 0;
    if (hasBattery && tradeInCheck.checked) {
        scrapDiscount = cart.filter(it => it.product.category === 'بطاريات')
                            .reduce((sum, it) => sum + (it.product.scrapValue * it.qty), 0);
    }

    const total = Math.max(0, subtotal - scrapDiscount);

    document.getElementById('cartSubtotal').textContent = window.AlHusseiniSales.formatCurrency(subtotal);
    
    const scrapRow = document.getElementById('cartScrapDiscountRow');
    const tradeInDisplay = document.getElementById('tradeInDiscountDisplay');
    if (scrapDiscount > 0) {
        scrapRow.classList.remove('d-none');
        document.getElementById('cartScrapDiscountDisplay').textContent = `- ${window.AlHusseiniSales.formatCurrency(scrapDiscount)}`;
        tradeInDisplay.textContent = `- ${window.AlHusseiniSales.formatCurrency(scrapDiscount)}`;
    } else {
        scrapRow.classList.add('d-none');
        tradeInDisplay.textContent = '- 0 ج.م';
    }

    document.getElementById('cartTotalAmount').textContent = window.AlHusseiniSales.formatCurrency(total);
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

    const custId = document.getElementById('posCustomerSelect').value;
    let custName = 'عميل نقدي مباشر';
    let custPhone = '-';
    let carModel = 'ملاكي';
    let carPlate = '-';

    if (custId !== 'CUST-CASH') {
        const cust = window.AlHusseiniSales.getCustomerById(custId);
        if (cust) {
            custName = cust.name;
            custPhone = cust.phone;
            carModel = cust.carModel;
            carPlate = cust.carPlate;
        }
    }

    const subtotal = cart.reduce((sum, it) => sum + (it.product.priceNew * it.qty), 0);
    const hasBattery = cart.some(it => it.product.category === 'بطاريات');
    const scrapDiscount = (hasBattery && document.getElementById('tradeInCheck').checked) ?
        cart.filter(it => it.product.category === 'بطاريات').reduce((sum, it) => sum + (it.product.scrapValue * it.qty), 0) : 0;
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
        remainingCredit = totalAmount - paidAmount;
    }

    const invoiceItems = cart.map(it => ({
        productId: it.product.id,
        barcode: it.product.barcode || '',
        brand: it.product.brand,
        name: it.product.name,
        category: it.product.category,
        amp: it.product.amp || it.product.unit || '',
        unitPrice: it.product.priceNew,
        qty: it.qty,
        hasTradeIn: it.product.category === 'بطاريات' && document.getElementById('tradeInCheck').checked,
        scrapDiscount: it.product.scrapValue || 0,
        finalPrice: it.product.priceNew * it.qty
    }));

    const invoiceData = {
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
        sellerName: 'إبراهيم حسن (كبير البائعين)',
        notes: currentPaymentMethod === 'credit' ? 'مبيعات بالآجل مع دفعة مقدمة' : 'مبيعات فورية بالمركز'
    };

    const newInv = window.AlHusseiniSales.createInvoice(invoiceData);

    playBeep(1200, 0.2);

    // Show Printable Official Invoice
    renderPrintableInvoice(newInv);
    const printModal = new bootstrap.Modal(document.getElementById('invoicePrintModal'));
    printModal.show();

    // Reset Cart
    cart = [];
    document.getElementById('tradeInCheck').checked = false;
    document.getElementById('cashReceivedInput').value = '';
    renderCart();
    renderCatalog();
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
@endsection
