<?php

namespace App\Console\Commands;

use App\Contracts\Catalog\ProductServiceInterface;
use App\Enums\ProductUnit;
use App\Models\Product;
use App\Support\Quantity;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Converts oils sold by the sealed package into liter products: stock, prices and old document
 * lines (see ProductService::convertToLiters). Dry run by default.
 *
 * Sizes come from --file (.xlsx with columns sku, package_size). Without it, only oils whose name
 * states a size are taken. The "oils" category also holds filters and pumps, so a category alone
 * is never enough to convert a product.
 */
class ConvertOilsToLitersCommand extends Command
{
    protected $signature = 'products:convert-oils-to-liters
        {--file= : .xlsx with columns sku, package_size}
        {--apply : Convert, after saving a report of the previous values}';

    protected $description = 'Convert packaged oils to liter products (dry run unless --apply)';

    private const SIZE_IN_NAME = '/(\d+(?:[.,]\d+)?)\s*(?:لتر|ليتر|L\b)/ui';
    private const NOT_AN_OIL = '/فلتر|فلاتر|طلمبة|طرمبة|جوان|بلف|غطا|مانع|حساس|خرطوم|طاسة/u';

    public function handle(ProductServiceInterface $products): int
    {
        $file = (string) $this->option('file');
        if ($file !== '' && ! is_file($file)) {
            $this->error('--file must point to an existing .xlsx file.');

            return self::FAILURE;
        }

        $candidates = $file !== '' ? $this->candidatesFromFile($file) : $this->candidatesFromNames();

        $this->table(['SKU', 'Size (L)', 'Stock now', 'Stock in liters', 'Retail now', 'Per liter'], $candidates->map(fn (array $c) => [
            $c['product']->sku,
            $c['size'],
            Quantity::format($c['product']->current_stock),
            Quantity::format(Quantity::fromMilli((int) round(Quantity::toMilli($c['product']->current_stock) * $c['size']))),
            number_format((float) $c['product']->retail_price, 2),
            number_format((float) $c['product']->retail_price / $c['size'], 2),
        ])->all());
        $this->info('To convert: '.$candidates->count().'. Oils left as they are: '.$this->remainingOils($candidates).'.');

        if (! $this->option('apply')) {
            $this->warn('DRY RUN: nothing was written. Re-run with --apply to convert.');

            return self::SUCCESS;
        }

        $report = storage_path('app/oil-conversion-'.now()->format('Ymd-His').'.json');
        file_put_contents($report, $candidates->map(fn (array $c) => [
            'id' => $c['product']->id,
            'sku' => $c['product']->sku,
            'size' => $c['size'],
            'before' => $c['product']->only(['current_stock', 'retail_price', 'wholesale_price', 'cost_price', 'reorder_threshold', 'unit']),
        ])->values()->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info('Previous values saved: '.$report);

        $failures = 0;
        foreach ($candidates as $candidate) {
            try {
                $products->convertToLiters($candidate['product'], $candidate['size']);
            } catch (DomainException $e) {
                $failures++;
                $this->error($candidate['product']->sku.': '.$e->getMessage());
            }
        }

        $this->info('Converted: '.($candidates->count() - $failures).'.');

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return Collection<int, array{product: Product, size: float}> */
    private function candidatesFromNames(): Collection
    {
        return $this->convertibleOils()->map(function (Product $product) {
            if (preg_match(self::NOT_AN_OIL, $product->name) || ! preg_match(self::SIZE_IN_NAME, $product->name, $match)) {
                return null;
            }

            return ['product' => $product, 'size' => (float) str_replace(',', '.', $match[1])];
        })->filter(fn (?array $c) => $c !== null && $c['size'] > 0)->values();
    }

    /** @return Collection<int, array{product: Product, size: float}> */
    private function candidatesFromFile(string $file): Collection
    {
        $reader = new XlsxReader(new XlsxOptions);
        $reader->open($file);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $header = null;
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(fn ($c) => trim((string) $c), $row->toArray());
                if ($header === null) {
                    $header = array_map('strtolower', $cells);

                    continue;
                }
                $rows[] = array_combine($header, array_pad($cells, count($header), ''));
            }
            break;
        }
        $reader->close();

        return collect($rows)->map(function (array $row) {
            $product = Product::where('sku', $row['sku'] ?? '')->where('unit', ProductUnit::Piece->value)->first();
            $size = (float) ($row['package_size'] ?? 0);
            if (! $product || $size <= 0) {
                $this->warn('Skipped: '.($row['sku'] ?? '?').' (not found, already converted, or no size)');

                return null;
            }

            return ['product' => $product, 'size' => $size];
        })->filter()->values();
    }

    private function convertibleOils(): Collection
    {
        return Product::where('unit', ProductUnit::Piece->value)
            ->where('is_battery', false)
            ->whereHas('category', fn ($q) => $q->where('slug', 'oils'))
            ->get();
    }

    private function remainingOils(Collection $candidates): int
    {
        return $this->convertibleOils()->whereNotIn('id', $candidates->pluck('product.id'))->count();
    }
}
