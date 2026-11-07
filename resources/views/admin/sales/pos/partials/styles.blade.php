<style>
    :root {
        --pos-surface: var(--vz-card-bg, var(--vz-white, #fff));
        --pos-muted: var(--vz-secondary-color, #878a99);
        --pos-border: var(--vz-border-color, #e9ebec);
        --pos-focus-ring: color-mix(in srgb, var(--vz-primary, #405189) 28%, transparent);
        --pos-control-height: 36px;
    }

    body:has([data-page="pos"]) .app-search {
        display: none !important;
    }

    .pos-catalog-panel {
        container-type: inline-size;
        min-width: 0;
    }

    .pos-catalog-card {
        overflow: visible;
    }

    .card-header.pos-transaction-context {
        position: relative;
        z-index: 5;
        padding: 5px 12px;
        background: var(--pos-surface);
        border-color: var(--pos-border);
    }

    /* Fluid: what does not fit wraps to the next row, instead of leaving the panel. */
    .pos-transaction-grid {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        min-height: 36px;
    }

    .pos-customer-field {
        flex: 3 1 400px;
    }

    .pos-vehicle-field,
    .pos-technician-field {
        flex: 1 1 165px;
    }

    .pos-context-meta {
        flex: 0 1 auto;
        margin-inline-start: auto;
    }

    .pos-context-field {
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
    }

    /* This field is a <label>: Bootstrap's bottom margin would add 8px to the whole bar. */
    .pos-technician-field {
        margin-bottom: 0;
    }

    .pos-context-label {
        color: var(--pos-muted);
        flex: 0 0 auto;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }

    .pos-context-label i {
        color: var(--vz-primary);
    }

    .pos-customer-combobox {
        position: relative;
        flex: 1 1 auto;
        min-width: 0;
    }

    .pos-customer-combobox .form-control,
    .pos-technician-field .form-select,
    .pos-vehicle-field .form-select {
        min-height: var(--pos-control-height);
        font-size: 12px;
    }

    .pos-source-select {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        clip-path: inset(50%);
        white-space: nowrap;
    }

    .pos-new-customer-button {
        min-height: var(--pos-control-height);
        height: var(--pos-control-height);
        /* The layout pads every .btn more strongly than a page rule can, which made this bar 55px tall. */
        padding-block: 0 !important;
        padding-inline: 9px !important;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }

    .pos-customer-options,
    #quickAddResultsDropdown {
        position: absolute;
        right: 0;
        left: 0;
        z-index: 1060;
        max-height: 300px;
        overflow-y: auto;
        background: var(--pos-surface);
        border: 1px solid var(--pos-border);
        border-radius: 8px;
        box-shadow: var(--vz-box-shadow, 0 10px 28px rgba(0, 0, 0, .14));
    }

    .pos-customer-options {
        top: calc(100% + 4px);
    }

    .pos-customer-option {
        display: flex;
        align-items: baseline;
        gap: 6px;
        width: 100%;
        padding: 8px 10px;
        color: var(--vz-body-color);
        cursor: pointer;
        text-align: right;
    }

    .pos-customer-option:hover,
    .pos-customer-option.active,
    .pos-customer-option[aria-selected="true"] {
        background: var(--vz-light, #f3f6f9);
    }

    .pos-customer-credit-tag {
        color: var(--pos-muted);
        font-size: 10px;
        white-space: nowrap;
    }

    .pos-vehicle-field {
        min-height: var(--pos-control-height);
        padding-inline: 8px;
        background: var(--vz-light, #f3f6f9);
        border: 1px solid var(--pos-border);
        border-radius: var(--vz-border-radius, 4px);
    }

    .pos-vehicle-display {
        overflow: hidden;
        color: var(--pos-muted);
        font-size: 12px;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pos-required-marker {
        color: var(--vz-danger);
    }

    .pos-context-meta {
        display: flex;
        align-items: center;
        gap: 5px;
        justify-content: flex-end;
        padding-inline-end: 6px;
        white-space: nowrap;
    }

    .pos-context-meta .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        min-height: 28px;
    }

    /* A long cashier name must not push the badges out of the panel. */
    .pos-cashier-name {
        max-width: 130px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pos-catalog-navigation {
        position: relative;
        z-index: 4;
        display: grid;
        /* minmax(0, ...): an auto column grows to the width of every category pill and overflows the cart. */
        grid-template-columns: minmax(0, 1fr);
        gap: 7px;
        padding: 8px 12px;
        background: var(--pos-surface);
        border-bottom: 1px solid var(--pos-border);
    }

    .pos-product-search-row {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .pos-product-search-wrap {
        position: relative;
        flex: 1 1 auto;
        min-width: 0;
    }

    .pos-product-search-wrap .form-control {
        min-height: 44px;
        padding-inline: 38px 262px;
        font-weight: 700;
    }

    .pos-product-search-icon {
        position: absolute;
        top: 50%;
        right: 13px;
        z-index: 1;
        color: var(--vz-primary);
        pointer-events: none;
        transform: translateY(-50%);
    }

    .pos-search-shortcuts {
        position: absolute;
        top: 50%;
        left: 10px;
        display: flex;
        align-items: center;
        gap: 4px;
        color: var(--pos-muted);
        font-size: 10px;
        pointer-events: none;
        transform: translateY(-50%);
        white-space: nowrap;
    }

    .pos-search-shortcuts kbd {
        color: var(--vz-body-color);
        background: var(--vz-light, #f3f6f9);
        border: 1px solid var(--pos-border);
        box-shadow: none;
    }

    /* Hints while the box is empty, the clear button once it has text: they never share the space. */
    .pos-product-search-wrap.has-value .pos-search-shortcuts {
        display: none;
    }

    .pos-product-search-wrap.has-value .form-control {
        padding-inline-end: 46px;
    }

    .pos-search-clear-button.btn {
        position: absolute;
        top: 4px;
        left: 4px;
        display: none !important;
        align-items: center;
        justify-content: center;
        width: 36px;
        min-height: 36px;
        padding: 0 !important;
        border: 0;
        background: transparent;
    }

    .pos-product-search-wrap.has-value .pos-search-clear-button.btn {
        display: inline-flex !important;
    }

    .pos-view-toggle .btn,
    .pos-category-nav-arrow {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: var(--pos-control-height);
        min-height: var(--pos-control-height);
    }

    #quickAddResultsDropdown {
        top: calc(100% + 4px);
    }

    .pos-catalog-panel :is(input, select, button):focus-visible,
    .pos-customer-option:focus-visible {
        outline: 0;
        box-shadow: 0 0 0 3px var(--pos-focus-ring);
    }

    @container (max-width: 900px) {
        /* Keys only: the words would run into the placeholder at this width. */
        .pos-search-shortcuts span {
            display: none;
        }

        .pos-product-search-wrap .form-control {
            padding-inline: 38px 126px;
        }
    }

    @container (max-width: 600px) {
        /* Customer on its row, vehicle and technician sharing the next; session badges give way. */
        .pos-customer-field {
            flex-basis: 100%;
        }

        .pos-context-meta {
            display: none;
        }

        .pos-search-shortcuts {
            display: none;
        }

        .pos-product-search-wrap .form-control {
            padding-inline-end: 46px;
        }
    }

    @container (max-width: 420px) {
        .pos-vehicle-field,
        .pos-technician-field {
            flex-basis: 100%;
        }

        /* Full-width customer and search boxes on a phone; the buttons move to the next row. */
        .pos-customer-field,
        .pos-product-search-row {
            flex-wrap: wrap;
        }

        .pos-customer-field .pos-context-label {
            display: none;
        }

        .pos-customer-combobox,
        .pos-product-search-wrap {
            flex: 1 1 100%;
        }

        .pos-new-customer-button {
            flex: 1 1 100%;
            justify-content: center;
        }

        .pos-product-search-row {
            align-items: stretch;
        }


        .pos-search-shortcuts {
            display: none;
        }

        .pos-product-search-wrap .form-control {
            padding-left: 38px;
        }
    }

    [data-bs-theme="dark"] .pos-view-toggle .btn:not(.active) {
        color: var(--vz-body-color);
        background: var(--pos-surface);
    }

    .pos-pill-count {
        white-space: nowrap;
    }

    .pos-stock-breakdown {
        display: block;
        margin-top: 2px;
        color: var(--pos-muted);
        font-size: 10px;
        white-space: nowrap;
    }

    .pos-liter-presets {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px;
        margin-top: 4px;
    }

    .pos-liter-presets-label {
        color: var(--pos-muted);
        font-size: 10px;
        font-weight: 700;
    }

    .pos-liter-preset.btn {
        padding: 1px 7px !important;
        font-size: 10px;
    }

    /* Needs a height to be observed; stays almost invisible until there is a status to show. */
    .pos-catalog-sentinel {
        min-height: 12px;
        padding-block: 4px;
        color: var(--pos-muted);
        font-size: 12px;
        text-align: center;
    }

    @media (prefers-reduced-motion: no-preference) {
        .pos-customer-option,
        .pos-category-pill,
        .pos-search-clear-button,
        .pos-view-toggle .btn {
            transition: background-color 120ms ease, border-color 120ms ease, box-shadow 120ms ease, color 120ms ease;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .pos-catalog-panel .pos-product-card,
        .pos-catalog-panel .pos-category-pill,
        .pos-catalog-panel .pos-customer-option,
        .pos-catalog-panel .pos-skeleton-line {
            animation: none;
            transition: none;
        }

        .pos-category-scroll-container {
            scroll-behavior: auto;
        }
    }

    /* Compact cart controls (cart-panel.blade.php) */
    .input-group.pos-scrap-count-group { width: 75px; }
    .input-group.pos-scrap-capacity-group { width: 70px; }
    .input-group.pos-cash-received-group { width: 120px; }
    #scrapPriceInput { width: 85px; }
    #cartDiscountInput { max-width: 90px; }

    /* Global POS Viewport Lock - Full Height Cashier Terminal on Large Screens */
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
            overflow: visible;
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
        /* Cart card: header / item list / checkout controls / pinned submit footer.
           The item list takes the height left over (at least 140px); the checkout controls keep their
           natural height and scroll only when the screen is too short; the footer never shrinks. */
        .pos-cart-panel > .pos-cart-card {
            display: flex !important;
            flex-direction: column !important;
            height: 100% !important;
            max-height: calc(100vh - 75px) !important;
            overflow: hidden !important;
        }
        .pos-cart-items-scroll {
            flex: 1 1 0 !important;
            overflow-y: auto !important;
            min-height: 140px !important;
            padding: 4px;
            background: var(--vz-light, #f8f9fa);
            border-radius: 6px;
            border: 1px solid var(--vz-border-color, #e9ebec);
        }
        .pos-checkout-body {
            flex: 0 1 auto !important;
            min-height: 0;
            overflow-y: auto !important;
            scrollbar-width: thin;
        }
        /* Slim custom scrollbar for fast cashier scanning */
        .pos-cart-items-scroll::-webkit-scrollbar,
        .pos-checkout-body::-webkit-scrollbar,
        .pos-catalog-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .pos-cart-items-scroll::-webkit-scrollbar-thumb,
        .pos-checkout-body::-webkit-scrollbar-thumb,
        .pos-catalog-scroll::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 4px;
        }
    }

    /* Tablet and Mobile Optimization (< 992px) */
    @media (max-width: 991.98px) {
        html, body {
            overflow-y: auto !important;
            height: auto !important;
            min-height: 100% !important;
        }
        #layout-wrapper, .main-content {
            height: auto !important;
            max-height: none !important;
            overflow: visible !important;
            min-height: 100% !important;
        }
        .page-content {
            padding-top: 65px !important;
            padding-bottom: 84px !important; /* Space for mobile floating cart bar */
            height: auto !important;
            overflow: visible !important;
        }
        .pos-container-fluid {
            height: auto !important;
            display: block !important;
        }
        .pos-grid-row {
            height: auto !important;
        }
        .pos-catalog-panel {
            height: auto !important;
            margin-bottom: 16px;
        }
        .pos-catalog-scroll {
            max-height: 65vh !important;
            overflow-y: auto !important;
            padding: 8px 10px !important;
            -webkit-overflow-scrolling: touch;
        }
        .pos-cart-panel {
            height: auto !important;
            margin-bottom: 24px;
        }
        .pos-cart-items-scroll {
            max-height: 45vh !important;
            min-height: 100px !important;
            padding: 4px 6px;
            background: var(--vz-light, #f8f9fa);
            border-radius: 6px;
            border: 1px solid var(--vz-border-color, #e9ebec);
        }
    }

    /* Horizontal Category Scroll Ribbon */
    .pos-category-scroll-container {
        display: flex;
        gap: 6px;
        overflow-x: auto !important;
        overflow-y: hidden;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        scroll-behavior: smooth;
        cursor: grab;
        padding: 2px 1px;
    }
    .pos-category-scroll-container:active,
    .pos-category-scroll-container.is-dragging {
        cursor: grabbing;
        scroll-behavior: auto; /* smooth-scroll fights an active drag; disable it mid-drag */
    }
    .pos-category-scroll-container::-webkit-scrollbar {
        display: none;
    }
    .pos-category-nav-row {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .pos-category-nav-arrow {
        flex-shrink: 0;
        width: 26px;
        height: 26px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 15px;
        line-height: 1;
    }
    .pos-category-nav-arrow:disabled {
        display: none;
    }
    .pos-category-pill {
        flex-shrink: 0;
        border-radius: 20px;
        padding: 5px 13px;
        font-size: 11.5px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: background-color 120ms ease, border-color 120ms ease, box-shadow 120ms ease;
    }
    .pos-category-pill:hover {
        border-color: var(--vz-primary);
    }
    .pos-category-pill.active, .pos-category-pill.btn-primary {
        box-shadow: 0 2px 8px rgba(64, 81, 137, 0.28);
    }

    .cart-item-row {
        background: var(--vz-card-bg, #ffffff);
        border-radius: 5px;
        border: 1px solid var(--vz-border-color, #e2e8f0);
        padding: 3px 6px;
        margin-bottom: 3px;
        transition: all 0.12s ease;
    }

    .pos-checkout-footer {
        flex-shrink: 0 !important;
        z-index: 10;
    }

    /* Trade-in inputs: one 26px row */
    #tradeInDetailsBox {
        margin-top: 4px;
    }
    #tradeInDetailsBox .form-control,
    #tradeInDetailsBox .btn,
    #tradeInDetailsBox .input-group-text {
        height: 26px;
        min-height: 26px;
        font-size: 11px;
        padding: 0 4px;
        line-height: 1;
    }

    /* Product name and price take what the stepper / total / delete leave. */
    .cart-item-title {
        flex: 1 1 auto;
        min-width: 0;
        max-width: 65%;
    }

    .cart-serial-group .input-group-text,
    .cart-serial-group .form-control {
        font-size: 10px;
        height: 24px;
        min-height: 24px;
        padding: 0 4px;
    }

    .cart-qty-group .btn,
    .cart-qty-group .form-control {
        height: 24px;
        line-height: 1;
    }

    .cart-item-row:hover {
        border-color: var(--vz-primary, #405189);
        background: #fdfdfd;
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    }

    /* Product Cards - High Visibility & Bold Typography */
    .pos-product-card {
        cursor: pointer;
        position: relative;
        background: var(--vz-card-bg, #ffffff) !important;
        background-color: var(--vz-card-bg, #ffffff) !important;
        border: 1.5px solid var(--vz-border-color, #e2e8f0);
        border-radius: 10px;
        padding: 10px 12px;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: border-color 120ms ease, box-shadow 120ms ease, transform 120ms ease;
        user-select: none;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
    }

    .pos-product-card:hover {
        border-color: #2563eb;
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(37, 99, 235, 0.12);
    }

    .pos-product-card:active {
        transform: scale(0.99);
    }

    .pos-stock-badge {
        font-size: 10.5px;
        font-weight: 800;
        padding: 2px 7px;
        border-radius: 6px;
        letter-spacing: 0.2px;
    }

    .in-cart-indicator {
        position: absolute;
        top: -7px;
        left: -7px;
        background: #10b981;
        color: #ffffff;
        font-size: 10.5px;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 12px;
        box-shadow: 0 3px 8px rgba(16, 185, 129, 0.4);
        display: inline-flex;
        align-items: center;
        gap: 4px;
        z-index: 3;
    }

    /* Product-search autocomplete dropdown (replaces the old 7,340-option <select>) */
    #quickAddResultsDropdown {
        position: absolute;
        top: calc(100% + 4px);
        right: 0;
        left: 0;
        z-index: 1050;
        max-height: 320px;
        overflow-y: auto;
        background: var(--vz-card-bg, #ffffff);
        border: 1px solid var(--vz-border-color, #e2e8f0);
        border-radius: 8px;
        box-shadow: 0 10px 28px rgba(0, 0, 0, 0.14);
    }
    .quick-add-result-item {
        padding: 7px 10px;
        cursor: pointer;
        border-bottom: 1px solid var(--vz-border-color, #eef0f2);
    }
    .quick-add-result-item:last-child {
        border-bottom: none;
    }
    .quick-add-result-item:hover,
    .quick-add-result-item.active {
        background-color: var(--vz-light, #f3f6f9);
    }
    .quick-add-result-empty {
        padding: 10px;
        text-align: center;
        font-size: 11.5px;
        color: var(--vz-secondary-color, #878a99);
    }

    /* Skeleton loading state for catalog cards while a search/category fetch is in flight */
    .pos-skeleton-card {
        cursor: default;
        pointer-events: none;
    }
    .pos-skeleton-line {
        height: 10px;
        border-radius: 4px;
        background: linear-gradient(90deg, #eef0f2 25%, #e2e5e9 37%, #eef0f2 63%);
        background-size: 400% 100%;
        animation: pos-skeleton-shimmer 1.4s ease infinite;
    }
    .pos-skeleton-line-tall { height: 14px; }
    .pos-skeleton-line-short { height: 16px; }
    .pos-catalog-list-shell { background-color: var(--vz-card-bg, #ffffff); }
    .pos-catalog-add-column { width: 100px; }
    .pos-catalog-sku { max-width: 50%; }
    .cart-qty-input { width: 62px; }
    .cart-line-total { min-width: 65px; }
    @keyframes pos-skeleton-shimmer {
        0% { background-position: 100% 50%; }
        100% { background-position: 0 50%; }
    }

    /* Mobile product card optimizations */
    @media (max-width: 575.98px) {
        .pos-product-card {
            padding: 8px 9px;
            border-radius: 8px;
        }
        .pos-product-card h6 {
            font-size: 11.5px !important;
            margin-bottom: 4px !important;
        }
        .pos-stock-badge {
            font-size: 9px;
            padding: 1px 5px;
        }
        .pos-product-card .btn {
            padding: 3px 6px !important;
            font-size: 10.5px !important;
        }
        .in-cart-indicator {
            font-size: 9.5px;
            padding: 1px 6px;
            top: -5px;
            left: -5px;
        }
    }

    /* POS Catalog List Mode (High Speed Table View) */
    .pos-catalog-table th {
        font-size: 12px;
        font-weight: 800;
        background-color: #f1f5f9;
        color: #1e293b;
        padding: 9px 12px;
        border-bottom: 2px solid #cbd5e1;
    }
    .pos-catalog-table td {
        font-size: 13px;
        padding: 9px 12px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .pos-catalog-table tr {
        cursor: pointer;
        transition: all 0.12s ease;
    }
    .pos-catalog-table tr:hover {
        background-color: #eff6ff !important;
    }

    .payment-method-group {
        display: flex;
        gap: 4px;
    }

    .payment-method-card {
        flex: 1 1 0;
        cursor: pointer;
        border: 1px solid var(--vz-border-color, #e9ebec);
        border-radius: 999px;
        height: 28px;
        padding: 2px 8px;
        font-size: 11px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        white-space: nowrap;
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

    /* Payment mode selector (كامل الدفع / جزء من المبلغ / باقي المبلغ) */
    .payment-mode-group {
        display: flex;
        gap: 4px;
    }

    .payment-mode-btn {
        flex: 1 1 0;
        cursor: pointer;
        border: 1px solid var(--vz-border-color, #e9ebec);
        border-radius: 999px;
        height: 28px;
        padding: 2px 8px;
        font-size: 11px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        white-space: nowrap;
        line-height: 1;
        background: var(--vz-card-bg, #ffffff);
        color: var(--vz-body-color, #495057);
        transition: all 0.15s ease;
    }

    .payment-mode-btn:hover {
        border-color: var(--vz-border-color-translucent, #cbd5e1);
    }

    .payment-mode-btn:focus-visible {
        outline: 2px solid var(--vz-primary);
        outline-offset: 1px;
    }

    .payment-mode-btn.active.mode-full {
        border-color: var(--vz-success);
        background-color: var(--vz-success-bg-subtle, rgba(10, 179, 156, 0.1));
        color: var(--vz-success);
        font-weight: 800;
    }

    .payment-mode-btn.active.mode-partial {
        border-color: var(--vz-primary);
        background-color: var(--vz-primary-bg-subtle, rgba(64, 81, 137, 0.1));
        color: var(--vz-primary);
        font-weight: 800;
    }

    .payment-mode-btn.active.mode-remaining {
        border-color: var(--vz-warning);
        background-color: var(--vz-warning-bg-subtle, rgba(247, 184, 75, 0.12));
        color: var(--vz-warning-text-emphasis, #b45309);
        font-weight: 800;
    }

    [data-bs-theme="dark"] .payment-mode-btn {
        background-color: #212529;
        border-color: #32383e;
        color: #ced4da;
    }

    [data-bs-theme="dark"] .payment-mode-btn.active {
        color: #ffffff;
    }

    #paidNowInput[readonly],
    #paidNowInput:disabled {
        background-color: var(--vz-secondary-bg, #f3f6f9);
        cursor: not-allowed;
    }

    /* Print styles */
    @media print {
        body { background: #fff !important; }
        .app-menu, .topbar, .footer, .btn, .no-print, #posMobileFloatingBar { display: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; }
        .print-invoice-sheet {
            display: block !important;
            width: 100% !important;
            border: 2px solid #000;
            padding: 20px;
            font-size: 12pt;
        }
    }

    /* ============================================================== */
    /* Comprehensive Dark Mode Optimization ([data-bs-theme="dark"])  */
    /* ============================================================== */
    [data-bs-theme="dark"] .pos-product-card,
    [data-bs-theme="dark"] .card.pos-product-card,
    html[data-bs-theme="dark"] .pos-product-card,
    body[data-bs-theme="dark"] .pos-product-card {
        background: #212529 !important;
        background-color: #212529 !important;
        border-color: #32383e !important;
        color: #f8f9fa !important;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.35) !important;
    }
    [data-bs-theme="dark"] .pos-product-card:hover,
    [data-bs-theme="dark"] .card.pos-product-card:hover {
        background: #262b30 !important;
        background-color: #262b30 !important;
        border-color: #3b82f6 !important;
        box-shadow: 0 8px 24px rgba(59, 130, 246, 0.22) !important;
    }
    [data-bs-theme="dark"] .pos-product-card h6 {
        color: #f8f9fa !important;
    }
    [data-bs-theme="dark"] .pos-product-card .bg-light {
        background-color: #1a1d21 !important;
        border-color: #2d3238 !important;
        color: #ced4da !important;
    }
    [data-bs-theme="dark"] .pos-product-card .border-top {
        border-color: #32383e !important;
    }
    [data-bs-theme="dark"] .pos-product-card .text-secondary {
        color: #94a3b8 !important;
    }
    [data-bs-theme="dark"] .pos-product-card .text-muted {
        color: #94a3b8 !important;
    }
    [data-bs-theme="dark"] .pos-product-card .badge.bg-dark-subtle {
        background-color: #2b3035 !important;
        color: #f8f9fa !important;
        border-color: #3e444a !important;
    }

    /* Cart Items & Scroll Container */
    [data-bs-theme="dark"] .pos-cart-items-scroll {
        background-color: #181b1e !important;
        border-color: #32383e !important;
    }
    [data-bs-theme="dark"] .cart-item-row {
        background-color: #212529 !important;
        border-color: #32383e !important;
        color: #e9ecef !important;
    }
    [data-bs-theme="dark"] .cart-item-row:hover {
        background-color: #262b30 !important;
        border-color: var(--vz-primary, #405189) !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3) !important;
    }
    [data-bs-theme="dark"] .cart-item-row .text-dark {
        color: #f8f9fa !important;
    }
    [data-bs-theme="dark"] .cart-item-row .bg-white {
        background-color: #1a1d21 !important;
        color: #f8f9fa !important;
        border-color: #32383e !important;
    }
    [data-bs-theme="dark"] .cart-item-row .btn-light {
        background-color: #2a2f35 !important;
        border-color: #383f45 !important;
        color: #ced4da !important;
    }
    [data-bs-theme="dark"] .cart-item-row .btn-light:hover {
        background-color: #343a40 !important;
        color: #ffffff !important;
    }

    /* Table Catalog List View */
    [data-bs-theme="dark"] .pos-catalog-table th {
        background-color: #212529 !important;
        color: #f1f5f9 !important;
        border-bottom: 2px solid #383f45 !important;
    }
    [data-bs-theme="dark"] .pos-catalog-table td {
        background-color: #1e2226 !important;
        color: #ced4da !important;
        border-bottom: 1px solid #2d3238 !important;
    }
    [data-bs-theme="dark"] .pos-catalog-table tr:hover td {
        background-color: #262c33 !important;
    }
    [data-bs-theme="dark"] .pos-catalog-table td strong.text-dark {
        color: #f8f9fa !important;
    }
    [data-bs-theme="dark"] .pos-catalog-panel .table-responsive.bg-white {
        background-color: #1e2226 !important;
        border-color: #32383e !important;
    }

    /* Payment Method Cards */
    [data-bs-theme="dark"] .payment-method-card {
        background-color: #212529 !important;
        border-color: #32383e !important;
        color: #adb5bd !important;
    }
    [data-bs-theme="dark"] .payment-method-card:hover {
        border-color: #495057 !important;
        background-color: #282d32 !important;
        color: #e9ecef !important;
    }
    [data-bs-theme="dark"] .payment-method-card.active.pay-cash {
        background-color: rgba(10, 179, 156, 0.18) !important;
        border-color: var(--vz-success) !important;
        color: var(--vz-success) !important;
    }
    [data-bs-theme="dark"] .payment-method-card.active.pay-instapay {
        background-color: rgba(64, 81, 137, 0.22) !important;
        border-color: var(--vz-primary) !important;
        color: #8da2fb !important;
    }
    [data-bs-theme="dark"] .payment-method-card.active.pay-card {
        background-color: rgba(41, 156, 219, 0.18) !important;
        border-color: var(--vz-info) !important;
        color: var(--vz-info) !important;
    }
    /* Top Customer / Tech / Vehicle Header Bar */
    [data-bs-theme="dark"] .pos-catalog-panel .card-header,
    [data-bs-theme="dark"] .pos-cart-panel .card-header {
        background-color: #212529 !important;
        border-color: #32383e !important;
    }
    [data-bs-theme="dark"] #posCarDetailsDisplay {
        color: #f8f9fa !important;
    }
    [data-bs-theme="dark"] .bg-body {
        background-color: #1a1d21 !important;
    }

    /* Quick Add Bar & Search Row */
    [data-bs-theme="dark"] #catalogSearchInput,
    [data-bs-theme="dark"] #posCustomerSearch,
    [data-bs-theme="dark"] #posTechnicianSelect,
    [data-bs-theme="dark"] #posTechnicianSelectCart,
    [data-bs-theme="dark"] #scrapCapacityInput,
    [data-bs-theme="dark"] #scrapPriceInput,
    [data-bs-theme="dark"] #scrapCountInput,
    [data-bs-theme="dark"] #cashReceivedInput,
    [data-bs-theme="dark"] #paidNowInput {
        background-color: #1e2226 !important;
        color: #f8f9fa !important;
        border-color: #383f45 !important;
    }
    [data-bs-theme="dark"] #catalogSearchInput::placeholder {
        color: #878a99 !important;
    }
    [data-bs-theme="dark"] #catalogSearchInput:focus,
    [data-bs-theme="dark"] #posCustomerSearch:focus,
    [data-bs-theme="dark"] #posTechnicianSelect:focus,
    [data-bs-theme="dark"] #posTechnicianSelectCart:focus {
        background-color: #212529 !important;
        color: #ffffff !important;
        border-color: var(--vz-primary) !important;
        box-shadow: 0 0 0 0.15rem rgba(64, 81, 137, 0.25) !important;
    }
    [data-bs-theme="dark"] #quickAddResultsDropdown {
        background-color: #1e2226 !important;
        border-color: #383f45 !important;
    }
    [data-bs-theme="dark"] .quick-add-result-item {
        border-color: #32383e !important;
        color: #f8f9fa !important;
    }
    [data-bs-theme="dark"] .quick-add-result-item:hover,
    [data-bs-theme="dark"] .quick-add-result-item.active {
        background-color: #262a2e !important;
    }
    [data-bs-theme="dark"] .pos-skeleton-card .pos-skeleton-line {
        background: linear-gradient(90deg, #262a2e 25%, #32383e 37%, #262a2e 63%) !important;
        background-size: 400% 100% !important;
    }
    [data-bs-theme="dark"] .pos-category-nav-arrow {
        background-color: #262a2e !important;
        border-color: #383f45 !important;
        color: #ced4da !important;
    }
    [data-bs-theme="dark"] .input-group-text {
        background-color: #262a2e !important;
        color: #ced4da !important;
        border-color: #383f45 !important;
    }
    [data-bs-theme="dark"] .btn-light {
        background-color: #262a2e !important;
        border-color: #383f45 !important;
        color: #ced4da !important;
    }
    [data-bs-theme="dark"] .btn-light:hover {
        background-color: #32383e !important;
        color: #f8f9fa !important;
    }
    [data-bs-theme="dark"] .btn-light.active {
        background-color: var(--vz-primary, #405189) !important;
        border-color: var(--vz-primary, #405189) !important;
        color: #ffffff !important;
    }

    /* Financials & Helpers in Checkout Panel */
    [data-bs-theme="dark"] #cashCalculatorBox,
    [data-bs-theme="dark"] #paymentAmountBox,
    [data-bs-theme="dark"] .pos-cart-panel .bg-light {
        background-color: #212529 !important;
        border-color: #32383e !important;
    }
    [data-bs-theme="dark"] .text-dark {
        color: #f8f9fa !important;
    }
    [data-bs-theme="dark"] #paymentRemainingOutput {
        background-color: #1a1d21 !important;
        border-color: #32383e !important;
    }

    /* Mobile Sticky Bottom Bar */
    [data-bs-theme="dark"] #posMobileFloatingBar {
        background-color: #212529 !important;
        border-color: #32383e !important;
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.5) !important;
    }

    /* Modals in Dark Mode */
    [data-bs-theme="dark"] .modal-content {
        background-color: #212529 !important;
        color: #ced4da !important;
        border-color: #383f45 !important;
    }
    [data-bs-theme="dark"] .modal-header.bg-light,
    [data-bs-theme="dark"] .modal-footer.bg-light {
        background-color: #1a1d21 !important;
        border-color: #32383e !important;
    }
    [data-bs-theme="dark"] .modal-body input.form-control,
    [data-bs-theme="dark"] .modal-body select.form-select {
        background-color: #1e2226 !important;
        color: #f8f9fa !important;
        border-color: #383f45 !important;
    }
</style>
