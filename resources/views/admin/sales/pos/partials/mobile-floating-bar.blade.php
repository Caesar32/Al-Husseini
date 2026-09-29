<!-- ============================================================== -->
<!-- Mobile & Tablet Sticky Floating Cart & Checkout Bar            -->
<!-- ============================================================== -->
<div class="d-lg-none fixed-bottom border-top shadow-lg p-2 px-3 z-3" id="posMobileFloatingBar" style="background-color: var(--vz-card-bg, #ffffff); box-shadow: 0 -4px 16px rgba(0,0,0,0.12) !important;">
    <div class="d-flex align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2" role="button" onclick="scrollToCartOrSubmit()">
            <div class="position-relative">
                <span class="btn btn-sm btn-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                    <i class="ri-shopping-cart-2-line fs-17"></i>
                </span>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="mobileCartCountBadge">0</span>
            </div>
            <div>
                <span class="fs-10 text-muted d-block lh-1">إجمالي الفاتورة:</span>
                <strong class="text-success font-monospace fs-15" id="mobileCartTotalAmount">0 ج.م</strong>
            </div>
        </div>
        <button type="button" class="btn btn-success fw-bold fs-12 px-3 py-2 shadow-sm d-flex align-items-center gap-1" onclick="scrollToCartOrSubmit()">
            <i class="ri-arrow-down-line"></i>
            <span>إتمام الفاتورة</span>
        </button>
    </div>
</div>
