@extends('admin.layouts.master')

@section('title', 'شاشة البصمة ومتابعة الحضور اليومي | مركز الحسيني لبطاريات السيارات')

@section('css')
<style>
    /* Styling for attendance shop management */
    .stat-filter-card {
        cursor: pointer;
        transition: all 0.25s ease;
        border-radius: 12px;
        border: 2px solid transparent;
    }
    .stat-filter-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    }
    .stat-filter-card.active-filter {
        border-color: #0ab39c !important;
        background-color: #f0fdf4 !important;
    }
    .stat-filter-card.active-filter-warning {
        border-color: #f7b84b !important;
        background-color: #fffbeb !important;
    }
    .stat-filter-card.active-filter-danger {
        border-color: #f06548 !important;
        background-color: #fef2f2 !important;
    }

    .shop-clock-card {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border-radius: 14px;
        color: #fff;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.25);
    }
    .live-digital-time {
        font-family: 'Courier New', Courier, monospace;
        font-size: 2.2rem;
        font-weight: 800;
        letter-spacing: 2px;
        color: #38bdf8;
        text-shadow: 0 0 15px rgba(56, 189, 248, 0.4);
    }

    .btn-punch-action {
        font-weight: 700;
        padding: 6px 14px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s ease;
    }
    .btn-punch-action:hover {
        transform: scale(1.04);
    }

    .filter-tab-pill {
        border-radius: 30px;
        padding: 8px 20px;
        font-size: 14px;
        font-weight: 700;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .filter-tab-pill:hover {
        background: #f1f5f9;
        color: #0f172a;
    }
    .filter-tab-pill.active {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    }

    .quick-amount-preset {
        font-weight: 700;
        border-radius: 8px;
        padding: 8px 12px;
        cursor: pointer;
        transition: all 0.15s;
    }
    .quick-amount-preset:hover {
        background-color: #fee2e2;
        border-color: #ef4444;
        color: #b91c1c;
    }

    .fingerprint-glow {
        animation: pulse-glow 2s infinite;
    }
    @keyframes pulse-glow {
        0% { box-shadow: 0 0 0 0 rgba(10, 179, 156, 0.5); }
        70% { box-shadow: 0 0 0 12px rgba(10, 179, 156, 0); }
        100% { box-shadow: 0 0 0 0 rgba(10, 179, 156, 0); }
    }
</style>
@endsection

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'الموارد البشرية والورشة', 'title' => 'شاشة متابعة حضور وبصمة العمال والفنيين اليومية'])

    {{-- 1. محطة الساعة وجهاز البصمة السريع بضغطة واحدة --}}
    @include('admin.hr.partials.attendance.clock-station')

    {{-- 2. بطاقات الإحصائيات البصرية الأربعة --}}
    @include('admin.hr.partials.attendance.stats')

    {{-- 3. جدول متابعة الحضور والفرز اليومي --}}
    @include('admin.hr.partials.attendance.table')

    {{-- 4. نافذة توقيع الجزاءات والخصومات الإدارية الفورية --}}
    @include('admin.hr.partials.attendance.modal-deduction')
@endsection

@section('script')
    {{-- 5. محرك الجافاسكريبت والتسجيل اللحظي للبصمات --}}
    @include('admin.hr.partials.attendance.scripts')
@endsection
