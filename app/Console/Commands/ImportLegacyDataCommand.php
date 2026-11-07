<?php

namespace App\Console\Commands;

use App\Contracts\Catalog\StockLedgerInterface;
use App\Enums\ProductUnit;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\JobTitle;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Quantity;
use Carbon\Carbon;
use Database\Seeders\ScrapPricingTiersSeeder;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * One-time migration of the legacy ERP Excel export into the current schema. Run it once, on a
 * wiped database (--fresh): payment steps are not idempotent.
 *
 * Owner decisions behind the mapping (2026-11-06):
 * - File 3 is named wholesale sales, but it holds PURCHASES from suppliers (invo_w): 99.9% of its
 *   value is on contacts the old system labels as suppliers, and the old program's own stock
 *   query adds it to stock. Its "collections" are supplier payments.
 * - Balances follow the old program's party formula (qry.mdb): a settlement naming a document
 *   settles it first, the rest goes on account, FIFO over the party's open documents, and what is
 *   still left stays on the balance, which may go below zero. That covers supplier payments,
 *   purchase returns, purchase discounts, retail payments and sales returns. The run ends by
 *   comparing every party's balance with that formula, rebuilt from the source alone. A voucher
 *   of 1,000,000 or more is a typing error and is excluded and reported.
 * - A line naming a product missing from the catalog creates that product, inactive and reported,
 *   so the document keeps its real value.
 * - The old program stores no stock: it computes it. Stock is imported with the same formula
 *   (qry.mdb): purchases + sales returns + file 3 disbursement sheet - sales - purchase returns -
 *   file 4 disbursement sheet, each as quantity + bonus. It goes through the stock ledger as one
 *   adjustment per product. It is a BOOK balance: sales recording almost stops after 2021 while
 *   purchases continue, so it overstates what is on the shelves until a physical count replaces it.
 *   Historical documents write no stock movements of their own.
 * - Everyone named on a sales document becomes a customer, whatever their contact label.
 * - pr_run1 is the retail price (real retail prices match it) and pr_r2un1 the wholesale price;
 *   cost is the product's latest purchase price.
 *
 * Historical documents keep their legacy numbers (LEG-P-*, INV-R-*) instead of the
 * DocumentNumberService series, so they stay traceable and never collide with new documents.
 *
 * Every excluded or doubtful row goes to storage/app/legacy-import-review-*.json. The run ends
 * with a source-versus-imported reconciliation and fails on any difference the report does not
 * explain.
 */
class ImportLegacyDataCommand extends Command
{
    protected $signature = 'app:import-legacy-data
                            {--fresh : Wipe demo and operational data first (writes a mysqldump backup before truncating)}
                            {--dry-run : Run every step inside one transaction that is always rolled back}
                            {--step=all : wipe|categories|suppliers|customers|products|invoices|payments|stock|employees|all}
                            {--chunk=1000 : Documents per committed transaction}
                            {--data-path= : Override the legacy Excel directory}
                            {--mysqldump-path=mysqldump : Path to the mysqldump binary used for the pre-wipe backup}
                            {--branch= : Branch id to attach imported records to (default: MAIN)}
                            {--user= : User id to attach as cashier/collector/receiver (default: first user)}';

    protected $description = 'Wipe demo data and import the legacy ERP Excel export (purchases, retail sales, catalog, contacts)';

    private const ADMIN_EMAIL = 'admin@alhusseini.com';
    private const MAIN_BRANCH_CODE = 'MAIN';

    private const PRESERVED_TABLES = ['roles', 'permissions', 'role_has_permissions', 'migrations', 'settings'];

    private const TRUNCATE_TABLES = [
        // Sales & ledger
        'invoice_payments', 'credit_ledger_entries', 'invoice_items', 'invoices',
        'stock_movements', 'document_sequences',
        // Catalog
        'supplier_products', 'products', 'categories',
        // Entities
        'customer_vehicles', 'customers', 'supplier_ledger_entries', 'purchase_invoice_items',
        'purchase_invoices', 'suppliers',
        // Operations
        'scrap_sales', 'scrap_batteries_inventory', 'scrap_pricing_tiers', 'warranty_claims', 'warranties',
        // HR & commissions
        'technician_commissions', 'employee_payroll_debts', 'payroll_items', 'payrolls',
        'employee_deductions', 'employee_leaves', 'attendances', 'salary_structures', 'employees',
        'deduction_rules', 'job_titles', 'departments', 'notifications',
    ];

    private const FILE_CONTACTS = '1_العملاء_وجهات_الاتصال.xlsx';
    private const SHEET_CONTACTS = 'العملاء_وجهات_الاتصال';
    private const FILE_CATALOG = '2_كتالوج_الأصناف_وقطع_الغيار.xlsx';
    private const SHEET_CATALOG = 'الأصناف_وقطع_الغيار';
    private const FILE_PURCHASES = '3_فواتير_المبيعات_الجملة.xlsx';
    private const SHEET_PURCHASE_LINES = 'بنود_المبيعات_والفواتير';
    private const SHEET_SUPPLIER_PAYMENTS = 'سندات_التحصيل_والدفعات';
    private const FILE_RETAIL = '4_فواتير_المبيعات_القطاعي.xlsx';
    private const SHEET_RETAIL_LINES = 'بنود_فواتير_القطاعي';
    private const SHEET_RETAIL_PAYMENTS = 'التحصيلات_النقدية';
    private const SHEET_RETURNS = 'المرتجعات';
    private const SHEET_DISBURSEMENTS = 'سندات_الصرف';
    private const SHEET_DISCOUNTS = 'الخصومات';
    private const FILE_HR = '5_الموظفين_والمصروفات.xlsx';
    private const SHEET_EMPLOYEES = 'الموظفين';

    /**
     * The old program's stock formula: file, sheet, sign. All of it is quantity + bonus.
     * Its other tables are empty; the export script stops if that ever changes.
     */
    private const STOCK_FORMULA = [
        [self::FILE_PURCHASES, self::SHEET_PURCHASE_LINES, 1],
        [self::FILE_PURCHASES, self::SHEET_DISBURSEMENTS, 1],
        [self::FILE_RETAIL, self::SHEET_RETURNS, 1],
        [self::FILE_RETAIL, self::SHEET_RETAIL_LINES, -1],
        [self::FILE_PURCHASES, self::SHEET_RETURNS, -1],
        [self::FILE_RETAIL, self::SHEET_DISBURSEMENTS, -1],
    ];

    /**
     * Sheets not imported as documents; the review report carries their row counts and totals.
     * Disbursements carry no money in the old program's party balance, only stock.
     */
    private const OUT_OF_SCOPE_SHEETS = [
        [self::FILE_CATALOG, 'مواقع_الأصناف_بالمخزن'],
        [self::FILE_CATALOG, 'المخازن'],
        [self::FILE_CATALOG, 'الأقسام_والأنواع'],
        [self::FILE_PURCHASES, self::SHEET_DISBURSEMENTS],
        [self::FILE_RETAIL, self::SHEET_DISBURSEMENTS],
        [self::FILE_HR, 'الحضور_والانصراف'],
        [self::FILE_HR, 'المصروفات'],
        ['6_المشاريع_والمقايسات.xlsx', 'المشاريع'],
        ['6_المشاريع_والمقايسات.xlsx', 'بنود_المشاريع'],
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
    private const DEFAULT_DEPARTMENT = 'غير محدد';

    private const BATTERY_PATTERN = '/بطارية|كلورايد|فارتا|\b\d+(?:\.\d+)?\s*ah\b/iu';
    private const TRANSFER_PATTERN = '/instapay|transfer|bank|تحويل|بنك|انستا|انيستا|اينسيتا|انيسا|فودافون|فودا كاش|محفظ/iu';

    private const CORRUPT_QUANTITY = 103304;
    private const SUSPICIOUS_LINE_PIASTERS = 20000000;

    /** A single voucher at or above this is a typing error, not money (the largest real one is 400,000). */
    private const CORRUPT_AMOUNT = 1000000.0;

    private const ON_ACCOUNT_NOTE = 'سداد على الحساب من النظام القديم';

    /** Used only when a source date is missing, and reported: old enough to stay out of current figures. */
    private const UNKNOWN_DATE = '2008-12-31';

    private const POS_CUSTOMER_PHONE = '01099999999';
    private const POS_CUSTOMER_NAME = 'عميل نقدي عام (POS)';

    private const TOLERANCE_PIASTERS = 1;

    /** metric => label, in print order. Metrics ending in _value are money in piasters, _qty quantities in thousandths. */
    private const METRICS = [
        'contact_suppliers' => 'Suppliers in contacts',
        'contact_customers' => 'Customers in contacts',
        'products' => 'Products',
        'purchase_invoices' => 'Purchase invoices',
        'purchase_lines' => 'Purchase lines',
        'purchase_value' => 'Purchase value',
        'supplier_payments' => 'Supplier payments',
        'supplier_payments_value' => 'Supplier payments value',
        'purchase_returns' => 'Purchase returns',
        'purchase_returns_value' => 'Purchase returns value',
        'purchase_discounts' => 'Purchase discounts',
        'purchase_discounts_value' => 'Purchase discounts value',
        'retail_invoices' => 'Retail invoices',
        'retail_lines' => 'Retail lines',
        'retail_value' => 'Retail value',
        'retail_payments' => 'Retail payments',
        'retail_payments_value' => 'Retail payments value',
        'retail_returns' => 'Sales returns',
        'retail_returns_value' => 'Sales returns value',
        'sales_parties' => 'Customers on sales documents',
        'stock_qty' => 'Book stock (units)',
        'employees' => 'Employees',
    ];

    private LoggerInterface $log;
    private string $dataPath;
    private bool $dryRun;
    private int $chunk;
    private Branch $branch;
    private User $user;

    /** @var list<array{sheet: string, row: ?string, document: ?string, reason: string, amount: float, detail: ?string}> */
    private array $review = [];

    /** @var array<string, int> metric => total read from the source */
    private array $source = [];

    /** @var array<string, int> metric => total the review report explains as excluded */
    private array $excluded = [];

    /** @var list<string> contact names read from the source, by kind */
    private array $contactSuppliers = [];
    private array $contactCustomers = [];

    /** @var list<string> */
    private array $sourceSkus = [];

    /** @var list<string> */
    private array $sourceEmployeeCodes = [];

    /** @var array<int, int> supplier id => payment piasters no open purchase could absorb */
    private array $unallocatedBySupplier = [];

    /** @var list<string> names on sales documents, for the reconciliation */
    private array $salesParties = [];

    /** @var list<int> products that got a book stock, for the reconciliation */
    private array $stockedProductIds = [];

    /** @var array<int, int> purchase id => piasters settled by returns and discounts */
    private array $purchaseCredits = [];

    /** @var array<int, int> retail invoice id => piasters settled by sales returns */
    private array $retailCredits = [];

    public function handle(): int
    {
        $this->resetState();

        $step = (string) $this->option('step');
        $validSteps = ['wipe', 'categories', 'suppliers', 'customers', 'products', 'invoices', 'payments', 'stock', 'employees', 'all'];
        if (! in_array($step, $validSteps, true)) {
            $this->error('Invalid --step. Expected one of: '.implode('|', $validSteps));

            return self::FAILURE;
        }

        if (($this->option('fresh') || $step === 'wipe') && app()->environment('production')) {
            $this->error('Refusing to wipe legacy data in production.');

            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('DRY RUN: every step runs inside one transaction that is rolled back at the end.');
            DB::beginTransaction();
        }

        try {
            if ($step === 'wipe') {
                $this->wipeMockData();
                $this->endDryRun();

                return self::SUCCESS;
            }

            if ($step === 'all' && $this->option('fresh')) {
                $this->wipeMockData();
            }

            $this->branch = $this->resolveDefaultBranch();
            $this->user = $this->resolveDefaultUser();
            $this->runSteps($step);

            $failures = $this->printReconciliation();
            $this->printReviewSummary();
            $this->writeReviewReport();
        } catch (Throwable $e) {
            $this->endDryRun();
            $this->error('Import aborted: '.$e->getMessage());
            $this->log->error('Import aborted', ['exception' => (string) $e]);

            return self::FAILURE;
        }

        $this->endDryRun();

        foreach ($failures as $failure) {
            $this->error($failure);
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }

    private function resetState(): void
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->chunk = max(1, (int) $this->option('chunk'));
        // Built from the live Access files by scripts/legacy-export.
        $this->dataPath = rtrim((string) ($this->option('data-path') ?: storage_path('app/legacy-export')), '\\/').DIRECTORY_SEPARATOR;
        $this->log = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/legacy_import.log'),
            'level' => 'debug',
        ]);
        $this->review = [];
        $this->source = [];
        $this->excluded = [];
        $this->contactSuppliers = [];
        $this->contactCustomers = [];
        $this->sourceSkus = [];
        $this->sourceEmployeeCodes = [];
        $this->unallocatedBySupplier = [];
        $this->salesParties = [];
        $this->stockedProductIds = [];
        $this->purchaseCredits = [];
        $this->retailCredits = [];
    }

    private function runSteps(string $step): void
    {
        $runs = fn (string $name) => in_array($step, ['all', $name], true);

        if ($runs('categories')) {
            $this->importCategories();
        }
        if ($runs('suppliers')) {
            $this->importSuppliers();
        }
        if ($runs('customers')) {
            $this->importCustomers();
        }
        if ($runs('products')) {
            $this->importProducts();
        }
        if ($runs('invoices')) {
            $this->importPurchases();
            $this->applyProductCosts();
            $this->syncSupplierProducts();
            $this->importRetailInvoices();
            $this->importSalesParties();
        }
        if ($runs('payments')) {
            $this->importSupplierSettlements();
            $this->importCustomerSettlements();
        }
        if ($runs('stock')) {
            $this->applyBookStock();
        }
        if ($runs('employees')) {
            $this->importEmployees();
        }
    }

    private function endDryRun(): void
    {
        if ($this->dryRun && DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    // ───────────────────────────────────────────── Phase 0: safe wipe ─────────────────────

    private function wipeMockData(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Wipe is MySQL-only; no SQLite wipe was attempted.');
        }

        $admin = User::where('email', self::ADMIN_EMAIL)->first();
        $main = Branch::where('code', self::MAIN_BRANCH_CODE)->first();
        if (! $admin || ! $main) {
            throw new RuntimeException('Wipe aborted before backup: '.self::ADMIN_EMAIL.' and branch '.self::MAIN_BRANCH_CODE.' must both exist.');
        }

        $usersToRemove = User::where('id', '!=', $admin->id)->pluck('id');
        $branchesToRemove = Branch::withoutGlobalScopes()->where('id', '!=', $main->id)->pluck('id');

        $tableCounts = [];
        foreach (self::TRUNCATE_TABLES as $table) {
            $tableCounts[$table] = DB::table($table)->count();
        }

        $this->table(['Table', 'Rows to truncate'], collect($tableCounts)->map(fn ($c, $t) => [$t, $c])->values()->all());
        $this->info('Users to remove: '.$usersToRemove->count().'; branches to remove: '.$branchesToRemove->count().'.');

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

            $morph = $admin->getMorphClass();
            DB::table('sessions')->whereIn('user_id', $usersToRemove)->delete();
            DB::table('user_devices')->whereIn('user_id', $usersToRemove)->delete();
            DB::table('password_reset_tokens')->where('email', '!=', self::ADMIN_EMAIL)->delete();
            DB::table('model_has_roles')->where('model_type', $morph)->whereIn('model_id', $usersToRemove)->delete();
            DB::table('model_has_permissions')->where('model_type', $morph)->whereIn('model_id', $usersToRemove)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', $morph)->whereIn('tokenable_id', $usersToRemove)->delete();
            DB::table('users')->whereIn('id', $usersToRemove)->delete();
            DB::table('branches')->whereIn('id', $branchesToRemove)->delete();
            DB::table('users')->where('id', $admin->id)->update(['branch_id' => $main->id]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        // Scrap pricing tiers are business configuration, not transactional mock data, and the
        // legacy export has no source for them: restore the owner's real defaults (idempotent).
        (new ScrapPricingTiersSeeder)->run();

        // Cached dashboards and permissions still refer to the removed rows.
        Artisan::call('cache:clear');

        $this->info('Wipe complete. Preserved: '.implode(', ', self::PRESERVED_TABLES).', the admin account and branch '.self::MAIN_BRANCH_CODE.'.');
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
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir.DIRECTORY_SEPARATOR.'pre-wipe-'.now()->format('Ymd-His').'.sql';

        // Password via MYSQL_PWD (not a CLI arg) so it never shows up in the process list.
        $result = Process::env(['MYSQL_PWD' => $cfg['password']])->run([
            $mysqldumpPath,
            '--host='.$cfg['host'],
            '--port='.$cfg['port'],
            '--user='.$cfg['username'],
            '--single-transaction',
            '--routines',
            '--result-file='.$path,
            $cfg['database'],
        ]);

        if (! $result->successful() || ! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException(
                "Database backup failed (mysqldump exit code {$result->exitCode()}): {$result->errorOutput()}\n".
                'Wipe aborted; nothing was truncated. Pass --mysqldump-path if the binary is not on PATH.'
            );
        }

        return $path;
    }

    // ───────────────────────────────────────────── Shared helpers ─────────────────────────

    private function resolveDefaultBranch(): Branch
    {
        $id = $this->option('branch');
        $branch = $id
            ? Branch::find($id)
            : (Branch::where('code', self::MAIN_BRANCH_CODE)->first() ?? Branch::orderBy('id')->first());

        if (! $branch) {
            throw new RuntimeException('No branch found to attach imported records to. Seed at least one branch first.');
        }

        return $branch;
    }

    private function resolveDefaultUser(): User
    {
        $id = $this->option('user');
        $user = $id ? User::find($id) : User::orderBy('id')->first();

        if (! $user) {
            throw new RuntimeException('No user found to attach as cashier/collector/receiver. Seed at least one user first.');
        }

        return $user;
    }

    /** In a dry run the outer transaction already covers everything and is rolled back at the end. */
    private function transact(callable $work): void
    {
        if ($this->dryRun) {
            $work();

            return;
        }

        DB::transaction(fn () => $work());
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
        $reader = new XlsxReader(new XlsxOptions);
        $reader->open($this->dataPath.$file);

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
                        $assoc[$name] = $this->cellToString($cells[$i] ?? '');
                    }
                    if (isset($assoc['to'])) {
                        $assoc['to'] = $this->cleanName($assoc['to']);
                    }
                    $assoc['__row'] = (string) $rowNum;

                    yield $assoc;
                }

                return;
            }
        } finally {
            $reader->close();
        }

        throw new RuntimeException("Sheet '{$sheetName}' not found in {$file}.");
    }

    /**
     * A sheet that older exports may not have.
     *
     * @return list<array<string, string>>
     */
    private function readOptionalRows(string $file, string $sheet): array
    {
        if (! is_file($this->dataPath.$file)) {
            return [];
        }

        try {
            return iterator_to_array($this->readSheetRows($file, $sheet), false);
        } catch (RuntimeException) {
            return [];
        }
    }

    /** One name however it was spaced: every sheet reads a party through here, so they stay linked. */
    private function cleanName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    private function cellToString(mixed $cell): string
    {
        return $cell instanceof DateTimeInterface ? $cell->format('Y-m-d H:i:s') : trim((string) $cell);
    }

    private function tally(string $metric, int $amount = 1): void
    {
        $this->source[$metric] = ($this->source[$metric] ?? 0) + $amount;
    }

    /**
     * Records a row in the review report. $excludes lists what this row removes from the
     * reconciliation (metric => amount), so the difference it causes counts as explained.
     *
     * @param array<string, int> $excludes
     */
    private function flag(string $sheet, array $row, string $reason, float $amount = 0, array $excludes = [], ?string $detail = null): void
    {
        $this->review[] = [
            'sheet' => $sheet,
            'row' => $row['__row'] ?? null,
            'document' => $row['no_s'] ?? ($row['code'] ?? null),
            'reason' => $reason,
            'amount' => round($amount, 2),
            'detail' => $detail,
        ];

        foreach ($excludes as $metric => $value) {
            $this->excluded[$metric] = ($this->excluded[$metric] ?? 0) + $value;
        }
    }

    private function toPiasters(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function fromPiasters(int $piasters): float
    {
        return round($piasters / 100, 2);
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

    /** A historical row must never carry today's date: it would show up in today's figures. */
    private function documentDate(array $row, string $sheet): Carbon
    {
        $date = $this->parseLegacyDate($row['da_s'] ?? null);
        if ($date) {
            return $date;
        }

        $this->flag($sheet, $row, 'missing date, '.self::UNKNOWN_DATE.' used');

        return Carbon::parse(self::UNKNOWN_DATE)->setTime(12, 0, 0);
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

    private function sanitizeEgyptianMobile(?string $phone, string $sheet, array $row): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '0020')) {
            $digits = '0'.substr($digits, 4);
        } elseif (str_starts_with($digits, '20')) {
            $digits = '0'.substr($digits, 2);
        }

        if (! preg_match('/^01\d{9}$/', $digits)) {
            $this->flag($sheet, $row, 'invalid Egyptian mobile cleared');

            return null;
        }

        return $digits;
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

    // ───────────────────────────────────────────── Categories ─────────────────────────────

    private function importCategories(): void
    {
        $slugs = array_merge(
            array_map(fn ($def) => $def['slug'], self::CATEGORY_KEYWORDS),
            [self::DEFAULT_CATEGORY_SLUG]
        );
        $names = array_combine($slugs, array_merge(array_keys(self::CATEGORY_KEYWORDS), [self::DEFAULT_CATEGORY]));

        $this->transact(function () use ($names) {
            foreach ($names as $slug => $name) {
                Category::updateOrCreate(['slug' => $slug], ['name' => $name]);
            }
        });

        $this->info('Categories: '.count($names).' upserted.');
    }

    // ───────────────────────────────────────────── Contacts: suppliers & customers ────────

    private function importSuppliers(): void
    {
        $this->importContacts('مورد', 'contact_suppliers', $this->contactSuppliers, function (string $name, ?string $phone) {
            Supplier::updateOrCreate(['name' => $name], [
                'company_name' => $name,
                'phone' => $phone,
                'credit_limit' => 0,
                'current_balance' => 0,
                'is_active' => true,
            ]);
        });
    }

    private function importCustomers(): void
    {
        $this->importContacts('عميل', 'contact_customers', $this->contactCustomers, function (string $name, ?string $phone) {
            Customer::updateOrCreate(['name' => $name], [
                'phone' => $phone,
                'tier' => 'standard',
                'credit_limit' => 0,
                'current_credit_balance' => 0,
                'is_active' => true,
            ]);
        });
    }

    /** @param list<string> $names collects the names read, for the reconciliation */
    private function importContacts(string $kind, string $metric, array &$names, callable $save): void
    {
        $this->transact(function () use ($kind, $metric, &$names, $save) {
            foreach ($this->readSheetRows(self::FILE_CONTACTS, self::SHEET_CONTACTS) as $row) {
                if (($row['kindtel'] ?? '') !== $kind) {
                    continue;
                }

                $this->tally($metric);
                $name = trim($row['to'] ?? '');
                // The address book holds one blank row, as "0". Other digit-only names stay: some have documents.
                if ($name === '' || $name === '0') {
                    $this->flag('contacts', $row, 'contact without a name', 0, [$metric => 1]);

                    continue;
                }

                $phone = $this->sanitizeEgyptianMobile(
                    $this->firstNonEmpty([$row['tel'] ?? null, $row['tel2'] ?? null, $row['tel3'] ?? null]),
                    'contacts',
                    $row
                );

                $save($name, $phone);
                $names[] = $name;
            }
        });

        $this->info("Contacts ({$kind}): ".count($names).' upserted.');
    }

    /** Only for an invoice naming nobody: real exports have none, so this account normally never exists. */
    private function posCustomer(): Customer
    {
        return Customer::firstOrCreate(['phone' => self::POS_CUSTOMER_PHONE], [
            'name' => self::POS_CUSTOMER_NAME,
            'tier' => 'standard',
            'credit_limit' => 0,
            'current_credit_balance' => 0,
            'is_active' => true,
        ]);
    }

    // ───────────────────────────────────────────── Catalog ────────────────────────────────

    private function importProducts(): void
    {
        $categoryIds = Category::pluck('id', 'slug');
        $warrantyMonths = (int) Setting::number('warranty_months_default', 12, 0, 120);
        $barcodes = [];
        $batch = [];

        foreach ($this->readSheetRows(self::FILE_CATALOG, self::SHEET_CATALOG) as $row) {
            $this->tally('products');
            $batch[] = $row;

            if (count($batch) >= $this->chunk) {
                $this->importProductBatch($batch, $categoryIds, $warrantyMonths, $barcodes);
                $batch = [];
            }
        }
        $this->importProductBatch($batch, $categoryIds, $warrantyMonths, $barcodes);

        $this->info('Products: '.count($this->sourceSkus).' upserted.');
    }

    private function importProductBatch(array $rows, $categoryIds, int $warrantyMonths, array &$barcodes): void
    {
        $this->transact(function () use ($rows, $categoryIds, $warrantyMonths, &$barcodes) {
            foreach ($rows as $row) {
                $this->importOneProduct($row, $categoryIds, $warrantyMonths, $barcodes);
            }
        });
    }

    private function importOneProduct(array $row, $categoryIds, int $warrantyMonths, array &$barcodes): void
    {
        $sku = trim($row['code'] ?? '');
        $name = trim($row['name'] ?? '');
        if ($sku === '' || $name === '') {
            $this->flag('catalog', $row, 'product without a code or name', 0, ['products' => 1]);

            return;
        }

        $categoryId = $categoryIds[$this->detectCategorySlug($name)] ?? null;
        if (! $categoryId) {
            throw new RuntimeException('Categories are missing; run the categories step first.');
        }

        $retailPrice = $this->toFloat($row['pr_run1'] ?? null);
        $wholesalePrice = $this->toFloat($row['pr_r2un1'] ?? null);
        if ($retailPrice <= 0) {
            $this->flag('catalog', $row, 'zero retail price, product left inactive', 0, [], $name);
        }
        if ($wholesalePrice <= 0 || $wholesalePrice > $retailPrice) {
            $this->flag('catalog', $row, 'wholesale price zero or above retail, retail price used', $wholesalePrice, [], $name);
            $wholesalePrice = $retailPrice;
        }

        $barcode = trim($row['code2'] ?? '');
        if ($barcode !== '' && isset($barcodes[$barcode])) {
            $this->flag('catalog', $row, 'duplicate barcode cleared', 0, [], $name);
            $barcode = '';
        }
        if ($barcode !== '') {
            $barcodes[$barcode] = true;
        }

        $isBattery = $this->isBatteryName($name);
        $reorder = $this->toFloat($row['qu_min'] ?? null);

        // voltage and terminal_type are required by the schema and absent from the export, so they
        // take the schema defaults.
        Product::updateOrCreate(['sku' => $sku], array_merge([
            'category_id' => $categoryId,
            'barcode' => $barcode !== '' ? $barcode : null,
            'name' => $name,
            'brand' => trim($row['company'] ?? '') ?: 'غير محدد',
            'capacity_ah' => null,
            'warranty_months' => $isBattery ? $warrantyMonths : 0,
            'cost_price' => 0,
            'retail_price' => $retailPrice,
            'wholesale_price' => $wholesalePrice,
            'current_stock' => 0, // set by the stock step, through the ledger
            'is_battery' => $isBattery,
            'is_active' => $retailPrice > 0,
        ], $reorder > 0 ? ['reorder_threshold' => $reorder] : []));

        $this->sourceSkus[] = $sku;
    }

    // ───────────────────────────────────────────── Document lines (shared) ────────────────

    /**
     * Reads a lines sheet, grouped by document number, counting everything for the reconciliation.
     *
     * @return array<string, list<array<string, string>>>
     */
    private function readDocumentLines(string $file, string $sheet, string $prefix, string $label): array
    {
        $groups = [];
        foreach ($this->readSheetRows($file, $sheet) as $row) {
            $amount = $this->lineAmount($row);
            $this->tally("{$prefix}_lines");
            $this->tally("{$prefix}_value", $this->toPiasters($amount));

            $number = trim($row['no_s'] ?? '');
            if ($number === '') {
                $this->excludeLine($prefix, $label, $row, 'line without a document number');

                continue;
            }

            $groups[$number][] = $row;
        }

        $this->tally("{$prefix}_invoices", count($groups));

        return $groups;
    }

    private function lineAmount(array $line): float
    {
        return round($this->toFloat($line['qu_s'] ?? null) * $this->toFloat($line['pr_s'] ?? null), 2);
    }

    private function excludeLine(string $prefix, string $label, array $line, string $reason, ?string $detail = null): void
    {
        $amount = $this->lineAmount($line);
        $this->flag($label, $line, $reason, $amount, [
            "{$prefix}_lines" => 1,
            "{$prefix}_value" => $this->toPiasters($amount),
        ], $detail);
    }

    private function excludeDocument(string $prefix, string $label, array $lines, string $reason): void
    {
        foreach ($lines as $line) {
            $this->excludeLine($prefix, $label, $line, $reason);
        }
        $this->excluded["{$prefix}_invoices"] = ($this->excluded["{$prefix}_invoices"] ?? 0) + 1;
    }

    /**
     * Prices and validates the lines of one document. Excluded lines go to the review report.
     *
     * @return array{items: list<array<string, mixed>>, piasters: int}
     */
    private function prepareLines(array $lines, array $productIds, string $prefix, string $label): array
    {
        $items = [];
        $piasters = 0;

        foreach ($lines as $line) {
            $name = trim($line['name'] ?? '');
            $quantity = round($this->toFloat($line['qu_s'] ?? null), 3);
            $amount = $this->lineAmount($line);

            if (! isset($productIds[$name])) {
                $this->excludeLine($prefix, $label, $line, 'unknown product', $name);

                continue;
            }
            if ((int) $quantity === self::CORRUPT_QUANTITY) {
                $this->excludeLine($prefix, $label, $line, 'corrupt quantity excluded', $name);

                continue;
            }
            if ($this->toPiasters($amount) > self::SUSPICIOUS_LINE_PIASTERS) {
                $this->flag($label, $line, 'line value above 200,000 kept as is', $amount, [], $name);
            }

            $items[] = [
                'product_id' => $productIds[$name],
                'quantity' => $quantity,
                'price' => round($this->toFloat($line['pr_s'] ?? null), 2),
                'amount' => $amount,
            ];
            $piasters += $this->toPiasters($amount);
        }

        return ['items' => $items, 'piasters' => $piasters];
    }

    /**
     * Products of these documents, by name. A name missing from the catalog is created, inactive
     * and reported, so the document keeps its real value and the supplier its real balance.
     *
     * @return array<string, int>
     */
    private function productIdsForDocuments(array $groups, string $label): array
    {
        $productIds = Product::query()->pluck('id', 'name')->all();

        $this->transact(function () use ($groups, $label, &$productIds) {
            foreach ($groups as $lines) {
                foreach ($lines as $line) {
                    $name = trim($line['name'] ?? '');
                    if ($name !== '' && ! isset($productIds[$name])) {
                        $productIds[$name] = $this->createDocumentProduct($name);
                        $this->flag($label, $line, 'product created from a document, inactive', $this->lineAmount($line), [], $name);
                    }
                }
            }
        });

        return $productIds;
    }

    private function createDocumentProduct(string $name): int
    {
        return Product::create([
            'category_id' => Category::where('slug', self::DEFAULT_CATEGORY_SLUG)->firstOrFail()->id,
            'sku' => 'LEG-DOC-'.strtoupper(substr(md5($name), 0, 10)),
            'name' => $name,
            'brand' => 'غير محدد',
            'warranty_months' => 0,
            'cost_price' => 0,
            'retail_price' => 0,
            'wholesale_price' => 0,
            'current_stock' => 0,
            'is_battery' => false,
            'is_active' => false,
        ])->id;
    }

    // ───────────────────────────────────────────── Purchases (file 3) ─────────────────────

    private function importPurchases(): void
    {
        $groups = $this->readDocumentLines(self::FILE_PURCHASES, self::SHEET_PURCHASE_LINES, 'purchase', 'purchase lines');
        $productIds = $this->productIdsForDocuments($groups, 'purchase lines');

        foreach (array_chunk($groups, $this->chunk, true) as $chunk) {
            $this->transact(function () use ($chunk, $productIds) {
                foreach ($chunk as $number => $lines) {
                    $this->createPurchase((string) $number, $lines, $productIds);
                }
            });
        }

        $this->info('Purchases: '.count($groups).' documents read.');
    }

    private function createPurchase(string $number, array $lines, array $productIds): void
    {
        $invoiceNumber = 'LEG-P-'.$number;
        if (PurchaseInvoice::where('invoice_number', $invoiceNumber)->exists()) {
            $this->excludeDocument('purchase', 'purchase lines', $lines, 'purchase number already imported');

            return;
        }

        $supplierName = trim($lines[0]['to'] ?? '');
        if ($supplierName === '') {
            $this->excludeDocument('purchase', 'purchase lines', $lines, 'purchase without a supplier');

            return;
        }

        $prepared = $this->prepareLines($lines, $productIds, 'purchase', 'purchase lines');
        if ($prepared['items'] === []) {
            $this->flag('purchase lines', $lines[0], 'purchase with no importable lines', 0, ['purchase_invoices' => 1]);

            return;
        }

        $date = $this->documentDate($lines[0], 'purchase lines');
        $total = $this->fromPiasters($prepared['piasters']);

        $invoice = new PurchaseInvoice;
        $invoice->forceFill([
            'invoice_number' => $invoiceNumber,
            'supplier_id' => $this->supplierIdByName($supplierName, 'purchase lines', $lines[0]),
            'branch_id' => $this->branch->id,
            'received_by' => $this->user->id,
            'invoice_date' => $date->toDateString(),
            'subtotal' => $total,
            'final_amount' => $total,
            'paid_amount' => 0,
            'remaining_amount' => $total,
            'payment_status' => 'unpaid',
            'created_at' => $date,
            'updated_at' => $date,
        ])->saveQuietly();

        $rows = array_map(fn (array $item) => [
            'purchase_invoice_id' => $invoice->id,
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'unit_cost_price' => $item['price'],
            'total_cost_price' => $item['amount'],
            'created_at' => $date,
            'updated_at' => $date,
        ], $prepared['items']);

        DB::table('purchase_invoice_items')->insert($rows);
    }

    private function supplierIdByName(string $name, string $sheet, array $row): int
    {
        $supplier = Supplier::firstOrCreate(['name' => $name], [
            'company_name' => $name,
            'phone' => null,
            'credit_limit' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        if ($supplier->wasRecentlyCreated) {
            $this->flag($sheet, $row, 'supplier created from a document (not a supplier in contacts)');
        }

        return $supplier->id;
    }

    /**
     * Cost = the product's latest purchase price with a real price; no purchase leaves cost at 0.
     * The history spans 2009 to 2026, so an average over it would understate today's cost.
     */
    private function applyProductCosts(): void
    {
        $costs = DB::table('purchase_invoice_items as i')
            ->join('purchase_invoices as p', 'p.id', '=', 'i.purchase_invoice_id')
            ->where('p.invoice_number', 'like', 'LEG-P-%')
            ->where('i.quantity', '>', 0)
            ->where('i.unit_cost_price', '>', 0)
            ->orderBy('p.invoice_date')
            ->orderBy('p.id')
            ->orderBy('i.id')
            ->get(['i.product_id', 'i.unit_cost_price'])
            ->pluck('unit_cost_price', 'product_id');

        foreach ($costs->chunk($this->chunk) as $chunk) {
            $this->transact(function () use ($chunk) {
                foreach ($chunk as $productId => $cost) {
                    DB::table('products')->where('id', $productId)->update(['cost_price' => $cost]);
                }
            });
        }

        $withoutCost = $this->sourceSkus === []
            ? collect()
            : collect($this->sourceSkus)->diff(
                Product::whereIn('id', $costs->keys())->pluck('sku')
            );
        foreach ($withoutCost as $sku) {
            $this->flag('catalog', ['code' => $sku], 'no purchase found, cost left at 0');
        }
    }

    /** Links each product to the suppliers it was really bought from; the latest purchase is primary. */
    private function syncSupplierProducts(): void
    {
        $lines = DB::table('purchase_invoice_items as i')
            ->join('purchase_invoices as p', 'p.id', '=', 'i.purchase_invoice_id')
            ->where('p.invoice_number', 'like', 'LEG-P-%')
            ->orderBy('p.invoice_date')
            ->orderBy('p.id')
            ->orderBy('i.id')
            ->get(['i.product_id', 'i.unit_cost_price', 'p.supplier_id', 'p.created_at']);

        $latest = [];
        $primary = [];
        foreach ($lines as $line) {
            $latest[$line->supplier_id.':'.$line->product_id] = $line;
            $primary[$line->product_id] = $line->supplier_id;
        }

        foreach (array_chunk($latest, $this->chunk) as $chunk) {
            $this->transact(function () use ($chunk, $primary) {
                foreach ($chunk as $line) {
                    DB::table('supplier_products')->updateOrInsert(
                        ['supplier_id' => $line->supplier_id, 'product_id' => $line->product_id],
                        [
                            'last_purchase_price' => $line->unit_cost_price,
                            'is_primary_supplier' => $primary[$line->product_id] === $line->supplier_id,
                            'created_at' => $line->created_at,
                            'updated_at' => $line->created_at,
                        ]
                    );
                }
            });
        }
    }

    // ───────────────────────────────────────────── Supplier settlements (file 3) ──────────

    /** Payments, purchase returns and discounts all reduce what a supplier is owed. */
    private function importSupplierSettlements(): void
    {
        $settlements = array_merge(
            $this->readSupplierPayments(),
            $this->readPurchaseReturns(),
            $this->readPurchaseDiscounts()
        );
        usort($settlements, fn (array $a, array $b) => [$a['date'], $a['order']] <=> [$b['date'], $b['order']]);

        $this->transact(function () use (&$settlements) {
            foreach ($settlements as &$settlement) {
                $settlement['supplier_id'] = $this->supplierIdByName($settlement['name'], $settlement['sheet'], $settlement['row']);
            }
        });

        $invoices = PurchaseInvoice::where('invoice_number', 'like', 'LEG-P-%')
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get(['id', 'invoice_number', 'supplier_id', 'invoice_date', 'final_amount']);

        [$open, $paid] = $this->allocateSupplierSettlements($settlements, $invoices);

        $this->transact(function () use ($invoices, $open, $paid) {
            foreach ($invoices as $invoice) {
                $remaining = $open[$invoice->id];
                $total = $this->toPiasters((float) $invoice->final_amount);

                DB::table('purchase_invoices')->where('id', $invoice->id)->update([
                    'paid_amount' => $this->fromPiasters($paid[$invoice->id] ?? 0),
                    'remaining_amount' => $this->fromPiasters($remaining),
                    'payment_status' => $this->settlementStatus($total, $remaining),
                ]);
            }
        });

        $this->postSupplierLedgers($settlements, $invoices);

        $this->info('Supplier settlements: '.count($settlements).' posted.');
    }

    private function settlementStatus(int $total, int $remaining): string
    {
        if ($remaining <= self::TOLERANCE_PIASTERS) {
            return 'paid';
        }

        return $remaining < $total ? 'partially_paid' : 'unpaid';
    }

    /** @return list<array<string, mixed>> */
    private function readSupplierPayments(): array
    {
        $settlements = [];
        $sheet = 'supplier payments';

        foreach ($this->readSheetRows(self::FILE_PURCHASES, self::SHEET_SUPPLIER_PAYMENTS) as $row) {
            $amount = round($this->toFloat($row['kst'] ?? null), 2);
            $piasters = $this->toPiasters($amount);
            $this->tally('supplier_payments');
            $this->tally('supplier_payments_value', $piasters);

            $name = trim($row['to'] ?? '');
            if ($piasters <= 0 || $name === '') {
                $this->flag($sheet, $row, $piasters <= 0 ? 'zero payment amount' : 'payment without a supplier', $amount, [
                    'supplier_payments' => 1,
                    'supplier_payments_value' => $piasters,
                ]);

                continue;
            }

            if (trim((string) ($row['kind'] ?? '')) !== '' && is_numeric(trim($row['kind']))) {
                $this->flag($sheet, $row, 'payment kind is only a number, please check', $amount);
            }

            $kind = trim((string) ($row['kind'] ?? ''));
            $settlements[] = $this->supplierSettlement($row, $sheet, $name, $piasters, 'payment', 'supplier_payment', 'LEG-PAY-', $kind !== '' ? $kind : null, preg_match(self::TRANSFER_PATTERN, $kind) ? 'bank_transfer' : 'cash');
        }

        return $settlements;
    }

    /** @return list<array<string, mixed>> */
    private function readPurchaseReturns(): array
    {
        $settlements = [];
        $sheet = 'purchase returns';

        foreach ($this->readOptionalRows(self::FILE_PURCHASES, self::SHEET_RETURNS) as $row) {
            $amount = $this->lineAmount($row);
            $piasters = $this->toPiasters($amount);
            $this->tally('purchase_returns');
            $this->tally('purchase_returns_value', $piasters);

            $name = trim($row['to'] ?? '');
            if ($piasters <= 0 || $name === '') {
                $this->flag($sheet, $row, 'purchase return without an amount or supplier', $amount, [
                    'purchase_returns' => 1,
                    'purchase_returns_value' => $piasters,
                ]);

                continue;
            }

            $settlements[] = $this->supplierSettlement($row, $sheet, $name, $piasters, 'return', 'purchase_return', 'LEG-RET-', 'مرتجع مشتريات من النظام القديم', 'cash');
        }

        return $settlements;
    }

    /** The old program's discount value: kst + otherdis - taxprof. */
    private function readPurchaseDiscounts(): array
    {
        $settlements = [];
        $sheet = 'purchase discounts';

        foreach ($this->readOptionalRows(self::FILE_PURCHASES, self::SHEET_DISCOUNTS) as $row) {
            $amount = $this->discountAmount($row);
            $this->tally('purchase_discounts');

            if ($this->isCorruptAmount($amount)) {
                $this->flag($sheet, $row, 'corrupt discount amount excluded', 0, ['purchase_discounts' => 1], 'kst='.($row['kst'] ?? ''));

                continue;
            }

            $amount = round($amount, 2);
            $piasters = $this->toPiasters($amount);
            $this->tally('purchase_discounts_value', $piasters);

            $name = trim($row['to'] ?? '');
            if ($piasters <= 0 || $name === '') {
                $this->flag($sheet, $row, 'discount without an amount or supplier', $amount, [
                    'purchase_discounts' => 1,
                    'purchase_discounts_value' => $piasters,
                ]);

                continue;
            }

            $settlements[] = $this->supplierSettlement($row, $sheet, $name, $piasters, 'discount', 'adjustment', 'LEG-DIS-', 'خصم من النظام القديم', 'cash');
        }

        return $settlements;
    }

    private function discountAmount(array $row): float
    {
        return $this->toFloat($row['kst'] ?? null)
            + $this->toFloat($row['otherdis'] ?? null)
            - $this->toFloat($row['taxprof'] ?? null);
    }

    private function isCorruptAmount(float $amount): bool
    {
        return abs($amount) >= self::CORRUPT_AMOUNT;
    }

    /** @return array<string, mixed> */
    private function supplierSettlement(array $row, string $sheet, string $name, int $piasters, string $kind, string $entryType, string $receiptPrefix, ?string $notes, string $method): array
    {
        return [
            'row' => $row,
            'sheet' => $sheet,
            'kind' => $kind,
            'entry_type' => $entryType,
            'receipt' => $receiptPrefix.$row['__row'],
            'notes' => $notes,
            'method' => $method,
            'supplier_id' => null,
            'name' => $name,
            'number' => trim($row['no_s'] ?? ''),
            'piasters' => $piasters,
            'date' => $this->documentDate($row, $sheet),
            'invoice_id' => null,
            'order' => (int) $row['__row'],
        ];
    }

    /**
     * Settlements naming a purchase settle it first, so an on-account one cannot consume an
     * invoice that a specific one settles later. Whatever is left goes FIFO over the supplier's
     * open purchases; anything still left stays on the running balance.
     *
     * @return array{0: array<int, int>, 1: array<int, int>} purchase id => remaining, and => paid, in piasters
     */
    private function allocateSupplierSettlements(array &$settlements, $invoices): array
    {
        $open = [];
        $paid = [];
        $byNumber = [];
        $bySupplier = [];
        foreach ($invoices as $invoice) {
            $open[$invoice->id] = $this->toPiasters((float) $invoice->final_amount);
            $byNumber[$invoice->invoice_number] = $invoice;
            $bySupplier[$invoice->supplier_id][] = $invoice->id;
        }

        $apply = function (array $settlement, int $invoiceId, int $amount) use (&$open, &$paid) {
            $open[$invoiceId] -= $amount;
            if ($settlement['kind'] === 'payment') {
                $paid[$invoiceId] = ($paid[$invoiceId] ?? 0) + $amount;
            } else {
                $this->purchaseCredits[$invoiceId] = ($this->purchaseCredits[$invoiceId] ?? 0) + $amount;
            }
        };

        $unallocated = [];
        foreach ($settlements as $index => &$settlement) {
            $unallocated[$index] = $settlement['piasters'];

            if ($settlement['number'] === '' || $settlement['number'] === '0') {
                continue;
            }

            $invoice = $byNumber['LEG-P-'.$settlement['number']] ?? null;
            if (! $invoice || $invoice->supplier_id !== $settlement['supplier_id']) {
                // Return documents have their own numbers, so only a payment that misses is news.
                if ($settlement['kind'] === 'payment') {
                    $this->flag(
                        $settlement['sheet'],
                        $settlement['row'],
                        $invoice ? 'names another supplier\'s purchase, posted on account' : 'purchase not found, posted on account',
                        $this->fromPiasters($settlement['piasters'])
                    );
                }

                continue;
            }

            $applied = min($unallocated[$index], $open[$invoice->id]);
            $apply($settlement, $invoice->id, $applied);
            $unallocated[$index] -= $applied;
            $settlement['invoice_id'] = $invoice->id;
        }
        unset($settlement);

        foreach ($settlements as $index => $settlement) {
            foreach ($bySupplier[$settlement['supplier_id']] ?? [] as $invoiceId) {
                if ($unallocated[$index] <= 0) {
                    break;
                }

                $applied = min($unallocated[$index], $open[$invoiceId]);
                $apply($settlement, $invoiceId, $applied);
                $unallocated[$index] -= $applied;
            }

            if ($unallocated[$index] > 0) {
                $id = $settlement['supplier_id'];
                $this->unallocatedBySupplier[$id] = ($this->unallocatedBySupplier[$id] ?? 0) + $unallocated[$index];
            }
        }

        return [$open, $paid];
    }

    /**
     * Writes each supplier's ledger in chronological order, so ids follow the running balance:
     * by day, purchases before settlements, then source order.
     */
    private function postSupplierLedgers(array $settlements, $invoices): void
    {
        $events = [];

        foreach ($invoices as $invoice) {
            $events[$invoice->supplier_id][] = [
                'day' => $invoice->invoice_date->toDateString(),
                'priority' => 0,
                'order' => $invoice->id,
                'sign' => 1,
                'piasters' => $this->toPiasters((float) $invoice->final_amount),
                'entry_type' => 'purchase_invoice',
                'purchase_invoice_id' => $invoice->id,
                'payment_method' => 'cash',
                'receipt_number' => null,
                'notes' => null,
            ];
        }

        foreach ($settlements as $settlement) {
            $events[$settlement['supplier_id']][] = [
                'day' => $settlement['date']->toDateString(),
                'priority' => 1,
                'order' => $settlement['order'],
                'sign' => -1,
                'piasters' => $settlement['piasters'],
                'entry_type' => $settlement['entry_type'],
                'purchase_invoice_id' => $settlement['invoice_id'],
                'payment_method' => $settlement['method'],
                'receipt_number' => $settlement['receipt'],
                'notes' => $settlement['notes'],
            ];
        }

        foreach (array_chunk($events, 50, true) as $chunk) {
            $this->transact(function () use ($chunk) {
                foreach ($chunk as $supplierId => $supplierEvents) {
                    $this->postOneSupplierLedger((int) $supplierId, $supplierEvents);
                }
            });
        }
    }

    private function postOneSupplierLedger(int $supplierId, array $events): void
    {
        usort($events, fn (array $a, array $b) => [$a['day'], $a['priority'], $a['order']] <=> [$b['day'], $b['priority'], $b['order']]);

        $balance = 0;
        $rows = [];
        foreach ($events as $event) {
            $before = $balance;
            $balance += $event['sign'] * $event['piasters'];
            $timestamp = Carbon::parse($event['day'])->setTime(12, 0, 0);

            $rows[] = [
                'supplier_id' => $supplierId,
                'purchase_invoice_id' => $event['purchase_invoice_id'],
                'entry_type' => $event['entry_type'],
                'amount' => $this->fromPiasters($event['piasters']),
                'balance_before' => $this->fromPiasters($before),
                'balance_after' => $this->fromPiasters($balance),
                'payment_method' => $event['payment_method'],
                'paid_by' => $this->user->id,
                'receipt_number' => $event['receipt_number'],
                'notes' => $event['notes'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DB::table('supplier_ledger_entries')->where('supplier_id', $supplierId)->delete();
        foreach (array_chunk($rows, 500) as $batch) {
            DB::table('supplier_ledger_entries')->insert($batch);
        }
        DB::table('suppliers')->where('id', $supplierId)->update(['current_balance' => $this->fromPiasters($balance)]);
    }

    // ───────────────────────────────────────────── Retail sales (file 4) ──────────────────

    private function importRetailInvoices(): void
    {
        $groups = $this->readDocumentLines(self::FILE_RETAIL, self::SHEET_RETAIL_LINES, 'retail', 'retail lines');
        $productIds = $this->productIdsForDocuments($groups, 'retail lines');

        foreach (array_chunk($groups, $this->chunk, true) as $chunk) {
            $this->transact(function () use ($chunk, $productIds) {
                foreach ($chunk as $number => $lines) {
                    $this->createRetailInvoice((string) $number, $lines, $productIds);
                }
            });
        }

        $this->info('Retail invoices: '.count($groups).' documents read.');
    }

    private function createRetailInvoice(string $number, array $lines, array $productIds): void
    {
        $invoiceNumber = 'INV-R-'.$number;
        if (Invoice::where('invoice_number', $invoiceNumber)->exists()) {
            $this->excludeDocument('retail', 'retail lines', $lines, 'invoice number already imported');

            return;
        }

        $prepared = $this->prepareLines($lines, $productIds, 'retail', 'retail lines');
        if ($prepared['items'] === []) {
            $this->flag('retail lines', $lines[0], 'invoice with no importable lines', 0, ['retail_invoices' => 1]);

            return;
        }

        $name = trim($lines[0]['to'] ?? '');
        $customer = $name === '' ? $this->posCustomer() : Customer::firstOrCreate(['name' => $name], [
            'phone' => null,
            'tier' => 'standard',
            'credit_limit' => 0,
            'current_credit_balance' => 0,
            'is_active' => true,
        ]);

        $date = $this->documentDate($lines[0], 'retail lines');
        $total = $this->fromPiasters($prepared['piasters']);

        $invoice = new Invoice;
        $invoice->forceFill([
            'invoice_number' => $invoiceNumber,
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'cashier_id' => $this->user->id,
            'subtotal' => $total,
            'final_amount' => $total,
            'paid_amount' => 0,
            'remaining_amount' => $total,
            'payment_method' => 'credit',
            'status' => 'unpaid',
            'created_at' => $date,
            'updated_at' => $date,
        ])->saveQuietly();

        $rows = array_map(fn (array $item) => [
            'invoice_id' => $invoice->id,
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['price'],
            'total_price' => $item['amount'],
            'created_at' => $date,
            'updated_at' => $date,
        ], $prepared['items']);

        DB::table('invoice_items')->insert($rows);
    }

    /** Everyone named on a sales document is a customer, including those with no invoice of their own. */
    private function importSalesParties(): void
    {
        $names = [];
        foreach ([self::SHEET_RETAIL_LINES, self::SHEET_RETAIL_PAYMENTS, self::SHEET_RETURNS, self::SHEET_DISBURSEMENTS] as $sheet) {
            foreach ($this->readOptionalRows(self::FILE_RETAIL, $sheet) as $row) {
                $name = trim($row['to'] ?? '');
                if ($name !== '') {
                    $names[$name] = true;
                }
            }
        }

        $this->salesParties = array_map('strval', array_keys($names));
        $this->tally('sales_parties', count($this->salesParties));

        $this->transact(function () {
            foreach ($this->salesParties as $name) {
                Customer::firstOrCreate(['name' => $name], [
                    'phone' => null,
                    'tier' => 'standard',
                    'credit_limit' => 0,
                    'current_credit_balance' => 0,
                    'is_active' => true,
                ]);
            }
        });

        $this->info('Customers on sales documents: '.count($this->salesParties).'.');
    }

    /**
     * Retail payments and sales returns, mirroring the supplier side: a settlement naming an
     * invoice settles it first, the rest goes FIFO over the customer's open invoices, and what is
     * still left stays on the customer's balance, which may go below zero (a credit).
     */
    private function importCustomerSettlements(): void
    {
        $invoices = Invoice::where('invoice_number', 'like', 'INV-R-%')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'invoice_number', 'customer_id', 'final_amount', 'created_at']);

        $settlements = array_merge(
            $this->readRetailPayments($invoices),
            $this->readSalesReturns()
        );
        usort($settlements, fn (array $a, array $b) => [$a['date'], $a['order']] <=> [$b['date'], $b['order']]);

        $this->transact(function () use (&$settlements) {
            foreach ($settlements as &$settlement) {
                $settlement['customer_id'] = $this->customerIdByName($settlement['name']);
            }
        });

        $state = $this->allocateCustomerSettlements($settlements, $invoices);

        foreach ($invoices->chunk($this->chunk) as $chunk) {
            $this->transact(function () use ($chunk, $state) {
                foreach ($chunk as $invoice) {
                    $this->saveRetailInvoiceState($invoice, $state);
                }
            });
        }

        $this->postCustomerLedgers($settlements, $invoices, $state['namedPaid']);

        $this->info('Customer settlements: '.count($settlements).' posted.');
    }

    /** @return list<array<string, mixed>> */
    private function readRetailPayments($invoices): array
    {
        $partyByNumber = [];
        foreach ($invoices as $invoice) {
            $partyByNumber[$invoice->invoice_number] = $invoice->customer_id;
        }
        $names = Customer::whereIn('id', array_unique(array_values($partyByNumber)))->pluck('name', 'id');

        $settlements = [];
        $sheet = 'retail payments';

        foreach ($this->readSheetRows(self::FILE_RETAIL, self::SHEET_RETAIL_PAYMENTS) as $row) {
            $amount = round($this->toFloat($row['kst'] ?? null), 2);
            $piasters = $this->toPiasters($amount);
            $number = trim($row['no_s'] ?? '');
            $this->tally('retail_payments');
            $this->tally('retail_payments_value', $piasters);

            $exclude = fn (string $reason) => $this->flag($sheet, $row, $reason, $amount, [
                'retail_payments' => 1,
                'retail_payments_value' => $piasters,
            ]);

            if ($this->isCorruptAmount($amount)) {
                $exclude('corrupt payment amount excluded');

                continue;
            }
            if ($piasters <= 0) {
                $exclude('payment without an amount');

                continue;
            }

            // Older exports have no party on this sheet: a numbered payment still has its invoice.
            $name = trim($row['to'] ?? '');
            if ($name === '' && isset($partyByNumber['INV-R-'.$number])) {
                $name = (string) $names[$partyByNumber['INV-R-'.$number]];
            }
            if ($name === '') {
                $exclude('payment without a customer or invoice');

                continue;
            }

            if ($number === '' || $number === '0') {
                $this->flag($sheet, $row, 'payment without an invoice, posted on account', $amount);
            }

            $settlements[] = $this->customerSettlement($row, $sheet, $name, $piasters, 'payment', 'payment_collection', 'LEG-COL-');
        }

        return $settlements;
    }

    /** @return list<array<string, mixed>> */
    private function readSalesReturns(): array
    {
        $settlements = [];
        $sheet = 'sales returns';

        foreach ($this->readOptionalRows(self::FILE_RETAIL, self::SHEET_RETURNS) as $row) {
            $amount = $this->lineAmount($row);
            $piasters = $this->toPiasters($amount);
            $this->tally('retail_returns');
            $this->tally('retail_returns_value', $piasters);

            $name = trim($row['to'] ?? '');
            if ($piasters <= 0 || $name === '') {
                $this->flag($sheet, $row, 'sales return without an amount or customer', $amount, [
                    'retail_returns' => 1,
                    'retail_returns_value' => $piasters,
                ]);

                continue;
            }

            $settlements[] = $this->customerSettlement($row, $sheet, $name, $piasters, 'return', 'refund', 'LEG-RET-');
        }

        return $settlements;
    }

    /** @return array<string, mixed> */
    private function customerSettlement(array $row, string $sheet, string $name, int $piasters, string $kind, string $entryType, string $receiptPrefix): array
    {
        return [
            'row' => $row,
            'sheet' => $sheet,
            'kind' => $kind,
            'entry_type' => $entryType,
            'receipt' => $receiptPrefix.$row['__row'],
            'customer_id' => null,
            'name' => $name,
            'number' => trim($row['no_s'] ?? ''),
            'piasters' => $piasters,
            'date' => $this->documentDate($row, $sheet),
            'invoice_id' => null,
            'order' => (int) $row['__row'],
        ];
    }

    private function customerIdByName(string $name): int
    {
        return Customer::firstOrCreate(['name' => $name], [
            'phone' => null,
            'tier' => 'standard',
            'credit_limit' => 0,
            'current_credit_balance' => 0,
            'is_active' => true,
        ])->id;
    }

    /** @return array{open: array<int, int>, paid: array<int, int>, namedPaid: array<int, int>, payments: list<array<string, mixed>>, lastPaidAt: array<int, Carbon>} */
    private function allocateCustomerSettlements(array &$settlements, $invoices): array
    {
        $state = ['open' => [], 'paid' => [], 'namedPaid' => [], 'payments' => [], 'lastPaidAt' => []];
        $byNumber = [];
        $byCustomer = [];
        foreach ($invoices as $invoice) {
            $state['open'][$invoice->id] = $this->toPiasters((float) $invoice->final_amount);
            $byNumber[$invoice->invoice_number] = $invoice;
            $byCustomer[$invoice->customer_id][] = $invoice->id;
        }

        $apply = function (array $settlement, int $invoiceId, int $amount, bool $named) use (&$state) {
            $state['open'][$invoiceId] -= $amount;
            if ($settlement['kind'] !== 'payment') {
                $this->retailCredits[$invoiceId] = ($this->retailCredits[$invoiceId] ?? 0) + $amount;

                return;
            }

            $state['paid'][$invoiceId] = ($state['paid'][$invoiceId] ?? 0) + $amount;
            $state['lastPaidAt'][$invoiceId] = $settlement['date'];
            $state['payments'][] = ['invoice_id' => $invoiceId, 'piasters' => $amount, 'date' => $settlement['date'], 'receipt' => $settlement['receipt'], 'named' => $named];
            if ($named) {
                $state['namedPaid'][$invoiceId] = ($state['namedPaid'][$invoiceId] ?? 0) + $amount;
            }
        };

        $unallocated = [];
        foreach ($settlements as $index => &$settlement) {
            $unallocated[$index] = $settlement['piasters'];
            if ($settlement['number'] === '' || $settlement['number'] === '0' || $settlement['kind'] !== 'payment') {
                continue;
            }

            $invoice = $byNumber['INV-R-'.$settlement['number']] ?? null;
            if (! $invoice || $invoice->customer_id !== $settlement['customer_id']) {
                $this->flag($settlement['sheet'], $settlement['row'], $invoice ? 'names another customer\'s invoice, posted on account' : 'invoice not found, posted on account', $this->fromPiasters($settlement['piasters']));

                continue;
            }

            $applied = min($unallocated[$index], $state['open'][$invoice->id]);
            if ($applied > 0) {
                $apply($settlement, $invoice->id, $applied, true);
            }
            $unallocated[$index] -= $applied;
            $settlement['invoice_id'] = $invoice->id;
            $settlement['named_applied'] = $applied;
        }
        unset($settlement);

        foreach ($settlements as $index => $settlement) {
            foreach ($byCustomer[$settlement['customer_id']] ?? [] as $invoiceId) {
                if ($unallocated[$index] <= 0) {
                    break;
                }

                $applied = min($unallocated[$index], $state['open'][$invoiceId]);
                if ($applied > 0) {
                    $apply($settlement, $invoiceId, $applied, false);
                    $unallocated[$index] -= $applied;
                }
            }
        }

        return $state;
    }

    private function saveRetailInvoiceState(Invoice $invoice, array $state): void
    {
        $id = $invoice->id;
        $total = $this->toPiasters((float) $invoice->final_amount);
        $remaining = $state['open'][$id];
        $settled = $remaining <= self::TOLERANCE_PIASTERS;

        $values = [
            'paid_amount' => $this->fromPiasters($state['paid'][$id] ?? 0),
            'remaining_amount' => $this->fromPiasters($remaining),
            'status' => $this->settlementStatus($total, $remaining),
            'payment_method' => $settled ? 'cash' : 'credit',
        ];
        if (isset($state['lastPaidAt'][$id])) {
            $values['updated_at'] = $state['lastPaidAt'][$id];
        }
        DB::table('invoices')->where('id', $id)->update($values);

        foreach ($state['payments'] as $payment) {
            if ($payment['invoice_id'] !== $id) {
                continue;
            }

            DB::table('invoice_payments')->insert([
                'invoice_id' => $id,
                'payment_method' => 'cash',
                'amount' => $this->fromPiasters($payment['piasters']),
                'transaction_reference' => $payment['receipt'],
                'notes' => $payment['named'] ? null : self::ON_ACCOUNT_NOTE,
                'created_at' => $payment['date'],
                'updated_at' => $payment['date'],
            ]);
        }
    }

    /**
     * The customer's statement, by day: what each invoice left to pay after the payments naming
     * it, then on-account payments and returns in full. A paid invoice writes no row.
     */
    private function postCustomerLedgers(array $settlements, $invoices, array $namedPaid): void
    {
        $events = [];

        foreach ($invoices as $invoice) {
            $debt = $this->toPiasters((float) $invoice->final_amount) - ($namedPaid[$invoice->id] ?? 0);
            if ($debt <= 0) {
                continue;
            }

            $events[$invoice->customer_id][] = [
                'day' => $invoice->created_at->toDateString(),
                'priority' => 0,
                'order' => $invoice->id,
                'sign' => 1,
                'piasters' => $debt,
                'entry_type' => 'invoice_debt',
                'invoice_id' => $invoice->id,
                'receipt_number' => null,
            ];
        }

        foreach ($settlements as $settlement) {
            $piasters = $settlement['piasters'] - ($settlement['named_applied'] ?? 0);
            if ($piasters <= 0) {
                continue;
            }

            $events[$settlement['customer_id']][] = [
                'day' => $settlement['date']->toDateString(),
                'priority' => 1,
                'order' => $settlement['order'],
                'sign' => -1,
                'piasters' => $piasters,
                'entry_type' => $settlement['entry_type'],
                'invoice_id' => $settlement['invoice_id'],
                'receipt_number' => $settlement['receipt'],
            ];
        }

        foreach (array_chunk($events, 50, true) as $chunk) {
            $this->transact(function () use ($chunk) {
                foreach ($chunk as $customerId => $customerEvents) {
                    $this->postOneCustomerLedger((int) $customerId, $customerEvents);
                }
            });
        }
    }

    private function postOneCustomerLedger(int $customerId, array $events): void
    {
        usort($events, fn (array $a, array $b) => [$a['day'], $a['priority'], $a['order']] <=> [$b['day'], $b['priority'], $b['order']]);

        $balance = 0;
        $rows = [];
        foreach ($events as $event) {
            $before = $balance;
            $balance += $event['sign'] * $event['piasters'];
            $timestamp = Carbon::parse($event['day'])->setTime(12, 0, 0);

            $rows[] = [
                'customer_id' => $customerId,
                'invoice_id' => $event['invoice_id'],
                'entry_type' => $event['entry_type'],
                'amount' => $this->fromPiasters($event['piasters']),
                'balance_before' => $this->fromPiasters($before),
                'balance_after' => $this->fromPiasters($balance),
                'collected_by' => $this->user->id,
                'receipt_number' => $event['receipt_number'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        foreach (array_chunk($rows, 500) as $batch) {
            DB::table('credit_ledger_entries')->insert($batch);
        }
        DB::table('customers')->where('id', $customerId)->update(['current_credit_balance' => $this->fromPiasters($balance)]);
    }

    // ───────────────────────────────────────────── Book stock ─────────────────────────────

    /**
     * Applies the old program's stock formula (see STOCK_FORMULA) and records the result through
     * the stock ledger. Every unit that does not reach current_stock is in the review report.
     */
    private function applyBookStock(): void
    {
        $products = DB::table('products as p')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->get(['p.id', 'p.name', 'p.sku', 'p.is_battery', 'c.slug'])
            ->keyBy('name');

        [$book, $fractional] = $this->readBookStock($products);

        foreach (array_chunk($book, $this->chunk, true) as $chunk) {
            $this->transact(function () use ($chunk, $products, $fractional) {
                foreach ($chunk as $name => $milli) {
                    $this->stockOneProduct($products[(string) $name], $milli, isset($fractional[$name]));
                }
            });
        }

        $this->info('Book stock: '.count($this->stockedProductIds).' products stocked.');
    }

    /** @return array{0: array<string, int>, 1: array<string, true>} net thousandths by name, and names with fractional movements */
    private function readBookStock($products): array
    {
        $book = [];
        $fractional = [];

        foreach (self::STOCK_FORMULA as [$file, $sheet, $sign]) {
            foreach ($this->readOptionalRows($file, $sheet) as $row) {
                $quantity = $this->toFloat($row['qu_s'] ?? null);
                $milli = $sign * (int) round(($quantity + $this->toFloat($row['bonus'] ?? null)) * Quantity::SCALE);
                $name = trim($row['name'] ?? '');
                $this->tally('stock_qty', $milli);

                if ((int) $quantity === self::CORRUPT_QUANTITY) {
                    $this->flag('book stock', $row, 'corrupt quantity left out of stock', $milli / Quantity::SCALE, ['stock_qty' => $milli], $name);

                    continue;
                }
                if (! isset($products[$name])) {
                    $this->flag('book stock', $row, 'movement of a product not in the catalog', $milli / Quantity::SCALE, ['stock_qty' => $milli], $name);

                    continue;
                }

                $book[$name] = ($book[$name] ?? 0) + $milli;
                if ($milli % Quantity::SCALE !== 0) {
                    $fractional[$name] = true;
                }
            }
        }

        return [$book, $fractional];
    }

    private function stockOneProduct(object $product, int $milli, bool $hasFractions): void
    {
        // An oil sold by the fraction is a liter product; the export has no usable unit (un1 says
        // box or jerrycan for everything). Other products stay in pieces, with whole stock.
        $inLiters = $hasFractions && ! $product->is_battery && $product->slug === 'oils';
        if ($inLiters) {
            DB::table('products')->where('id', $product->id)->update(['unit' => ProductUnit::Liter->value]);
        }

        if ($milli < 0) {
            $this->flag('book stock', ['code' => $product->sku], 'negative book balance set to 0', $milli / Quantity::SCALE, ['stock_qty' => $milli], $product->name);

            return;
        }

        $remainder = $inLiters ? 0 : $milli % Quantity::SCALE;
        if ($remainder !== 0) {
            $this->flag('book stock', ['code' => $product->sku], 'fractional balance of a piece product rounded down', $remainder / Quantity::SCALE, ['stock_qty' => $remainder], $product->name);
            $milli -= $remainder;
        }

        if ($milli === 0) {
            return;
        }

        $source = Product::findOrFail($product->id);
        app(StockLedgerInterface::class)->setTo($product->id, Quantity::fromMilli($milli), $source, $this->user->id);
        $this->stockedProductIds[] = $product->id;
    }

    // ───────────────────────────────────────────── Employees ──────────────────────────────

    private function importEmployees(): void
    {
        $rows = [];
        foreach ($this->readSheetRows(self::FILE_HR, self::SHEET_EMPLOYEES) as $row) {
            $this->tally('employees');
            $rows[] = $row;
        }

        $this->transact(function () use ($rows) {
            foreach ($rows as $row) {
                $this->importOneEmployee($row);
            }
        });

        $this->info('Employees: '.count($this->sourceEmployeeCodes).' upserted.');
    }

    private function importOneEmployee(array $row): void
    {
        $name = trim($row['to'] ?? '');
        $code = trim($row['code'] ?? '');
        if ($name === '' || $code === '') {
            $this->flag('employees', $row, 'employee without a name or code', 0, ['employees' => 1]);

            return;
        }

        $hireDate = $this->parseLegacyDate($row['da_s2'] ?? null) ?? $this->parseLegacyDate($row['da_s'] ?? null);
        if (! $hireDate) {
            $this->flag('employees', $row, 'missing hire date, import date used');
            $hireDate = now();
        }

        $employeeCode = 'LEGACY-'.$code;
        $employee = Employee::updateOrCreate(['employee_code' => $employeeCode], [
            'branch_id' => $this->branch->id,
            'job_title_id' => $this->jobTitleId((string) ($row['dep'] ?? ''), (string) ($row['staf'] ?? '')),
            'full_name' => $name,
            'national_id' => null,
            'phone' => $this->sanitizeEgyptianMobile($this->firstNonEmpty([$row['tel'] ?? null]), 'employees', $row),
            'hire_date' => $hireDate,
            'grace_period_minutes' => 15,
            'status' => 'active',
        ]);

        SalaryStructure::updateOrCreate(['employee_id' => $employee->id, 'is_current' => true], [
            'basic_salary' => $this->toFloat($row['kst'] ?? null),
            'housing_allowance' => 0,
            'transport_allowance' => 0,
            'other_allowances' => 0,
            'effective_from' => $hireDate,
            'effective_to' => null,
        ]);

        $this->sourceEmployeeCodes[] = $employeeCode;
    }

    /** Same resolution as EmployeeService: departments and titles come from the source text. */
    private function jobTitleId(string $department, string $title): int
    {
        $department = trim($department) ?: self::DEFAULT_DEPARTMENT;
        $title = trim($title) ?: self::DEFAULT_DEPARTMENT;

        $dept = Department::firstOrCreate(
            ['name' => $department],
            ['code' => 'DEP-'.strtoupper(substr(md5($department), 0, 8))]
        );

        return JobTitle::firstOrCreate(['department_id' => $dept->id, 'title' => $title])->id;
    }

    // ───────────────────────────────────────────── Reconciliation ─────────────────────────

    /** @return list<string> every unexplained difference and broken invariant */
    private function printReconciliation(): array
    {
        $this->newLine();
        $this->info('=== Legacy reconciliation (source vs imported) ===');

        $imported = $this->importedTotals();
        $rows = [];
        $failures = [];

        foreach (self::METRICS as $metric => $label) {
            if (! array_key_exists($metric, $this->source)) {
                continue;
            }

            $isMoney = str_ends_with($metric, '_value');
            $isQuantity = str_ends_with($metric, '_qty');
            $source = $this->source[$metric];
            $excluded = $this->excluded[$metric] ?? 0;
            $actual = $imported[$metric];
            $difference = $source - $excluded - $actual;

            $format = fn (int $value) => match (true) {
                $isMoney => number_format($value / 100, 2),
                $isQuantity => number_format($value / Quantity::SCALE, 3),
                default => number_format($value),
            };
            $rows[] = [$label, $format($source), $format($excluded), $format($actual), $format($difference)];

            if (abs($difference) > ($isMoney ? self::TOLERANCE_PIASTERS : 0)) {
                $failures[] = "Unexplained difference in {$label}: {$format($difference)}";
            }
        }

        $this->table(['Metric', 'Source', 'Excluded (see review)', 'Imported', 'Unexplained'], $rows);

        $failures = array_merge($failures, $this->consistencyFailures(), $this->partyBalanceFailures());
        $this->info($failures === [] ? 'All consistency checks passed.' : count($failures).' reconciliation check(s) FAILED.');

        return $failures;
    }

    /** @return array<string, int> */
    private function importedTotals(): array
    {
        $purchaseItems = fn () => DB::table('purchase_invoice_items as i')
            ->join('purchase_invoices as p', 'p.id', '=', 'i.purchase_invoice_id')
            ->where('p.invoice_number', 'like', 'LEG-P-%');
        $retailItems = fn () => DB::table('invoice_items as i')
            ->join('invoices as v', 'v.id', '=', 'i.invoice_id')
            ->where('v.invoice_number', 'like', 'INV-R-%');
        $supplierPayments = fn () => DB::table('supplier_ledger_entries')->where('receipt_number', 'like', 'LEG-PAY-%');
        $ledger = fn (string $table, string $prefix) => DB::table($table)->where('receipt_number', 'like', $prefix.'%');
        $retailPayments = fn () => DB::table('invoice_payments as a')
            ->join('invoices as v', 'v.id', '=', 'a.invoice_id')
            ->where('v.invoice_number', 'like', 'INV-R-%');
        // A receipt can reach both an invoice and the ledger: on-account money is in the ledger in
        // full, so only payments naming their invoice add to it.
        $retailReceipts = $retailPayments()->pluck('a.transaction_reference')
            ->merge($ledger('credit_ledger_entries', 'LEG-COL-')->pluck('receipt_number'))
            ->unique();

        return [
            'contact_suppliers' => $this->countByChunks(Supplier::query(), 'name', $this->contactSuppliers),
            'contact_customers' => $this->countByChunks(Customer::query(), 'name', $this->contactCustomers),
            'products' => $this->countByChunks(Product::query(), 'sku', $this->sourceSkus),
            'purchase_invoices' => PurchaseInvoice::where('invoice_number', 'like', 'LEG-P-%')->count(),
            'purchase_lines' => $purchaseItems()->count(),
            'purchase_value' => $this->toPiasters((float) $purchaseItems()->sum('i.total_cost_price')),
            'supplier_payments' => $supplierPayments()->count(),
            'supplier_payments_value' => $this->toPiasters((float) $supplierPayments()->sum('amount')),
            'retail_invoices' => Invoice::where('invoice_number', 'like', 'INV-R-%')->count(),
            'retail_lines' => $retailItems()->count(),
            'retail_value' => $this->toPiasters((float) $retailItems()->sum('i.total_price')),
            'purchase_returns' => $ledger('supplier_ledger_entries', 'LEG-RET-')->count(),
            'purchase_returns_value' => $this->toPiasters((float) $ledger('supplier_ledger_entries', 'LEG-RET-')->sum('amount')),
            'purchase_discounts' => $ledger('supplier_ledger_entries', 'LEG-DIS-')->count(),
            'purchase_discounts_value' => $this->toPiasters((float) $ledger('supplier_ledger_entries', 'LEG-DIS-')->sum('amount')),
            'retail_payments' => $retailReceipts->count(),
            'retail_payments_value' => $this->toPiasters((float) $retailPayments()->whereNull('a.notes')->sum('a.amount'))
                + $this->toPiasters((float) $ledger('credit_ledger_entries', 'LEG-COL-')->sum('amount')),
            'retail_returns' => $ledger('credit_ledger_entries', 'LEG-RET-')->count(),
            'retail_returns_value' => $this->toPiasters((float) $ledger('credit_ledger_entries', 'LEG-RET-')->sum('amount')),
            'sales_parties' => $this->countByChunks(Customer::query(), 'name', $this->salesParties),
            'stock_qty' => $this->stockedThousandths(),
            'employees' => $this->countByChunks(Employee::query(), 'employee_code', $this->sourceEmployeeCodes),
        ];
    }

    private function stockedThousandths(): int
    {
        $total = 0;
        foreach (array_chunk($this->stockedProductIds, 500) as $chunk) {
            foreach (DB::table('products')->whereIn('id', $chunk)->pluck('current_stock') as $stock) {
                $total += Quantity::toMilli($stock);
            }
        }

        return $total;
    }

    private function countByChunks($query, string $column, array $values): int
    {
        $count = 0;
        foreach (array_chunk(array_values(array_unique($values)), 500) as $chunk) {
            $count += (clone $query)->whereIn($column, $chunk)->count();
        }

        return $count;
    }

    /** @return list<string> */
    private function consistencyFailures(): array
    {
        $failures = [];

        $badPurchases = $this->inconsistentDocuments(
            PurchaseInvoice::where('invoice_number', 'like', 'LEG-P-%')->get(['id', 'final_amount', 'paid_amount', 'remaining_amount', 'payment_status']),
            'payment_status',
            $this->purchaseCredits
        );
        if ($badPurchases > 0) {
            $failures[] = "{$badPurchases} purchase(s) where final, paid, remaining or status disagree.";
        }

        $badInvoices = $this->inconsistentDocuments(
            Invoice::where('invoice_number', 'like', 'INV-R-%')->get(['id', 'final_amount', 'paid_amount', 'remaining_amount', 'status']),
            'status',
            $this->retailCredits
        );
        if ($badInvoices > 0) {
            $failures[] = "{$badInvoices} retail invoice(s) where final, paid, remaining or status disagree.";
        }

        if (array_key_exists('supplier_payments', $this->source)) {
            $failures = array_merge($failures, $this->supplierLedgerFailures());
        }

        return $failures;
    }

    /** @param array<int, int> $credits document id => piasters settled by returns and discounts, not by payments */
    private function inconsistentDocuments($documents, string $statusColumn, array $credits): int
    {
        return $documents->filter(function ($document) use ($statusColumn, $credits) {
            $final = $this->toPiasters((float) $document->final_amount);
            $paid = $this->toPiasters((float) $document->paid_amount);
            $remaining = $this->toPiasters((float) $document->remaining_amount);
            $credited = $credits[$document->id] ?? 0;
            $status = $document->getRawOriginal($statusColumn);

            return abs($final - $paid - $credited - $remaining) > self::TOLERANCE_PIASTERS
                || $status !== $this->settlementStatus($final, $remaining);
        })->count();
    }

    /** @return list<string> */
    private function supplierLedgerFailures(): array
    {
        $failures = [];
        $supplierIds = DB::table('purchase_invoices')->where('invoice_number', 'like', 'LEG-P-%')->distinct()->pluck('supplier_id')
            ->merge(DB::table('supplier_ledger_entries')->where('receipt_number', 'like', 'LEG-PAY-%')->distinct()->pluck('supplier_id'))
            ->unique();

        foreach ($supplierIds as $supplierId) {
            $running = 0;
            $brokenChain = false;
            foreach (DB::table('supplier_ledger_entries')->where('supplier_id', $supplierId)->orderBy('id')->get(['amount', 'balance_before', 'balance_after', 'entry_type']) as $entry) {
                $amount = $this->toPiasters((float) $entry->amount);
                $sign = $entry->entry_type === 'purchase_invoice' ? 1 : -1;
                if ($this->toPiasters((float) $entry->balance_before) !== $running) {
                    $brokenChain = true;
                }
                $running += $sign * $amount;
                if ($this->toPiasters((float) $entry->balance_after) !== $running) {
                    $brokenChain = true;
                }
            }

            $balance = $this->toPiasters((float) DB::table('suppliers')->where('id', $supplierId)->value('current_balance'));
            $open = $this->toPiasters((float) DB::table('purchase_invoices')
                ->where('supplier_id', $supplierId)
                ->where('invoice_number', 'like', 'LEG-P-%')
                ->sum('remaining_amount'));
            $expected = $open - ($this->unallocatedBySupplier[$supplierId] ?? 0);

            if ($brokenChain || $balance !== $running || abs($balance - $expected) > self::TOLERANCE_PIASTERS) {
                $failures[] = "Supplier #{$supplierId}: ledger chain, balance and open purchases disagree.";
            }
        }

        return $failures;
    }

    /**
     * The old program keeps one balance per party across purchases and sales (qry.mdb). It is
     * rebuilt here from the source alone, without the import's own arithmetic, and compared with
     * customer balance minus supplier balance. Positive means the party owes us.
     *
     * @return list<string>
     */
    private function partyBalanceFailures(): array
    {
        if (! array_key_exists('purchase_lines', $this->source) || ! array_key_exists('supplier_payments', $this->source)) {
            return [];
        }

        $legacy = $this->legacyPartyBalances();
        $customers = Customer::whereIn('name', array_keys($legacy))->pluck('current_credit_balance', 'name');
        $suppliers = Supplier::whereIn('name', array_keys($legacy))->pluck('current_balance', 'name');
        $labels = [];
        foreach ($this->readSheetRows(self::FILE_CONTACTS, self::SHEET_CONTACTS) as $row) {
            $labels[trim($row['to'] ?? '')] = trim($row['kindtel'] ?? '');
        }

        $groups = [];
        $mismatched = 0;
        foreach ($legacy as $name => $balance) {
            $name = (string) $name;
            $ours = $this->toPiasters((float) ($customers[$name] ?? 0)) - $this->toPiasters((float) ($suppliers[$name] ?? 0));
            $label = $labels[$name] ?? 'غير موجود في الدفتر';

            $groups[$label] ??= ['parties' => 0, 'owed' => 0, 'owe' => 0, 'ours_owed' => 0, 'ours_owe' => 0];
            $groups[$label]['parties']++;
            $groups[$label][$balance > 0 ? 'owed' : 'owe'] += $balance;
            $groups[$label][$ours > 0 ? 'ours_owed' : 'ours_owe'] += $ours;

            if (abs($ours - $balance) > self::TOLERANCE_PIASTERS) {
                $mismatched++;
            }
        }

        $money = fn (int $piasters) => number_format($piasters / 100, 2);
        $this->newLine();
        $this->info('=== Party balances vs the old program formula (positive: owes us) ===');
        $this->table(
            ['Contact label', 'Parties', 'Old: owes us', 'Old: we owe', 'New: owes us', 'New: we owe'],
            collect($groups)->map(fn (array $g, $label) => [$label, $g['parties'], $money($g['owed']), $money($g['owe']), $money($g['ours_owed']), $money($g['ours_owe'])])->values()->all()
        );
        $this->info('Parties with a different balance: '.$mismatched.' of '.count($legacy).'.');

        return $mismatched === 0 ? [] : ["{$mismatched} party balance(s) differ from the old program formula."];
    }

    /** @return array<string, int> party => piasters */
    private function legacyPartyBalances(): array
    {
        $balances = [];
        $add = function (string $name, int $piasters) use (&$balances) {
            $name = trim($name);
            if ($name !== '') {
                $balances[$name] = ($balances[$name] ?? 0) + $piasters;
            }
        };
        $line = fn (array $row) => $this->toPiasters(round(
            $this->toFloat($row['qu_s'] ?? null) * ($this->toFloat($row['pr_s'] ?? null) + $this->toFloat($row['pr_sa'] ?? null))
                + $this->toFloat($row['bonus'] ?? null) * $this->toFloat($row['pr_sa'] ?? null),
            2
        ));
        $isCorruptLine = fn (array $row) => (int) $this->toFloat($row['qu_s'] ?? null) === self::CORRUPT_QUANTITY;

        $invoiceParty = [];
        foreach ($this->readOptionalRows(self::FILE_RETAIL, self::SHEET_RETAIL_LINES) as $row) {
            $invoiceParty[trim($row['no_s'] ?? '')] = trim($row['to'] ?? '');
            if (! $isCorruptLine($row)) {
                $add($row['to'] ?? '', $line($row));
            }
        }
        foreach ($this->readOptionalRows(self::FILE_RETAIL, self::SHEET_RETURNS) as $row) {
            $add($row['to'] ?? '', -$this->toPiasters($this->lineAmount($row)));
        }
        foreach ($this->readOptionalRows(self::FILE_RETAIL, self::SHEET_RETAIL_PAYMENTS) as $row) {
            $amount = round($this->toFloat($row['kst'] ?? null), 2);
            $counts = trim($row['cb'] ?? '') === '' || trim($row['cb']) === '0';
            if ($counts && ! $this->isCorruptAmount($amount)) {
                $party = trim($row['to'] ?? '') ?: ($invoiceParty[trim($row['no_s'] ?? '')] ?? '');
                $add($party, -$this->toPiasters($amount));
            }
        }

        foreach ($this->readOptionalRows(self::FILE_PURCHASES, self::SHEET_PURCHASE_LINES) as $row) {
            if (! $isCorruptLine($row)) {
                $add($row['to'] ?? '', -$line($row));
            }
        }
        foreach ($this->readOptionalRows(self::FILE_PURCHASES, self::SHEET_RETURNS) as $row) {
            $add($row['to'] ?? '', $this->toPiasters($this->lineAmount($row)));
        }
        foreach ($this->readOptionalRows(self::FILE_PURCHASES, self::SHEET_SUPPLIER_PAYMENTS) as $row) {
            $counts = (trim($row['cb'] ?? '') === '' || trim($row['cb']) === '0') && $this->toFloat($row['qu_s'] ?? null) == 0.0;
            if ($counts) {
                $add($row['to'] ?? '', $this->toPiasters(round($this->toFloat($row['kst'] ?? null), 2)));
            }
        }
        foreach ($this->readOptionalRows(self::FILE_PURCHASES, self::SHEET_DISCOUNTS) as $row) {
            $amount = $this->discountAmount($row);
            if (! $this->isCorruptAmount($amount)) {
                $add($row['to'] ?? '', $this->toPiasters(round($amount, 2)));
            }
        }

        return $balances;
    }

    // ───────────────────────────────────────────── Review report ──────────────────────────

    private function printReviewSummary(): void
    {
        $summary = collect($this->review)
            ->groupBy(fn (array $row) => $row['sheet'].' | '.$row['reason'])
            ->map(fn ($rows, $key) => [$key, $rows->count(), number_format((float) $rows->sum('amount'), 2)])
            ->values()
            ->all();

        $this->newLine();
        $this->info('=== Review report by reason ===');
        $this->table(['Sheet | reason', 'Rows', 'Amount'], $summary);

        $this->info('=== Sheets out of scope (not imported) ===');
        $this->table(['Sheet', 'Rows', 'Total'], array_map(
            fn (array $sheet) => [$sheet['sheet'], $sheet['rows'], number_format($sheet['total'], 2)],
            $this->outOfScopeSheets()
        ));
    }

    /** @return list<array{sheet: string, rows: int, total: float}> */
    private function outOfScopeSheets(): array
    {
        $sheets = [];
        foreach (self::OUT_OF_SCOPE_SHEETS as [$file, $sheet]) {
            if (! is_file($this->dataPath.$file)) {
                continue;
            }

            try {
                $rows = 0;
                $total = 0.0;
                foreach ($this->readSheetRows($file, $sheet) as $row) {
                    $rows++;
                    $total += array_key_exists('kst', $row) ? $this->toFloat($row['kst']) : $this->lineAmount($row);
                }
                $sheets[] = ['sheet' => $file.' / '.$sheet, 'rows' => $rows, 'total' => $total];
            } catch (RuntimeException) {
                // A workbook without this sheet has nothing to report.
            }
        }

        return $sheets;
    }

    /** storage/app is private and git-ignored: the report holds no names of people, but real document numbers. */
    private function writeReviewReport(): void
    {
        if ($this->dryRun) {
            $this->info('DRY RUN: review report not written.');

            return;
        }

        $path = storage_path('app/legacy-import-review-'.now()->format('Ymd-His').'.json');
        file_put_contents($path, json_encode([
            'rows' => $this->review,
            'out_of_scope' => $this->outOfScopeSheets(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Review report: '.$path);
    }
}
