@extends('admin.layouts.master')

@section('title', 'تسجيل فاتورة توريد ومشتريات بطاريات | مركز الحسيني')

@section('content')
<div class="container-fluid">
    <!-- Breadcrumb -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">تسجيل فاتورة توريد شحنة بطاريات جديدة</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.purchases.index') }}">المشتريات</a></li>
                        <li class="breadcrumb-item active">فاتورة جديدة</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.purchases.store') }}" id="purchaseForm">
        @csrf

        <!-- Supplier & Invoice Meta -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">1. بيانات المورد والفاتورة والشحنة</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4 position-relative">
                        <label class="form-label">شركة التوريد / المورد <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="ri-search-2-line"></i></span>
                            <input type="text" id="supplierSearchInput" class="form-control" autocomplete="off"
                                   placeholder="ابحث باسم المورد أو الشركة (من أول حرف)..." required
                                   value="{{ old('supplier_id') ? ($suppliers->firstWhere('id', old('supplier_id'))?->company_name ?? '') : '' }}">
                            <button class="btn btn-outline-secondary" type="button" id="clearSupplierBtn" title="تفريغ">
                                <i class="ri-close-line"></i>
                            </button>
                        </div>
                        <input type="hidden" name="supplier_id" id="selectedSupplierId" required value="{{ old('supplier_id') }}">
                        <div id="supplierSearchResults" class="list-group position-absolute w-100 shadow d-none" style="z-index: 1060; max-height: 260px; overflow-y: auto;"></div>
                        <div id="supplierInfoBadge" class="mt-1 {{ old('supplier_id') ? '' : 'd-none' }}">
                            <span class="badge bg-primary-subtle text-primary" id="supplierBalanceBadge"></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">رقم فاتورة المورد / الشحنة <span class="text-danger">*</span></label>
                        <input type="text" name="invoice_number" class="form-control" required placeholder="مثال: INV-SUP-1082" value="{{ old('invoice_number') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">تاريخ التوريد والاستلام <span class="text-danger">*</span></label>
                        <input type="date" name="invoice_date" class="form-control" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">الفرع المستلم <span class="text-danger">*</span></label>
                        <select name="branch_id" class="form-select" required>
                            @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table Card -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">2. بنود وأصناف الشحنة المستلمة</h5>
                <button type="button" class="btn btn-sm btn-success" id="addRowBtn">
                    <i class="ri-add-line me-1"></i> إضافة صنف للشحنة
                </button>
            </div>
            <div class="card-body">
                <div class="position-relative mb-3">
                    <input type="text" id="productSearchInput" class="form-control" autocomplete="off"
                           placeholder="ابحث بالاسم أو SKU أو الباركود لإضافة صنف فوراً...">
                    <div id="productSearchResults" class="list-group position-absolute w-100 shadow d-none" style="z-index:1050; max-height:320px; overflow-y:auto;"></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 30%;">الصنف والمنتج <span class="text-danger">*</span></th>
                                <th style="width: 15%;">الكمية الموردة <span class="text-danger">*</span></th>
                                <th style="width: 15%;">سعر تكلفة الشراء (ج.م) <span class="text-danger">*</span></th>
                                <th style="width: 15%;">كود المورد (SKU)</th>
                                <th style="width: 15%;">الإجمالي</th>
                                <th style="width: 10%;" class="text-center">حذف</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <tr class="item-row">
                                <td>
                                    <select name="items[0][product_id]" class="form-select product-select" required>
                                        <option value="">-- اختر المنتج --</option>
                                        @foreach($products as $p)
                                        <option value="{{ $p->id }}" data-cost="{{ $p->cost_price }}" data-stock="{{ $p->current_stock }}">
                                            {{ $p->name }} (مخزون: {{ $p->current_stock }} | تكلفة: {{ $p->cost_price }} ج.م)
                                        </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="items[0][quantity]" class="form-control qty-input" min="1" value="1" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="items[0][unit_cost_price]" class="form-control price-input" min="0" required placeholder="0.00">
                                </td>
                                <td>
                                    <input type="text" name="items[0][supplier_sku]" class="form-control" placeholder="كود الصنف لديه">
                                </td>
                                <td>
                                    <input type="text" class="form-control row-total" readonly value="0.00">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-soft-danger remove-row-btn" disabled>
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Totals & Payment Section -->
        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">3. ملاحظات إضافية على الشحنة</h5>
                    </div>
                    <div class="card-body">
                        <textarea name="notes" class="form-control" rows="4" placeholder="أرقام أذون الاستلام، رقم رصاص الشاحنة، أو أي ملاحظات فنية"></textarea>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">4. حسابات وسداد الفاتورة</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>إجمالي البنود:</span>
                            <strong id="subtotalDisplay">0.00 ج.م</strong>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label fs-12">ضريبة القيمة المضافة (+)</label>
                                <input type="number" step="0.01" name="tax_amount" id="taxInput" class="form-control form-control-sm" value="0.00">
                            </div>
                            <div class="col-6">
                                <label class="form-label fs-12">خصم من المورد (-)</label>
                                <input type="number" step="0.01" name="discount_amount" id="discountInput" class="form-control form-control-sm" value="0.00">
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between mb-3 fs-15">
                            <span class="fw-bold">الصافي الإجمالي للفاتورة:</span>
                            <strong class="text-primary fs-16" id="finalTotalDisplay">0.00 ج.م</strong>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fs-12">المبلغ المسدد نقداً فوراً <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="paid_amount" id="paidInput" class="form-control" value="0.00" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fs-12">طريقة السداد <span class="text-danger">*</span></label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="cash">نقداً من الخزينة</option>
                                    <option value="bank_transfer">تحويل بنكي</option>
                                    <option value="cheque">شيك بنكي</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-3 fs-14 bg-light p-2 rounded">
                            <span class="fw-bold text-danger">المتبقي آجل على حساب المورد:</span>
                            <strong class="text-danger" id="remainingDisplay">0.00 ج.م</strong>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold">
                                <i class="ri-check-line me-1"></i> حفظ واعتماد فاتورة التوريد وتحديث WAC
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('script')
@php
$searchSuppliers = $suppliers->map(function ($s) {
    return [
        'id' => $s->id,
        'company_name' => $s->company_name,
        'name' => $s->name,
        'balance' => (float) $s->current_balance,
        'limit' => (float) $s->credit_limit,
    ];
})->values()->all();

$searchProducts = $products->map(function ($p) {
    return [
        'id' => $p->id,
        'name' => $p->name,
        'sku' => $p->sku,
        'barcode' => $p->barcode,
        'cost' => (float) $p->cost_price,
        'stock' => (int) $p->current_stock,
    ];
})->values()->all();
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    let rowIndex = {{ count(old('items', [1])) }};
    const itemsBody = document.getElementById('itemsBody');
    const addRowBtn = document.getElementById('addRowBtn');
    const subtotalDisplay = document.getElementById('subtotalDisplay');
    const finalTotalDisplay = document.getElementById('finalTotalDisplay');
    const remainingDisplay = document.getElementById('remainingDisplay');
    const taxInput = document.getElementById('taxInput');
    const discountInput = document.getElementById('discountInput');
    const paidInput = document.getElementById('paidInput');

    // --- Supplier Live Search ---
    const searchSuppliers = {!! json_encode($searchSuppliers, JSON_UNESCAPED_UNICODE) !!};
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
            btn.innerHTML = `
                <div>
                    <strong>${s.company_name}</strong>
                    ${s.name ? `<small class="text-muted d-block">${s.name}</small>` : ''}
                </div>
                <span class="badge bg-secondary-subtle text-secondary">رصيد: ${Number(s.balance).toLocaleString()} ج.م</span>
            `;
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
    function calculateTotals() {
        let subtotal = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            const total = qty * price;
            row.querySelector('.row-total').value = total.toFixed(2);
            subtotal += total;
        });

        subtotalDisplay.innerText = subtotal.toFixed(2) + ' ج.م';

        const tax = parseFloat(taxInput.value) || 0;
        const discount = parseFloat(discountInput.value) || 0;
        const finalTotal = Math.max(0, subtotal + tax - discount);
        finalTotalDisplay.innerText = finalTotal.toFixed(2) + ' ج.م';

        const paid = parseFloat(paidInput.value) || 0;
        const remaining = Math.max(0, finalTotal - paid);
        remainingDisplay.innerText = remaining.toFixed(2) + ' ج.م';
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
        itemsBody.appendChild(clone);
        rowIndex++;
        calculateTotals();
        return clone;
    }

    addRowBtn.addEventListener('click', appendRow);

    // --- Products Live Search ---
    const searchProducts = {!! json_encode($searchProducts, JSON_UNESCAPED_UNICODE) !!};
    const productSearchInput = document.getElementById('productSearchInput');
    const productSearchResults = document.getElementById('productSearchResults');
    let productMatches = [], activeProductIdx = -1;

    function renderProductResults() {
        productSearchResults.innerHTML = '';
        productMatches.forEach((p, i) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center' + (i === activeProductIdx ? ' active' : '');
            btn.innerHTML = `
                <div>
                    <strong>${p.name}</strong>
                    <div class="small text-muted">
                        ${p.sku ? `<span class="me-2">SKU: ${p.sku}</span>` : ''}
                        ${p.barcode ? `<span>باركود: ${p.barcode}</span>` : ''}
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-success-subtle text-success me-1">تكلفة: ${Number(p.cost).toLocaleString()} ج.م</span>
                    <span class="badge bg-secondary-subtle text-secondary">مخزون: ${p.stock}</span>
                </div>
            `;
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
</script>
@endsection
