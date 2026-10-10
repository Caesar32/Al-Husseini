<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Psr\Log\LoggerInterface;
use Throwable;

class ImportStockCommand extends Command
{
    protected $signature = 'inventory:import-stock
        {--file= : Path to the counted inventory .xlsx (columns: sku, actual_stock; optional barcode)}
        {--dry-run : Validate and report only, write nothing (this is also the default without --apply)}
        {--apply : Write current_stock after saving a JSON backup of the previous values}';

    protected $description = 'Set products.current_stock from a physical-count spreadsheet (sku -> actual_stock)';

    private LoggerInterface $log;

    public function handle(): int
    {
        if ($this->option('dry-run') && $this->option('apply')) {
            $this->error('Use either --dry-run or --apply, not both.');
            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');

        $file = (string) $this->option('file');
        if ($file === '' || !is_file($file)) {
            $this->error('--file is required and must point to an existing .xlsx file.');
            return self::FAILURE;
        }

        $this->log = Log::build([
            'driver' => 'single',
            'path'   => storage_path('logs/stock_import.log'),
            'level'  => 'debug',
        ]);
        $this->log->info('--- stock import started', ['file' => $file, 'apply' => $apply]);

        $stats = ['rows' => 0, 'blank' => 0, 'invalid' => 0, 'duplicate' => 0, 'not_found' => 0, 'matched' => 0, 'by_barcode' => 0];
        $updates = []; // product id => [sku, old, new]
        $seen = [];

        $reader = new XlsxReader(new XlsxOptions());
        $reader->open($file);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getIndex() !== 0) {
                    break;
                }
                $cols = null;
                $line = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = $row->toArray();
                    if ($cols === null) {
                        $cols = $this->mapHeader($cells);
                        if (!isset($cols['sku']) || !isset($cols['actual_stock'])) {
                            $this->error('Header row must contain "sku" and "actual_stock" columns.');
                            return self::FAILURE;
                        }
                        continue;
                    }
                    $this->processRow($cells, $cols, $line, $stats, $updates, $seen);
                }
            }
        } finally {
            $reader->close();
        }

        $changed = array_filter($updates, fn ($u) => $u['old'] !== $u['new']);
        $unchanged = count($updates) - count($changed);

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Data rows in file', $stats['rows']],
            ['Blank actual_stock (not counted, ignored)', $stats['blank']],
            ['Invalid value (negative/fractional/non-numeric/over limit)', $stats['invalid']],
            ['Duplicate sku rows skipped', $stats['duplicate']],
            ['Not found in products', $stats['not_found']],
            ['Matched products', $stats['matched'] . ' (' . $stats['by_barcode'] . ' via barcode)'],
            ['  - stock will change', count($changed)],
            ['  - already equal', $unchanged],
            ['  - of which new stock = 0', count(array_filter($updates, fn ($u) => $u['new'] === 0))],
        ]);
        $this->line('Details: ' . storage_path('logs/stock_import.log'));

        if (!$apply) {
            $this->warn('DRY RUN: nothing was written. Re-run with --apply to save.');
            return self::SUCCESS;
        }

        if (empty($changed)) {
            $this->info('Nothing to update.');
            return self::SUCCESS;
        }

        $backup = $this->writeBackup($changed);
        $this->info('Backup saved: ' . $backup);

        $written = 0;
        try {
            foreach (array_chunk($changed, 500, true) as $chunk) {
                DB::transaction(function () use ($chunk, &$written) {
                    foreach ($chunk as $id => $u) {
                        $product = Product::whereKey($id)->lockForUpdate()->first();
                        if (!$product) {
                            $this->log->warning('Product vanished before write', ['id' => $id, 'sku' => $u['sku']]);
                            continue;
                        }
                        $product->current_stock = $u['new'];
                        $product->save();
                        $written++;
                    }
                });
            }
        } catch (Throwable $e) {
            $this->log->error('Apply failed', ['error' => $e->getMessage(), 'written_before_failure' => $written]);
            $this->error('Apply failed after ' . $written . ' rows (completed chunks are kept): ' . $e->getMessage());
            $this->line('Restore from backup if needed: ' . $backup);
            return self::FAILURE;
        }

        $this->log->info('--- stock import applied', ['updated' => $written, 'backup' => $backup]);
        $this->info("Updated {$written} products.");
        return self::SUCCESS;
    }

    /** @param array<int, mixed> $cells */
    private function mapHeader(array $cells): array
    {
        $map = [];
        foreach ($cells as $i => $c) {
            $key = strtolower(trim((string) $c));
            if (in_array($key, ['sku', 'barcode', 'actual_stock'], true) && !isset($map[$key])) {
                $map[$key] = $i;
            }
        }
        return $map;
    }

    private function processRow(array $cells, array $cols, int $line, array &$stats, array &$updates, array &$seen): void
    {
        $sku = trim((string) ($cells[$cols['sku']] ?? ''));
        $barcode = isset($cols['barcode']) ? trim((string) ($cells[$cols['barcode']] ?? '')) : '';
        $raw = $cells[$cols['actual_stock']] ?? null;

        if ($sku === '' && $barcode === '') {
            return; // fully empty line
        }
        $stats['rows']++;

        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            $stats['blank']++;
            return;
        }

        $qty = $this->parseQuantity($raw);
        if ($qty === null) {
            $stats['invalid']++;
            $this->log->warning('Invalid quantity', ['line' => $line, 'sku' => $sku, 'value' => is_scalar($raw) ? $raw : gettype($raw)]);
            return;
        }

        $product = $sku !== '' ? Product::where('sku', $sku)->first() : null;
        $viaBarcode = false;
        if (!$product && $barcode !== '') {
            $product = Product::where('barcode', $barcode)->first();
            $viaBarcode = $product !== null;
        }
        if (!$product) {
            $stats['not_found']++;
            $this->log->warning('Product not found', ['line' => $line, 'sku' => $sku, 'barcode' => $barcode]);
            return;
        }

        if (isset($seen[$product->id])) {
            $stats['duplicate']++;
            $this->log->warning('Duplicate row for product skipped (first value kept)', ['line' => $line, 'sku' => $product->sku, 'first_line' => $seen[$product->id], 'value' => $qty]);
            return;
        }
        $seen[$product->id] = $line;

        $stats['matched']++;
        if ($viaBarcode) {
            $stats['by_barcode']++;
            $this->log->info('Matched by barcode (sku did not match)', ['line' => $line, 'sku' => $sku, 'barcode' => $barcode, 'product_sku' => $product->sku]);
        }
        $updates[$product->id] = ['sku' => $product->sku, 'old' => (int) $product->current_stock, 'new' => $qty];
    }

    /** Whole, non-negative number within unsigned-int range, else null. */
    private function parseQuantity(mixed $raw): ?int
    {
        if (is_string($raw)) {
            $raw = strtr(trim($raw), ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '٬' => '', ',' => '']);
            if (!is_numeric($raw)) {
                return null;
            }
        } elseif (!is_int($raw) && !is_float($raw)) {
            return null;
        }
        $n = (float) $raw;
        if ($n < 0 || $n > 4294967295 || abs($n - round($n)) > 1e-9) {
            return null;
        }
        return (int) round($n);
    }

    private function writeBackup(array $changed): string
    {
        $dir = storage_path('app/private/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . 'stock_backup_' . now()->format('Ymd_His') . '.json';
        $rows = [];
        foreach ($changed as $id => $u) {
            $rows[] = ['id' => $id, 'sku' => $u['sku'], 'previous_stock' => $u['old'], 'new_stock' => $u['new']];
        }
        file_put_contents($path, json_encode(['created_at' => now()->toIso8601String(), 'products' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $path;
    }
}
