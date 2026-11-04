<?php

namespace App\Services\Catalog;

use App\Contracts\Catalog\BarcodeLabelServiceInterface;
use App\Enums\BarcodeLabelSize;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Support\BarcodeLabel;
use App\Support\Quantity;
use DomainException;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

class BarcodeLabelService implements BarcodeLabelServiceInterface
{
    private const COMPANY_NAME = 'الحسيني للبطاريات';

    /**
     * Bar widths in mm, widest first. Both are whole dots on a 203 dpi head (3 and 2 dots), so every
     * bar prints at the same width. Below 0.25 mm the code no longer maps to whole dots and scanning
     * becomes unreliable, which is what BarcodeLabel::$isDense reports.
     */
    private const MODULE_WIDTHS_MM = [0.375, 0.25];

    /** Code 128 needs a blank margin of 10 bar widths on each side to be scanned. */
    private const QUIET_ZONE_MODULES = 20;

    public function purchaseLines(PurchaseInvoice $purchase): array
    {
        $purchase->loadMissing('items.product');

        return $purchase->items
            ->filter(fn (PurchaseInvoiceItem $item) => $item->product !== null)
            ->map(fn (PurchaseInvoiceItem $item) => [
                'item_id' => $item->id,
                'product' => $item->product,
                'quantity' => Quantity::fromMilli($this->netReceivedMilli($item)),
                'code' => $this->printableCode($item->product),
                'suggested' => $this->suggestedCopies($item),
            ])
            ->values()
            ->all();
    }

    public function forPurchase(PurchaseInvoice $purchase, array $copiesByItem, BarcodeLabelSize $size, bool $showPrice): array
    {
        $purchase->loadMissing('items.product');
        $items = $purchase->items->keyBy('id');
        $copiesByItem = array_filter(array_map('intval', $copiesByItem), fn (int $copies) => $copies > 0);

        $labels = [];
        foreach ($copiesByItem as $itemId => $copies) {
            $product = $items->get($itemId)?->product;
            if ($product === null) {
                throw new DomainException('أحد الأصناف المحددة لا يتبع هذه الفاتورة.');
            }

            $labels[] = $this->makeLabel($product, $copies, $size, $showPrice);
        }

        $this->assertPrintableCount($labels);

        return $labels;
    }

    public function forProduct(Product $product, int $copies, BarcodeLabelSize $size, bool $showPrice): array
    {
        $labels = [$this->makeLabel($product, $copies, $size, $showPrice)];
        $this->assertPrintableCount($labels);

        return $labels;
    }

    /**
     * A product sold by liter/kg/meter has no one-label-per-unit meaning (20 L may be 5 cans), so
     * only piece products suggest one label per received unit.
     */
    private function suggestedCopies(PurchaseInvoiceItem $item): int
    {
        $netMilli = $this->netReceivedMilli($item);
        if ($netMilli <= 0) {
            return 0;
        }

        if ($item->product->allowsFraction()) {
            return 1;
        }

        return min(intdiv($netMilli, Quantity::SCALE), self::MAX_LABELS);
    }

    private function netReceivedMilli(PurchaseInvoiceItem $item): int
    {
        return Quantity::toMilli($item->quantity) - Quantity::toMilli($item->returned_quantity);
    }

    /**
     * The value the label encodes. It must be one the POS scanner lookup resolves (barcode or sku,
     * see PosController::searchProducts), and Code 128 only encodes printable ASCII.
     */
    private function printableCode(Product $product): ?string
    {
        foreach ([$product->barcode, $product->sku] as $candidate) {
            $code = trim((string) $candidate);
            if ($code !== '' && preg_match('/^[\x20-\x7E]+$/D', $code) === 1) {
                return $code;
            }
        }

        return null;
    }

    private function makeLabel(Product $product, int $copies, BarcodeLabelSize $size, bool $showPrice): BarcodeLabel
    {
        $code = $this->printableCode($product);
        if ($code === null) {
            throw new DomainException("الصنف ({$product->name}) ليس له باركود أو كود بحروف وأرقام إنجليزية؛ عدّل بياناته أولاً.");
        }

        $barcode = (new TypeCode128())->getBarcode($code);
        $moduleWidth = $this->moduleWidthMm($barcode->getWidth(), $size);
        $widthMm = round($barcode->getWidth() * $moduleWidth, 3);

        $svg = (new SvgRenderer())
            ->setSvgType(SvgRenderer::TYPE_SVG_INLINE)
            ->render($barcode, $widthMm, $size->barcodeHeightMm());

        // The renderer's user units are already millimetres; the root size makes that physical.
        $svg = preg_replace('/<svg width="([\d.]+)" height="([\d.]+)"/', '<svg width="$1mm" height="$2mm" shape-rendering="crispEdges"', $svg, 1);

        return new BarcodeLabel(
            company: self::COMPANY_NAME,
            title: $this->title($product),
            code: $code,
            // A data URI in <img> stays vector when printed, and an image cannot run markup or scripts.
            barcodeImage: 'data:image/svg+xml;base64,'.base64_encode($svg),
            isDense: $moduleWidth < min(self::MODULE_WIDTHS_MM),
            price: $showPrice ? (float) $product->retail_price : null,
            warrantyMonths: (int) $product->warranty_months,
            copies: $copies,
        );
    }

    private function moduleWidthMm(int $modules, BarcodeLabelSize $size): float
    {
        $available = $size->printableWidthMm() / ($modules + self::QUIET_ZONE_MODULES);

        foreach (self::MODULE_WIDTHS_MM as $width) {
            if ($width <= $available) {
                return $width;
            }
        }

        return $available;
    }

    private function title(Product $product): string
    {
        $name = trim((string) $product->name);
        $capacity = trim((string) $product->capacity_ah);

        return $capacity === '' || $this->nameHoldsCapacity($name, $capacity) ? $name : "{$name} {$capacity}";
    }

    /**
     * An amp capacity counts as present when its number is in the name however the unit is written
     * ("70Ah" in "كلورايد 70 أمبير"), as a whole number only: 70 is not in "170". Any other
     * specification ("5W-40") has to appear as written.
     */
    private function nameHoldsCapacity(string $name, string $capacity): bool
    {
        $westernDigits = array_combine(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], range(0, 9));
        $name = strtr($name, $westernDigits);
        $capacity = strtr($capacity, $westernDigits);

        if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:ah|أمبير|امبير)?$/iu', $capacity, $match) === 1) {
            return preg_match('/(?<!\d)(?<!\d\.)'.preg_quote($match[1], '/').'(?!\.?\d)/u', $name) === 1;
        }

        return mb_stripos($name, $capacity) !== false;
    }

    /** @param list<BarcodeLabel> $labels */
    private function assertPrintableCount(array $labels): void
    {
        $total = array_sum(array_map(fn (BarcodeLabel $label) => $label->copies, $labels));

        if ($total < 1) {
            throw new DomainException('حدد ملصقاً واحداً على الأقل للطباعة.');
        }

        if ($total > self::MAX_LABELS) {
            throw new DomainException('الحد الأقصى ' . self::MAX_LABELS . " ملصق في أمر الطباعة الواحد (المطلوب {$total}). قسّم الطباعة على أكثر من مرة.");
        }
    }
}
