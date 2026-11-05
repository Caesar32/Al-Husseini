@php
    $totalLabels = array_sum(array_map(fn ($label) => $label->copies, $labels));
    $denseCodes = collect($labels)->filter(fn ($label) => $label->isDense)->map(fn ($label) => $label->code);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('assets/css/admin/labels/labels.css') }}" rel="stylesheet">
    <link href="{{ asset("assets/css/admin/labels/label-{$size->value}.css") }}" rel="stylesheet">
</head>
<body @if($denseCodes->isEmpty()) data-auto-print @endif>
    <div class="toolbar">
        <strong>{{ $title }}</strong>
        <span>{{ $size->label() }} &middot; {{ $totalLabels }} ملصق</span>
        <span class="toolbar__spacer"></span>
        <button type="button" class="btn-print" id="printLabels">طباعة</button>
        <button type="button" class="btn-close-tab" id="closeLabels">إغلاق</button>
    </div>

    @if($denseCodes->isNotEmpty())
        <div class="notice notice--warning" role="alert">
            <strong>تنبيه قبل الطباعة:</strong> كود الأصناف التالية طويل على هذا المقاس، فتصبح خطوط الباركود أرفع من 0.25 مم وقد لا يقرؤها المسدس:
            <bdi>{{ $denseCodes->join('، ') }}</bdi>.
            جرّب مقاساً أكبر أو كوداً أقصر، أو اطبع ملصقاً واحداً واختبره بالمسدس أولاً. لذلك لم تُفتح نافذة الطباعة تلقائياً.
        </div>
    @endif
    <div class="notice notice--warning" id="titleCutNotice" role="alert" hidden>
        <strong>تنبيه قبل الطباعة:</strong> اسم هذه الأصناف أطول من سطرين على هذا المقاس وسيُقص آخره، وقد تضيع معه السعة:
        <bdi id="titleCutList"></bdi>.
        اختصر اسم الصنف أو اختر مقاساً أكبر. لذلك لم تُفتح نافذة الطباعة تلقائياً.
    </div>
    <div class="notice notice--hint">
        في نافذة الطباعة: اختر طابعة الاستيكر، والهوامش <strong>بدون (None)</strong>، والمقياس <strong>100%</strong>، وألغِ رؤوس وتذييلات الصفحة.
        وفي إعدادات تعريف الطابعة: مقاس الورق <strong>عرض {{ $size->widthMm() }} مم × طول {{ $size->heightMm() }} مم</strong> والاتجاه <strong>Portrait</strong>؛ فالعرض مكتوب هنا كأبعاد، واختيار Landscape في التعريف قد يدير الملصق 90 درجة.
    </div>

    <main class="sheet">
        @foreach($labels as $label)
            @for($copy = 0; $copy < $label->copies; $copy++)
                <section class="label {{ $label->isDense ? 'label--dense' : '' }}">
                    <div class="label__header">
                        <div class="label__company">{{ $label->company }}</div>
                        @if($label->price !== null)
                            <div class="label__price">{{ number_format($label->price, fmod($label->price, 1) == 0 ? 0 : 2) }} ج.م</div>
                        @endif
                    </div>
                    <div class="label__title">{{ $label->title }}</div>
                    <div class="label__barcode"><img src="{{ $label->barcodeImage }}" alt="{{ $label->code }}"></div>
                    <div class="label__footer">
                        @if($label->warrantyMonths > 0)
                            <div class="label__warranty">ضمان {{ $label->warrantyMonths }} شهر</div>
                        @endif
                        <div class="label__code">{{ $label->code }}</div>
                    </div>
                </section>
            @endfor
        @endforeach
    </main>

    <script src="{{ asset('assets/js/admin/labels-print.js') }}"></script>
</body>
</html>
