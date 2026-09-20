@extends('admin.layouts.master')

@section('title', 'لوحة تحكم الحسيني | Al-Husseini')

@section('css')
    <!-- jsvectormap css -->
    <link href="{{ asset('assets/libs/jsvectormap/jsvectormap.min.css') }}" rel="stylesheet" type="text/css" />
    <!--Swiper slider css-->
    <link href="{{ asset('assets/libs/swiper/swiper-bundle.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'لوحات تحكم الحسيني', 'title' => 'لوحة القيادة والتحليلات - Al-Husseini'])

    <div class="row">
        <div class="col">
            <div class="h-100">
                <!-- Welcome & Date filter row -->
                <div class="row mb-3 pb-1">
                    <div class="col-12">
                        <div class="d-flex align-items-lg-center flex-lg-row flex-column">
                            <div class="flex-grow-1">
                                <h4 class="fs-18 fw-bold mb-1">مرحباً بك في لوحة تحكم مجموعة الحسيني (Al-Husseini) !</h4>
                                <p class="text-muted mb-0">إليك تقرير نشاط وإحصائيات نظام ومتجر الحسيني لليوم.</p>
                            </div>
                            <div class="mt-3 mt-lg-0">
                                <form action="javascript:void(0);">
                                    <div class="row g-3 mb-0 align-items-center">
                                        <div class="col-sm-auto">
                                            <div class="input-group">
                                                <input type="text" class="form-control border-0 minimal-border dash-filter-picker shadow" data-provider="flatpickr" data-range-date="true" data-date-format="d M, Y" placeholder="اختر الفترة الزمنية">
                                                <div class="input-group-text bg-primary border-primary text-white">
                                                    <i class="ri-calendar-2-line"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <a href="{{ route('admin.starter') }}" class="btn btn-soft-success material-shadow-none">
                                                <i class="ri-add-circle-line align-middle me-1"></i> إضافة منتج جديد
                                            </a>
                                        </div>
                                        <div class="col-auto">
                                            <button type="button" class="btn btn-soft-info btn-icon waves-effect material-shadow-none waves-light layout-rightside-btn">
                                                <i class="ri-pulse-line"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4 Stat Cards -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1 overflow-hidden">
                                        <p class="text-uppercase fw-medium text-muted text-truncate mb-0">إجمالي الأرباح</p>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <h5 class="text-success fs-14 mb-0">
                                            <i class="ri-arrow-right-up-line fs-13 align-middle"></i> +16.24 %
                                        </h5>
                                    </div>
                                </div>
                                <div class="d-flex align-items-end justify-content-between mt-4">
                                    <div>
                                        <h4 class="fs-22 fw-semibold ff-secondary mb-4">$<span class="counter-value" data-target="559.25">559.25</span>k </h4>
                                        <a href="javascript:void(0);" class="text-decoration-underline">عرض الأرباح الصافية</a>
                                    </div>
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-success-subtle rounded fs-3">
                                            <i class="bx bx-dollar-circle text-success"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1 overflow-hidden">
                                        <p class="text-uppercase fw-medium text-muted text-truncate mb-0">الطلبات الكلية</p>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <h5 class="text-danger fs-14 mb-0">
                                            <i class="ri-arrow-right-down-line fs-13 align-middle"></i> -3.57 %
                                        </h5>
                                    </div>
                                </div>
                                <div class="d-flex align-items-end justify-content-between mt-4">
                                    <div>
                                        <h4 class="fs-22 fw-semibold ff-secondary mb-4"><span class="counter-value" data-target="36894">36,894</span></h4>
                                        <a href="javascript:void(0);" class="text-decoration-underline">عرض جميع الطلبات</a>
                                    </div>
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-info-subtle rounded fs-3">
                                            <i class="bx bx-shopping-bag text-info"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1 overflow-hidden">
                                        <p class="text-uppercase fw-medium text-muted text-truncate mb-0">العملاء النشطون</p>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <h5 class="text-success fs-14 mb-0">
                                            <i class="ri-arrow-right-up-line fs-13 align-middle"></i> +29.08 %
                                        </h5>
                                    </div>
                                </div>
                                <div class="d-flex align-items-end justify-content-between mt-4">
                                    <div>
                                        <h4 class="fs-22 fw-semibold ff-secondary mb-4"><span class="counter-value" data-target="183.35">183.35</span>M </h4>
                                        <a href="javascript:void(0);" class="text-decoration-underline">عرض العملاء</a>
                                    </div>
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-warning-subtle rounded fs-3">
                                            <i class="bx bx-user-circle text-warning"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1 overflow-hidden">
                                        <p class="text-uppercase fw-medium text-muted text-truncate mb-0">الرصيد المتاح</p>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <h5 class="text-muted fs-14 mb-0">+0.00 %</h5>
                                    </div>
                                </div>
                                <div class="d-flex align-items-end justify-content-between mt-4">
                                    <div>
                                        <h4 class="fs-22 fw-semibold ff-secondary mb-4">$<span class="counter-value" data-target="165.89">165.89</span>k </h4>
                                        <a href="javascript:void(0);" class="text-decoration-underline">سحب الرصيد</a>
                                    </div>
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle rounded fs-3">
                                            <i class="bx bx-wallet text-primary"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section: Revenue Analytics & Sales by Location -->
                <div class="row">
                    <div class="col-xl-8">
                        <div class="card">
                            <div class="card-header border-0 align-items-center d-flex">
                                <h4 class="card-title mb-0 flex-grow-1">تحليل الإيرادات والنمو (Revenue)</h4>
                                <div>
                                    <button type="button" class="btn btn-soft-secondary material-shadow-none btn-sm">الكل</button>
                                    <button type="button" class="btn btn-soft-secondary material-shadow-none btn-sm">1 شهر</button>
                                    <button type="button" class="btn btn-soft-secondary material-shadow-none btn-sm">6 أشهر</button>
                                    <button type="button" class="btn btn-soft-primary material-shadow-none btn-sm">1 سنة</button>
                                </div>
                            </div>

                            <div class="card-header p-0 border-0 bg-light-subtle">
                                <div class="row g-0 text-center">
                                    <div class="col-6 col-sm-3">
                                        <div class="p-3 border border-dashed border-start-0">
                                            <h5 class="mb-1"><span class="counter-value" data-target="7585">7,585</span></h5>
                                            <p class="text-muted mb-0">إجمالي الطلبات</p>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <div class="p-3 border border-dashed border-start-0">
                                            <h5 class="mb-1">$<span class="counter-value" data-target="22.89">22.89</span>k</h5>
                                            <p class="text-muted mb-0">الأرباح المحققة</p>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <div class="p-3 border border-dashed border-start-0">
                                            <h5 class="mb-1"><span class="counter-value" data-target="367">367</span></h5>
                                            <p class="text-muted mb-0">المسترجعات</p>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <div class="p-3 border border-dashed border-start-0 border-end-0">
                                            <h5 class="mb-1 text-success"><span class="counter-value" data-target="18.92">18.92</span>%</h5>
                                            <p class="text-muted mb-0">معدل التحويل</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body p-0 pb-2">
                                <div class="w-100">
                                    <div id="customer_impression_charts" data-colors='["--vz-primary", "--vz-success", "--vz-danger"]' class="apex-charts" dir="ltr"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card card-height-100">
                            <div class="card-header align-items-center d-flex">
                                <h4 class="card-title mb-0 flex-grow-1">المبيعات حسب المواقع</h4>
                                <div class="flex-shrink-0">
                                    <button type="button" class="btn btn-soft-primary material-shadow-none btn-sm">تصدير التقرير</button>
                                </div>
                            </div>

                            <div class="card-body">
                                <div id="sales-by-locations" data-colors='["--vz-light", "--vz-success", "--vz-primary"]' style="height: 269px" dir="ltr"></div>

                                <div class="px-2 py-2 mt-1">
                                    <p class="mb-1">المملكة العربية السعودية <span class="float-end">75%</span></p>
                                    <div class="progress mt-2" style="height: 6px;">
                                        <div class="progress-bar progress-bar-striped bg-primary" role="progressbar" style="width: 75%"></div>
                                    </div>

                                    <p class="mt-3 mb-1">الإمارات العربية المتحدة <span class="float-end">47%</span></p>
                                    <div class="progress mt-2" style="height: 6px;">
                                        <div class="progress-bar progress-bar-striped bg-success" role="progressbar" style="width: 47%"></div>
                                    </div>

                                    <p class="mt-3 mb-1">جمهورية مصر العربية <span class="float-end">82%</span></p>
                                    <div class="progress mt-2" style="height: 6px;">
                                        <div class="progress-bar progress-bar-striped bg-warning" role="progressbar" style="width: 82%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Best Selling Products & Top Sellers -->
                <div class="row">
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header align-items-center d-flex">
                                <h4 class="card-title mb-0 flex-grow-1">المنتجات الأكثر مبيعاً</h4>
                                <div class="flex-shrink-0">
                                    <div class="dropdown card-header-dropdown">
                                        <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <span class="fw-semibold text-uppercase fs-12">ترتيب حسب: </span><span class="text-muted">اليوم <i class="mdi mdi-chevron-down ms-1"></i></span>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="#">اليوم</a>
                                            <a class="dropdown-item" href="#">أمس</a>
                                            <a class="dropdown-item" href="#">آخر 7 أيام</a>
                                            <a class="dropdown-item" href="#">آخر 30 يوماً</a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive table-card">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm bg-light rounded p-1 me-2">
                                                            <img src="{{ asset('assets/images/products/img-1.png') }}" alt="" class="img-fluid d-block" />
                                                        </div>
                                                        <div>
                                                            <h5 class="fs-14 my-1"><a href="javascript:void(0);" class="text-reset">قمصان رياضية ماركة</a></h5>
                                                            <span class="text-muted">24 إبريل 2024</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <h5 class="fs-14 my-1 fw-normal">$29.00</h5>
                                                    <span class="text-muted">السعر</span>
                                                </td>
                                                <td>
                                                    <h5 class="fs-14 my-1 fw-normal">62</h5>
                                                    <span class="text-muted">الطلبات</span>
                                                </td>
                                                <td>
                                                    <h5 class="fs-14 my-1 fw-normal">510</h5>
                                                    <span class="text-muted">المخزون</span>
                                                </td>
                                                <td>
                                                    <h5 class="fs-14 my-1 fw-normal">$1,798</h5>
                                                    <span class="text-muted">المجموع</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm bg-light rounded p-1 me-2">
                                                            <img src="{{ asset('assets/images/products/img-2.png') }}" alt="" class="img-fluid d-block" />
                                                        </div>
                                                        <div>
                                                            <h5 class="fs-14 my-1"><a href="javascript:void(0);" class="text-reset">كرسي مريح فاخر</a></h5>
                                                            <span class="text-muted">19 مارس 2024</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <h5 class="fs-14 my-1 fw-normal">$85.20</h5>
                                                    <span class="text-muted">السعر</span>
                                                </td>
                                                <td>
                                                    <h5 class="fs-14 my-1 fw-normal">35</h5>
                                                    <span class="text-muted">الطلبات</span>
                                                </td>
                                                <td>
                                                    <h5 class="fs-14 my-1 fw-normal"><span class="badge bg-danger-subtle text-danger">نفد المخزون</span></h5>
                                                    <span class="text-muted">المخزون</span>
                                                </td>
                                                <td>
                                                    <h5 class="fs-14 my-1 fw-normal">$2,982</h5>
                                                    <span class="text-muted">المجموع</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm bg-light rounded p-1 me-2">
                                                            <img src="{{ asset('assets/images/products/img-5.png') }}" alt="" class="img-fluid d-block" />
                                                        </div>
                                                        <div>
                                                            <h5 class="fs-14 my-1"><a href="javascript:void(0);" class="text-reset">خوذة دراجات متطورة</a></h5>
                                                            <span class="text-muted">17 يناير 2024</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <h5 class="fs-14 my-1 fw-normal">$54.00</h5>
                                                    <span class="text-muted">السعر</span>
                                                </td>
                                                <td>
                                                    <h5 class="fs-14 my-1 fw-normal">74</h5>
                                                    <span class="text-muted">الطلبات</span>
                                                </td>
                                                <td>
                                                    <h5 class="fs-14 my-1 fw-normal">805</h5>
                                                    <span class="text-muted">المخزون</span>
                                                </td>
                                                <td>
                                                    <h5 class="fs-14 my-1 fw-normal">$3,996</h5>
                                                    <span class="text-muted">المجموع</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6">
                        <div class="card card-height-100">
                            <div class="card-header align-items-center d-flex">
                                <h4 class="card-title mb-0 flex-grow-1">أفضل المتاجر والبائعين (Top Sellers)</h4>
                                <div class="flex-shrink-0">
                                    <button type="button" class="btn btn-soft-info btn-sm">تقرير مفصل</button>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive table-card">
                                    <table class="table table-centered table-hover align-middle table-nowrap mb-0">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-2">
                                                            <img src="{{ asset('assets/images/companies/img-1.png') }}" alt="" class="avatar-sm p-2" />
                                                        </div>
                                                        <div>
                                                            <h5 class="fs-14 my-1 fw-medium"><a href="javascript:void(0);" class="text-reset">شركة النجوم للتجارة</a></h5>
                                                            <span class="text-muted">خالد العتيبي</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span class="text-muted">حقائب وإكسسوارات</span></td>
                                                <td><p class="mb-0">8,547</p><span class="text-muted">المخزون</span></td>
                                                <td><span class="text-muted">$54,120</span></td>
                                                <td><h5 class="fs-14 mb-0 text-success">32% <i class="ri-bar-chart-fill fs-16 align-middle ms-1"></i></h5></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-2">
                                                            <img src="{{ asset('assets/images/companies/img-2.png') }}" alt="" class="avatar-sm p-2" />
                                                        </div>
                                                        <div>
                                                            <h5 class="fs-14 my-1 fw-medium"><a href="javascript:void(0);" class="text-reset">مؤسسة ديجيتك إلكترونكس</a></h5>
                                                            <span class="text-muted">ياسر رضوان</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span class="text-muted">ساعات وأجهزة ذكية</span></td>
                                                <td><p class="mb-0">895</p><span class="text-muted">المخزون</span></td>
                                                <td><span class="text-muted">$75,030</span></td>
                                                <td><h5 class="fs-14 mb-0 text-success">79% <i class="ri-bar-chart-fill fs-16 align-middle ms-1"></i></h5></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-2">
                                                            <img src="{{ asset('assets/images/companies/img-3.png') }}" alt="" class="avatar-sm p-2" />
                                                        </div>
                                                        <div>
                                                            <h5 class="fs-14 my-1 fw-medium"><a href="javascript:void(0);" class="text-reset">تقنيات نيستا الذكية</a></h5>
                                                            <span class="text-muted">سامي كمال</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span class="text-muted">قطع ومستلزمات</span></td>
                                                <td><p class="mb-0">3,470</p><span class="text-muted">المخزون</span></td>
                                                <td><span class="text-muted">$45,600</span></td>
                                                <td><h5 class="fs-14 mb-0 text-success">90% <i class="ri-bar-chart-fill fs-16 align-middle ms-1"></i></h5></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Store Visits by Source & Recent Orders Table -->
                <div class="row">
                    <div class="col-xl-4">
                        <div class="card card-height-100">
                            <div class="card-header align-items-center d-flex">
                                <h4 class="card-title mb-0 flex-grow-1">مصادر زيارات المتجر (Traffic Sources)</h4>
                                <div class="flex-shrink-0">
                                    <button type="button" class="btn btn-soft-secondary btn-sm">تقرير</button>
                                </div>
                            </div>

                            <div class="card-body">
                                <div id="store-visits-source" data-colors='["--vz-primary", "--vz-success", "--vz-warning", "--vz-danger", "--vz-info"]' class="apex-charts" dir="ltr"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-8">
                        <div class="card">
                            <div class="card-header align-items-center d-flex">
                                <h4 class="card-title mb-0 flex-grow-1">أحدث الطلبات (Recent Orders)</h4>
                                <div class="flex-shrink-0">
                                    <button type="button" class="btn btn-soft-info btn-sm material-shadow-none">
                                        <i class="ri-file-list-3-line align-middle me-1"></i> طباعة كشف الطلبات
                                    </button>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive table-card">
                                    <table class="table table-borderless table-centered align-middle table-nowrap mb-0">
                                        <thead class="text-muted table-light">
                                            <tr>
                                                <th scope="col">رقم الطلب</th>
                                                <th scope="col">العميل</th>
                                                <th scope="col">المنتج</th>
                                                <th scope="col">المبلغ</th>
                                                <th scope="col">طريقة الدفع</th>
                                                <th scope="col">الحالة</th>
                                                <th scope="col">التقييم</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><a href="javascript:void(0);" class="fw-medium link-primary">#VZ2112</a></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-2">
                                                            <img src="{{ asset('assets/images/users/avatar-1.jpg') }}" alt="" class="avatar-xs rounded-circle material-shadow" />
                                                        </div>
                                                        <div class="flex-grow-1">أحمد الحسيني</div>
                                                    </div>
                                                </td>
                                                <td>ملابس رياضية</td>
                                                <td><span class="text-success">$109.00</span></td>
                                                <td>بطاقة ائتمانية</td>
                                                <td><span class="badge bg-success-subtle text-success">مدفوع</span></td>
                                                <td><h5 class="fs-14 fw-medium mb-0">5.0 <i class="ri-star-fill text-warning fs-12"></i></h5></td>
                                            </tr>
                                            <tr>
                                                <td><a href="javascript:void(0);" class="fw-medium link-primary">#VZ2111</a></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-2">
                                                            <img src="{{ asset('assets/images/users/avatar-2.jpg') }}" alt="" class="avatar-xs rounded-circle material-shadow" />
                                                        </div>
                                                        <div class="flex-grow-1">محمد عبد الله</div>
                                                    </div>
                                                </td>
                                                <td>أجهزة إلكترونية</td>
                                                <td><span class="text-success">$249.00</span></td>
                                                <td>تحويل بنكي</td>
                                                <td><span class="badge bg-warning-subtle text-warning">قيد الانتظار</span></td>
                                                <td><h5 class="fs-14 fw-medium mb-0">4.5 <i class="ri-star-fill text-warning fs-12"></i></h5></td>
                                            </tr>
                                            <tr>
                                                <td><a href="javascript:void(0);" class="fw-medium link-primary">#VZ2109</a></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-2">
                                                            <img src="{{ asset('assets/images/users/avatar-3.jpg') }}" alt="" class="avatar-xs rounded-circle material-shadow" />
                                                        </div>
                                                        <div class="flex-grow-1">سارة إبراهيم</div>
                                                    </div>
                                                </td>
                                                <td>مستلزمات منزلية</td>
                                                <td><span class="text-success">$78.50</span></td>
                                                <td>عند الاستلام</td>
                                                <td><span class="badge bg-success-subtle text-success">مدفوع</span></td>
                                                <td><h5 class="fs-14 fw-medium mb-0">4.8 <i class="ri-star-fill text-warning fs-12"></i></h5></td>
                                            </tr>
                                            <tr>
                                                <td><a href="javascript:void(0);" class="fw-medium link-primary">#VZ2108</a></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-2">
                                                            <img src="{{ asset('assets/images/users/avatar-4.jpg') }}" alt="" class="avatar-xs rounded-circle material-shadow" />
                                                        </div>
                                                        <div class="flex-grow-1">خالد المنصور</div>
                                                    </div>
                                                </td>
                                                <td>ساعات ذكية</td>
                                                <td><span class="text-success">$199.00</span></td>
                                                <td>بطاقة مدى</td>
                                                <td><span class="badge bg-danger-subtle text-danger">ملغي</span></td>
                                                <td><h5 class="fs-14 fw-medium mb-0">3.5 <i class="ri-star-fill text-warning fs-12"></i></h5></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Rightside Activity and Reviews Drawer -->
        <div class="col-auto layout-rightside-col">
            <div class="overlay"></div>
            <div class="layout-rightside">
                <div class="card h-100 rounded-0">
                    <div class="card-body p-0">
                        <div class="p-3">
                            <h6 class="text-muted mb-0 text-uppercase fw-semibold">النشاطات الحديثة</h6>
                        </div>
                        <div data-simplebar style="max-height: 410px;" class="p-3 pt-0">
                            <div class="acitivity-timeline acitivity-main">
                                <div class="acitivity-item d-flex">
                                    <div class="flex-shrink-0 avatar-xs acitivity-avatar">
                                        <div class="avatar-title bg-success-subtle text-success rounded-circle material-shadow">
                                            <i class="ri-shopping-cart-2-line"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1 lh-base">عملية شراء بواسطة أحمد الشريف</h6>
                                        <p class="text-muted mb-1">تم شراء ساعة ذكية مع التوصيل السريع</p>
                                        <small class="mb-0 text-muted">02:14 م اليوم</small>
                                    </div>
                                </div>
                                <div class="acitivity-item py-3 d-flex">
                                    <div class="flex-shrink-0 avatar-xs acitivity-avatar">
                                        <div class="avatar-title bg-danger-subtle text-danger rounded-circle material-shadow">
                                            <i class="ri-stack-fill"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1 lh-base">تمت إضافة <span class="fw-semibold">مجموعة منتجات جديدة</span></h6>
                                        <p class="text-muted mb-1">بواسطة إدارة المنتجات</p>
                                        <small class="mb-0 text-muted">أمس 09:47 م</small>
                                    </div>
                                </div>
                                <div class="acitivity-item py-3 d-flex">
                                    <div class="flex-shrink-0">
                                        <img src="{{ asset('assets/images/users/avatar-2.jpg') }}" alt="" class="avatar-xs rounded-circle acitivity-avatar material-shadow">
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1 lh-base">سارة محمد أضافت تقييماً إيجابياً 5 نجوم</h6>
                                        <p class="text-muted mb-1">المنتج ممتاز وسرعة التوصيل خيالية</p>
                                        <small class="mb-0 text-muted">25 ديسمبر</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 mt-2">
                            <h6 class="text-muted mb-3 text-uppercase fw-semibold">أعلى التصنيفات مبيعاً</h6>
                            <ol class="ps-3 text-muted">
                                <li class="py-1"><a href="javascript:void(0);" class="text-muted">الهواتف والإكسسوارات <span class="float-end">(10,294)</span></a></li>
                                <li class="py-1"><a href="javascript:void(0);" class="text-muted">أجهزة الكمبيوتر واللابتوب <span class="float-end">(6,256)</span></a></li>
                                <li class="py-1"><a href="javascript:void(0);" class="text-muted">الأجهزة الإلكترونية المنزلية <span class="float-end">(3,479)</span></a></li>
                                <li class="py-1"><a href="javascript:void(0);" class="text-muted">الأزياء والملابس الفاخرة <span class="float-end">(1,582)</span></a></li>
                            </ol>
                        </div>

                        <div class="p-3">
                            <h6 class="text-muted mb-3 text-uppercase fw-semibold">آراء وتقييمات العملاء</h6>
                            <!-- Vertical Swiper -->
                            <div class="swiper vertical-swiper" style="height: 250px;">
                                <div class="swiper-wrapper">
                                    <div class="swiper-slide">
                                        <div class="card border border-dashed shadow-none">
                                            <div class="card-body">
                                                <div class="d-flex">
                                                    <div class="flex-shrink-0 avatar-sm">
                                                        <div class="avatar-title bg-light rounded material-shadow">
                                                            <img src="{{ asset('assets/images/companies/img-1.png') }}" alt="" height="30">
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <p class="text-muted mb-1 fst-italic text-truncate-two-lines"> "منتج رائع وجودة تصنيع عالية جداً وتغليف ممتاز."</p>
                                                        <div class="fs-11 align-middle text-warning">
                                                            <i class="ri-star-fill"></i>
                                                            <i class="ri-star-fill"></i>
                                                            <i class="ri-star-fill"></i>
                                                            <i class="ri-star-fill"></i>
                                                            <i class="ri-star-fill"></i>
                                                        </div>
                                                        <div class="text-end mb-0 text-muted">
                                                            - بواسطة <cite title="Source Title">شركة الأفق</cite>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="swiper-slide">
                                        <div class="card border border-dashed shadow-none">
                                            <div class="card-body">
                                                <div class="d-flex">
                                                    <div class="flex-shrink-0 avatar-sm">
                                                        <div class="avatar-title bg-light rounded material-shadow">
                                                            <img src="{{ asset('assets/images/companies/img-2.png') }}" alt="" height="30">
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <p class="text-muted mb-1 fst-italic text-truncate-two-lines"> "خدمة العملاء احترافية وسريعة الاستجابة."</p>
                                                        <div class="fs-11 align-middle text-warning">
                                                            <i class="ri-star-fill"></i>
                                                            <i class="ri-star-fill"></i>
                                                            <i class="ri-star-fill"></i>
                                                            <i class="ri-star-fill"></i>
                                                            <i class="ri-star-half-fill"></i>
                                                        </div>
                                                        <div class="text-end mb-0 text-muted">
                                                            - بواسطة <cite title="Source Title">مؤسسة الريادة</cite>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <!-- apexcharts -->
    <script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>

    <!-- Vector map-->
    <script src="{{ asset('assets/libs/jsvectormap/jsvectormap.min.js') }}"></script>
    <script src="{{ asset('assets/libs/jsvectormap/maps/world-merc.js') }}"></script>

    <!--Swiper slider js-->
    <script src="{{ asset('assets/libs/swiper/swiper-bundle.min.js') }}"></script>

    <!-- Dashboard init -->
    <script src="{{ asset('assets/js/pages/dashboard-ecommerce.init.js') }}"></script>
@endsection
