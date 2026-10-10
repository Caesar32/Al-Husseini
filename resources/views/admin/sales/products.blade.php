@extends('admin.layouts.master')

@section('title', 'المنتجات والزيوت وخدمات الصيانة | مركز الحسيني لبطاريات وزيوت السيارات')

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'المخزون والورشة', 'title' => 'كتالوج المنتجات والزيوت والشحوم وخدمات الصيانة'])

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Top Stats (live from the products table) -->
    <div class="row mb-3">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-animate border-start border-success border-4 shadow-sm h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">بطاريات السيارات بالمخزن</p>
                            <h3 class="fs-22 fw-extrabold text-success mb-1 font-monospace">{{ number_format($stats['batteries_stock']) }} بطارية</h3>
                            <small class="text-muted fs-11">{{ number_format($stats['batteries_count']) }} موديل مفعّل</small>
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
                            <p class="text-uppercase fw-bold text-muted fs-12 mb-1">زيوت المحركات والفلاتر</p>
                            <h3 class="fs-22 fw-extrabold text-warning mb-1 font-monospace">{{ number_format($stats['oils_stock']) }} عبوة</h3>
                            <small class="text-muted fs-11">رصيد الأصناف المفعّلة</small>
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
                            <h3 class="fs-22 fw-extrabold text-info mb-1 font-monospace">{{ number_format($stats['greases_stock']) }} قطعة</h3>
                            <small class="text-muted fs-11">رصيد الأصناف المفعّلة</small>
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
                            <h3 class="fs-22 fw-extrabold text-primary mb-1 font-monospace">{{ number_format($stats['services_count']) }} خدمة</h3>
                            <small class="text-muted fs-11">أصناف تحت الحد الأدنى: {{ number_format($stats['low_stock_count']) }}</small>
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
                    <form method="GET" action="{{ route('admin.sales.products') }}" class="row g-2 align-items-center justify-content-between">
                        <div class="col-lg-6 col-12">
                            <div class="d-flex flex-wrap gap-1">
                                @php($activeCategory = $filters['category'] ?? '')
                                <a href="{{ route('admin.sales.products', array_filter(['search' => $filters['search'] ?? null])) }}"
                                   class="btn btn-sm {{ $activeCategory === '' ? 'btn-primary fw-bold px-3' : 'btn-outline-secondary' }}">الكل</a>
                                @foreach($categories as $category)
                                    <a href="{{ route('admin.sales.products', array_filter(['category' => $category->slug, 'search' => $filters['search'] ?? null])) }}"
                                       class="btn btn-sm {{ $activeCategory === $category->slug ? 'btn-primary fw-bold px-3' : 'btn-outline-secondary' }}">{{ $category->name }}</a>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-lg-6 col-12 text-lg-end">
                            <div class="d-flex gap-2 justify-content-lg-end">
                                @if($activeCategory !== '')
                                    <input type="hidden" name="category" value="{{ $activeCategory }}">
                                @endif
                                <div class="input-group input-group-sm" style="max-width: 270px;">
                                    <span class="input-group-text bg-light text-primary"><i class="ri-barcode-box-line me-1"></i> <i class="ri-search-line"></i></span>
                                    <input type="text" class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث بالاسم، الماركة، الكود أو الباركود...">
                                    <button type="submit" class="btn btn-light border">بحث</button>
                                </div>
                                @can('products.create')
                                    <button type="button" class="btn btn-sm btn-primary fw-bold" onclick="openProductModal(null)">
                                        <i class="ri-add-line me-1"></i> إضافة صنف / خدمة جديدة
                                    </button>
                                @endcan
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-nowrap mb-0" id="productsTable">
                            <thead class="table-light">
                                <tr class="text-muted fs-12 text-uppercase">
                                    <th>البيان والاسم التجاري</th>
                                    <th>الكود / الباركود</th>
                                    <th>القسم</th>
                                    <th>المواصفة</th>
                                    <th>سعر البيع (ج.م)</th>
                                    <th>الضمان</th>
                                    <th>الرصيد بالمخزن</th>
                                    <th>الحالة</th>
                                    <th class="text-center">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $product)
                                    @php($isService = $product->category?->slug === 'services')
                                    <tr>
                                        <td>
                                            <strong class="text-dark fs-13 d-block">{{ $product->name }}</strong>
                                            <span class="badge bg-light text-secondary border font-monospace fs-11">{{ $product->brand }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace fs-11 d-block mb-1">{{ $product->sku }}</span>
                                            <span class="badge bg-light text-dark border font-monospace fs-11">
                                                <i class="ri-barcode-line text-primary me-1"></i>{{ $product->barcode ?: 'بدون باركود' }}
                                            </span>
                                        </td>
                                        <td><span class="badge bg-primary-subtle text-primary fs-11">{{ $product->category?->name ?? '-' }}</span></td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace fs-11">
                                                {{ $product->is_battery ? trim(($product->capacity_ah ?? '') . ' ' . ($product->voltage ?? '')) ?: '-' : ($product->capacity_ah ?: '-') }}
                                            </span>
                                        </td>
                                        <td><strong class="text-success font-monospace fs-13">{{ number_format((float) $product->retail_price, 2) }}</strong></td>
                                        <td>
                                            @if($product->warranty_months > 0)
                                                <span class="badge bg-info-subtle text-info fs-11">{{ $product->warranty_months }} شهر</span>
                                            @else
                                                <span class="text-muted fs-11">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($isService)
                                                <span class="badge bg-light text-muted border fs-11">خدمة ورشة</span>
                                            @else
                                                <span class="badge {{ $product->current_stock > $product->reorder_threshold ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }} font-monospace fs-12">{{ number_format($product->current_stock) }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($product->is_active)
                                                <span class="badge bg-success-subtle text-success fs-11">مفعّل</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary fs-11">موقوف</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-1">
                                                @can('products.edit')
                                                    <button type="button" class="btn btn-sm btn-soft-secondary js-edit-product"
                                                            data-product="{{ json_encode($product->only(['id', 'category_id', 'sku', 'barcode', 'name', 'brand', 'capacity_ah', 'voltage', 'terminal_type', 'warranty_months', 'retail_price', 'wholesale_price', 'reorder_threshold', 'is_battery', 'is_active'])) }}">
                                                        <i class="ri-edit-line"></i> تعديل
                                                    </button>
                                                @endcan
                                                @can('products.delete')
                                                    <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('حذف هذا الصنف نهائياً من الكتالوج؟');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-soft-danger"><i class="ri-delete-bin-line"></i></button>
                                                    </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="text-center py-5 text-muted fs-13">لا توجد أصناف أو خدمات مطابقة للبحث.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($products->hasPages())
                    <div class="card-footer bg-transparent border-top py-2 d-flex justify-content-center">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @canany(['products.create', 'products.edit'])
    <!-- Modal: Add / Edit Product or Service -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white p-3">
                    <h5 class="modal-title fw-bold text-white fs-15" id="productModalTitle">إضافة صنف أو خدمة جديدة</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="productForm" novalidate>
                    <input type="hidden" id="prodFormId">
                    <div class="modal-body p-4">
                        <div class="alert alert-danger d-none" id="productFormErrors"></div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-13">القسم <span class="text-danger">*</span></label>
                                <select class="form-select fw-bold" name="category_id" required>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" data-slug="{{ $category->slug }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-13">الماركة / الشركة <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="brand" required maxlength="100">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-13">كود الصنف (SKU) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control font-monospace" name="sku" required maxlength="50">
                            </div>

                            <div class="col-md-8">
                                <label class="form-label fw-bold text-dark fs-13">الاسم التجاري الكامل <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required maxlength="200">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-13">الباركود</label>
                                <input type="text" class="form-control font-monospace" name="barcode" maxlength="100">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark fs-13">بطارية؟</label>
                                <select class="form-select" name="is_battery">
                                    <option value="1">نعم (يتطلب سيريال وضمان)</option>
                                    <option value="0">لا</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark fs-13">السعة / المواصفة</label>
                                <input type="text" class="form-control font-monospace" name="capacity_ah" maxlength="20" placeholder="70Ah أو 5W-40">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark fs-13">الجهد</label>
                                <input type="text" class="form-control font-monospace" name="voltage" maxlength="20" placeholder="12V">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark fs-13">نوع الأقطاب</label>
                                <select class="form-select" name="terminal_type">
                                    <option value="regular">عادي</option>
                                    <option value="reverse">معكوس</option>
                                    <option value="side">جانبي</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark fs-13">مدة الضمان (شهور) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control font-monospace" name="warranty_months" required min="0" max="120" value="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark fs-13">سعر البيع (ج.م) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control font-monospace fw-bold" name="retail_price" required min="0" step="0.01">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark fs-13">سعر الجملة (ج.م)</label>
                                <input type="number" class="form-control font-monospace" name="wholesale_price" min="0" step="0.01">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark fs-13">حد إعادة الطلب</label>
                                <input type="number" class="form-control font-monospace" name="reorder_threshold" min="0" value="5">
                            </div>

                            <div class="col-md-4" id="prodCostGroup">
                                <label class="form-label fw-bold text-dark fs-13">التكلفة المبدئية (ج.م)</label>
                                <input type="number" class="form-control font-monospace" name="cost_price" min="0" step="0.01" value="0">
                                <small class="text-muted fs-11">تُحدَّث تلقائياً بالمتوسط المرجح من فواتير الشراء.</small>
                            </div>
                            <div class="col-md-4 d-none" id="prodActiveGroup">
                                <label class="form-label fw-bold text-dark fs-13">الحالة</label>
                                <select class="form-select" name="is_active">
                                    <option value="1">مفعّل</option>
                                    <option value="0">موقوف</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <small class="text-muted fs-11"><i class="ri-information-line"></i> رصيد المخزون لا يُعدَّل من هنا؛ يتغير فقط عبر فواتير الشراء والبيع والمرتجعات.</small>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light p-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4" id="productFormSubmit">
                            <i class="ri-save-line me-1"></i> حفظ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endcanany
@endsection

@section('script')
<script>
'use strict';

(function () {
    const form = document.getElementById('productForm');
    if (!form) return;

    const storeUrl = @json(route('admin.products.store'));
    const updateUrlTemplate = @json(route('admin.products.update', ['product' => '__ID__']));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const modalEl = document.getElementById('productModal');
    const errorsEl = document.getElementById('productFormErrors');

    window.openProductModal = function (product) {
        form.reset();
        errorsEl.classList.add('d-none');
        errorsEl.textContent = '';
        document.getElementById('prodFormId').value = product ? product.id : '';
        document.getElementById('productModalTitle').textContent = product ? 'تعديل بيانات الصنف / الخدمة' : 'إضافة صنف أو خدمة جديدة';
        document.getElementById('prodCostGroup').classList.toggle('d-none', !!product);
        document.getElementById('prodActiveGroup').classList.toggle('d-none', !product);

        if (product) {
            Object.keys(product).forEach(function (key) {
                const field = form.elements[key];
                if (!field) return;
                let value = product[key];
                if (typeof value === 'boolean') value = value ? '1' : '0';
                field.value = value === null || value === undefined ? '' : value;
            });
        }

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };

    document.querySelectorAll('.js-edit-product').forEach(function (btn) {
        btn.addEventListener('click', function () {
            window.openProductModal(JSON.parse(btn.dataset.product));
        });
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const id = document.getElementById('prodFormId').value;
        const payload = {};
        new FormData(form).forEach(function (value, key) {
            payload[key] = value === '' ? null : value;
        });
        if (id) {
            delete payload.cost_price;
        } else {
            delete payload.is_active;
        }

        const submitBtn = document.getElementById('productFormSubmit');
        submitBtn.disabled = true;
        try {
            const res = await fetch(id ? updateUrlTemplate.replace('__ID__', encodeURIComponent(id)) : storeUrl, {
                method: id ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(function () { return {}; });

            if (!res.ok) {
                const messages = data.errors ? Object.values(data.errors).flat() : [data.message || 'تعذر حفظ الصنف.'];
                errorsEl.textContent = messages.join(' | ');
                errorsEl.classList.remove('d-none');
                return;
            }

            window.location.reload();
        } catch (err) {
            errorsEl.textContent = 'تعذر الاتصال بالخادم. حاول مرة أخرى.';
            errorsEl.classList.remove('d-none');
        } finally {
            submitBtn.disabled = false;
        }
    });
})();
</script>
@endsection
