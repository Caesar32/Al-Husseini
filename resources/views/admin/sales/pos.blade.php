@extends('admin.layouts.master')

@section('title', 'نقطة البيع وفاتورة الصيانة والزيوت والبطاريات | مركز الحسيني')

@section('css')
    @include('admin.sales.pos.partials.styles')
@endsection

@section('content')
@php
    $pageConfig = [
        'data' => ['products' => $products, 'customers' => $customers, 'scrapTiers' => $scrapTiers],
        'routes' => [
            'store' => route('admin.pos.store'),
            'customerStore' => route('admin.customers.store'),
            'invoiceShow' => route('admin.invoices.show', ['invoice' => '__ID__']),
            'productsSearch' => route('admin.pos.products.search'),
        ],
    ];
@endphp
<div class="pos-container-fluid" data-page="pos" data-config='@json($pageConfig)'>
    <div class="row g-2 pos-grid-row">

        <!-- ============================================================== -->
        <!-- 1. RIGHT (RTL): Products & Services Catalog Panel (65% width)  -->
        <!-- ============================================================== -->
        @include('admin.sales.pos.partials.catalog-panel')

        <!-- ============================================================== -->
        <!-- 2. LEFT (RTL): Live Cart & Checkout Terminal (35% width)        -->
        <!-- ============================================================== -->
        @include('admin.sales.pos.partials.cart-panel')

    </div>
</div>

<!-- ============================================================== -->
<!-- 3. Mobile & Tablet Sticky Floating Cart & Checkout Bar         -->
<!-- ============================================================== -->
@include('admin.sales.pos.partials.mobile-floating-bar')

<!-- ============================================================== -->
<!-- 4. Modals (Fast Customer Registration & Warranty Invoice Print) -->
<!-- ============================================================== -->
@include('admin.sales.pos.partials.modals')
@endsection

@section('script')
    @include('admin.sales.pos.partials.scripts')
@endsection
