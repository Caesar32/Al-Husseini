<!-- ============================================================== -->
<!-- LEFT (RTL): Live Cart & Checkout Terminal (35% width)           -->
<!-- ============================================================== -->
<div class="col-xl-4 col-lg-5 pos-cart-panel">
    <div class="card pos-cart-card shadow-sm border-0 h-100 d-flex flex-column overflow-hidden mb-0">
        
        <!-- Cart Header (Uses Dashboard Theme Style) -->
        <div class="card-header bg-light border-bottom p-2 px-3 d-flex justify-content-between align-items-center">
            <h6 class="card-title fw-bold text-dark mb-0 fs-13">
                <i class="ri-shopping-cart-2-line text-primary me-1"></i> سلة الفاتورة (<span id="cartCountBadge" class="badge bg-primary">0</span>)
            </h6>
            <button type="button" class="btn btn-sm btn-soft-danger py-0 px-2 fs-11" data-action="pos.clear-cart" title="إفراغ السلة بالكامل">
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

        <!-- Checkout controls: shrink and scroll on short screens; the submit footer below never moves -->
        <div class="pos-checkout-body p-2 px-3 bg-body border-top">

            <!-- Old battery trade-in: switch + tier hint on one line, count / capacity / price on one line.
                 The deduction itself shows in the totals (خصم الكهنة المسترجعة). -->
            <div class="p-1 px-2 bg-success-subtle rounded border border-success-subtle mb-1" id="tradeInContainer">
                <div class="form-check form-switch p-0 d-flex align-items-center gap-2 mb-0">
                    <label class="form-check-label fw-bold text-dark fs-11 mb-0 d-flex align-items-center gap-1 cursor-pointer text-nowrap" for="tradeInCheck">
                        <i class="ri-recycle-line text-success fs-13"></i>
                        تسليم بطاريات كهنة
                    </label>
                    <span class="fs-10 text-muted text-truncate flex-grow-1 mb-0 py-0" id="scrapTierHint"></span>
                    <input class="form-check-input me-0 ms-auto flex-shrink-0" type="checkbox" role="switch" id="tradeInCheck" data-action="pos.trade-in">
                </div>

                <div id="tradeInDetailsBox" class="d-none">
                    <div class="d-flex align-items-center gap-1">
                        <div class="input-group input-group-sm flex-nowrap" style="width: 75px;" title="العدد">
                            <button class="btn btn-outline-secondary" type="button" data-action="pos.scrap-count" data-delta="-1" aria-label="تقليل العدد">-</button>
                            <input type="number" class="form-control text-center font-monospace fw-bold" id="scrapCountInput" value="1" min="1" max="20" aria-label="عدد البطاريات الكهنة" data-action="pos.scrap-capacity">
                            <button class="btn btn-outline-secondary" type="button" data-action="pos.scrap-count" data-delta="1" aria-label="زيادة العدد">+</button>
                        </div>
                        <div class="input-group input-group-sm flex-nowrap" style="width: 70px;" title="السعة (أمبير)">
                            <input type="number" class="form-control text-center font-monospace fw-bold" id="scrapCapacityInput" value="70" min="30" max="250" aria-label="سعة البطارية الكهنة (أمبير)" data-action="pos.scrap-capacity">
                            <span class="input-group-text">Ah</span>
                        </div>
                        <input type="number" step="10" min="0" class="form-control form-control-sm font-monospace fw-bold text-success text-center" style="width: 85px;" id="scrapPriceInput" placeholder="السعر" aria-label="سعر الكهنة" title="اتركه فارغاً لاحتساب السعر من شرائح التسعير" data-action="pos.recalc">
                    </div>
                </div>
            </div>

            <!-- Financial Breakdown -->
            <div class="border-top pt-1 mb-1 fs-11">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-0">
                    <span class="text-nowrap">
                        <span class="text-muted">المجموع قبل الخصم:</span>
                        <span class="fw-bold text-dark font-monospace" id="cartSubtotal">0 ج.م</span>
                    </span>
                    <span class="d-flex align-items-center gap-1">
                        <label for="cartDiscountInput" class="text-muted mb-0 text-nowrap">خصم إضافي:</label>
                        <input type="number" step="1" min="0" class="form-control form-control-sm font-monospace fw-bold py-0 fs-11 text-end" style="max-width: 90px;" id="cartDiscountInput" placeholder="0" title="يتطلب صلاحية الخصم أو كود موافقة المشرف" data-action="pos.recalc">
                    </span>
                </div>
                <div class="d-flex justify-content-between mb-0 text-danger d-none" id="cartScrapDiscountRow">
                    <span>خصم الكهنة المسترجعة:</span>
                    <span class="fw-bold font-monospace" id="cartScrapDiscountDisplay">- 0 ج.م</span>
                </div>
                <div class="d-flex justify-content-between align-items-center bg-light px-2 py-1 rounded border mt-1">
                    <strong class="text-dark fs-12">المبلغ المستحق:</strong>
                    <h4 class="fw-extrabold text-success mb-0 fs-16 font-monospace" id="cartTotalAmount">0 ج.م</h4>
                </div>
            </div>

            <!-- Payment mode: how much of the amount due is paid now -->
            <div class="mb-1">
                <div class="payment-mode-group" role="radiogroup" aria-label="طريقة سداد الفاتورة">
                    <button type="button" class="payment-mode-btn mode-full active" id="mode-full" role="radio" aria-checked="true" data-action="pos.payment-mode" data-mode="full">
                        <i class="ri-checkbox-circle-line"></i><span>كامل الدفع</span>
                    </button>
                    <button type="button" class="payment-mode-btn mode-partial" id="mode-partial" role="radio" aria-checked="false" data-action="pos.payment-mode" data-mode="partial">
                        <i class="ri-pie-chart-2-line"></i><span>جزء من المبلغ</span>
                    </button>
                    <button type="button" class="payment-mode-btn mode-remaining" id="mode-remaining" role="radio" aria-checked="false" data-action="pos.payment-mode" data-mode="remaining">
                        <i class="ri-hand-coin-line"></i><span>باقي المبلغ</span>
                    </button>
                </div>
                <div class="fs-9 text-muted mt-1" id="paymentModeHint"></div>
            </div>

            <!-- Payment method: how the amount paid now is paid (not needed when nothing is paid now) -->
            <div class="mb-1" id="paymentMethodBox">
                <div class="payment-method-group">
                    <div class="payment-method-card active pay-cash" id="pay-cash" data-action="pos.payment-method" data-method="cash">
                        <i class="ri-money-dollar-circle-line text-success"></i><span>كاش</span>
                    </div>
                    <div class="payment-method-card pay-instapay" id="pay-instapay" data-action="pos.payment-method" data-method="instapay">
                        <i class="ri-smartphone-line text-primary"></i><span>إنستاباي</span>
                    </div>
                    <div class="payment-method-card pay-card" id="pay-card" data-action="pos.payment-method" data-method="card">
                        <i class="ri-bank-card-line text-info"></i><span>فيزا</span>
                    </div>
                </div>
            </div>

            <!-- Amount paid now / remaining (computed live; the server re-validates everything) -->
            <div class="p-1 px-2 bg-light rounded border mb-1" id="paymentAmountBox">
                <div class="row g-1 align-items-end">
                    <div class="col-6">
                        <label for="paidNowInput" class="form-label fs-9 fw-bold text-dark mb-0">المبلغ المدفوع الآن:</label>
                        <input type="number" inputmode="decimal" step="0.01" min="0.01" autocomplete="off"
                               class="form-control form-control-sm font-monospace fw-bold py-0 fs-11 text-end" id="paidNowInput" placeholder="0.00"
                               aria-describedby="paidNowMessage" data-action="pos.paid-now">
                    </div>
                    <div class="col-6">
                        <label for="paymentRemainingOutput" class="form-label fs-9 fw-bold mb-0" id="paymentRemainingLabel">المتبقي:</label>
                        <input type="text" readonly class="form-control form-control-sm font-monospace fw-bold py-0 fs-11 text-end" id="paymentRemainingOutput" value="0 ج.م">
                    </div>
                </div>
                <div class="fs-9 mt-1 d-none" id="paidNowMessage" role="alert"></div>
            </div>

            <!-- Cash calculator (cash payments only): received / change / quick amounts on one line -->
            <div class="p-1 px-2 bg-light rounded border mb-1 d-flex flex-wrap align-items-center gap-1" id="cashCalculatorBox">
                <div class="input-group input-group-sm flex-nowrap" style="width: 120px;">
                    <span class="input-group-text bg-body fs-9 py-0 px-1">المستلم:</span>
                    <input type="number" min="0" step="0.01" class="form-control form-control-sm font-monospace fw-bold py-0 fs-11" id="cashReceivedInput" placeholder="0" data-action="pos.cash-change">
                </div>
                <span class="text-nowrap">
                    <span class="fs-10 text-muted">الباقي:</span>
                    <strong class="text-success font-monospace fs-12" id="cashChangeOutput">0 ج.م</strong>
                </span>
                <div class="d-flex gap-1 ms-auto" id="quickCashChips"></div>
            </div>

            <!-- Mandatory Technician in Checkout Box -->
            <div class="p-1 px-2 bg-light rounded border border-danger-subtle mb-1">
                <div class="d-flex justify-content-between align-items-center mb-0">
                    <label class="form-label fs-10 fw-bold text-dark mb-0"><i class="ri-user-settings-line text-primary me-1"></i> العامل / الفني القائم بالتركيب:</label>
                    <span class="badge bg-danger text-white fs-9">إجباري *</span>
                </div>
                <select class="form-select form-select-sm py-1 fw-bold fs-11 border-danger" id="posTechnicianSelectCart" required data-action="pos.sync-tech" data-target="posTechnicianSelect">
                    <option value="">-- اختر العامل / الفني المسؤول عن التركيب --</option>
                    @foreach($technicians as $tech)
                        <option value="{{ $tech->id }}">{{ $tech->full_name }} ({{ $tech->employee_code }})</option>
                    @endforeach
                </select>
            </div>

        </div>

        <!-- Pinned submit footer: always visible, whatever the checkout controls above expand to -->
        <div class="card-footer pos-checkout-footer bg-body border-top p-2">
            <button type="button" class="btn btn-success w-100 fw-bold fs-13 py-1 shadow-sm" id="btnSubmitInvoice" data-action="pos.submit">
                <i class="ri-check-double-line me-1"></i> اعتماد الفاتورة والضمان (Enter ↵)
            </button>
        </div>

    </div>
</div>
