<?php

namespace App\Enums;

enum ProductUnit: string
{
    case Piece = 'piece';
    case Liter = 'liter';
    case Kg = 'kg';
    case Meter = 'meter';

    public function label(): string
    {
        return match ($this) {
            self::Piece => 'قطعة',
            self::Liter => 'لتر',
            self::Kg => 'كجم',
            self::Meter => 'متر',
        };
    }

    public function allowsFraction(): bool
    {
        return $this !== self::Piece;
    }

    public function stepperIncrement(): int|float
    {
        return $this === self::Piece ? 1 : 0.25;
    }
}
