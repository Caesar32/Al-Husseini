@extends('admin.layouts.master')

@section('title', 'نقطة البيع وفاتورة الصيانة والزيوت والبطاريات | مركز الحسيني')

@section('css')
    @include('admin.sales.pos.partials.styles')
@endsection

@section('content')
<div class="pos-container-fluid">
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
