<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Category;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\JobTitle;
use App\Models\Product;
use App\Models\SalaryStructure;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ScrapPricingTiersSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * One-time migration of the legacy "Al-Husseini" ERP Excel export into the current schema.
 *
 * Source files live in `البيانات_المستخرجة/` (sheet column names verified against the real
 * export; several differ from what the original migration brief assumed — see the per-step
 * methods for the resolved mapping). Scope is deliberately limited to the sheets named below:
 * extra sheets in the same workbooks (discounts, returns, disbursement vouchers, attendance,
 * expenses) and files 6/7 (project estimates; an unrelated/empty wood-barcode export) are not
 * imported and are reported as skipped in the final summary.
 */
class ImportLegacyDataCommand extends Command
{
    protected $signature = 'app:import-legacy-data
                            {--fresh : Wipe operational/mock tables first (writes a mysqldump backup before truncating)}
                            {--dry-run : Run every step inside a transaction that is always rolled back}
                            {--step=all : wipe|categories|suppliers|customers|products|invoices|payments|employees|all}
                            {--chunk=1000 : Row batch size per committed transaction}
                            {--data-path= : Override the legacy Excel directory}
                            {--mysqldump-path=mysqldump : Path to the mysqldump binary used for the pre-wipe backup}
                            {--branch= : Branch id to attach imported records to (default: first branch)}
                            {--user= : User id to attach as cashier/collector/receiver (default: first user)}';

    protected $description = 'Purge mock/operational data and import the legacy ERP Excel export into the current schema';

    private const WHITELISTED_TABLES = [
        'users', 'roles', 'permissions', 'model_has_roles', 'role_has_permissions',
        'model_has_permissions', 'branches', 'migrations',
    ];

    private const TRUNCATE_TABLES = [
        // Sales & ledger
        'invoice_payments', 'credit_ledger_entries', 'invoice_items', 'invoices',
        // Catalog
        'supplier_products', 'products', 'categories',
        // Entities
        'customer_vehicles', 'customers', 'supplier_ledger_entries', 'purchase_invoice_items',
        'purchase_invoices', 'suppliers',
        // Operations
        'scrap_sales', 'scrap_batteries_inventory', 'scrap_pricing_tiers', 'warranty_claims', 'warranties',
        // HR & commissions
        'technician_commissions', 'employee_payroll_debts', 'payroll_items', 'payrolls',
        'employee_deductions', 'attendances', 'salary_structures', 'employees',
    ];

    /**
     * name => [slug, keywords]. Slugs for "oils" and "batteries" intentionally match the ones
     * DashboardController/SystemDiagnosticService already filter on (category whereHas slug=);
     * a Str::slug() transliteration of the Arabic names would silently break those stat cards.
     */
    private const CATEGORY_KEYWORDS = [
        'زيوت وشحومات'  => ['slug' => 'oils',       'keywords' => ['زيت', 'شحم', 'جريس']],
        'فلاتر'          => ['slug' => 'filters',    'keywords' => ['فلتر', 'فلاتر']],
        'تيل وفرامل'     => ['slug' => 'brakes',     'keywords' => ['تيل', 'فرامل', 'دسك', 'طنبورة']],
        'عفشة وبلي'      => ['slug' => 'suspension', 'keywords' => ['عفشة', 'بلي', 'مقص', 'كرسي']],
        'سيور'           => ['slug' => 'belts',      'keywords' => ['سير', 'سيور', 'طرمبة مياه']],
        'كهرباء'         => ['slug' => 'electrical', 'keywords' => ['كهرب', 'دينامو', 'سلف', 'فيشة', 'شمعة']],
        'محرك'           => ['slug' => 'engine',     'keywords' => ['محرك', 'جلبة', 'طلمبة زيت', 'كاتينة', 'سلندر']],
        'تبريد وتكييف'   => ['slug' => 'cooling-ac', 'keywords' => ['تبريد', 'تكييف', 'رديتر', 'مروحة', 'كمبروسر']],
        'بطاريات'        => ['slug' => 'batteries',  'keywords' => ['بطارية', 'كلورايد', 'فارتا']],
    ];

    private const DEFAULT_CATEGORY = 'عام / متنوعة';
    private const DEFAULT_CATEGORY_SLUG = 'general';

    private const BATTERY_PATTERN = '/بطارية|كلورايد|فارتا|ah/iu';

    private const ANOMALY_MAX_LINE_VALUE = 200000.0;
    private const ANOMALY_BAD_QTY = 103304;

    private const POS_CUSTOMER_PHONE = '01099999999';
    private const POS_CUSTOMER_NAME = 'عميل نقدي عام (POS)';

    private LoggerInterface $log;
    private string $dataPath;
    private bool $dryRun;
    private int $chunk;
    private Branch $branch;
    private User $user;

    private array $stats = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->chunk = max(1, (int) $this->option('chunk'));
        $this->dataPath = rtrim((string) ($this->option('data-path') ?: 'D:\\Projects\\Al-Husseini\\البيانات_المستخرجة'), '\\/') . DIRECTORY_SEPARATOR;
        $this->log = Log::build([
            'driver' => 'single',
            'path'   => storage_path('logs/legacy_import.log'),
            'level'  => 'debug',
        ]);

        $step = (string) $this->option('step');
        $validSteps = ['wipe', 'categories', 'suppliers', 'customers', 'products', 'invoices', 'payments', 'employees', 'all'];
        if (!in_array($step, $validSteps, true)) {
            $this->error("Invalid --step. Expected one of: " . implode('|', $validSteps));
            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('DRY RUN: no data will be committed. Every step runs inside a transaction that is rolled back at the end.');
        }

        try {
            if ($step === 'wipe') {
                $this->wipeMockData();
                return self::SUCCESS;
            }

            if ($step === 'all' && $this->option('fresh')) {
                $this->wipeMockData();
            }

            // Steps after "wipe" need a branch/user to attach records to.
            if ($step !== 'wipe') {
                $this->branch = $this->resolveDefaultBranch();
                $this->user = $this->resolveDefaultUser();
            }

            if (in_array($step, ['all', 'categories'], true)) {
                $this->importCategories();
            }
            if (in_array($step, ['all', 'suppliers'], true)) {
                $this->importSuppliers();
            }
            if (in_array($step, ['all', 'customers'], true)) {
                $this->importCustomers();
            }
            if (in_array($step, ['all', 'products'], true)) {
                $this->importProducts();
            }
            if (in_array($step, ['all', 'invoices'], true)) {
                $this->importWholesaleInvoices();
                $this->importRetailInvoices();
            }
            if (in_array($step, ['all', 'payments'], true)) {
                $this->importWholesalePayments();
                $this->importRetailPayments();
            }
            if (in_array($step, ['all', 'employees'], true)) {
                $this->importEmployees();
            }
        } catch (Throwable $e) {
            $this->error('Import aborted: ' . $e->getMessage());
            $this->log->error('Import aborted', ['exception' => (string) $e]);
            return self::FAILURE;
        }

        $this->printVerificationSummary();

        return self::SUCCESS;
    }

    // ───────────────────────────────────────────── Phase 0: safe wipe ─────────────────────

    private function wipeMockData(): void
    {
        $tableCounts = [];
        foreach (self::TRUNCATE_TABLES as $table) {
            $tableCounts[$table] = DB::table($table)->count();
        }

        $this->table(['Table', 'Rows to truncate'], collect($tableCounts)->map(fn ($c, $t) => [$t, $c])->values()->all());

        if ($this->dryRun) {
            $this->info('DRY RUN: wipe reported above, nothing truncated.');
            return;
        }

        $backupPath = $this->backupDatabase((string) $this->option('mysqldump-path'));
        $this->info("Pre-wipe backup written: {$backupPath}");

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        try {
            foreach (self::TRUNCATE_TABLES as $table) {
                DB::table($table)->truncate();
                $this->log->info("Truncated {$table}", ['rows_removed' => $tableCounts[$table]]);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        // Scrap pricing tiers are business configuration, not transactional mock data, and the
        // legacy export has no source for them: restore the owner's real defaults (idempotent).
        (new ScrapPricingTiersSeeder())->run();

        $this->info('Wipe complete. Whitelisted tables (' . implode(', ', self::WHITELISTED_TABLES) . ') were never touched.');
    }

    private function backupDatabase(string $mysqldumpPath): string
    {
        $connection = config('database.default');
        if (config("database.connections.{$connection}.driver") !== 'mysql') {
            throw new RuntimeException("--fresh requires the '{$connection}' connection to be MySQL; a backup cannot be taken otherwise.");
        }

        if ($mysqldumpPath === 'mysqldump' && PHP_OS_FAMILY === 'Windows') {
            $candidatePaths = [
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysqldump.exe',
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\laragon\\bin\\mysql\\bin\\mysqldump.exe',
            ];
            foreach ($candidatePaths as $candidate) {
                if (file_exists($candidate)) {
                    $mysqldumpPath = $candidate;
                    break;
                }
            }
        }

        $cfg = config("database.connections.{$connection}");
        $dir = storage_path('app/legacy-import-backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . 'pre-wipe-' . now()->format('Ymd-His') . '.sql';

        // Password via MYSQL_PWD (not a CLI arg) so it never shows up in the process list.
        $result = Process::env(['MYSQL_PWD' => $cfg['password']])->run([
            $mysqldumpPath,
            '--host=' . $cfg['host'],
            '--port=' . $cfg['port'],
            '--user=' . $cfg['username'],
            '--single-transaction',
            '--routines',
            '--result-file=' . $path,
            $cfg['database'],
        ]);

        if (!$result->successful() || !is_file($path) || filesize($path) === 0) {
            throw new RuntimeException(
                "Database backup failed (mysqldump exit code {$result->exitCode()}): {$result->errorOutput()}\n" .
                'Wipe aborted; nothing was truncated. Pass --mysqldump-path if the binary is not on PATH.'
            );
        }

        return $path;
    }

    // ───────────────────────────────────────────── Shared helpers ─────────────────────────

    private function resolveDefaultBranch(): Branch
    {
        $id = $this->option('branch');
        $branch = $id ? Branch::find($id) : (Branch::where('code', 'MAIN')->first() ?? Branch::orderBy('id')->first());

        if (!$branch) {
            throw new RuntimeException('No branch found to attach imported records to. Seed at least one branch first.');
        }

        return $branch;
    }

    private function resolveDefaultUser(): User
    {
        $id = $this->option('user');
        $user = $id ? User::find($id) : User::orderBy('id')->first();

        if (!$user) {
            throw new RuntimeException('No user found to attach as cashier/collector/receiver. Seed at least one user first.');
        }

        return $user;
    }

    /**
     * Wraps $work in a transaction; rolls back unconditionally in dry-run mode.
     */
    private function transact(callable $work): void
    {
        DB::transaction(function () use ($work) {
            $work();

            if ($this->dryRun) {
                // DB::transaction() only commits on a clean return, so throwing here forces a
                // rollback; the sentinel is caught by the caller and treated as success.
                throw new DryRunRollback();
            }
        });
    }

    private function runDryRunSafe(callable $body): void
    {
        try {
            $this->transact($body);
        } catch (DryRunRollback) {
            // Expected in --dry-run: the transaction above already rolled back.
        }
    }

    private function cleanHeader(string $value): string
    {
        $value = str_replace("\u{FEFF}", '', $value);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        return trim($value);
    }

    /**
     * Streams one sheet of an xlsx file as associative arrays keyed by its (cleaned) header row.
     *
     * @return \Generator<int, array<string, string>>
     */
    private function readSheetRows(string $file, string $sheetName): \Generator
    {
        $reader = new XlsxReader(new XlsxOptions());
        $reader->open($this->dataPath . $file);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== $sheetName) {
                    continue;
                }

                $header = [];
                $rowNum = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNum++;
                    $cells = $row->toArray();
                    if ($rowNum === 1) {
                        $header = array_map(fn ($c) => $this->cleanHeader((string) $c), $cells);
                        continue;
                    }

                    $assoc = [];
                    foreach ($header as $i => $name) {
                        $assoc[$name] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
                    }
                    yield $assoc;
                }

                return;
            }
        } finally {
            $reader->close();
        }

        throw new RuntimeException("Sheet '{$sheetName}' not found in {$file}.");
    }

    private function toFloat(?string $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        return (float) str_replace(',', '', $value);
    }

    /**
     * The source only ever carries a date (time is always 00:00:00), so timestamps are
     * normalized to noon rather than midnight: MySQL's session time_zone on this server is
     * SYSTEM (Windows "Egypt Daylight Time", with historical DST rules), and a handful of
     * legacy dates land exactly in a DST spring-forward gap where local midnight doesn't
     * exist — MySQL rejects literal 00:00:00 on those dates for TIMESTAMP columns. Noon is
     * never inside a DST transition window.
     */
    private function parseLegacyDate(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        // Excel epoch sentinels for "no real date" in this export.
        if (str_starts_with($value, '1899-12-30') || str_starts_with($value, '1900-01-01')) {
            return null;
        }
        try {
            return Carbon::parse($value)->setTime(12, 0, 0);
        } catch (Throwable) {
            return null;
        }
    }

    private function firstNonEmpty(array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if ($c !== null && trim((string) $c) !== '') {
                return trim((string) $c);
            }
        }
        return null;
    }

    private function detectCategorySlug(string $productName): string
    {
        foreach (self::CATEGORY_KEYWORDS as $def) {
            foreach ($def['keywords'] as $keyword) {
                if (mb_stripos($productName, $keyword) !== false) {
                    return $def['slug'];
                }
            }
        }
        return self::DEFAULT_CATEGORY_SLUG;
    }

    private function isBatteryName(string $productName): bool
    {
        return (bool) preg_match(self::BATTERY_PATTERN, $productName);
    }

    /**
     * Clamps a line's quantity/price when it matches the known legacy data-entry anomaly
     * (e.g. invoice 3587, item 103304: the item code was typed into both qty and price,
     * producing a ~10.6 billion EGP line). Returns [qty, price, wasClamped].
     *
     * @return array{0:int,1:float,2:bool}
     */
    private function applyAnomalyGuard(int $qty, float $price, float $fallbackPrice): array
    {
        if ($qty === self::ANOMALY_BAD_QTY || ($qty * $price) > self::ANOMALY_MAX_LINE_VALUE) {
            return [1, $fallbackPrice, true];
        }
        return [$qty, $price, false];
    }

    // ───────────────────────────────────────────── Categories ─────────────────────────────

    private function importCategories(): void
    {
        $slugs = array_merge(
            array_map(fn ($def) => $def['slug'], self::CATEGORY_KEYWORDS),
            [self::DEFAULT_CATEGORY_SLUG]
        );
        $names = array_combine($slugs, array_merge(array_keys(self::CATEGORY_KEYWORDS), [self::DEFAULT_CATEGORY]));
        $created = 0;

        $this->runDryRunSafe(function () use ($names, &$created) {
            foreach ($names as $slug => $name) {
                Category::updateOrCreate(['slug' => $slug], ['name' => $name]);
                $created++;
            }
        });

        $this->stats['categories'] = $created;
        $this->info("Categories: {$created} upserted.");
    }

    // ───────────────────────────────────────────── Contacts: suppliers & customers ────────

    private const FILE_CONTACTS = '1_العملاء_وجهات_الاتصال.xlsx';
    private const SHEET_CONTACTS = 'العملاء_وجهات_الاتصال';

    private function importSuppliers(): void
    {
        $count = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar();
        $bar->start();

        foreach ($this->readSheetRows(self::FILE_CONTACTS, self::SHEET_CONTACTS) as $row) {
            $bar->advance();
            if (($row['kindtel'] ?? '') !== 'مورد') {
                continue;
            }

            $name = trim($row['to'] ?? '');
            if ($name === '') {
                $skipped++;
                $this->log->warning('Supplier row skipped: empty name', ['autono' => $row['autono'] ?? null]);
                continue;
            }

            $phone = $this->firstNonEmpty([$row['tel'] ?? null, $row['tel2'] ?? null, $row['tel3'] ?? null]);

            try {
                $this->runDryRunSafe(function () use ($name, $phone, &$count) {
                    Supplier::updateOrCreate(
                        ['name' => $name],
                        [
                            'company_name'    => $name,
                            'phone'           => $phone,
                            'address'         => null,
                            'credit_limit'    => 0,
                            'current_balance' => 0,
                            'is_active'       => true,
                        ]
                    );
                    $count++;
                });
            } catch (Throwable $e) {
                $skipped++;
                $this->log->error('Supplier row failed', ['name' => $name, 'error' => $e->getMessage()]);
            }
        }

        $bar->finish();
        $this->newLine();
        $this->stats['suppliers'] = $count;
        $this->info("Suppliers: {$count} upserted, {$skipped} skipped (see legacy_import.log).");
    }

    private function importCustomers(): void
    {
        $count = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar();
        $bar->start();

        foreach ($this->readSheetRows(self::FILE_CONTACTS, self::SHEET_CONTACTS) as $row) {
            $bar->advance();
            if (($row['kindtel'] ?? '') !== 'عميل') {
                continue;
            }

            $name = trim($row['to'] ?? '');
            if ($name === '') {
                $skipped++;
                $this->log->warning('Customer row skipped: empty name', ['autono' => $row['autono'] ?? null]);
                continue;
            }

            $phone = $this->firstNonEmpty([$row['tel'] ?? null, $row['tel2'] ?? null, $row['tel3'] ?? null]);

            try {
                $this->runDryRunSafe(function () use ($name, $phone, &$count) {
                    Customer::updateOrCreate(
                        ['name' => $name],
                        [
                            'phone'                  => $phone,
                            'tier'                   => 'standard',
                            'credit_limit'           => 5000,
                            'current_credit_balance' => 0,
                            'is_active'              => true,
                        ]
                    );
                    $count++;
                });
            } catch (Throwable $e) {
                $skipped++;
                $this->log->error('Customer row failed', ['name' => $name, 'error' => $e->getMessage()]);
            }
        }

        $bar->finish();
        $this->newLine();

        // Default walk-in customer every retail invoice is linked to.
        $this->runDryRunSafe(function () {
            Customer::updateOrCreate(
                ['phone' => self::POS_CUSTOMER_PHONE],
                [
                    'name'                   => self::POS_CUSTOMER_NAME,
                    'tier'                   => 'standard',
                    'credit_limit'           => 0,
                    'current_credit_balance' => 0,
                    'is_active'              => true,
                ]
            );
        });

        $this->stats['customers'] = $count;
        $this->info("Customers: {$count} upserted (+1 system POS customer), {$skipped} skipped.");
    }

    // ───────────────────────────────────────────── Catalog ────────────────────────────────

    private const FILE_CATALOG = '2_كتالوج_الأصناف_وقطع_الغيار.xlsx';
    private const SHEET_CATALOG = 'الأصناف_وقطع_الغيار';

    private function importProducts(): void
    {
        $count = 0;
        $skipped = 0;
        $batch = [];
        $bar = $this->output->createProgressBar();
        $bar->start();

        foreach ($this->readSheetRows(self::FILE_CATALOG, self::SHEET_CATALOG) as $row) {
            $bar->advance();
            $batch[] = $row;

            if (count($batch) >= $this->chunk) {
                $this->importProductBatch($batch, $count, $skipped);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            $this->importProductBatch($batch, $count, $skipped);
        }

        $bar->finish();
        $this->newLine();
        $this->stats['products'] = $count;
        $this->info("Products: {$count} upserted, {$skipped} skipped (see legacy_import.log).");
    }

    private function importProductBatch(array $rows, int &$count, int &$skipped): void
    {
        $this->runDryRunSafe(function () use ($rows, &$count, &$skipped) {
            foreach ($rows as $row) {
                try {
                    $this->importOneProduct($row, $count, $skipped);
                } catch (Throwable $e) {
                    $skipped++;
                    $this->log->error('Product row failed', ['sku' => $row['code'] ?? null, 'error' => $e->getMessage()]);
                }
            }
        });
    }

    private function importOneProduct(array $row, int &$count, int &$skipped): void
    {
        $sku = trim($row['code'] ?? '');
        $name = trim($row['name'] ?? '');
        if ($sku === '' || $name === '') {
            $skipped++;
            $this->log->warning('Product row skipped: missing code or name', $row);
            return;
        }

        $categorySlug = $this->detectCategorySlug($name);
        $category = Category::where('slug', $categorySlug)->first();
        if (!$category) {
            $skipped++;
            $this->log->warning('Product row skipped: category not found (run --step=categories first)', ['sku' => $sku, 'category' => $categorySlug]);
            return;
        }

        $costPrice = $this->toFloat($row['pr_run1'] ?? null);
        $wholesalePrice = $this->toFloat($row['pr_r2un1'] ?? null);
        if ($wholesalePrice <= 0) {
            $wholesalePrice = round($costPrice * 1.15, 2);
        }
        $retailPrice = $this->toFloat($row['pr_s'] ?? null);
        if ($retailPrice <= 0) {
            $retailPrice = round($costPrice * 1.25, 2);
        }

        $isBattery = $this->isBatteryName($name);
        $barcode = trim($row['code2'] ?? '');

        $product = Product::updateOrCreate(
            ['sku' => $sku],
            [
                'category_id'       => $category->id,
                'barcode'           => $barcode !== '' ? $barcode : null,
                'name'              => $name,
                'brand'             => trim($row['company'] ?? '') ?: 'غير محدد',
                'capacity_ah'       => null,
                'voltage'           => '12V',
                'terminal_type'     => 'regular',
                'warranty_months'   => 12,
                'cost_price'        => $costPrice,
                'retail_price'      => $retailPrice,
                'wholesale_price'   => $wholesalePrice,
                'current_stock'     => 0,
                'reorder_threshold' => 5,
                'is_battery'        => $isBattery,
                'is_active'         => true,
            ]
        );

        $supplierName = trim($row['company'] ?? '');
        if ($supplierName !== '') {
            $supplier = Supplier::where('name', $supplierName)->first();
            if ($supplier) {
                $supplier->products()->syncWithoutDetaching([
                    $product->id => [
                        'last_purchase_price' => $costPrice,
                        'min_order_qty'       => 1,
                        'lead_time_days'      => 1,
                        'is_primary_supplier' => true,
                    ],
                ]);
            } else {
                $this->log->info('Product supplier not linked: no matching supplier name', ['sku' => $sku, 'company' => $supplierName]);
            }
        }

        $count++;
    }

    // ───────────────────────────────────────────── Wholesale invoices & payments ──────────

    private const FILE_WHOLESALE = '3_فواتير_المبيعات_الجملة.xlsx';
    private const SHEET_WHOLESALE_ITEMS = 'بنود_المبيعات_والفواتير';
    private const SHEET_WHOLESALE_PAYMENTS = 'سندات_التحصيل_والدفعات';

    private function importWholesaleInvoices(): void
    {
        $groups = [];
        foreach ($this->readSheetRows(self::FILE_WHOLESALE, self::SHEET_WHOLESALE_ITEMS) as $row) {
            $no = trim($row['no_s'] ?? '');
            if ($no === '') {
                continue;
            }
            $groups[$no][] = $row;
        }

        $this->info('Wholesale invoices: ' . count($groups) . ' groups found (no_s).');

        // Chronological order so customer credit-ledger running balances are correct.
        uasort($groups, function ($a, $b) {
            return ($this->parseLegacyDate($a[0]['da_s'] ?? null) ?? Carbon::parse('1970-01-01'))
                <=> ($this->parseLegacyDate($b[0]['da_s'] ?? null) ?? Carbon::parse('1970-01-01'));
        });

        $created = 0;
        $skippedInvoices = 0;
        $skippedLines = 0;
        $anomalies = 0;
        $customerBalances = [];
        $bar = $this->output->createProgressBar(count($groups));
        $bar->start();

        $batch = [];
        foreach ($groups as $no => $lines) {
            $batch[$no] = $lines;
            if (count($batch) >= $this->chunk) {
                $this->importWholesaleInvoiceBatch($batch, $created, $skippedInvoices, $skippedLines, $anomalies, $customerBalances, $bar);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            $this->importWholesaleInvoiceBatch($batch, $created, $skippedInvoices, $skippedLines, $anomalies, $customerBalances, $bar);
        }

        $bar->finish();
        $this->newLine();
        $this->stats['wholesale_invoices'] = $created;
        $this->info("Wholesale invoices: {$created} created, {$skippedInvoices} skipped, {$skippedLines} lines unmatched, {$anomalies} anomalies clamped.");
    }

    private function importWholesaleInvoiceBatch(array $batch, int &$created, int &$skippedInvoices, int &$skippedLines, int &$anomalies, array &$customerBalances, $bar): void
    {
        $this->runDryRunSafe(function () use ($batch, &$created, &$skippedInvoices, &$skippedLines, &$anomalies, &$customerBalances, $bar) {
            foreach ($batch as $no => $lines) {
                $bar->advance();
                try {
                    $this->importOneWholesaleInvoice($no, $lines, $created, $skippedInvoices, $skippedLines, $anomalies, $customerBalances);
                } catch (Throwable $e) {
                    $skippedInvoices++;
                    $this->log->error('Wholesale invoice failed', ['no_s' => $no, 'error' => $e->getMessage()]);
                }
            }
        });
    }

    private function importOneWholesaleInvoice($no, array $lines, int &$created, int &$skippedInvoices, int &$skippedLines, int &$anomalies, array &$customerBalances): void
    {
        $first = $lines[0];
        $customerName = trim($first['to'] ?? '');
        $date = $this->parseLegacyDate($first['da_s'] ?? null) ?? now();

        if ($customerName === '') {
            $skippedInvoices++;
            $this->log->warning('Wholesale invoice skipped: empty customer name', ['no_s' => $no]);
            return;
        }

        // The legacy contacts sheet tags these same names as "مورد" (supplier); we still
        // create/reuse a Customer here because (a) the credit ledger only references
        // customers, and (b) the same company legitimately has two separate ledgers with
        // us (what we buy from them as a supplier, what they buy from us wholesale).
        $customer = Customer::firstOrCreate(
            ['name' => $customerName],
            ['tier' => 'standard', 'credit_limit' => 5000, 'current_credit_balance' => 0, 'is_active' => true]
        );

        $invoiceNumber = 'INV-W-' . $no;
        if (Invoice::where('invoice_number', $invoiceNumber)->exists()) {
            $skippedInvoices++;
            $this->log->warning('Wholesale invoice skipped: invoice_number already exists', ['invoice_number' => $invoiceNumber]);
            return;
        }

        $preparedItems = [];
        $subtotal = 0.0;
        foreach ($lines as $line) {
            $productName = trim($line['name'] ?? '');
            $product = $productName !== '' ? Product::where('name', $productName)->first() : null;
            if (!$product) {
                $skippedLines++;
                $this->log->warning('Wholesale line skipped: product not found by name', ['no_s' => $no, 'name' => $productName]);
                continue;
            }

            $qty = (int) round($this->toFloat($line['qu_s'] ?? null));
            $price = $this->toFloat($line['pr_s'] ?? null);
            [$qty, $price, $wasAnomaly] = $this->applyAnomalyGuard($qty, $price, (float) $product->cost_price);
            if ($wasAnomaly) {
                $anomalies++;
                $this->log->warning('Anomalous line clamped', ['no_s' => $no, 'product' => $productName, 'raw_qty' => $line['qu_s'] ?? null, 'raw_price' => $line['pr_s'] ?? null]);
            }
            if ($qty < 1) {
                $qty = 1;
            }

            $totalPrice = round($qty * $price, 2);
            $subtotal += $totalPrice;
            $preparedItems[] = ['product_id' => $product->id, 'quantity' => $qty, 'unit_price' => $price, 'total_price' => $totalPrice];
        }

        if (empty($preparedItems)) {
            $skippedInvoices++;
            $this->log->warning('Wholesale invoice skipped: no line matched a product', ['no_s' => $no]);
            return;
        }

        $subtotal = round($subtotal, 2);

        $invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'invoice_number'   => $invoiceNumber,
            'branch_id'        => $this->branch->id,
            'customer_id'      => $customer->id,
            'cashier_id'       => $this->user->id,
            'subtotal'         => $subtotal,
            'final_amount'     => $subtotal,
            'paid_amount'      => 0,
            'remaining_amount' => $subtotal,
            'payment_method'   => 'credit',
            'status'           => 'unpaid',
            'notes'            => "مستورد من النظام القديم (فاتورة جملة رقم {$no})",
        ]));
        $invoice->created_at = $date;
        $invoice->updated_at = $date;
        $invoice->saveQuietly();

        foreach ($preparedItems as $item) {
            $invoiceItem = new InvoiceItem([
                'invoice_id'  => $invoice->id,
                'product_id'  => $item['product_id'],
                'quantity'    => $item['quantity'],
                'unit_price'  => $item['unit_price'],
                'total_price' => $item['total_price'],
            ]);
            $invoiceItem->created_at = $date;
            $invoiceItem->updated_at = $date;
            $invoiceItem->save();
        }

        $before = $customerBalances[$customer->id] ?? (float) $customer->current_credit_balance;
        $after = round($before + $subtotal, 2);
        $customerBalances[$customer->id] = $after;
        $customer->update(['current_credit_balance' => $after]);

        $ledger = new CreditLedgerEntry([
            'customer_id'    => $customer->id,
            'invoice_id'     => $invoice->id,
            'entry_type'     => 'invoice_debt',
            'amount'         => $subtotal,
            'balance_before' => $before,
            'balance_after'  => $after,
            'collected_by'   => $this->user->id,
            'notes'          => "مديونية آجلة ناتجة عن فاتورة جملة مستوردة رقم {$invoice->invoice_number}",
        ]);
        $ledger->created_at = $date;
        $ledger->updated_at = $date;
        $ledger->save();

        $created++;
    }

    private function importWholesalePayments(): void
    {
        $rows = [];
        foreach ($this->readSheetRows(self::FILE_WHOLESALE, self::SHEET_WHOLESALE_PAYMENTS) as $row) {
            $rows[] = $row;
        }
        usort($rows, fn ($a, $b) => ($this->parseLegacyDate($a['da_s'] ?? null) ?? Carbon::parse('1970-01-01'))
            <=> ($this->parseLegacyDate($b['da_s'] ?? null) ?? Carbon::parse('1970-01-01')));

        $applied = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        $batch = [];
        foreach ($rows as $row) {
            $batch[] = $row;
            if (count($batch) >= $this->chunk) {
                $this->applyWholesalePaymentBatch($batch, $applied, $skipped, $bar);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            $this->applyWholesalePaymentBatch($batch, $applied, $skipped, $bar);
        }

        $bar->finish();
        $this->newLine();
        $this->stats['wholesale_payments'] = $applied;
        $this->info("Wholesale collections: {$applied} applied, {$skipped} skipped (see legacy_import.log).");
    }

    private function applyWholesalePaymentBatch(array $rows, int &$applied, int &$skipped, $bar): void
    {
        $this->runDryRunSafe(function () use ($rows, &$applied, &$skipped, $bar) {
            foreach ($rows as $row) {
                $bar->advance();
                try {
                    $this->applyOneWholesalePayment($row, $applied, $skipped);
                } catch (Throwable $e) {
                    $skipped++;
                    $this->log->error('Wholesale collection failed', ['no_s' => $row['no_s'] ?? null, 'error' => $e->getMessage()]);
                }
            }
        });
    }

    private function applyOneWholesalePayment(array $row, int &$applied, int &$skipped): void
    {
        $no = trim($row['no_s'] ?? '');
        $amount = round($this->toFloat($row['kst'] ?? null), 2);
        $date = $this->parseLegacyDate($row['da_s'] ?? null) ?? now();

        if ($no === '' || $amount <= 0) {
            $skipped++;
            return;
        }

        $invoice = Invoice::where('invoice_number', 'INV-W-' . $no)->lockForUpdate()->first();
        if (!$invoice) {
            $skipped++;
            $this->log->warning('Wholesale collection skipped: invoice not found', ['no_s' => $no]);
            return;
        }

        $customer = Customer::where('id', $invoice->customer_id)->lockForUpdate()->first();
        if (!$customer) {
            $skipped++;
            return;
        }

        $remaining = (float) $invoice->remaining_amount;
        if ($remaining <= self::EPSILON_VALUE) {
            $skipped++;
            $this->log->warning('Wholesale collection skipped: invoice already settled', ['no_s' => $no]);
            return;
        }

        $applyAmount = min($amount, $remaining);
        if ($applyAmount < $amount) {
            $this->log->warning('Wholesale collection clamped to remaining balance', ['no_s' => $no, 'receipt_amount' => $amount, 'applied' => $applyAmount]);
        }

        $newRemaining = round($remaining - $applyAmount, 2);
        $newPaid = round((float) $invoice->paid_amount + $applyAmount, 2);
        $newStatus = $newRemaining <= self::EPSILON_VALUE ? 'paid' : 'partially_paid';

        $invoice->update(['paid_amount' => $newPaid, 'remaining_amount' => max(0, $newRemaining), 'status' => $newStatus]);

        $payment = new InvoicePayment([
            'invoice_id'     => $invoice->id,
            'payment_method' => 'cash',
            'amount'         => $applyAmount,
            'notes'          => "تحصيل مستورد من النظام القديم لفاتورة جملة رقم {$no}",
        ]);
        $payment->created_at = $date;
        $payment->updated_at = $date;
        $payment->save();

        $before = (float) $customer->current_credit_balance;
        $after = max(0, round($before - $applyAmount, 2));
        $customer->update(['current_credit_balance' => $after]);

        $ledger = new CreditLedgerEntry([
            'customer_id'    => $customer->id,
            'invoice_id'     => $invoice->id,
            'entry_type'     => 'payment_collection',
            'amount'         => $applyAmount,
            'balance_before' => $before,
            'balance_after'  => $after,
            'collected_by'   => $this->user->id,
            'notes'          => "تحصيل دفعة آجل مستورد من النظام القديم لفاتورة رقم INV-W-{$no}",
        ]);
        $ledger->created_at = $date;
        $ledger->updated_at = $date;
        $ledger->save();

        $applied++;
    }

    // ───────────────────────────────────────────── Retail invoices & payments ────────────

    private const FILE_RETAIL = '4_فواتير_المبيعات_القطاعي.xlsx';
    private const SHEET_RETAIL_ITEMS = 'بنود_فواتير_القطاعي';
    private const SHEET_RETAIL_PAYMENTS = 'التحصيلات_النقدية';

    private function importRetailInvoices(): void
    {
        $posCustomer = Customer::where('phone', self::POS_CUSTOMER_PHONE)->first();
        if (!$posCustomer) {
            throw new RuntimeException('System POS customer not found. Run --step=customers first.');
        }

        $groups = [];
        foreach ($this->readSheetRows(self::FILE_RETAIL, self::SHEET_RETAIL_ITEMS) as $row) {
            $no = trim($row['no_s'] ?? '');
            if ($no === '') {
                continue;
            }
            $groups[$no][] = $row;
        }

        $this->info('Retail invoices: ' . count($groups) . ' groups found (no_s).');

        $created = 0;
        $skippedInvoices = 0;
        $skippedLines = 0;
        $anomalies = 0;
        $bar = $this->output->createProgressBar(count($groups));
        $bar->start();

        $batch = [];
        foreach ($groups as $no => $lines) {
            $batch[$no] = $lines;
            if (count($batch) >= $this->chunk) {
                $this->importRetailInvoiceBatch($batch, $posCustomer, $created, $skippedInvoices, $skippedLines, $anomalies, $bar);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            $this->importRetailInvoiceBatch($batch, $posCustomer, $created, $skippedInvoices, $skippedLines, $anomalies, $bar);
        }

        $bar->finish();
        $this->newLine();
        $this->stats['retail_invoices'] = $created;
        $this->info("Retail invoices: {$created} created, {$skippedInvoices} skipped, {$skippedLines} lines unmatched, {$anomalies} anomalies clamped.");
    }

    private function importRetailInvoiceBatch(array $batch, Customer $posCustomer, int &$created, int &$skippedInvoices, int &$skippedLines, int &$anomalies, $bar): void
    {
        $this->runDryRunSafe(function () use ($batch, $posCustomer, &$created, &$skippedInvoices, &$skippedLines, &$anomalies, $bar) {
            foreach ($batch as $no => $lines) {
                $bar->advance();
                try {
                    $this->importOneRetailInvoice($no, $lines, $posCustomer, $created, $skippedInvoices, $skippedLines, $anomalies);
                } catch (Throwable $e) {
                    $skippedInvoices++;
                    $this->log->error('Retail invoice failed', ['no_s' => $no, 'error' => $e->getMessage()]);
                }
            }
        });
    }

    private function importOneRetailInvoice($no, array $lines, Customer $posCustomer, int &$created, int &$skippedInvoices, int &$skippedLines, int &$anomalies): void
    {
        $first = $lines[0];
        $date = $this->parseLegacyDate($first['da_s'] ?? null) ?? now();

        $invoiceNumber = 'INV-R-' . $no;
        if (Invoice::where('invoice_number', $invoiceNumber)->exists()) {
            $skippedInvoices++;
            $this->log->warning('Retail invoice skipped: invoice_number already exists', ['invoice_number' => $invoiceNumber]);
            return;
        }

        $preparedItems = [];
        $subtotal = 0.0;
        foreach ($lines as $line) {
            $productName = trim($line['name'] ?? '');
            $product = $productName !== '' ? Product::where('name', $productName)->first() : null;
            if (!$product) {
                $skippedLines++;
                $this->log->warning('Retail line skipped: product not found by name', ['no_s' => $no, 'name' => $productName]);
                continue;
            }

            $qty = (int) round($this->toFloat($line['qu_s'] ?? null));
            $price = $this->toFloat($line['pr_s'] ?? null);
            [$qty, $price, $wasAnomaly] = $this->applyAnomalyGuard($qty, $price, (float) $product->cost_price);
            if ($wasAnomaly) {
                $anomalies++;
                $this->log->warning('Anomalous line clamped', ['no_s' => $no, 'product' => $productName, 'raw_qty' => $line['qu_s'] ?? null, 'raw_price' => $line['pr_s'] ?? null]);
            }
            if ($qty < 1) {
                $qty = 1;
            }

            $totalPrice = round($qty * $price, 2);
            $subtotal += $totalPrice;
            $preparedItems[] = ['product_id' => $product->id, 'quantity' => $qty, 'unit_price' => $price, 'total_price' => $totalPrice];
        }

        if (empty($preparedItems)) {
            $skippedInvoices++;
            $this->log->warning('Retail invoice skipped: no line matched a product', ['no_s' => $no]);
            return;
        }

        $subtotal = round($subtotal, 2);

        $invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'invoice_number'   => $invoiceNumber,
            'branch_id'        => $this->branch->id,
            'customer_id'      => $posCustomer->id,
            'cashier_id'       => $this->user->id,
            'subtotal'         => $subtotal,
            'final_amount'     => $subtotal,
            'paid_amount'      => 0,
            'remaining_amount' => $subtotal,
            'payment_method'   => 'cash',
            'status'           => 'unpaid',
            'notes'            => "مستورد من النظام القديم (فاتورة قطاعي رقم {$no})",
        ]));
        $invoice->created_at = $date;
        $invoice->updated_at = $date;
        $invoice->saveQuietly();

        foreach ($preparedItems as $item) {
            $invoiceItem = new InvoiceItem([
                'invoice_id'  => $invoice->id,
                'product_id'  => $item['product_id'],
                'quantity'    => $item['quantity'],
                'unit_price'  => $item['unit_price'],
                'total_price' => $item['total_price'],
            ]);
            $invoiceItem->created_at = $date;
            $invoiceItem->updated_at = $date;
            $invoiceItem->save();
        }

        $created++;
    }

    private function importRetailPayments(): void
    {
        $rows = [];
        foreach ($this->readSheetRows(self::FILE_RETAIL, self::SHEET_RETAIL_PAYMENTS) as $row) {
            $rows[] = $row;
        }

        $applied = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        $batch = [];
        foreach ($rows as $row) {
            $batch[] = $row;
            if (count($batch) >= $this->chunk) {
                $this->applyRetailPaymentBatch($batch, $applied, $skipped, $bar);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            $this->applyRetailPaymentBatch($batch, $applied, $skipped, $bar);
        }

        $bar->finish();
        $this->newLine();
        $this->stats['retail_payments'] = $applied;
        $this->info("Retail cash collections: {$applied} applied, {$skipped} skipped (see legacy_import.log).");
    }

    private function applyRetailPaymentBatch(array $rows, int &$applied, int &$skipped, $bar): void
    {
        $this->runDryRunSafe(function () use ($rows, &$applied, &$skipped, $bar) {
            foreach ($rows as $row) {
                $bar->advance();
                try {
                    $this->applyOneRetailPayment($row, $applied, $skipped);
                } catch (Throwable $e) {
                    $skipped++;
                    $this->log->error('Retail collection failed', ['no_s' => $row['no_s'] ?? null, 'error' => $e->getMessage()]);
                }
            }
        });
    }

    private function applyOneRetailPayment(array $row, int &$applied, int &$skipped): void
    {
        $no = trim($row['no_s'] ?? '');
        $amount = round($this->toFloat($row['kst'] ?? null), 2);
        $date = $this->parseLegacyDate($row['da_s'] ?? null) ?? now();

        if ($no === '' || $amount <= 0) {
            $skipped++;
            return;
        }

        $invoice = Invoice::where('invoice_number', 'INV-R-' . $no)->lockForUpdate()->first();
        if (!$invoice) {
            $skipped++;
            $this->log->warning('Retail collection skipped: invoice not found', ['no_s' => $no]);
            return;
        }

        $remaining = (float) $invoice->remaining_amount;
        if ($remaining <= self::EPSILON_VALUE) {
            $skipped++;
            return;
        }

        $applyAmount = min($amount, $remaining);
        if ($applyAmount < $amount) {
            $this->log->warning('Retail collection clamped to remaining balance', ['no_s' => $no, 'receipt_amount' => $amount, 'applied' => $applyAmount]);
        }

        $newRemaining = round($remaining - $applyAmount, 2);
        $newPaid = round((float) $invoice->paid_amount + $applyAmount, 2);
        $newStatus = $newRemaining <= self::EPSILON_VALUE ? 'paid' : 'partially_paid';

        $invoice->update(['paid_amount' => $newPaid, 'remaining_amount' => max(0, $newRemaining), 'status' => $newStatus]);

        $payment = new InvoicePayment([
            'invoice_id'     => $invoice->id,
            'payment_method' => 'cash',
            'amount'         => $applyAmount,
            'notes'          => "تحصيل نقدي مستورد من النظام القديم لفاتورة قطاعي رقم {$no}",
        ]);
        $payment->created_at = $date;
        $payment->updated_at = $date;
        $payment->save();

        $applied++;
    }

    // ───────────────────────────────────────────── Employees ──────────────────────────────

    private const FILE_HR = '5_الموظفين_والمصروفات.xlsx';
    private const SHEET_EMPLOYEES = 'الموظفين';

    private function importEmployees(): void
    {
        $defaultJobTitle = JobTitle::orderBy('id')->first();
        if (!$defaultJobTitle) {
            throw new RuntimeException('No job title found to fall back to. Seed departments/job titles first.');
        }

        $created = 0;
        $skipped = 0;

        foreach ($this->readSheetRows(self::FILE_HR, self::SHEET_EMPLOYEES) as $row) {
            $name = trim($row['to'] ?? '');
            $code = trim($row['code'] ?? '');
            if ($name === '' || $code === '') {
                $skipped++;
                $this->log->warning('Employee row skipped: missing name or code', $row);
                continue;
            }

            try {
                $this->runDryRunSafe(function () use ($row, $name, $code, $defaultJobTitle, &$created) {
                    $jobTitleText = trim($row['staf'] ?? '');
                    $jobTitle = $jobTitleText !== '' ? JobTitle::where('title', 'like', "%{$jobTitleText}%")->first() : null;
                    $jobTitle ??= $defaultJobTitle;

                    $hireDate = $this->parseLegacyDate($row['da_s2'] ?? null)
                        ?? $this->parseLegacyDate($row['da_s'] ?? null)
                        ?? now();

                    $phone = $this->firstNonEmpty([$row['tel'] ?? null]);
                    $basicSalary = $this->toFloat($row['kst'] ?? null);

                    $employee = Employee::updateOrCreate(
                        ['employee_code' => 'LEGACY-' . $code],
                        [
                            'branch_id'            => $this->branch->id,
                            'job_title_id'         => $jobTitle->id,
                            'full_name'            => $name,
                            'national_id'          => null,
                            'phone'                => $phone,
                            'hire_date'            => $hireDate,
                            'grace_period_minutes' => 15,
                            'status'               => 'active',
                        ]
                    );

                    SalaryStructure::updateOrCreate(
                        ['employee_id' => $employee->id, 'is_current' => true],
                        [
                            'basic_salary'         => $basicSalary,
                            'housing_allowance'    => 0,
                            'transport_allowance'  => 0,
                            'other_allowances'     => 0,
                            'effective_from'       => $hireDate,
                            'effective_to'         => null,
                        ]
                    );

                    $created++;
                });
            } catch (Throwable $e) {
                $skipped++;
                $this->log->error('Employee row failed', ['code' => $code, 'name' => $name, 'error' => $e->getMessage()]);
            }
        }

        $this->stats['employees'] = $created;
        $this->info("Employees: {$created} upserted (+ salary structures), {$skipped} skipped.");
    }

    // ───────────────────────────────────────────── Summary ────────────────────────────────

    private const EPSILON_VALUE = 0.01;

    private function printVerificationSummary(): void
    {
        $this->newLine();
        $this->info('=== Verification summary ===');
        $this->table(['Metric', 'Value'], [
            ['Categories', Category::count()],
            ['Suppliers', Supplier::count()],
            ['Customers', Customer::count()],
            ['Products', Product::count()],
            ['Invoices (total)', Invoice::count()],
            ['  of which wholesale (INV-W-)', Invoice::where('invoice_number', 'like', 'INV-W-%')->count()],
            ['  of which retail (INV-R-)', Invoice::where('invoice_number', 'like', 'INV-R-%')->count()],
            ['Total revenue (final_amount)', number_format((float) Invoice::sum('final_amount'), 2) . ' EGP'],
            ['Total outstanding (remaining_amount)', number_format((float) Invoice::sum('remaining_amount'), 2) . ' EGP'],
            ['Employees', Employee::count()],
            ['Protected users (untouched)', User::count()],
        ]);

        $this->comment(
            'Not imported (out of this spec\'s scope): discounts, returns and disbursement-voucher sheets in files 3/4, ' .
            'attendance and expenses sheets in file 5, and files 6 (project estimates) and 7 (unrelated/empty).'
        );
        $this->comment('Full detail on skipped/clamped rows: storage/logs/legacy_import.log');
    }
}

/**
 * Internal sentinel: thrown to force a transaction rollback in --dry-run mode.
 * Never escapes the command (always caught by runDryRunSafe()).
 */
final class DryRunRollback extends \RuntimeException
{
}
