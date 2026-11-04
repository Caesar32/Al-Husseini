@extends('admin.layouts.master')

@section('title', 'تفاصيل فاتورة المشتريات - ' . $purchase->invoice_number)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">تفاصيل فاتورة المشتريات والتوريد</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.purchases.index') }}">المشتريات</a></li>
                        <li class="breadcrumb-item active">{{ $purchase->invoice_number }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Header Actions -->
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row mb-3">
        <div class="col-12 text-end">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#purchaseLabelsModal">
                <i class="ri-barcode-line align-bottom me-1"></i> طباعة باركود الشحنة / التوريدة
            </button>
            <a href="{{ route('admin.purchases.print', $purchase) }}" class="btn btn-primary ms-2">
                <i class="ri-printer-line align-bottom me-1"></i> طباعة إذن الاستلام
            </a>
            <a href="{{ route('admin.suppliers.ledger', $purchase->supplier) }}" class="btn btn-soft-secondary ms-2">
                <i class="ri-file-list-3-line align-bottom me-1"></i> كشف حساب المورد
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header border-bottom-dashed d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">فاتورة توريد رقم: <span class="text-primary">{{ $purchase->invoice_number }}</span></h5>
            <span class="badge {{ $purchase->payment_status === 'paid' ? 'bg-success' : 'bg-warning' }} fs-12">
                {{ $purchase->payment_status }}
            </span>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <p class="text-muted mb-1">المورد:</p>
                    <h6 class="fs-14 fw-bold">{{ $purchase->supplier?->company_name }}</h6>
                </div>
                <div class="col-md-3">
                    <p class="text-muted mb-1">تاريخ الفاتورة:</p>
                    <h6 class="fs-14">{{ $purchase->invoice_date->format('Y-m-d') }}</h6>
                </div>
                <div class="col-md-3">
                    <p class="text-muted mb-1">المستخدم المستلم:</p>
                    <h6 class="fs-14">{{ $purchase->receivedByUser?->name }}</h6>
                </div>
                <div class="col-md-3">
                    <p class="text-muted mb-1">الفرع:</p>
                    <h6 class="fs-14">{{ $purchase->branch?->name }}</h6>
                </div>
            </div>

            <!-- Items Table -->
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>المنتج / الصنف</th>
                            <th>التصنيف</th>
                            <th>الكمية الموردة</th>
                            <th>سعر تكلفة الشراء</th>
                            <th>الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchase->items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="fw-bold">{{ $item->product?->name }}</td>
                            <td>{{ $item->product?->category?->name ?? '---' }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format($item->unit_cost_price, 2) }} ج.م</td>
                            <td class="fw-bold">{{ number_format($item->total_cost_price, 2) }} ج.م</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="row justify-content-end">
                <div class="col-md-4">
                    <div class="bg-light p-3 rounded">
                        <div class="d-flex justify-content-between mb-2">
                            <span>المجموع الفرعي:</span>
                            <strong>{{ number_format($purchase->subtotal, 2) }} ج.م</strong>
                        </div>
                        @if($purchase->tax_amount > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span>الضريبة:</span>
                            <strong>+{{ number_format($purchase->tax_amount, 2) }} ج.م</strong>
                        </div>
                        @endif
                        @if($purchase->discount_amount > 0)
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>الخصم:</span>
                            <strong>-{{ number_format($purchase->discount_amount, 2) }} ج.م</strong>
                        </div>
                        @endif
                        <hr>
                        <div class="d-flex justify-content-between mb-2 fs-15">
                            <span class="fw-bold">الصافي الإجمالي:</span>
                            <strong class="text-primary">{{ number_format($purchase->final_amount, 2) }} ج.م</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>المسدد:</span>
                            <strong>{{ number_format($purchase->paid_amount, 2) }} ج.م</strong>
                        </div>
                        <div class="d-flex justify-content-between text-danger fw-bold">
                            <span>المتبقي آجل:</span>
                            <strong>{{ number_format($purchase->remaining_amount, 2) }} ج.م</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: review the shipment's barcode labels before printing -->
    <div class="modal fade" id="purchaseLabelsModal" tabindex="-1" aria-labelledby="purchaseLabelsModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content border-0 shadow" method="GET" action="{{ route('admin.purchases.labels', $purchase) }}" target="_blank">
                <div class="modal-header bg-success text-white p-3">
                    <h5 class="modal-title fw-bold text-white fs-15" id="purchaseLabelsModalTitle">طباعة باركود التوريدة {{ $purchase->invoice_number }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    @include('admin.labels.partials.print-options', ['idPrefix' => 'purchaseLabels'])

                    <div class="table-responsive mt-3">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>الصنف</th>
                                    <th>الكود على الملصق</th>
                                    <th>الكمية المستلمة</th>
                                    <th>عدد الملصقات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($labelLines as $line)
                                    <tr>
                                        <td class="fw-bold">{{ $line['product']->name }}</td>
                                        <td>
                                            @if($line['code'] !== null)
                                                <span class="font-monospace" dir="ltr">{{ $line['code'] }}</span>
                                            @else
                                                <span class="text-danger fs-12">لا يوجد كود إنجليزي؛ عدّل بيانات الصنف أولاً</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ \App\Support\Quantity::format($line['quantity']) }} {{ $line['product']->unit?->label() }}
                                            @if($line['product']->allowsFraction())
                                                <small class="text-muted d-block fs-11">يُباع بال{{ $line['product']->unit?->label() }}: أدخل عدد العبوات</small>
                                            @endif
                                        </td>
                                        <td>
                                            <input type="number" class="form-control font-monospace" name="items[{{ $line['item_id'] }}]"
                                                   aria-label="عدد ملصقات {{ $line['product']->name }}"
                                                   value="{{ $line['code'] !== null ? $line['suggested'] : 0 }}"
                                                   min="0" max="{{ \App\Contracts\Catalog\BarcodeLabelServiceInterface::MAX_LABELS }}" step="1"
                                                   @disabled($line['code'] === null)>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">لا توجد أصناف قابلة للطباعة في هذه الفاتورة.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">
                        <i class="ri-printer-line me-1"></i> طباعة
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
