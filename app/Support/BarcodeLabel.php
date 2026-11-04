<?php

namespace App\Support;

/**
 * One product's label, ready for the print view, and how many copies of it to print.
 */
final readonly class BarcodeLabel
{
    public function __construct(
        public string $company,
        public string $title,
        public string $code,
        public string $barcodeImage,
        public bool $isDense,
        public ?float $price,
        public int $warrantyMonths,
        public int $copies,
    ) {}
}
