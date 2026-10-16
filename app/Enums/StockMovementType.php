<?php

namespace App\Enums;

/**
 * Kinds of stock_movements rows. Each change to products.current_stock goes through
 * StockLedgerService with one of these; a new kind of movement is a new case.
 */
enum StockMovementType: string
{
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case Purchase = 'purchase';
    case PurchaseReturn = 'purchase_return';
    case WarrantyReplacement = 'warranty_replacement';
    case SupplierReplacement = 'supplier_replacement';
    case Adjustment = 'adjustment';
}
