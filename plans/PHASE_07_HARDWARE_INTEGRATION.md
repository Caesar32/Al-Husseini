# المرحلة السابعة: تكامل العتاد والأجهزة (Hardware & Peripherals Integration)

> **طبيعة الملف:** وثيقة تقنية تفصيلية توضح كيفية دمج وبرمجة الأجهزة الطرفية للمركز: قارئ الباركود (USB HID Scanner)، طابعات الفواتير الحرارية (80mm ESC/POS)، وجهاز البصمة البيومتري (ZKTeco ADMS Device).

---

## 1. قراءة الباركود السريعة بالماسح الضوئي (USB HID Barcode Scanner)

تعتمد أجهزة قراءة الباركود اليدوية على محاكاة لوحة المفاتيح (Keystroke Emulation). يتم إرسال المحارف بفاصل زمني ضئيل جداً (<30ms) يتبعه مفتاح Enter (`KeyCode 13`).

### تضمين السكربت في شاشة الكاشير `public/assets/js/pos-scanner.js`:
```javascript
(function () {
    let barcodeBuffer = '';
    let lastKeyTime = Date.now();

    window.addEventListener('keydown', function (e) {
        const currentTime = Date.now();
        const diff = currentTime - lastKeyTime;
        lastKeyTime = currentTime;

        // إذا كان الفارق الزمني كبير (>60ms) فهذا يعني إدخال بشري عادي وليس قارئ باركود
        if (diff > 60) {
            barcodeBuffer = '';
        }

        if (e.key === 'Enter') {
            if (barcodeBuffer.length >= 4) {
                e.preventDefault();
                console.log('Barcode Scanned:', barcodeBuffer);
                handleScannedBarcode(barcodeBuffer.trim());
                barcodeBuffer = '';
            }
        } else if (e.key.length === 1) { // الأحرف والأرقام فقط
            barcodeBuffer += e.key;
        }
    });

    async function handleScannedBarcode(code) {
        // البحث عن المنتج أو الرقم التسلسلي عبر السيرفر
        try {
            const response = await axios.get('/admin/pos/search-product', { params: { q: code } });
            const products = response.data;

            if (products && products.length > 0) {
                // إضافة الصنف مباشرة إلى السلة
                addProductToCart(products[0]);
                // تشغيل صوت تأكيد القراءة
                playBeepSound();
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'صنف غير معروف',
                    text: `الباركود [${code}] غير مسجل في قاعدة البيانات.`,
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        } catch (err) {
            console.error('Barcode lookup failed:', err);
        }
    }

    function playBeepSound() {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(1800, audioCtx.currentTime);
        osc.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.08);
    }
})();
```

---

## 2. إعدادات وقالب الطابعة الحرارية (80mm ESC/POS Thermal Receipt)

قالب Blade مخصص للطباعة المباشرة بأبعاد عرض 72mm-80mm مع قطع الورق التلقائي بدون هوامش متصفح:

### `resources/views/admin/pos/receipt_80mm.blade.php`
```blade
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>فاتورة رقم #{{ $invoice->invoice_number }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }
        body {
            font-family: 'Courier New', Courier, monospace, 'Cairo', sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 8px 4px;
            color: #000;
            background: #fff;
            width: 76mm;
        }
        .text-center { text-align: center; }
        .text-end { text-align: left; }
        .text-start { text-align: right; }
        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        th, td {
            padding: 3px 0;
        }
        .fw-bold { font-weight: bold; }
        .qrcode-container {
            margin: 8px 0;
            text-align: center;
        }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print();">

    <div class="no-print" style="margin-bottom: 10px; text-align: center;">
        <button onclick="window.print();" style="padding: 6px 12px; font-weight: bold;">طباعة الفاتورة</button>
    </div>

    <div class="text-center">
        <h3 style="margin: 2px 0; font-size: 15px;">مركز الحسيني للبطاريات</h3>
        <p style="margin: 2px 0;">فرع: {{ $invoice->branch->name }}</p>
        <p style="margin: 2px 0;">هاتف: {{ $invoice->branch->phone ?? '01000000000' }}</p>
    </div>

    <div class="divider"></div>

    <div style="font-size: 11px;">
        <div><b>رقم الفاتورة:</b> {{ $invoice->invoice_number }}</div>
        <div><b>التاريخ:</b> {{ $invoice->created_at->format('Y-m-d H:i') }}</div>
        <div><b>الكاشير:</b> {{ $invoice->cashier->name ?? 'مسؤول النظام' }}</div>
        @if($invoice->customer)
            <div><b>العميل:</b> {{ $invoice->customer->name }} ({{ $invoice->customer->phone }})</div>
            @if($invoice->customerVehicle)
                <div><b>السيارة:</b> {{ $invoice->customerVehicle->car_brand }} {{ $invoice->customerVehicle->car_model }} [{{ $invoice->customerVehicle->plate_number }}]</div>
            @endif
        @endif
    </div>

    <div class="divider"></div>

    <table>
        <thead>
            <tr style="border-bottom: 1px solid #000;">
                <th class="text-start">الصنف</th>
                <th class="text-center">الكمية</th>
                <th class="text-end">الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td class="text-start">
                    {{ $item->product->name }}
                    @if($item->battery_serial_number)
                        <br><small>S/N: {{ $item->battery_serial_number }}</small>
                    @endif
                </td>
                <td class="text-center">{{ $item->quantity }}</td>
                <td class="text-end">{{ number_format($item->total_price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <table>
        <tr>
            <td>المجموع:</td>
            <td class="text-end">{{ number_format($invoice->subtotal, 2) }} ج.م</td>
        </tr>
        @if($invoice->discount_amount > 0)
        <tr>
            <td>الخصم:</td>
            <td class="text-end">-{{ number_format($invoice->discount_amount, 2) }} ج.م</td>
        </tr>
        @endif
        @if($invoice->scrap_deduction_amount > 0)
        <tr>
            <td>خصم البطارية القديمة (كهنة):</td>
            <td class="text-end">-{{ number_format($invoice->scrap_deduction_amount, 2) }} ج.م</td>
        </tr>
        @endif
        <tr class="fw-bold" style="font-size: 13px;">
            <td>الصافي النهائي:</td>
            <td class="text-end">{{ number_format($invoice->final_amount, 2) }} ج.م</td>
        </tr>
        <tr>
            <td>المدفوع:</td>
            <td class="text-end">{{ number_format($invoice->paid_amount, 2) }} ج.م</td>
        </tr>
        @if($invoice->remaining_amount > 0)
        <tr class="fw-bold" style="color: red;">
            <td>المتبقي آجل:</td>
            <td class="text-end">{{ number_format($invoice->remaining_amount, 2) }} ج.م</td>
        </tr>
        @endif
    </table>

    <div class="divider"></div>

    <div class="qrcode-container">
        <!-- توليد QR Code للتحقق من الضمان والفاتورة -->
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode(route('warranties.check', $invoice->invoice_number)) }}" alt="QR Code" width="90" height="90">
    </div>

    <div class="text-center" style="font-size: 10px;">
        <p style="margin: 2px 0;">الضمان لا يسري في حالة العبث بقطبي البطارية أو فتحها.</p>
        <p style="margin: 2px 0;">شكراً لزيارتكم مركز الحسيني!</p>
    </div>
</body>
</html>
```

---

## 3. تكامل أجهزة البصمة البيومترية (ZKTeco ADMS Configuration)

### مواصفات بروتوكول جهاز ZKTeco:
- يتم ضبط جهاز البصمة من لوحة التحكم (Comm. -> Cloud Server Setting):
  - **Server Address:** عنوان خادم النظام (مثال: `alhusseini.com` أو IP السيرفر المحلي).
  - **Server Port:** `80` أو `443` (HTTPS).
  - **Push Protocol / ADMS:** مُفعّل (Enabled).
  - **Webhook URL:** `https://your-domain.com/api/v1/hardware/zkteco/punch`
  - **Token / Secret:** قيمة متطابقة في `.env`: `ZKTECO_WEBHOOK_SECRET=AlHusseini_ZkSec_2026`
