<style>
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
            min-height: 120px;
            max-height: 38vh;
            padding: 4px 6px;
            background: var(--vz-light, #f8f9fa);
            border-radius: 6px;
            border: 1px solid var(--vz-border-color, #e9ebec);
        }
        /* Slim custom scrollbar for fast cashier scanning */
        .pos-cart-items-scroll::-webkit-scrollbar,
        .pos-catalog-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .pos-cart-items-scroll::-webkit-scrollbar-thumb,
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
        overflow-x: auto;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none; /* IE/Edge */
        padding: 2px 1px 4px 1px;
    }
    .pos-category-scroll-container::-webkit-scrollbar {
        display: none; /* Chrome, Safari, Opera */
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
        transition: all 0.15s ease-in-out;
    }
    .pos-category-pill:hover {
        transform: translateY(-1px);
    }
    .pos-category-pill.active, .pos-category-pill.btn-primary {
        box-shadow: 0 2px 8px rgba(64, 81, 137, 0.28);
    }

    .cart-item-row {
        background: var(--vz-card-bg, #ffffff);
        border-radius: 5px;
        border: 1px solid var(--vz-border-color, #e2e8f0);
        padding: 5px 8px;
        margin-bottom: 4px;
        transition: all 0.12s ease;
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
        transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
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

    /* Payment mode selector (كامل الدفع / جزء من المبلغ / باقي المبلغ) */
    .payment-mode-group {
        display: flex;
        gap: 4px;
    }

    .payment-mode-btn {
        flex: 1 1 0;
        cursor: pointer;
        border: 2px solid var(--vz-border-color, #e9ebec);
        border-radius: 8px;
        padding: 5px 2px;
        text-align: center;
        line-height: 1.25;
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
    [data-bs-theme="dark"] #posCustomerSelect,
    [data-bs-theme="dark"] #posTechnicianSelect,
    [data-bs-theme="dark"] #posTechnicianSelectCart,
    [data-bs-theme="dark"] #quickAddProductSelect,
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
    [data-bs-theme="dark"] #posCustomerSelect:focus,
    [data-bs-theme="dark"] #posTechnicianSelect:focus,
    [data-bs-theme="dark"] #quickAddProductSelect:focus {
        background-color: #212529 !important;
        color: #ffffff !important;
        border-color: var(--vz-primary) !important;
        box-shadow: 0 0 0 0.15rem rgba(64, 81, 137, 0.25) !important;
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
    [data-bs-theme="dark"] #cashPresetsBox,
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
