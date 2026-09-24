<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إذن استلام وتوريد بضاعة - {{ $purchase->invoice_number }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', sans-serif; color: #111; }
        body { background: #f4f5f7; padding: 25px; display: flex; justify-content: center; }
        .print-page { width: 210mm; background: #fff; padding: 30px; border-radius: 6px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0d2238; padding-bottom: 12px; margin-bottom: 15px; }
        .table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px 10px; text-align: right; }
        .table th { background: #f8f9fa; font-weight: 700; }
        .sig-section { display: flex; justify-content: space-between; margin-top: 40px; }
        .sig-box { width: 30%; text-align: center; border-top: 1px solid #333; padding-top: 6px; font-weight: 700; }
        @media print {
            body { background: #fff; padding: 0; }
            .print-page { box-shadow: none; width: 100%; }
            .btn-bar { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="print-page">
        <div class="header">
            <div>
                <h2 style="color: #0d2238;">مركز الحسيني لصيانة وبطاريات السيارات</h2>
                <div style="font-size: 12px; color: #555;">فرع دمياط الجديدة - إذن استلام وفحص مخزني</div>
            </div>
            <div style="text-align: left;">
                <h3>إذن استلام شحنة بضاعة</h3>
                <div style="font-size: 13px; font-weight: 700;">رقم الفاتورة: {{ $purchase->invoice_number }}</div>
                <div style="font-size: 12px;">التاريخ: {{ $purchase->invoice_date->format('Y-m-d') }}</div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 13px;">
            <div><strong>شركة التوريد:</strong> {{ $purchase->supplier?->company_name }}</div>
            <div><strong>المستلم / أمين المخزن:</strong> {{ $purchase->receivedByUser?->name }}</div>
            <div><strong>فرع التوريد:</strong> {{ $purchase->branch?->name }}</div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 45%;">الصنف / البطارية</th>
                    <th style="width: 15%;">الكمية الموردة</th>
                    <th style="width: 15%;">سعر الوحدة</th>
                    <th style="width: 20%;">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchase->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $item->product?->name }}</strong></td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->unit_cost_price, 2) }} ج.م</td>
                    <td>{{ number_format($item->total_cost_price, 2) }} ج.م</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" style="text-align: left;">الصافي الإجمالي:</th>
                    <th>{{ number_format($purchase->final_amount, 2) }} ج.م</th>
                </tr>
            </tfoot>
        </table>

        <div class="sig-section">
            <div class="sig-box">توقيع مندوب المورد</div>
            <div class="sig-box">توقيع أمين المخزن المستلم</div>
            <div class="sig-box">اعتماد الإدارة والفرع</div>
        </div>
    </div>

    <div class="btn-bar" style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);">
        <button onclick="window.print()" style="background: #0d2238; color: #fff; border: none; padding: 8px 20px; border-radius: 20px; font-weight: 700; cursor: pointer;">
            طباعة إذن الاستلام
        </button>
    </div>
</body>
</html>
