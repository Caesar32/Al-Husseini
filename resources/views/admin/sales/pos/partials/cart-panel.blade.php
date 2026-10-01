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

        <!-- Bottom Calculations & Dues Terminal (Ultra-clean and compact) -->
        <div class="p-2 px-3 bg-body border-top flex-shrink-0">
            
            <!-- Old Battery Trade-in Scrap Switch & Flexible Pricing Section -->
            <div class="p-2 bg-success-subtle rounded border border-success-subtle mb-1" id="tradeInContainer">
                <div class="form-check form-switch p-0 d-flex justify-content-between align-items-center mb-0">
                    <label class="form-check-label fw-bold text-dark fs-11 mb-0 d-flex align-items-center gap-1 cursor-pointer" for="tradeInCheck">
                        <i class="ri-recycle-line text-success fs-14"></i>
                        تسليم بطاريات كهنة (قديمة / استبدال)
                    </label>
                    <input class="form-check-input me-0" type="checkbox" role="switch" id="tradeInCheck" onchange="toggleTradeInFields()">
                </div>

                <!-- Flexible Details (Count + Custom Price) -->
                <div id="tradeInDetailsBox" class="mt-1 pt-1 border-top border-success-subtle d-none">
                    <div class="row g-1 align-items-center">
                        <div class="col-4">
                            <label class="form-label fs-9 text-muted mb-0 fw-bold">العدد:</label>
                            <div class="input-group input-group-sm">
                                <button class="btn btn-outline-secondary px-1 py-0 fs-10" type="button" onclick="adjustScrapCount(-1)">-</button>
                                <input type="number" class="form-control form-control-sm text-center font-monospace fw-bold p-0 fs-11" id="scrapCountInput" value="1" min="1" max="20" oninput="onScrapCapacityChange()">
                                <button class="btn btn-outline-secondary px-1 py-0 fs-10" type="button" onclick="adjustScrapCount(1)">+</button>
                            </div>
                        </div>
                        <div class="col-4">
                            <label class="form-label fs-9 text-muted mb-0 fw-bold">السعة (Ah):</label>
                            <input type="number" class="form-control form-control-sm text-center font-monospace fw-bold p-0 fs-11" id="scrapCapacityInput" value="70" min="30" max="250" oninput="onScrapCapacityChange()">
                        </div>
                        <div class="col-4">
                            <label class="form-label fs-9 text-muted mb-0 fw-bold">سعر الكهنة:</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="10" min="0" class="form-control form-control-sm font-monospace fw-bold text-success text-center p-0 fs-11" id="scrapPriceInput" placeholder="السعر" title="اتركه فارغاً لاحتساب السعر من شرائح التسعير" oninput="calculateCartTotal()">
                            </div>
                        </div>
                    </div>
                    <div class="fs-10 text-muted mt-1" id="scrapTierHint"></div>
                    <div class="d-flex justify-content-between align-items-center mt-1 fs-10 text-muted">
                        <span>صافي خصم الكهنة:</span>
                        <span class="fw-bold text-success font-monospace fs-11" id="tradeInDiscountDisplay">- 0 ج.م</span>
                    </div>
                </div>
            </div>

            <!-- Financial Breakdown -->
            <div class="border-top pt-1 mb-1 fs-11">
                <div class="d-flex justify-content-between mb-0">
                    <span class="text-muted">المجموع قبل الخصم:</span>
                    <span class="fw-bold text-dark font-monospace" id="cartSubtotal">0 ج.م</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-0 gap-2">
                    <label for="cartDiscountInput" class="text-muted mb-0 text-nowrap">خصم إضافي:</label>
                    <input type="number" step="1" min="0" class="form-control form-control-sm font-monospace fw-bold py-0 fs-11 text-end" style="max-width: 110px;" id="cartDiscountInput" placeholder="0" title="يتطلب صلاحية الخصم أو كود موافقة المشرف" oninput="calculateCartTotal()">
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

            <!-- Payment Method Selector -->
            <div class="mb-1">
                <div class="row g-1 text-center">
                    <div class="col-3">
                        <div class="payment-method-card active pay-cash py-1" id="pay-cash" onclick="setPaymentMethod('cash')">
                            <i class="ri-money-dollar-circle-line fs-14 d-block mb-0 text-success"></i>
                            <span class="fs-10">كاش</span>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="payment-method-card pay-instapay py-1" id="pay-instapay" onclick="setPaymentMethod('instapay')">
                            <i class="ri-smartphone-line fs-14 d-block mb-0 text-primary"></i>
                            <span class="fs-10">إنستاباي</span>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="payment-method-card pay-card py-1" id="pay-card" onclick="setPaymentMethod('card')">
                            <i class="ri-bank-card-line fs-14 d-block mb-0 text-info"></i>
                            <span class="fs-10">فيزا</span>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="payment-method-card pay-credit py-1" id="pay-credit" onclick="setPaymentMethod('credit')">
                            <i class="ri-hand-coin-line fs-14 d-block mb-0 text-warning"></i>
                            <span class="fs-10 fw-bold">الآجل</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Cash Calculation Helper (When Cash is chosen) -->
            <div class="p-1 px-2 bg-light rounded border mb-1" id="cashPresetsBox">
                <div class="row g-1 align-items-center">
                    <div class="col-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body fs-9 py-0 px-1">المستلم:</span>
                            <input type="number" class="form-control form-control-sm font-monospace fw-bold py-0 fs-11" id="cashReceivedInput" placeholder="0" oninput="calculateCashChange()">
                        </div>
                    </div>
                    <div class="col-6 text-end">
                        <span class="fs-10 text-muted">الباقي: </span>
                        <strong class="text-success font-monospace fs-12" id="cashChangeOutput">0 ج.م</strong>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-1 mt-1" id="quickCashChips"></div>
            </div>

            <!-- Credit Fields (If payment is "الآجل") -->
            <div class="p-1 px-2 bg-warning-subtle rounded border border-warning mb-1 d-none" id="creditFieldsBox">
                <div class="row g-1">
                    <div class="col-6">
                        <label class="form-label fs-9 fw-bold text-dark mb-0">المقدم المدفوع الآن:</label>
                        <input type="number" class="form-control form-control-sm font-monospace fw-bold py-0 fs-11" id="creditDepositInput" value="500" oninput="updateCreditBalance()">
                    </div>
                    <div class="col-6">
                        <label class="form-label fs-9 fw-bold text-danger mb-0">المتبقي على الآجل:</label>
                        <input type="text" class="form-control form-control-sm font-monospace text-danger fw-bold bg-light py-0 fs-11" id="creditBalanceOutput" readonly value="0 ج.م">
                    </div>
                </div>
            </div>

            <!-- Mandatory Technician in Checkout Box -->
            <div class="p-1 px-2 bg-light rounded border border-danger-subtle mb-1">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label fs-10 fw-bold text-dark mb-0"><i class="ri-user-settings-line text-primary me-1"></i> العامل / الفني القائم بالتركيب:</label>
                    <span class="badge bg-danger text-white fs-9">إجباري *</span>
                </div>
                <select class="form-select form-select-sm fw-bold fs-11 border-danger" id="posTechnicianSelectCart" required onchange="if(document.getElementById('posTechnicianSelect')) document.getElementById('posTechnicianSelect').value = this.value">
                    <option value="">-- اختر العامل / الفني المسؤول عن التركيب --</option>
                    @foreach($technicians as $tech)
                        <option value="{{ $tech->id }}">{{ $tech->full_name }} ({{ $tech->employee_code }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Submit Invoice Button -->
            <button type="button" class="btn btn-success w-100 fw-bold fs-13 py-1 shadow-sm mt-1" id="btnSubmitInvoice" onclick="submitFullInvoice()">
                <i class="ri-check-double-line me-1"></i> اعتماد الفاتورة والضمان (Enter ↵)
            </button>

        </div>

    </div>
</div>
