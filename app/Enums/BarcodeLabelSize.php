<?php

namespace App\Enums;

/**
 * Physical label stocks of the Xprinter XP-237B (2 inch head), wider than tall as they sit in the
 * printer. Every printed label is one page of this size, so the printer advances exactly one label
 * per page and no stock is wasted between labels.
 * Each case has a stylesheet public/assets/css/admin/labels/label-<value>.css holding its @page rule.
 */
enum BarcodeLabelSize: string
{
    case Medium = '50x25';
    case Small = '40x25';

    public const DEFAULT = self::Medium;

    public function label(): string
    {
        return match ($this) {
            self::Medium => 'ملصق 50 × 25 مم',
            self::Small => 'ملصق 40 × 25 مم',
        };
    }

    public function widthMm(): float
    {
        return match ($this) {
            self::Medium => 50,
            self::Small => 40,
        };
    }

    public function heightMm(): float
    {
        return 25;
    }

    /**
     * Measured on a printed label: 1.5 mm clipped the characters at the left edge.
     * The layout check accepts a minimum of 2 mm.
     */
    public function sidePaddingMm(): float
    {
        return match ($this) {
            self::Medium => 2.5,
            self::Small => 2.0,
        };
    }

    public function printableWidthMm(): float
    {
        return $this->widthMm() - 2 * $this->sidePaddingMm();
    }

    public function barcodeHeightMm(): float
    {
        return match ($this) {
            self::Medium => 10,
            self::Small => 9.5,
        };
    }
}
