<div class="card dashboard-card h-100 mb-0">
    <div class="card-header dashboard-header align-items-center d-flex justify-content-between bg-transparent">
        <div>
            <h5 class="card-title mb-0 fw-bold fs-14 text-dark">
                <i class="ri-hand-coin-line text-warning me-1"></i> كشف متابعة مبالغ الآجل
            </h5>
            <small class="text-muted fs-11">عملاء عليهم مستحقات مالية واجبة السداد</small>
        </div>
        @can('credit.view')
        <a href="{{ route('admin.sales.credit') }}" class="btn btn-sm btn-soft-warning rounded-pill px-3 py-1 fs-12">
            سجل الآجل <i class="ri-arrow-left-s-line align-middle"></i>
        </a>
        @endcan
    </div>

    <div class="card-body p-3">
        <div class="d-flex flex-column gap-2" id="dashCreditListContainer">
            @forelse($debtors as $debtor)
                @php
                    $veh = $debtor->vehicles->first();
                @endphp
                <div class="p-2.5 bg-body-tertiary rounded-3 border d-flex justify-content-between align-items-center dash-mini-tile">
                    <div>
                        <strong class="fs-12 text-dark d-block">{{ $debtor->name }}</strong>
                        <small class="text-muted fs-11">
                            <i class="ri-car-line me-1 text-primary"></i>{{ $veh ? ($veh->car_brand . ' ' . $veh->car_model) : 'سيارة مسجلة' }} | لوحة: {{ $veh->plate_number ?? '-' }}
                        </small>
                    </div>
                    <div class="text-end">
                        <strong class="text-danger font-monospace fs-13 d-block">{{ number_format($debtor->current_credit_balance, 2) }} ج.م</strong>
                        @can('credit.view')
                        <a href="{{ route('admin.credit.statement', $debtor->id) }}" class="btn btn-sm btn-soft-warning rounded-pill py-0.5 px-2.5 fs-11 fw-bold shadow-none">
                            تحصيل <i class="ri-arrow-left-s-line"></i>
                        </a>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-muted fs-12">
                    <i class="ri-checkbox-circle-line fs-24 text-success d-block mb-1"></i>
                    لا توجد أي مبالغ متأخرة بالآجل حالياً.. جميع الحسابات مسددة!
                </div>
            @endforelse
        </div>
    </div>
</div>
