<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>شهادة ضمان إلكترونية معتمدة - {{ $invoice->invoice_number }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Cairo', sans-serif;
            color: #111;
        }

        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            padding: 30px 15px;
        }

        .cert-container {
            width: 210mm;
            min-height: 148mm;
            background: #fff;
            padding: 24px 30px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            border-radius: 8px;
            border: 8px double #c59b27;
            position: relative;
        }

        .cert-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #c59b27;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }

        .cert-logo {
            font-size: 24px;
            font-weight: 900;
            color: #0d2238;
        }

        .cert-subtitle {
            font-size: 13px;
            font-weight: 700;
            color: #c59b27;
        }

        .cert-title-badge {
            background: #0d2238;
            color: #fff;
            padding: 6px 20px;
            border-radius: 4px;
            font-weight: 800;
            font-size: 16px;
            text-align: center;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .meta-card {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 10px 14px;
            background: #fafafa;
        }

        .meta-card-title {
            font-size: 13px;
            font-weight: 800;
            color: #0d2238;
            border-bottom: 1px solid #ddd;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }

        .meta-line {
            display: flex;
            justify-content: space-between;
            font-size: 12.5px;
            margin-bottom: 4px;
        }

        .battery-box {
            background: #fffdf5;
            border: 2px solid #c59b27;
            border-radius: 6px;
            padding: 12px 18px;
            margin-bottom: 15px;
        }

        .battery-serial-display {
            font-family: monospace, sans-serif;
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 2px;
            color: #b02a37;
            text-align: center;
            margin: 6px 0;
            padding: 4px;
            border: 1px dashed #c59b27;
            background: #fff;
        }

        .conditions-box {
            font-size: 11px;
            line-height: 1.6;
            color: #444;
            border: 1px solid #ddd;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 18px;
            background: #fafafa;
        }

        .signatures-area {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            padding-top: 10px;
        }

        .sig-block {
            width: 40%;
            text-align: center;
            font-size: 12px;
        }

        .sig-line {
            margin-top: 40px;
            border-top: 1px solid #333;
            padding-top: 4px;
            font-weight: 700;
        }

        .stamp-box {
            width: 100px;
            height: 70px;
            border: 2px dashed #bbb;
            border-radius: 6px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            font-size: 10px;
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
            background: #c59b27;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(197,155,39,0.4);
        }

        .btn-back {
            background: #0d2238;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 20px;
            font-size: 14px;
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
            .cert-container {
                box-shadow: none;
                width: 100%;
                border: 6px double #c59b27;
            }
        }
    </style>
</head>
<body>

    <div class="cert-container">
        <!-- Header -->
        <div class="cert-header">
            <div>
                <div class="cert-logo">مجموعة الحسيني لخدمات السيارات</div>
                <div class="cert-subtitle">وكلاء وموزعون معتمدون لكبرى شركات البطاريات العالمية</div>
                <div style="font-size: 10px; color: #555;">فرع دمياط الجديدة - شارع المحجوب | هاتف: 01000000000</div>
            </div>
            <div>
                <div class="cert-title-badge">شهادة ضمان إلكتروني معتمد</div>
                <div style="text-align: center; font-size: 11px; margin-top: 4px; font-weight: 700;">
                    رقم الفاتورة: {{ $invoice->invoice_number }}
                </div>
            </div>
        </div>

        <!-- Meta Grid -->
        <div class="grid-2">
            <!-- Customer & Vehicle Info -->
            <div class="meta-card">
                <div class="meta-card-title">بيانات العميل والمركبة</div>
                <div class="meta-line">
                    <span>اسم العميل:</span>
                    <strong>{{ $invoice->customer?->name ?? 'عميل نقدي عابر' }}</strong>
                </div>
                <div class="meta-line">
                    <span>رقم الهاتف:</span>
                    <strong>{{ $invoice->customer?->phone ?? '---' }}</strong>
                </div>
                <div class="meta-line">
                    <span>ماركة وموديل السيارة:</span>
                    <strong>{{ $invoice->customerVehicle ? $invoice->customerVehicle->car_brand . ' ' . $invoice->customerVehicle->car_model : 'غير محدد' }}</strong>
                </div>
                <div class="meta-line">
                    <span>رقم اللوحة المعدنية:</span>
                    <strong>{{ $invoice->customerVehicle?->plate_number ?? 'غير مسجل' }}</strong>
                </div>
            </div>

            <!-- Invoice & Purchase Info -->
            <div class="meta-card">
                <div class="meta-card-title">بيانات الفاتورة والفرع</div>
                <div class="meta-line">
                    <span>تاريخ الشراء:</span>
                    <strong>{{ $invoice->created_at->format('Y-m-d') }}</strong>
                </div>
                <div class="meta-line">
                    <span>فرع الإصدار:</span>
                    <strong>{{ $invoice->branch?->name ?? 'فرع دمياط الجديدة' }}</strong>
                </div>
                <div class="meta-line">
                    <span>الفني المعتمد للتركيب:</span>
                    <strong>{{ $invoice->technician?->full_name ?? 'قسم التركيبات الفنية' }}</strong>
                </div>
                <div class="meta-line">
                    <span>المستخدم الكاشير:</span>
                    <strong>{{ $invoice->cashier?->name ?? 'الكاشير' }}</strong>
                </div>
            </div>
        </div>

        <!-- Battery & Warranty Details -->
        @foreach($invoice->items as $item)
            @if($item->battery_serial_number)
            <div class="battery-box">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 15px; font-weight: 800; color: #0d2238;">
                        الصنف: {{ $item->product?->name }} ({{ $item->product?->brand }}) - {{ $item->product?->capacity_ah }}
                    </span>
                    <span style="background: #c59b27; color: #fff; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 700;">
                        فترة الضمان: {{ $item->warranty_duration_months }} شهراً
                    </span>
                </div>

                <div class="battery-serial-display">
                    {{ $item->battery_serial_number }}
                </div>

                <div style="display: flex; justify-content: space-between; font-size: 12px; color: #444; margin-top: 4px;">
                    <span>تاريخ بدء الضمان: <strong>{{ $invoice->created_at->format('Y-m-d') }}</strong></span>
                    <span>تاريخ انتهاء الضمان: <strong>{{ $invoice->created_at->addMonths($item->warranty_duration_months)->format('Y-m-d') }}</strong></span>
                </div>
            </div>
            @endif
        @endforeach

        <!-- Warranty Conditions -->
        <div class="conditions-box">
            <strong style="color: #b02a37;">شروط وضوابط سريان الضمان المعتمد:</strong><br>
            1. يسري هذا الضمان ضد عيوب الصناعة الفنية للبطارية لمدة الفحص المقررة من تاريخ الشراء.<br>
            2. يشترط لسريان الضمان سلامة دائرة شحن دينامو السيارة وأن تكون قراءة الشحن بين <strong>13.8V إلى 14.4V</strong>.<br>
            3. لا يسري الضمان في حالات: الكسر أو الانتفاخ الناتج عن الشحن الزائد (Overcharging)، أو تفريغ الشحنة بالكامل نتيجة ماس كهربائي خارجي.<br>
            4. يلتزم المركز بالفحص الدوري المجاني لشحن الدينامو كل 60 يوماً داخل أي من فروعنا بدمياط الجديدة.
        </div>

        <!-- Signatures & Official Stamp -->
        <div class="signatures-area">
            <div class="sig-block">
                <div class="stamp-box">ختم المركز المعتمد</div>
                <div style="margin-top: 4px; font-size: 11px;">مجموعة الحسيني - دمياط الجديدة</div>
            </div>
            <div class="sig-block">
                <div class="sig-line">توقيع الفني المسؤول / مسؤول الجودة</div>
            </div>
            <div class="sig-block">
                <div class="sig-line">توقيع العميل المستلم</div>
            </div>
        </div>
    </div>

    <!-- Print Action Buttons -->
    <div class="print-btn-bar">
        <button onclick="window.print()" class="btn-print">طباعة شهادة الضمان (A4 / A5)</button>
        <a href="{{ route('admin.pos.index') }}" class="btn-back">العودة لشاشة البيع</a>
    </div>

</body>
</html>
