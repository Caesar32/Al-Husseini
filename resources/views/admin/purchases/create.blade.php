@extends('admin.layouts.master')

@section('title', 'تسجيل فاتورة توريد ومشتريات بطاريات | مركز الحسيني')

@section('content')
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
@php
    $pageConfig = ['rowIndex' => count(old('items', [1])), 'suppliers' => $searchSuppliers, 'products' => $searchProducts];
@endphp
<div class="container-fluid" data-page="purchases.create" data-config='@json($pageConfig)'>
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
                    <x-form.date name="invoice_date" label="تاريخ التوريد والاستلام" required :value="date('Y-m-d')" wrapper-class="col-md-3" label-class="form-label" />
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
<script src="{{ asset('assets/js/admin/purchases/create.js') }}"></script>
@endsection
