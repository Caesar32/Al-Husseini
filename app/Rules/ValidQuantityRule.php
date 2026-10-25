<?php

namespace App\Rules;

use App\Models\Product;
use App\Support\Quantity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A sale/purchase/return quantity: above zero, at most 3 decimals, within the column limit,
 * and whole when the product is sold by the piece. Messages come from Quantity::validationError(),
 * which the services use too.
 */
class ValidQuantityRule implements ValidationRule
{
    public function __construct(
        private ?Product $product = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $error = Quantity::validationError(
            $value,
            $this->product !== null && ! $this->product->allowsFraction(),
            (string) $this->product?->name
        );

        if ($error !== null) {
            $fail($error);
        }
    }
}
