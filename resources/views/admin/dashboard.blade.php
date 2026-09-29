@extends('admin.layouts.master')

@section('title', 'لوحة التحكم والتحليلات | مركز الحسيني لبطاريات وزيوت وصيانة السيارات')

@section('css')
    <style>
        /* ============================================================== */
        /* Professional Card, Border & Elevation System                  */
        /* ============================================================== */
        .dashboard-card {
            border-radius: 14px !important;
            border: 1px solid rgba(var(--vz-dark-rgb), 0.08) !important;
            box-shadow: 0 2px 12px rgba(18, 38, 63, 0.04) !important;
            overflow: hidden;
            background: var(--vz-card-bg, #fff);
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }
        [data-bs-theme="dark"] .dashboard-card {
            border-color: rgba(255, 255, 255, 0.08) !important;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25) !important;
        }

        .stat-card-widget {
            border-radius: 14px !important;
            border: 1px solid rgba(var(--vz-dark-rgb), 0.08) !important;
            box-shadow: 0 2px 10px rgba(18, 38, 63, 0.04) !important;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            position: relative;
            overflow: hidden;
            background: var(--vz-card-bg, #fff);
        }
        .stat-card-widget:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(18, 38, 63, 0.08) !important;
            border-color: rgba(var(--vz-primary-rgb), 0.35) !important;
        }
        [data-bs-theme="dark"] .stat-card-widget {
            border-color: rgba(255, 255, 255, 0.08) !important;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25) !important;
        }
        [data-bs-theme="dark"] .stat-card-widget:hover {
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.4) !important;
            border-color: rgba(var(--vz-primary-rgb), 0.5) !important;
        }

        /* Top Accent Indicator Strip */
        .stat-card-widget::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 3px;
        }
        .stat-card-widget.kpi-sales::before { background: linear-gradient(90deg, #0ab39c, #299cdb); }
        .stat-card-widget.kpi-credit::before { background: linear-gradient(90deg, #f7b84b, #f06548); }
        .stat-card-widget.kpi-customers::before { background: linear-gradient(90deg, #405189, #3577f1); }
        .stat-card-widget.kpi-inventory::before { background: linear-gradient(90deg, #299cdb, #0ab39c); }

        /* Segmented Period Switcher */
        .segmented-control-bar {
            display: flex;
            align-items: center;
            background: rgba(var(--vz-light-rgb), 0.75);
            border: 1px solid rgba(var(--vz-dark-rgb), 0.08);
            border-radius: 24px;
            padding: 2px;
            width: 100%;
            box-sizing: border-box;
            gap: 2px;
        }
        [data-bs-theme="dark"] .segmented-control-bar {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.08);
        }
        .segmented-btn {
            flex: 1 1 0;
            min-width: 0;
            padding: 3px 0 !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            text-align: center !important;
            border: none !important;
            border-radius: 18px !important;
            background: transparent;
            color: var(--vz-muted, #74788d);
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
            line-height: 1.3;
        }
        .segmented-btn:hover {
            color: var(--vz-dark, #212529);
            background: rgba(var(--vz-dark-rgb), 0.04);
        }
        [data-bs-theme="dark"] .segmented-btn:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.08);
        }
        .segmented-btn.active {
            background: #0ab39c !important;
            color: #ffffff !important;
            box-shadow: 0 2px 6px rgba(10, 179, 156, 0.35) !important;
        }

        .dashboard-header {
            border-bottom: 1px solid rgba(var(--vz-dark-rgb), 0.06) !important;
            padding: 1rem 1.25rem !important;
        }
        [data-bs-theme="dark"] .dashboard-header {
            border-bottom-color: rgba(255, 255, 255, 0.07) !important;
        }

        .welcome-banner-card {
            border: 1px solid rgba(var(--vz-dark-rgb), 0.08) !important;
            border-inline-start: 4px solid var(--vz-primary) !important;
            background: linear-gradient(135deg, rgba(31, 79, 150, 0.03) 0%, rgba(255, 255, 255, 1) 50%, rgba(212, 175, 55, 0.03) 100%) !important;
            border-radius: 14px !important;
            box-shadow: 0 2px 12px rgba(18, 38, 63, 0.04) !important;
        }
        [data-bs-theme="dark"] .welcome-banner-card {
            border-color: rgba(255, 255, 255, 0.08) !important;
            border-inline-start-color: var(--vz-primary) !important;
            background: linear-gradient(135deg, rgba(31, 79, 150, 0.12) 0%, #18202b 60%, rgba(212, 175, 55, 0.05) 100%) !important;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25) !important;
        }
        [data-bs-theme="dark"] .welcome-banner-card h4 {
            color: #f1f5f9 !important;
        }

        .quick-action-btn {
            border-radius: 9px !important;
            transition: all 0.2s ease;
            font-weight: 700;
        }
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Seamless Table Styling */
        .dash-table {
            margin-bottom: 0 !important;
            width: 100%;
        }
        .dash-table thead th {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 0.8rem 0.9rem;
            background-color: var(--vz-tertiary-bg, rgba(var(--vz-dark-rgb), 0.025)) !important;
            border-bottom: 1px solid rgba(var(--vz-dark-rgb), 0.08) !important;
        }
        [data-bs-theme="dark"] .dash-table thead th {
            border-bottom-color: rgba(255, 255, 255, 0.08) !important;
        }
        .dash-table tbody td {
            padding: 0.8rem 0.9rem;
            vertical-align: middle;
            border-bottom: 1px solid rgba(var(--vz-dark-rgb), 0.05);
        }
        [data-bs-theme="dark"] .dash-table tbody td {
            border-bottom-color: rgba(255, 255, 255, 0.05);
        }
        .dash-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Responsive Custom Scrollbar */
        .custom-scroll-container::-webkit-scrollbar {
            height: 5px;
            width: 5px;
        }
        .custom-scroll-container::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scroll-container::-webkit-scrollbar-thumb {
            background: rgba(var(--vz-dark-rgb), 0.15);
            border-radius: 4px;
        }
        .custom-scroll-container::-webkit-scrollbar-thumb:hover {
            background: rgba(var(--vz-dark-rgb), 0.25);
        }

        /* Mini Tiles & Badges */
        .dash-mini-tile {
            border-radius: 10px !important;
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .dash-mini-tile:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        @media print {
            .app-menu, .topbar, .footer, .btn, .no-print { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; }
            .print-invoice-sheet {
                display: block !important;
                width: 100% !important;
                border: 2px solid #000;
                padding: 20px;
                font-size: 12pt;
            }
        }
    </style>
@endsection

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'لوحات تحكم الحسيني', 'title' => 'لوحة القيادة والمبيعات - مركز الحسيني'])

    {{-- 1. شريط الترحيب والعمليات السريعة --}}
    @include('admin.dashboard.partials.welcome-bar')

    {{-- 2. بطاقات مؤشرات الأداء الحية الأربعة --}}
    @include('admin.dashboard.partials.kpi-cards')

    {{-- 3. التحليلات التفاعلية وحركة المبيعات الأسبوعية وتوزيع الأقسام --}}
    @include('admin.dashboard.partials.charts')

    {{-- 4. أحدث فواتير المبيعات ومتابعة ديون الآجل --}}
    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            @include('admin.dashboard.partials.recent-invoices')
        </div>
        <div class="col-xl-4">
            @include('admin.dashboard.partials.credit-dues')
        </div>
    </div>

    {{-- 5. تنبيهات نواقص المخزون وحضور فنيي الورشة اليومي --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-6">
            @include('admin.dashboard.partials.low-stock')
        </div>
        <div class="col-xl-6">
            @include('admin.dashboard.partials.workshop-attendance')
        </div>
    </div>

    {{-- 6. نافذة معاينة وطباعة الفاتورة والضمان --}}
    @include('admin.dashboard.partials.invoice-modal')
@endsection

@section('script')
    @include('admin.dashboard.partials.scripts')
@endsection
