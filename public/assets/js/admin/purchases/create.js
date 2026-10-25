/**
 * Purchase invoice entry (resources/views/admin/purchases/create.blade.php), frontend F4.
 * Config (data-config on [data-page="purchases.create"]): { rowIndex, suppliers: [...], products: [...] }
 * Moved from the page's inline script (live supplier/product search renders with textContent, B2).
 */
(function () {
    'use strict';

    Admin.page.register('purchases.create', function (root, config) {
        let rowIndex = config.rowIndex;
        const itemsBody = document.getElementById('itemsBody');
        const addRowBtn = document.getElementById('addRowBtn');
        const subtotalDisplay = document.getElementById('subtotalDisplay');
        const finalTotalDisplay = document.getElementById('finalTotalDisplay');
        const remainingDisplay = document.getElementById('remainingDisplay');
        const taxInput = document.getElementById('taxInput');
        const discountInput = document.getElementById('discountInput');
        const paidInput = document.getElementById('paidInput');

        // --- Supplier Live Search ---
        const searchSuppliers = config.suppliers;
        const supplierSearchInput = document.getElementById('supplierSearchInput');
        const supplierSearchResults = document.getElementById('supplierSearchResults');
        const selectedSupplierId = document.getElementById('selectedSupplierId');
        const supplierInfoBadge = document.getElementById('supplierInfoBadge');
        const supplierBalanceBadge = document.getElementById('supplierBalanceBadge');
        const clearSupplierBtn = document.getElementById('clearSupplierBtn');
        let supplierMatches = [], activeSupplierIdx = -1;

        function renderSupplierResults() {
            supplierSearchResults.innerHTML = '';
            supplierMatches.forEach((s, idx) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center' + (idx === activeSupplierIdx ? ' active' : '');
                // Server values go through textContent only (B2: stored XSS).
                const details = document.createElement('div');
                const companyName = document.createElement('strong');
                companyName.textContent = s.company_name;
                details.appendChild(companyName);
                if (s.name) {
                    const contactName = document.createElement('small');
                    contactName.className = 'text-muted d-block';
                    contactName.textContent = s.name;
                    details.appendChild(contactName);
                }
                const balance = document.createElement('span');
                balance.className = 'badge bg-secondary-subtle text-secondary';
                balance.textContent = `رصيد: ${Number(s.balance).toLocaleString()} ج.م`;
                btn.append(details, balance);
                btn.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    selectSupplier(s);
                });
                supplierSearchResults.appendChild(btn);
            });
            supplierSearchResults.classList.toggle('d-none', supplierMatches.length === 0);
        }

        function selectSupplier(s) {
            selectedSupplierId.value = s.id;
            supplierSearchInput.value = s.company_name + (s.name ? ` (${s.name})` : '');
            supplierBalanceBadge.textContent = `رصيد المورد: ${Number(s.balance).toLocaleString()} ج.م | حد الائتمان: ${Number(s.limit).toLocaleString()} ج.م`;
            supplierInfoBadge.classList.remove('d-none');
            supplierMatches = [];
            activeSupplierIdx = -1;
            renderSupplierResults();
        }

        supplierSearchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            if (q.length < 1) {
                supplierMatches = [];
                activeSupplierIdx = -1;
                renderSupplierResults();
                return;
            }
            supplierMatches = searchSuppliers.filter(s =>
                (s.company_name && s.company_name.toLowerCase().includes(q)) ||
                (s.name && s.name.toLowerCase().includes(q))
            ).slice(0, 15);
            activeSupplierIdx = supplierMatches.length ? 0 : -1;
            renderSupplierResults();
        });

        supplierSearchInput.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                if (!supplierMatches.length) return;
                e.preventDefault();
                activeSupplierIdx = (activeSupplierIdx + (e.key === 'ArrowDown' ? 1 : -1) + supplierMatches.length) % supplierMatches.length;
                renderSupplierResults();
            } else if (e.key === 'Enter') {
                if (supplierMatches.length && activeSupplierIdx >= 0) {
                    e.preventDefault();
                    selectSupplier(supplierMatches[activeSupplierIdx]);
                }
            } else if (e.key === 'Escape') {
                supplierMatches = [];
                renderSupplierResults();
            }
        });

        supplierSearchInput.addEventListener('blur', function () {
            setTimeout(() => {
                supplierMatches = [];
                renderSupplierResults();
            }, 200);
        });

        clearSupplierBtn.addEventListener('click', function () {
            selectedSupplierId.value = '';
            supplierSearchInput.value = '';
            supplierInfoBadge.classList.add('d-none');
            supplierSearchInput.focus();
        });

        if (selectedSupplierId.value) {
            const pre = searchSuppliers.find(s => s.id == selectedSupplierId.value);
            if (pre) selectSupplier(pre);
        }

        // --- Calculations ---
        // Line total in piasters from the quantity in thousandths, rounded half up like
        // Quantity::lineTotal() on the server (2.5 L x 120.50 = 301.25).
        function lineCents(qty, price) {
            return Math.round(Math.round(qty * 1000) * Math.round(price * 100) / 1000);
        }

        function calculateTotals() {
            let subtotalCents = 0;
            document.querySelectorAll('.item-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                const price = parseFloat(row.querySelector('.price-input').value) || 0;
                const cents = lineCents(qty, price);
                row.querySelector('.row-total').value = (cents / 100).toFixed(2);
                subtotalCents += cents;
            });
            const subtotal = subtotalCents / 100;

            subtotalDisplay.innerText = subtotal.toFixed(2) + ' ج.م';

            const tax = parseFloat(taxInput.value) || 0;
            const discount = parseFloat(discountInput.value) || 0;
            const finalTotal = Math.max(0, subtotal + tax - discount);
            finalTotalDisplay.innerText = finalTotal.toFixed(2) + ' ج.م';

            const paid = parseFloat(paidInput.value) || 0;
            const remaining = Math.max(0, finalTotal - paid);
            remainingDisplay.innerText = remaining.toFixed(2) + ' ج.م';
        }

        // Piece products take whole quantities; liter/kg/meter products up to 3 decimals
        // (StorePurchaseInvoiceRequest checks the same). No product picked yet: allow decimals.
        function applyQtyRules(row) {
            const selected = row.querySelector('.product-select').selectedOptions[0];
            const qtyInput = row.querySelector('.qty-input');
            const wholeOnly = selected && selected.value && selected.getAttribute('data-fraction') === '0';
            qtyInput.min = wholeOnly ? '1' : '0.001';
            qtyInput.step = wholeOnly ? '1' : 'any';
        }

        function appendRow() {
            const firstRow = document.querySelector('.item-row');
            const clone = firstRow.cloneNode(true);

            clone.querySelectorAll('input, select').forEach(input => {
                if (input.name) {
                    input.name = input.name.replace(/\[\d+\]/, '[' + rowIndex + ']');
                }
                if (!input.classList.contains('qty-input')) {
                    input.value = '';
                } else {
                    input.value = '1';
                }
            });

            clone.querySelector('.remove-row-btn').removeAttribute('disabled');
            applyQtyRules(clone);
            itemsBody.appendChild(clone);
            rowIndex++;
            calculateTotals();
            return clone;
        }

        addRowBtn.addEventListener('click', appendRow);

        // --- Products Live Search ---
        const searchProducts = config.products;
        const productSearchInput = document.getElementById('productSearchInput');
        const productSearchResults = document.getElementById('productSearchResults');
        let productMatches = [], activeProductIdx = -1;

        function renderProductResults() {
            productSearchResults.innerHTML = '';
            productMatches.forEach((p, i) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center' + (i === activeProductIdx ? ' active' : '');
                // Server values go through textContent only (B2: stored XSS).
                const details = document.createElement('div');
                const productName = document.createElement('strong');
                productName.textContent = p.name;
                const metadata = document.createElement('div');
                metadata.className = 'small text-muted';
                if (p.sku) {
                    const sku = document.createElement('span');
                    sku.className = 'me-2';
                    sku.textContent = `SKU: ${p.sku}`;
                    metadata.append(sku, ' ');
                }
                if (p.barcode) {
                    const barcode = document.createElement('span');
                    barcode.textContent = `باركود: ${p.barcode}`;
                    metadata.appendChild(barcode);
                }
                details.append(productName, metadata);
                const summary = document.createElement('div');
                summary.className = 'text-end';
                const cost = document.createElement('span');
                cost.className = 'badge bg-success-subtle text-success me-1';
                cost.textContent = `تكلفة: ${Number(p.cost).toLocaleString()} ج.م`;
                const stock = document.createElement('span');
                stock.className = 'badge bg-secondary-subtle text-secondary';
                stock.textContent = `مخزون: ${p.stock} ${p.unit_label}`;
                summary.append(cost, ' ', stock);
                btn.append(details, summary);
                btn.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    pickProduct(p);
                });
                productSearchResults.appendChild(btn);
            });
            productSearchResults.classList.toggle('d-none', productMatches.length === 0);
        }

        function pickProduct(p) {
            const first = itemsBody.querySelector('.item-row');
            const row = (itemsBody.children.length === 1 && !first.querySelector('.product-select').value)
                ? first : appendRow();
            row.querySelector('.product-select').value = p.id;
            row.querySelector('.qty-input').value = 1;
            applyQtyRules(row);
            row.querySelector('.price-input').value = parseFloat(p.cost || 0).toFixed(2);
            calculateTotals();
            productSearchInput.value = '';
            productMatches = [];
            activeProductIdx = -1;
            renderProductResults();
            productSearchInput.focus();
        }

        productSearchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            if (q.length < 1) {
                productMatches = [];
                activeProductIdx = -1;
                renderProductResults();
                return;
            }
            productMatches = searchProducts.filter(p =>
                (p.name && p.name.toLowerCase().includes(q)) ||
                (p.sku && p.sku.toLowerCase().includes(q)) ||
                (p.barcode && p.barcode.toLowerCase().includes(q))
            ).slice(0, 15);
            activeProductIdx = productMatches.length ? 0 : -1;
            renderProductResults();
        });

        productSearchInput.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                if (!productMatches.length) return;
                e.preventDefault();
                activeProductIdx = (activeProductIdx + (e.key === 'ArrowDown' ? 1 : -1) + productMatches.length) % productMatches.length;
                renderProductResults();
            } else if (e.key === 'Enter') {
                if (productMatches.length && activeProductIdx >= 0) {
                    e.preventDefault();
                    pickProduct(productMatches[activeProductIdx]);
                }
            } else if (e.key === 'Escape') {
                productMatches = [];
                renderProductResults();
            }
        });

        productSearchInput.addEventListener('blur', function () {
            setTimeout(() => {
                productMatches = [];
                renderProductResults();
            }, 200);
        });

        itemsBody.addEventListener('input', function (e) {
            if (e.target.classList.contains('qty-input') || e.target.classList.contains('price-input')) {
                calculateTotals();
            }
        });

        itemsBody.addEventListener('change', function (e) {
            if (e.target.classList.contains('product-select')) {
                const selected = e.target.selectedOptions[0];
                const cost = selected ? selected.getAttribute('data-cost') : 0;
                const row = e.target.closest('.item-row');
                if (cost) {
                    row.querySelector('.price-input').value = parseFloat(cost).toFixed(2);
                }
                applyQtyRules(row);
                calculateTotals();
            }
        });

        itemsBody.addEventListener('click', function (e) {
            if (e.target.closest('.remove-row-btn')) {
                const row = e.target.closest('.item-row');
                if (document.querySelectorAll('.item-row').length > 1) {
                    row.remove();
                    calculateTotals();
                }
            }
        });

        [taxInput, discountInput, paidInput].forEach(inp => {
            inp.addEventListener('input', calculateTotals);
        });
    });
})();
