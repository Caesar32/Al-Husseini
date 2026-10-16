<?php

/*
 * Single-writer guard (Phase 2, ARC-04): only StockLedgerService may write products.current_stock,
 * so every change is journaled in stock_movements.
 */

const STOCK_WRITE_PATTERN = '/(increment|decrement)\(\s*\'current_stock\'|update\(\s*\[[^\]]*\'current_stock\'\s*=>|->current_stock\s*=(?!=)/';

/** Files that may contain the pattern, with the reason. */
const STOCK_WRITE_EXCLUDED = [
    'app/Services/Catalog/StockLedgerService.php',   // the single writer
    'app/Observers/InvoiceObserver.php',             // dead code, never registered
    'app/Observers/PurchaseInvoiceObserver.php',     // dead code, never registered
    'app/Models/Product.php',                        // $fillable / $casts
];

/** @return list<string> "file:line" of each match */
function stockWriteViolations(string $relativePath, string $code): array
{
    preg_match_all(STOCK_WRITE_PATTERN, $code, $matches, PREG_OFFSET_CAPTURE);

    return array_map(
        fn (array $m) => $relativePath.':'.(substr_count(substr($code, 0, $m[1]), "\n") + 1),
        $matches[0]
    );
}

test('the stock write pattern catches every known way of writing current_stock', function (string $code) {
    expect(stockWriteViolations('sample.php', $code))->toHaveCount(1);
})->with([
    "\$product->decrement('current_stock', 2);",
    "\$product->increment( 'current_stock');",
    "\$product->update(['cost_price' => 1,\n    'current_stock' => 5]);",
    '$product->current_stock = 7;',
]);

test('the stock write pattern ignores reads and comparisons', function (string $code) {
    expect(stockWriteViolations('sample.php', $code))->toBe([]);
})->with([
    'if ($product->current_stock == 0) {}',
    '$stock = (int) $product->current_stock;',
    "Product::where('current_stock', '>', 0);",
]);

test('no code outside StockLedgerService writes products.current_stock', function () {
    $root = dirname(__DIR__, 2);
    $violations = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
        if (in_array($relative, STOCK_WRITE_EXCLUDED, true)) {
            continue;
        }
        $violations = [...$violations, ...stockWriteViolations($relative, file_get_contents($file->getPathname()))];
    }

    expect($violations)->toBe([], 'Direct current_stock writes found; use StockLedgerInterface: '.implode(', ', $violations));
});
