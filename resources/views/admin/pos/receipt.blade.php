<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيصال فاتورة مبيعات - {{ $invoice->invoice_number }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Cairo', sans-serif;
            color: #000;
        }

        body {
            background-color: #f3f4f6;
            display: flex;
            justify-content: center;
            padding: 20px;
        }

        .receipt-container {
            width: 80mm;
            background: #fff;
            padding: 12px 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-radius: 4px;
        }

        .text-center { text-align: center; }
        .text-end { text-align: left; }
        .text-start { text-align: right; }
        .fw-bold { font-weight: 700; }
        .fw-bolder { font-weight: 800; }
        .fs-12 { font-size: 12px; }
        .fs-11 { font-size: 11px; }
        .fs-10 { font-size: 10px; }
        .fs-9 { font-size: 9px; }

        .header-logo {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 2px;
            letter-spacing: 0.5px;
        }

        .header-sub {
            font-size: 10.5px;
            color: #333;
            margin-bottom: 3px;
        }

        .branch-info {
            font-size: 9.5px;
            color: #444;
            border-bottom: 1px dashed #000;
            padding-bottom: 6px;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .invoice-meta {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            margin-bottom: 3px;
        }

        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 10.5px;
        }

        .receipt-table th {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 4px 2px;
            text-align: right;
            font-weight: 700;
        }

        .receipt-table td {
            padding: 4px 2px;
            vertical-align: top;
            border-bottom: 1px dotted #ccc;
        }

        .totals-section {
            border-top: 1px dashed #000;
            padding-top: 6px;
            margin-top: 4px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 3px;
        }

        .total-row.grand {
            font-size: 13px;
            font-weight: 800;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 0;
            margin: 4px 0;
        }

        .warranty-box {
            border: 1px solid #000;
            padding: 6px;
            margin-top: 8px;
            text-align: center;
            border-radius: 3px;
            background: #fafafa;
        }

        .qr-placeholder {
            margin: 8px auto 4px auto;
            text-align: center;
        }

        .print-btn-bar {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 999;
        }

        .btn-print {
            background: #0ab39c;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(10,179,156,0.3);
        }

        .btn-back {
            background: #3577f1;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .print-btn-bar {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none;
                width: 100%;
                padding: 0;
            }
        }
    </style>
</head>
<body>

    <div class="receipt-container">
        <!-- Brand Header -->
        <div class="text-center">
            <div class="header-logo">مركز الحسيني</div>
            <div class="header-sub">لبطاريات وزيوت وصيانة السيارات</div>
            <div class="branch-info">
                فرع دمياط الجديدة - شارع المحجوب الرئيسي<br>
                خدمة العملاء والدعم الفني: 01000000000<br>
                سجل تجاري: 45892 | بطاقة ضريبية: 541-890-123
            </div>
        </div>

        <!-- Invoice Meta -->
        <div class="invoice-meta">
            <span><strong>رقم الفاتورة:</strong> {{ $invoice->invoice_number }}</span>
            <span>{{ $invoice->created_at->format('Y-m-d H:i') }}</span>
        </div>
        <div class="invoice-meta">
            <span><strong>الكاشير:</strong> {{ $invoice->cashier?->name ?? 'الكاشير الرئيسي' }}</span>
            <span><strong>طريقة الدفع:</strong> {{ match($invoice->payment_method) {
                'cash' => 'نقدي',
                'card' => 'فيزا / بطاقة',
                'credit' => 'آجل',
                'split' => 'دفع مجزأ',
                default => $invoice->payment_method
            } }}</span>
        </div>

        @if($invoice->customer)
        <div class="invoice-meta">
            <span><strong>العميل:</strong> {{ $invoice->customer->name }}</span>
            <span><strong>الهاتف:</strong> {{ $invoice->customer->phone }}</span>
        </div>
        @endif

        @if($invoice->customerVehicle)
        <div class="invoice-meta">
            <span><strong>السيارة:</strong> {{ $invoice->customerVehicle->car_brand }} {{ $invoice->customerVehicle->car_model }}</span>
            <span><strong>اللوحة:</strong> {{ $invoice->customerVehicle->plate_number }}</span>
        </div>
        @endif

        @if($invoice->technician)
        <div class="invoice-meta">
            <span><strong>الفني القائم بالتركيب:</strong> {{ $invoice->technician->full_name }}</span>
        </div>
        @endif

        <!-- Items Table -->
        <table class="receipt-table">
            <thead>
                <tr>
                    <th style="width: 50%;">الصنف والبيان</th>
                    <th style="width: 15%; text-align: center;">الكمية</th>
                    <th style="width: 35%; text-align: left;">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                <tr>
                    <td>
                        <span class="fw-bold">{{ $item->product?->name }}</span>
                        @if($item->battery_serial_number)
                        <div class="fs-9" style="color: #222;">سيريال: <strong>{{ $item->battery_serial_number }}</strong></div>
                        <div class="fs-9 text-muted">ضمان معتمد: {{ $item->warranty_duration_months }} شهراً</div>
                        @endif
                    </td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td style="text-align: left;" class="fw-bold">{{ number_format($item->total_price, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals Section -->
        <div class="totals-section">
            <div class="total-row">
                <span>المجموع الفرعي:</span>
                <span>{{ number_format($invoice->subtotal, 2) }} ج.م</span>
            </div>

            @if($invoice->scrap_deduction_amount > 0)
            <div class="total-row" style="color: #b02a37; font-weight: 700;">
                <span>خصم استبدال بطارية كهنة:</span>
                <span>-{{ number_format($invoice->scrap_deduction_amount, 2) }} ج.م</span>
            </div>
            @endif

            @if($invoice->discount_amount > 0)
            <div class="total-row" style="color: #b02a37;">
                <span>خصم إضافي:</span>
                <span>-{{ number_format($invoice->discount_amount, 2) }} ج.م</span>
            </div>
            @endif

            @if($invoice->tax_amount > 0)
            <div class="total-row">
                <span>ضريبة القيمة المضافة:</span>
                <span>+{{ number_format($invoice->tax_amount, 2) }} ج.م</span>
            </div>
            @endif

            <div class="total-row grand">
                <span>الصافي المطلوب سداده:</span>
                <span>{{ number_format($invoice->final_amount, 2) }} ج.م</span>
            </div>

            <div class="total-row">
                <span>المسدد فعلياً:</span>
                <span>{{ number_format($invoice->paid_amount, 2) }} ج.م</span>
            </div>

            @if($invoice->remaining_amount > 0)
            <div class="total-row" style="color: #b02a37; font-weight: 700;">
                <span>المتبقي آجل على الحساب:</span>
                <span>{{ number_format($invoice->remaining_amount, 2) }} ج.م</span>
            </div>
            @endif
        </div>

        <!-- Warranty & Inspection Notice -->
        <div class="warranty-box">
            <div class="fw-bold fs-11">تنبيهات الضمان والفحص الفني المجاني</div>
            <div class="fs-9" style="margin-top: 2px;">
                * يُرجى التوجه لفرع المركز كل شهرين لفحص شحن الدينامو مجاناً.<br>
                * الحفاظ على هذا الإيصال أو سيريال البطارية سارٍ لصيانة وضمان المركز.<br>
                * شحن الدينامو السليم: من 13.8V إلى 14.4V.
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center" style="margin-top: 10px;">
            <div class="fs-10 fw-bold">شكراً لثقتكم بمجموعة الحسيني</div>
            <div class="fs-9 text-muted">طبع بواسطة نظام الحسيني لإدارة البطاريات ونقاط البيع</div>
        </div>
    </div>

    <!-- Print Action Bar -->
    <div class="print-btn-bar">
        <button onclick="window.print()" class="btn-print">طباعة الإيصال الفوري (80mm)</button>
        <a href="{{ route('admin.pos.index') }}" class="btn-back">العودة لشاشة الكاشير</a>
    </div>

</body>
</html>
