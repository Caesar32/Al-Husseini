<?php

namespace App\Enums;

/**
 * Physical label stocks the print page supports. Every printed label is one page of this size,
 * so the printer advances exactly one label per page and no stock is wasted between labels.
 * Each case has a stylesheet public/assets/css/admin/labels/label-<value>.css holding its @page rule.
 */
enum BarcodeLabelSize: string
{
    case Medium = '50x25';
    case Small = '40x25';
    case Roll80 = '80x30';

    public function label(): string
    {
        return match ($this) {
            self::Medium => 'ملصق 50 × 25 مم',
            self::Small => 'ملصق 40 × 25 مم',
            self::Roll80 => 'رول حراري 80 مم (ملصق بطول 30 مم)',
        };
    }

    public function widthMm(): float
    {
        return match ($this) {
            self::Medium => 50,
            self::Small => 40,
            self::Roll80 => 80,
        };
    }

    public function heightMm(): float
    {
        return match ($this) {
            self::Medium, self::Small => 25,
            self::Roll80 => 30,
        };
    }

    /** Side padding; a roll printer's print head is narrower than its 80 mm paper. */
    public function sidePaddingMm(): float
    {
        return $this === self::Roll80 ? 4 : 1.5;
    }

    public function printableWidthMm(): float
    {
        return $this->widthMm() - 2 * $this->sidePaddingMm();
    }

    public function barcodeHeightMm(): float
    {
        return $this === self::Roll80 ? 11 : 8;
    }
}
