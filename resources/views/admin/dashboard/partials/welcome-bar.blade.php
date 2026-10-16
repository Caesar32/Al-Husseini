<!-- ============================================================== -->
<!-- 1. Welcome & Fast Action Bar                                   -->
<!-- ============================================================== -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card welcome-banner-card border-0 shadow-sm overflow-hidden mb-0">
            <div class="card-body p-3 p-md-3">
                <div class="row align-items-center g-3">
                    <!-- Title & Subtitle Column -->
                    <div class="col-xxl-5 col-xl-5 col-lg-12">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-primary-subtle text-primary rounded-3 fs-20 shadow-xs">
                                    <i class="ri-car-fill"></i>
                                </span>
                            </div>
                            <div>
                                <h4 class="fs-15 fw-bold mb-1 ah-welcome-title">
                                    مركز الحسيني لبطاريات وزيوت وصيانة السيارات
                                </h4>
                                <p class="text-muted mb-0 fs-12">
                                    متابعة حية وشاملة لمبيعات المعرض، فواتير الورشة، حركة المخزون بالباركود، وتحصيلات الآجل.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Operations Bar -->
                    <div class="col-xxl-7 col-xl-7 col-lg-12">
                        <div class="d-flex flex-wrap gap-2 justify-content-xl-end align-items-center">
                            <!-- Quick Sales Period Selector in Page Header -->
                            <div class="d-none d-lg-flex align-items-center bg-body border rounded-pill px-2.5 py-1 shadow-xs ah-welcome-period">
                                <span class="fs-11 fw-bold text-muted d-inline-flex align-items-center gap-1 text-nowrap">
                                    <i class="ri-filter-3-line text-success"></i>
                                    <span>فترة المبيعات:</span>
                                </span>
                                <div class="d-flex align-items-center gap-1">
                                    @foreach($salesPeriods ?? [] as $key => $p)
                                        <button type="button" 
                                                class="btn btn-xs rounded-pill px-2.5 py-0.5 fw-bold welcome-period-btn {{ ($selectedPeriodKey ?? 'all') === $key ? 'btn-success text-white shadow-xs active' : 'btn-ghost-secondary text-muted' }}"
                                                data-action="dashboard.period" data-period="{{ $key }}">
                                            {{ $p['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            @can('pos.access')
                            <x-button href="{{ route('admin.sales.pos') }}" variant="primary" size="sm" icon="barcode-box-line" icon-class="align-middle me-1" class="quick-action-btn shadow-sm fs-12 px-3 py-2">نقطة البيع (POS)</x-button>
                            @endcan
                            @can('credit.view')
                            <x-button href="{{ route('admin.sales.credit') }}" variant="soft-warning" size="sm" icon="hand-coin-line" icon-class="align-middle me-1" class="quick-action-btn fs-12 px-3 py-2">تحصيل الآجل</x-button>
                            @endcan
                            @can('products.view')
                            <x-button href="{{ route('admin.sales.products') }}" variant="soft-info" size="sm" icon="box-3-line" icon-class="align-middle me-1" class="quick-action-btn fs-12 px-3 py-2">المخزون والباركود</x-button>
                            @endcan
                            @can('attendance.view')
                            <x-button href="{{ route('admin.hr.attendance') }}" variant="soft-success" size="sm" icon="user-follow-line" icon-class="align-middle me-1" class="quick-action-btn fs-12 px-3 py-2">حضور الورشة</x-button>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
