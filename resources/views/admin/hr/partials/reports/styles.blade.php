<style>
    /* Custom styles for Reports & Analytics */
    .report-filter-pill {
        border-radius: 30px;
        padding: 6px 18px;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    .report-stat-card {
        border-radius: 12px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .report-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.06);
    }
    .badge-status-on_time {
        background-color: rgba(42, 157, 143, 0.15);
        color: #2a9d8f;
        border: 1px solid rgba(42, 157, 143, 0.3);
    }
    .badge-status-late {
        background-color: rgba(244, 162, 97, 0.18);
        color: #e76f51;
        border: 1px solid rgba(244, 162, 97, 0.4);
    }
    .badge-status-absent {
        background-color: rgba(230, 57, 70, 0.15);
        color: #e63946;
        border: 1px solid rgba(230, 57, 70, 0.3);
    }
    .badge-status-on_leave {
        background-color: rgba(69, 123, 157, 0.15);
        color: #457b9d;
        border: 1px solid rgba(69, 123, 157, 0.3);
    }

    /* Print Specific Layout */
    @media print {
        body {
            background-color: #fff !important;
            color: #000 !important;
        }
        .app-menu, .topbar, .footer, .btn, .nav-tabs, .report-controls-card, .no-print {
            display: none !important;
        }
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        .page-content {
            padding: 0 !important;
        }
        .container-fluid {
            width: 100% !important;
            padding: 0 !important;
        }
        .print-header {
            display: block !important;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .print-footer {
            display: block !important;
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
            margin-bottom: 20px !important;
        }
        .table {
            border: 1px solid #000 !important;
        }
        .table th, .table td {
            border: 1px solid #ccc !important;
            color: #000 !important;
            font-size: 11pt !important;
        }
        .tab-content > .tab-pane {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        body.modal-open .modal {
            position: static !important;
            overflow: visible !important;
        }
        body.modal-open .modal-dialog {
            max-width: 100% !important;
            margin: 0 !important;
        }
        body.modal-open .modal-content {
            border: none !important;
            box-shadow: none !important;
        }
        body.modal-open .modal-header .btn-close,
        body.modal-open .modal-footer {
            display: none !important;
        }
        body.modal-open .main-content > .page-content > .container-fluid > *:not(#employeeDetailModal) {
            display: none !important;
        }
    }

    .print-header, .print-footer {
        display: none;
    }
</style>
