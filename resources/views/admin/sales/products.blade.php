@extends('admin.layouts.master')

@section('title', 'المنتجات والزيوت وخدمات الصيانة | مركز الحسيني لبطاريات وزيوت السيارات')

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'المخزون والورشة', 'title' => 'كتالوج المنتجات والزيوت والشحوم وخدمات الصيانة'])

    <!-- Top Stats -->
    <div class="row mb-3">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate border-start border-success border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">بطاريات السيارات بالمخزن</p>
                            <h3 class="fs-22 fw-extrabold text-success mb-1 font-monospace" id="statBatteriesStock">0 بطارية</h3>
                            <small class="text-muted fs-11" id="statBatteriesCount">0 موديل معتمد</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-success-subtle text-success rounded-circle fs-20">
                                <i class="ri-battery-2-charge-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate border-start border-warning border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">زيوت المحركات والفتيس</p>
                            <h3 class="fs-22 fw-extrabold text-warning mb-1 font-monospace" id="statOilsStock">0 عبوة/جالون</h3>
                            <small class="text-muted fs-11">موبيل، شل، كاسترول، فلاتر</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-20">
                                <i class="ri-oil-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate border-start border-info border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">الشحوم وسوائل التبريد</p>
                            <h3 class="fs-22 fw-extrabold text-info mb-1 font-monospace" id="statGreaseStock">0 قطعة</h3>
                            <small class="text-muted fs-11">مياه ردياتير، باكم، مياه نار، WD-40</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-info-subtle text-info rounded-circle fs-20">
                                <i class="ri-flask-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate border-start border-primary border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">خدمات الصيانة والورشة</p>
                            <h3 class="fs-22 fw-extrabold text-primary mb-1 font-monospace" id="statServicesCount">5 خدمات</h3>
                            <small class="text-muted fs-11">دينامو، كمبيوتر، شحن، طوارئ طريق</small>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-20">
                                <i class="ri-tools-line"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Products & Services Table Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom p-3">
                    <div class="row g-2 align-items-center justify-content-between">
                        <!-- Category Filter Tabs -->
                        <div class="col-lg-6 col-12">
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-sm btn-primary fw-bold px-3" id="tab-prod-all" onclick="filterProductTab('all')">الكل</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="tab-prod-batteries" onclick="filterProductTab('بطاريات')">🔋 بطاريات</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="tab-prod-oils" onclick="filterProductTab('زيوت')">🛢️ زيوت وفلاتر</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="tab-prod-greases" onclick="filterProductTab('شحوم وسوائل')">🧪 شحوم وسوائل</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="tab-prod-services" onclick="filterProductTab('خدمات وصيانة')">🔧 خدمات الورشة</button>
                            </div>
                        </div>

                        <!-- Search & Add Button -->
                        <div class="col-lg-6 col-12 text-lg-end">
                            <div class="d-flex gap-2 justify-content-lg-end">
                                <div class="input-group input-group-sm" style="max-width: 270px;">
                                    <span class="input-group-text bg-light text-primary"><i class="ri-barcode-box-line me-1"></i> <i class="ri-search-line"></i></span>
                                    <input type="text" class="form-control" id="searchProductInput" placeholder="بحث بالاسم، الماركة، أو الباركود..." oninput="renderProductsTable()">
                                </div>
                                <button type="button" class="btn btn-sm btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#productModal" onclick="openNewProductModal()">
                                    <i class="ri-add-line me-1"></i> إضافة صنف / خدمة جديدة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-nowrap mb-0" id="productsTable">
                            <thead class="table-light">
                                <tr class="text-muted fs-12 text-uppercase">
                                    <th>البيان والاسم التجاري</th>
                                    <th>الباركود</th>
                                    <th>القسم</th>
                                    <th>المواصفة / الحجم</th>
                                    <th>النوع / التقنية</th>
                                    <th>السعر (ج.م)</th>
                                    <th>استبدال قديمة (كهنة)</th>
                                    <th>الضمان</th>
                                    <th>الرصيد بالمخزن</th>
                                    <th class="text-center">تعديل</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyProducts">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Add / Edit Product or Service -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white p-3">
                    <h5 class="modal-title fw-bold text-white fs-15" id="productModalTitle">إضافة صنف أو خدمة جديدة</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="productForm" onsubmit="saveProductData(event)">
                    <input type="hidden" id="prodFormId">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-13">قسم الصنف / الخدمة <span class="text-danger">*</span></label>
                                <select class="form-select fw-bold" id="prodFormCategory" onchange="onFormCategoryChange()" required>
                                    <option value="بطاريات">🔋 بطاريات سيارات</option>
                                    <option value="زيوت">🛢️ زيوت محركات وفتيس</option>
                                    <option value="شحوم وسوائل">🧪 شحوم وسوائل تبريد</option>
                                    <option value="خدمات وصيانة">🔧 خدمات وصيانة ورشة</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-13">الماركة / الشركة <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="prodFormBrand" required placeholder="مثلاً: فارتا، موبيل، شل، بوش، ورشة">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-13">كود الباركود (Barcode) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="ri-barcode-line text-primary"></i></span>
                                    <input type="text" class="form-control font-monospace fw-bold" id="prodFormBarcode" required placeholder="6221001010018">
                                    <button class="btn btn-outline-secondary" type="button" onclick="generateRandomBarcode()" title="توليد باركود تلقائي"><i class="ri-magic-line"></i></button>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-13">الوحدة</label>
                                <input type="text" class="form-control" id="prodFormUnit" value="قطعة" placeholder="جالون، عبوة، خدمة، بطارية">
                            </div>

                            <div class="col-md-8">
                                <label class="form-label fw-bold text-dark fs-13">الاسم التجاري الكامل <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="prodFormName" required placeholder="مثلاً: زيت موبيل 1 تخليقي 5W-40 (4L)">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-13">المواصفة / الأمبير / اللزوجة</label>
                                <input type="text" class="form-control font-monospace" id="prodFormAmp" placeholder="70A أو 5W-40 أو DOT 4">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-13">نوع التقنية / التفاصيل</label>
                                <input type="text" class="form-control" id="prodFormType" placeholder="جافة كالسيوم، تخليقي بالكامل، إلخ">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-13">مدة الضمان المعتمد (بالشهور)</label>
                                <select class="form-select" id="prodFormWarranty">
                                    <option value="0">بدون ضمان (زيوت وشحوم)</option>
                                    <option value="1">شهر واحد (صيانة)</option>
                                    <option value="3">3 شهور (إصلاح دينامو)</option>
                                    <option value="12">12 شهر (سنة)</option>
                                    <option value="18">18 شهر (سنة ونصف)</option>
                                    <option value="24">24 شهر (سنتين)</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-13">سعر البيع الأساسي (ج.م) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control font-monospace fw-bold" id="prodFormPriceNew" required min="10" step="10">
                            </div>

                            <div class="col-md-4" id="scrapGroup">
                                <label class="form-label fw-bold text-warning-emphasis fs-13">قيمة استرجاع القديمة (للبطاريات فقط)</label>
                                <input type="number" class="form-control font-monospace text-danger fw-bold" id="prodFormScrap" value="0" min="0" step="50">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-13">الرصيد المتاح بالمخزن</label>
                                <input type="number" class="form-control font-monospace" id="prodFormStock" value="20" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light p-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4">
                            <i class="ri-save-line me-1"></i> حفظ وتحديث الكتالوج
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@section('script')
<script>
'use strict';

let currentTab = 'all';

document.addEventListener('DOMContentLoaded', function () {
    renderProductsDashboard();

    window.addEventListener('alhusseini-sales-updated', function () {
        renderProductsDashboard();
    });
});

function renderProductsDashboard() {
    if (!window.AlHusseiniSales) return;

    const products = window.AlHusseiniSales.getProducts();

    const batteries = products.filter(p => p.category === 'بطاريات');
    const oils = products.filter(p => p.category === 'زيوت');
    const greases = products.filter(p => p.category === 'شحوم وسوائل');
    const services = products.filter(p => p.category === 'خدمات وصيانة');

    const batStock = batteries.reduce((s, p) => s + Number(p.stock || 0), 0);
    const oilStock = oils.reduce((s, p) => s + Number(p.stock || 0), 0);
    const greaseStock = greases.reduce((s, p) => s + Number(p.stock || 0), 0);

    document.getElementById('statBatteriesStock').textContent = `${batStock} بطارية`;
    document.getElementById('statBatteriesCount').textContent = `${batteries.length} موديل معتمد`;

    document.getElementById('statOilsStock').textContent = `${oilStock} جالون/عبوة`;
    document.getElementById('statGreaseStock').textContent = `${greaseStock} قطعة`;
    document.getElementById('statServicesCount').textContent = `${services.length} خدمات بالورشة`;

    renderProductsTable();
}

function filterProductTab(cat) {
    currentTab = cat;

    const tabs = ['all', 'batteries', 'oils', 'greases', 'services'];
    const catMap = { 'all': 'all', 'بطاريات': 'batteries', 'زيوت': 'oils', 'شحوم وسوائل': 'greases', 'خدمات وصيانة': 'services' };

    tabs.forEach(t => {
        const btn = document.getElementById(`tab-prod-${t}`);
        if (btn) {
            if (catMap[cat] === t) btn.className = 'btn btn-sm btn-primary fw-bold px-3';
            else btn.className = 'btn btn-sm btn-outline-secondary';
        }
    });

    renderProductsTable();
}

function renderProductsTable() {
    if (!window.AlHusseiniSales) return;

    const products = window.AlHusseiniSales.getProducts();
    const searchVal = (document.getElementById('searchProductInput')?.value || '').trim().toLowerCase();
    const tbody = document.getElementById('tbodyProducts');
    if (!tbody) return;

    const filtered = products.filter(p => {
        if (currentTab !== 'all' && p.category !== currentTab) return false;
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
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted fs-13">لا توجد أصناف أو خدمات مطابقة للبحث أو الباركود</td></tr>`;
        return;
    }

    const catBadges = {
        'بطاريات': 'bg-success-subtle text-success',
        'زيوت': 'bg-warning-subtle text-warning',
        'شحوم وسوائل': 'bg-info-subtle text-info',
        'خدمات وصيانة': 'bg-primary-subtle text-primary'
    };

    let html = '';
    filtered.forEach(p => {
        const isBattery = p.category === 'بطاريات';
        const isService = p.category === 'خدمات وصيانة';

        const scrapDisplay = isBattery && p.scrapValue > 0 ? 
            `<span class="badge bg-danger-subtle text-danger font-monospace fs-11">- ${p.scrapValue} ج.م</span>` : 
            '<span class="text-muted fs-11">-</span>';

        const warrantyDisplay = p.warrantyMonths > 0 ? 
            `<span class="badge bg-info-subtle text-info fs-11">${p.warrantyMonths} شهر</span>` : 
            '<span class="text-muted fs-11">-</span>';

        const stockDisplay = isService ? 
            '<span class="badge bg-light text-muted border fs-11">خدمة ورشة</span>' : 
            `<span class="badge ${p.stock > 10 ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'} font-monospace fs-12">${p.stock} ${p.unit || 'قطعة'}</span>`;

        html += `
            <tr>
                <td>
                    <strong class="text-dark fs-13 d-block">${p.name}</strong>
                    <span class="badge bg-light text-secondary border font-monospace fs-11">${p.brand}</span>
                </td>
                <td>
                    <span class="badge bg-light text-dark border font-monospace fs-11" title="كود الباركود">
                        <i class="ri-barcode-line text-primary me-1"></i>${p.barcode || 'بدون باركود'}
                    </span>
                </td>
                <td><span class="badge ${catBadges[p.category] || 'bg-light text-dark'} fs-11">${p.category}</span></td>
                <td><span class="badge bg-light text-dark border font-monospace fs-11">${p.amp || p.unit || '-'}</span></td>
                <td><span class="text-muted fs-12">${p.type || '-'}</span></td>
                <td><strong class="text-success font-monospace fs-13">${window.AlHusseiniSales.formatCurrency(p.priceNew)}</strong></td>
                <td>${scrapDisplay}</td>
                <td>${warrantyDisplay}</td>
                <td>${stockDisplay}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-soft-secondary" onclick="editProduct('${p.id}')">
                        <i class="ri-edit-line"></i> تعديل
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function onFormCategoryChange() {
    const cat = document.getElementById('prodFormCategory').value;
    const scrapGrp = document.getElementById('scrapGroup');
    const warrantySel = document.getElementById('prodFormWarranty');

    if (cat === 'بطاريات') {
        scrapGrp.classList.remove('d-none');
        warrantySel.value = "18";
    } else if (cat === 'خدمات وصيانة') {
        scrapGrp.classList.add('d-none');
        document.getElementById('prodFormStock').value = 999;
        warrantySel.value = "0";
    } else {
        scrapGrp.classList.add('d-none');
        warrantySel.value = "0";
    }
}

function generateRandomBarcode() {
    const cat = document.getElementById('prodFormCategory').value;
    if (window.AlHusseiniSales && window.AlHusseiniSales.generateBarcode) {
        document.getElementById('prodFormBarcode').value = window.AlHusseiniSales.generateBarcode(cat);
    }
}

function openNewProductModal() {
    document.getElementById('productModalTitle').textContent = 'إضافة صنف أو خدمة جديدة';
    document.getElementById('prodFormId').value = '';
    document.getElementById('prodFormCategory').value = 'زيوت';
    document.getElementById('prodFormBrand').value = 'موبيل (Mobil)';
    document.getElementById('prodFormName').value = '';
    document.getElementById('prodFormAmp').value = '5W-40';
    document.getElementById('prodFormType').value = 'زيت محرك تخليقي';
    document.getElementById('prodFormUnit').value = 'جالون 4L';
    document.getElementById('prodFormWarranty').value = '0';
    document.getElementById('prodFormPriceNew').value = 1850;
    document.getElementById('prodFormScrap').value = 0;
    document.getElementById('prodFormStock').value = 30;
    generateRandomBarcode();
    onFormCategoryChange();
}

function editProduct(id) {
    const p = window.AlHusseiniSales.getProductById(id);
    if (!p) return;

    document.getElementById('productModalTitle').textContent = 'تعديل بيانات الصنف / الخدمة';
    document.getElementById('prodFormId').value = p.id;
    document.getElementById('prodFormCategory').value = p.category || 'بطاريات';
    document.getElementById('prodFormBrand').value = p.brand;
    document.getElementById('prodFormBarcode').value = p.barcode || (window.AlHusseiniSales ? window.AlHusseiniSales.generateBarcode(p.category) : '');
    document.getElementById('prodFormName').value = p.name;
    document.getElementById('prodFormAmp').value = p.amp || '';
    document.getElementById('prodFormType').value = p.type || '';
    document.getElementById('prodFormUnit').value = p.unit || 'قطعة';
    document.getElementById('prodFormWarranty').value = p.warrantyMonths || 0;
    document.getElementById('prodFormPriceNew').value = p.priceNew;
    document.getElementById('prodFormScrap').value = p.scrapValue || 0;
    document.getElementById('prodFormStock').value = p.stock;
    onFormCategoryChange();

    const modal = new bootstrap.Modal(document.getElementById('productModal'));
    modal.show();
}

function saveProductData(e) {
    e.preventDefault();
    const id = document.getElementById('prodFormId').value;
    const cat = document.getElementById('prodFormCategory').value;
    const brand = document.getElementById('prodFormBrand').value.trim();
    const barcode = document.getElementById('prodFormBarcode').value.trim();
    const name = document.getElementById('prodFormName').value.trim();
    const amp = document.getElementById('prodFormAmp').value.trim();
    const type = document.getElementById('prodFormType').value.trim();
    const unit = document.getElementById('prodFormUnit').value.trim();
    const warranty = Number(document.getElementById('prodFormWarranty').value);
    const priceNew = Number(document.getElementById('prodFormPriceNew').value);
    const scrap = Number(document.getElementById('prodFormScrap').value) || 0;
    const stock = Number(document.getElementById('prodFormStock').value) || 0;

    window.AlHusseiniSales.saveProduct({
        id: id || null,
        category: cat,
        brand: brand,
        barcode: barcode,
        name: name,
        amp: amp,
        type: type,
        unit: unit,
        warrantyMonths: warranty,
        priceNew: priceNew,
        priceWithOld: Math.max(0, priceNew - scrap),
        scrapValue: scrap,
        stock: stock
    });

    const modalEl = document.getElementById('productModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    Swal.fire({
        icon: 'success',
        title: 'تم حفظ الصنف وتحديث الباركود بنجاح!',
        timer: 1500,
        showConfirmButton: false
    });

    renderProductsDashboard();
}
</script>
@endsection
