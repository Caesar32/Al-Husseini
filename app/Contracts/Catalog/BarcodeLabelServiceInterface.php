<?php

namespace App\Contracts\Catalog;

use App\Enums\BarcodeLabelSize;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Support\BarcodeLabel;

interface BarcodeLabelServiceInterface
{
    /** Most labels one print job may hold; larger shipments are printed in several jobs. */
    public const MAX_LABELS = 1000;

    /**
     * The invoice lines offered for printing, each with the suggested number of labels.
     *
     * @return list<array{item_id: int, product: Product, quantity: int|float, code: ?string, suggested: int}>
     */
    public function purchaseLines(PurchaseInvoice $purchase): array;

    /**
     * @param  array<int|string, int|string>  $copiesByItem  purchase_invoice_items.id => labels
     * @return list<BarcodeLabel>
     *
     * @throws \DomainException
     */
    public function forPurchase(PurchaseInvoice $purchase, array $copiesByItem, BarcodeLabelSize $size, bool $showPrice): array;

    /**
     * @return list<BarcodeLabel>
     *
     * @throws \DomainException
     */
    public function forProduct(Product $product, int $copies, BarcodeLabelSize $size, bool $showPrice): array;
}
