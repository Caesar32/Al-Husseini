<div class="card dashboard-card h-100 d-flex flex-column mb-0">
    <div class="card-header dashboard-header align-items-center d-flex justify-content-between bg-transparent flex-shrink-0">
        <h5 class="card-title mb-0 fw-bold fs-14 text-dark">
            <i class="ri-alarm-warning-line text-danger me-1"></i> تنبيهات نواقص المخزون (تحت حد الأمان)
        </h5>
        @can('products.view')
        <a href="{{ route('admin.sales.products') }}" class="btn btn-sm btn-soft-danger rounded-pill px-3 py-1 fs-12">
            إدارة المخزون <i class="ri-arrow-left-s-line align-middle"></i>
        </a>
        @endcan
    </div>

    <div class="card-body p-0 flex-grow-1 d-flex flex-column overflow-hidden">
        <div class="table-responsive custom-scroll-container flex-grow-1" style="max-height: 330px; overflow-y: auto;">
            <table class="table dash-table table-hover table-centered align-middle mb-0 fs-12">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width: 32%;">الصنف / الماركة</th>
                        <th style="width: 22%;">القسم</th>
                        <th class="text-center" style="width: 18%;">الباركود</th>
                        <th class="text-center" style="width: 14%;">الرصيد المتبقي</th>
                        <th class="text-center" style="width: 14%;">الحالة</th>
                    </tr>
                </thead>
                <tbody id="dashLowStockTableBody">
                    @forelse($lowStockProducts as $prod)
                        @php
                            $isDanger = $prod->current_stock <= 5;
                            $badgeClass = $isDanger ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle';
                            $badgeText = $isDanger ? 'حرج جداً' : 'أوشك على النفاد';
                        @endphp
                        <tr>
                            <td>
                                <strong class="text-dark d-block fs-12">{{ $prod->name }}</strong>
                                <span class="badge bg-light text-secondary border font-monospace fs-10" dir="ltr">{{ $prod->brand ?? '-' }}</span>
                            </td>
                            <td><span class="badge bg-light text-secondary rounded-pill px-2 py-1">{{ $prod->category->name ?? '-' }}</span></td>
                            <td class="text-center font-monospace fs-11 text-muted">{{ $prod->barcode ?? '-' }}</td>
                            <td class="text-center font-monospace fw-bold {{ $isDanger ? 'text-danger' : 'text-warning' }} fs-13">{{ $prod->current_stock }}</td>
                            <td class="text-center"><span class="badge {{ $badgeClass }} rounded-pill px-2.5 py-1 fs-10">{{ $badgeText }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="ri-checkbox-circle-fill text-success me-1"></i> جميع الأصناف متوفرة ومخزونها في المنطقة الآمنة
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
