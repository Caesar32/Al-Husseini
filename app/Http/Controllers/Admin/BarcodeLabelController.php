<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Catalog\BarcodeLabelServiceInterface;
use App\Enums\BarcodeLabelSize;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Labels\PrintProductLabelsRequest;
use App\Http\Requests\Admin\Labels\PrintPurchaseLabelsRequest;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Support\BarcodeLabel;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BarcodeLabelController extends Controller
{
    public function __construct(
        protected BarcodeLabelServiceInterface $labels
    ) {}

    public function purchase(PrintPurchaseLabelsRequest $request, PurchaseInvoice $purchase): View|RedirectResponse
    {
        $size = $request->labelSize();

        try {
            $labels = $this->labels->forPurchase($purchase, $request->validated('items'), $size, $request->showPrice());
        } catch (DomainException $e) {
            return redirect()->route('admin.purchases.show', $purchase)->withErrors(['labels' => $e->getMessage()]);
        }

        return $this->printView($labels, $size, "ملصقات توريدة {$purchase->invoice_number}");
    }

    public function product(PrintProductLabelsRequest $request, Product $product): View|RedirectResponse
    {
        $size = $request->labelSize();

        try {
            $labels = $this->labels->forProduct($product, (int) $request->validated('copies'), $size, $request->showPrice());
        } catch (DomainException $e) {
            return redirect()->route('admin.sales.products')->withErrors(['labels' => $e->getMessage()]);
        }

        return $this->printView($labels, $size, "ملصقات {$product->name}");
    }

    /** @param list<BarcodeLabel> $labels */
    private function printView(array $labels, BarcodeLabelSize $size, string $title): View
    {
        return view('admin.labels.print', compact('labels', 'size', 'title'));
    }
}
