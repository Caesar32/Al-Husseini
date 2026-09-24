<div class="card dashboard-card h-100 mb-0">
    <div class="card-header dashboard-header align-items-center d-flex justify-content-between bg-transparent">
        <h5 class="card-title mb-0 fw-bold fs-14">
            <i class="ri-file-list-3-line text-primary me-1"></i> أحدث فواتير المبيعات وصيانة السيارات بالمركز
        </h5>
        <div class="d-flex gap-1">
            <a href="{{ route('admin.sales.invoices') }}" class="btn btn-sm btn-soft-primary rounded-pill px-3 py-1 fs-12">
                عرض جميع الفواتير <i class="ri-arrow-left-s-line align-middle"></i>
            </a>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive custom-scroll-container">
            <table class="table dash-table table-hover table-centered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="text-center" style="width: 15%; min-width: 110px;">رقم الفاتورة</th>
                        <th scope="col" style="width: 28%; min-width: 170px;">العميل والسيارة</th>
                        <th scope="col" style="width: 27%; min-width: 170px;">الأصناف المشتراة</th>
                        <th scope="col" class="text-end" style="width: 14%; min-width: 100px;">القيمة الإجمالية</th>
                        <th scope="col" class="text-center" style="width: 10%; min-width: 85px;">طريقة الدفع</th>
                        <th scope="col" class="text-center" style="width: 6%; min-width: 65px;">معاينة</th>
                    </tr>
                </thead>
                <tbody id="dashRecentInvoicesBody" class="fs-12">
                    @forelse($recentInvoices as $inv)
                        @php
                            $firstItem = $inv->items->first()?->product?->name ?? 'صنف مبيعات';
                            $extraCount = $inv->items->count() > 1 ? ' (+' . ($inv->items->count() - 1) . ' أصناف)' : '';
                        @endphp
                        <tr>
                            <td class="text-center">
                                <a href="{{ route('admin.invoices.show', $inv->id) }}" class="fw-bold font-monospace link-primary fs-12 d-block">
                                    #{{ $inv->invoice_number }}
                                </a>
                                <small class="text-muted fs-10 font-monospace">{{ $inv->created_at->format('Y-m-d') }}</small>
                            </td>
                            <td>
                                <div class="fw-bold text-dark text-truncate" style="max-width: 210px;" title="{{ $inv->customer->name ?? 'عميل نقدي' }}">
                                    {{ $inv->customer->name ?? 'عميل نقدي' }}
                                </div>
                                <small class="text-muted d-block text-truncate fs-11" style="max-width: 210px;">
                                    <i class="ri-car-line text-primary me-1"></i>{{ $inv->customerVehicle->car_brand ?? '' }} {{ $inv->customerVehicle->car_model ?? '' }}
                                    <span class="badge bg-light text-secondary border font-monospace fs-10 ms-1">{{ $inv->customerVehicle->plate_number ?? 'بدون لوحة' }}</span>
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fw-medium text-truncate d-inline-block text-start p-1.5" style="max-width: 210px;" title="{{ $firstItem }}">
                                    <i class="ri-box-3-line text-muted me-1"></i>{{ $firstItem }}{{ $extraCount }}
                                </span>
                            </td>
                            <td class="text-end">
                                <strong class="font-monospace text-success fs-13 d-block">{{ number_format($inv->final_amount, 2) }} ج.م</strong>
                            </td>
                            <td class="text-center">
                                @if($inv->payment_method === 'cash')
                                    <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1">نقدي كاش</span>
                                @elseif($inv->payment_method === 'card')
                                    <span class="badge bg-info-subtle text-info rounded-pill px-2 py-1">فيزا بنكية</span>
                                @elseif($inv->payment_method === 'bank_transfer')
                                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1">تحويل بنكي</span>
                                @elseif($inv->payment_method === 'credit')
                                    <span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-1 fw-bold">الآجل ⏱️</span>
                                @elseif($inv->payment_method === 'split')
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1">دفع مجزأ</span>
                                @else
                                    <span class="badge bg-light text-dark rounded-pill px-2 py-1">{{ $inv->payment_method }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.invoices.show', $inv->id) }}" class="btn btn-sm btn-soft-primary rounded-pill px-2.5 py-1 fs-11 shadow-none" title="معاينة وطباعة">
                                    <i class="ri-eye-line me-0.5"></i> عرض
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="ri-file-list-line fs-20 d-block mb-1"></i>
                                لا توجد فواتير مسجلة حتى الآن
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
