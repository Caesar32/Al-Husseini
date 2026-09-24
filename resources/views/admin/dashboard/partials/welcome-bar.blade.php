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
                                <h4 class="fs-15 fw-bold mb-1" style="letter-spacing: -0.2px;">
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
                            <a href="{{ route('admin.sales.pos') }}" class="btn btn-primary btn-sm quick-action-btn shadow-sm fs-12 px-3 py-2">
                                <i class="ri-barcode-box-line align-middle me-1"></i> نقطة البيع (POS)
                            </a>
                            <a href="{{ route('admin.sales.credit') }}" class="btn btn-soft-warning btn-sm quick-action-btn fs-12 px-3 py-2">
                                <i class="ri-hand-coin-line align-middle me-1"></i> تحصيل الآجل
                            </a>
                            <a href="{{ route('admin.sales.products') }}" class="btn btn-soft-info btn-sm quick-action-btn fs-12 px-3 py-2">
                                <i class="ri-box-3-line align-middle me-1"></i> المخزون والباركود
                            </a>
                            <a href="{{ route('admin.hr.attendance') }}" class="btn btn-soft-success btn-sm quick-action-btn fs-12 px-3 py-2">
                                <i class="ri-user-follow-line align-middle me-1"></i> حضور الورشة
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
