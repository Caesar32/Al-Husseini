<div class="row g-3 align-items-end">
    <div class="col-sm-7">
        <label class="form-label fw-bold text-dark fs-13" for="{{ $idPrefix }}Size">مقاس الملصق</label>
        <select class="form-select" id="{{ $idPrefix }}Size" name="size">
            @foreach(\App\Enums\BarcodeLabelSize::cases() as $labelSize)
                <option value="{{ $labelSize->value }}" @selected($labelSize === \App\Enums\BarcodeLabelSize::DEFAULT)>{{ $labelSize->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-5">
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="{{ $idPrefix }}ShowPrice" name="show_price" value="1" checked>
            <label class="form-check-label fs-13" for="{{ $idPrefix }}ShowPrice">طباعة سعر البيع للجمهور</label>
        </div>
    </div>
</div>
