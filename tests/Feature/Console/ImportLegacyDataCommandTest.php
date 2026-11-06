<?php

use App\Models\Branch;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

uses(RefreshDatabase::class);

const LEGACY_CONTACTS = ['1_العملاء_وجهات_الاتصال.xlsx', 'العملاء_وجهات_الاتصال'];
const LEGACY_CATALOG = ['2_كتالوج_الأصناف_وقطع_الغيار.xlsx', 'الأصناف_وقطع_الغيار'];
const LEGACY_PURCHASE_LINES = ['3_فواتير_المبيعات_الجملة.xlsx', 'بنود_المبيعات_والفواتير'];
const LEGACY_SUPPLIER_PAYMENTS = 'سندات_التحصيل_والدفعات';
const LEGACY_RETAIL_LINES = ['4_فواتير_المبيعات_القطاعي.xlsx', 'بنود_فواتير_القطاعي'];
const LEGACY_RETAIL_PAYMENTS = 'التحصيلات_النقدية';
const LEGACY_EMPLOYEES = ['5_الموظفين_والمصروفات.xlsx', 'الموظفين'];

const LEGACY_LINE_HEADER = ['no_s', 'to', 'name', 'qu_s', 'pr_s', 'da_s'];
const LEGACY_PAYMENT_HEADER = ['no_s', 'to', 'kst', 'da_s', 'kind'];
const LEGACY_DISCOUNT_HEADER = ['no_s', 'to', 'kst', 'otherdis', 'taxprof', 'da_s'];

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->reviewFilesBefore = glob(storage_path('app/legacy-import-review-*.json'));
    $this->path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'legacy-import-'.uniqid();
    mkdir($this->path);

    legacyFixture($this->path);
});

afterEach(function () {
    foreach (glob($this->path.DIRECTORY_SEPARATOR.'*') as $file) {
        @unlink($file);
    }
    @rmdir($this->path);

    foreach (array_diff(glob(storage_path('app/legacy-import-review-*.json')), $this->reviewFilesBefore) as $file) {
        @unlink($file);
    }
});

/** A consistent small export: one purchase, one retail invoice paid in full, no payments on account. */
function legacyFixture(string $path, array $overrides = []): void
{
    $sheets = array_merge([
        'contacts' => [
            ['autono', 'kindtel', 'to', 'tel', 'tel2', 'tel3'],
            ['1', 'عميل', 'Synthetic Customer', '+20 10 1234 5678', '', ''],
            ['2', 'عميل', 'Synthetic Invalid Phone', 'not-a-phone', '', ''],
            ['3', 'مورد', 'Synthetic Supplier', '', '', ''],
        ],
        'catalog' => [
            ['code', 'code2', 'name', 'company', 'pr_run1', 'pr_r2un1', 'qu_min'],
            ['SYN-ONE', '', 'Synthetic One', 'Synthetic Brand', 100, 90, 2],
            ['SYN-NONE', '', 'Synthetic None', 'Synthetic Brand', 50, 40, 0],
        ],
        'purchases' => [LEGACY_LINE_HEADER, ['10', 'Synthetic Supplier', 'Synthetic One', 2, 30, '2025-01-02']],
        'supplier_payments' => [LEGACY_PAYMENT_HEADER],
        'retail' => [LEGACY_LINE_HEADER, ['20', 'Synthetic Customer', 'Synthetic One', 1, 100, '2025-01-05']],
        'retail_payments' => [['no_s', 'kst', 'da_s'], ['20', 100, '2025-01-05']],
        'employees' => [['to', 'code', 'staf', 'dep', 'kst', 'da_s', 'da_s2', 'tel']],
        'purchase_returns' => [LEGACY_LINE_HEADER],
        'purchase_disbursements' => [LEGACY_LINE_HEADER],
        'retail_returns' => [LEGACY_LINE_HEADER],
        'retail_disbursements' => [LEGACY_LINE_HEADER],
        'discounts' => [LEGACY_DISCOUNT_HEADER],
    ], $overrides);

    writeLegacyWorkbook($path, LEGACY_CONTACTS[0], [LEGACY_CONTACTS[1] => $sheets['contacts']]);
    writeLegacyWorkbook($path, LEGACY_CATALOG[0], [LEGACY_CATALOG[1] => $sheets['catalog']]);
    writeLegacyWorkbook($path, LEGACY_PURCHASE_LINES[0], [
        LEGACY_PURCHASE_LINES[1] => $sheets['purchases'],
        LEGACY_SUPPLIER_PAYMENTS => $sheets['supplier_payments'],
        'المرتجعات' => $sheets['purchase_returns'],
        'سندات_الصرف' => $sheets['purchase_disbursements'],
        'الخصومات' => $sheets['discounts'],
    ]);
    writeLegacyWorkbook($path, LEGACY_RETAIL_LINES[0], [
        LEGACY_RETAIL_LINES[1] => $sheets['retail'],
        LEGACY_RETAIL_PAYMENTS => $sheets['retail_payments'],
        'المرتجعات' => $sheets['retail_returns'],
        'سندات_الصرف' => $sheets['retail_disbursements'],
    ]);
    writeLegacyWorkbook($path, LEGACY_EMPLOYEES[0], [LEGACY_EMPLOYEES[1] => $sheets['employees']]);
}

function writeLegacyWorkbook(string $path, string $file, array $sheets): void
{
    $writer = new Writer;
    $writer->openToFile($path.DIRECTORY_SEPARATOR.$file);

    $first = true;
    foreach ($sheets as $name => $rows) {
        if (! $first) {
            $writer->addNewSheetAndMakeItCurrent();
        }
        $writer->getCurrentSheet()->setName($name);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $first = false;
    }

    $writer->close();
}

function runLegacyImport(object $test, array $options = []): mixed
{
    return $test->artisan('app:import-legacy-data', array_merge(['--data-path' => $test->path], $options));
}

function latestLegacyReview(): array
{
    $path = collect(glob(storage_path('app/legacy-import-review-*.json')))->sort()->last();

    return json_decode(file_get_contents($path), true);
}

function reviewReasons(): array
{
    return array_column(latestLegacyReview()['rows'], 'reason');
}

function legacyPurchase(string $number): PurchaseInvoice
{
    return PurchaseInvoice::where('invoice_number', 'LEG-P-'.$number)->firstOrFail();
}

function legacySupplier(string $name = 'Synthetic Supplier'): Supplier
{
    return Supplier::where('name', $name)->firstOrFail();
}

test('file 3 becomes purchases and supplier ledger rows, never sales', function () {
    runLegacyImport($this)->assertSuccessful();

    $purchase = legacyPurchase('10');
    expect((float) $purchase->final_amount)->toBe(60.0)
        ->and($purchase->payment_status)->toBe('unpaid')
        ->and($purchase->supplier->name)->toBe('Synthetic Supplier')
        ->and(Invoice::where('invoice_number', 'like', 'INV-W-%')->count())->toBe(0)
        ->and(Invoice::where('invoice_number', 'INV-R-10')->exists())->toBeFalse();

    expect((float) legacySupplier()->current_balance)->toBe(60.0);
});

test('book stock follows the old program formula, with bonus, and is one ledger adjustment per product', function () {
    legacyFixture($this->path, [
        'purchases' => [
            [...LEGACY_LINE_HEADER, 'bonus'],
            ['10', 'Synthetic Supplier', 'Synthetic One', 10, 30, '2025-01-02', 2],
        ],
        'purchase_returns' => [[...LEGACY_LINE_HEADER, 'bonus'], ['1', 'Synthetic Supplier', 'Synthetic One', 1, 30, '2025-01-03', 0]],
        'purchase_disbursements' => [[...LEGACY_LINE_HEADER, 'bonus'], ['2', 'Synthetic Supplier', 'Synthetic One', 3, 30, '2025-01-03', 0]],
        'retail' => [[...LEGACY_LINE_HEADER, 'bonus'], ['20', 'Synthetic Customer', 'Synthetic One', 4, 100, '2025-01-05', 1]],
        'retail_payments' => [['no_s', 'kst', 'da_s'], ['20', 400, '2025-01-05']],
        'retail_returns' => [[...LEGACY_LINE_HEADER, 'bonus'], ['3', 'Synthetic Customer', 'Synthetic One', 2, 100, '2025-01-06', 0]],
        'retail_disbursements' => [[...LEGACY_LINE_HEADER, 'bonus'], ['4', 'Synthetic Customer', 'Synthetic One', 1, 100, '2025-01-06', 0]],
    ]);

    runLegacyImport($this)->assertSuccessful();

    // +10+2 purchases, +3 disbursement, +2 sales return, -4-1 sales, -1 purchase return, -1 disbursement
    $product = Product::where('sku', 'SYN-ONE')->firstOrFail();
    $movements = StockMovement::where('product_id', $product->id)->get();
    expect((float) $product->current_stock)->toBe(10.0)
        ->and($movements)->toHaveCount(1)
        ->and($movements->first()->type)->toBe(App\Enums\StockMovementType::Adjustment)
        ->and((float) $movements->first()->stock_after)->toBe(10.0)
        ->and(StockMovement::where('product_id', Product::where('sku', 'SYN-NONE')->value('id'))->exists())->toBeFalse();
});

test('the corrupt line stays out of stock and the reconciliation explains it', function () {
    legacyFixture($this->path, [
        'purchases' => [
            LEGACY_LINE_HEADER,
            ['10', 'Synthetic Supplier', 'Synthetic One', 5, 30, '2025-01-02'],
            ['10', 'Synthetic Supplier', 'Synthetic One', 103304, 103304, '2025-01-02'],
        ],
        'retail' => [LEGACY_LINE_HEADER],
        'retail_payments' => [['no_s', 'kst', 'da_s']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    expect((float) Product::where('sku', 'SYN-ONE')->value('current_stock'))->toBe(5.0)
        ->and(reviewReasons())->toContain('corrupt quantity left out of stock');
});

test('a negative book balance becomes 0, reported, and writes no movement', function () {
    legacyFixture($this->path, [
        'purchases' => [LEGACY_LINE_HEADER, ['10', 'Synthetic Supplier', 'Synthetic One', 1, 30, '2025-01-02']],
        'retail' => [LEGACY_LINE_HEADER, ['20', 'Synthetic Customer', 'Synthetic One', 3, 100, '2025-01-05']],
        'retail_payments' => [['no_s', 'kst', 'da_s'], ['20', 300, '2025-01-05']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $row = collect(latestLegacyReview()['rows'])->firstWhere('reason', 'negative book balance set to 0');
    expect((float) Product::where('sku', 'SYN-ONE')->value('current_stock'))->toBe(0.0)
        ->and(StockMovement::count())->toBe(0)
        ->and((float) $row['amount'])->toBe(-2.0);
});

test('a fractional balance is kept for an oil, in liters, and rounded down for a piece product', function () {
    legacyFixture($this->path, [
        'catalog' => [
            ['code', 'code2', 'name', 'company', 'pr_run1', 'pr_r2un1', 'qu_min'],
            ['SYN-OIL', '', 'زيت تجريبي', 'b', 100, 90, 0],
            ['SYN-ONE', '', 'Synthetic One', 'b', 100, 90, 0],
        ],
        'purchases' => [
            LEGACY_LINE_HEADER,
            ['10', 'Synthetic Supplier', 'زيت تجريبي', 4, 30, '2025-01-02'],
            ['10', 'Synthetic Supplier', 'Synthetic One', 4, 30, '2025-01-02'],
        ],
        'retail' => [
            LEGACY_LINE_HEADER,
            ['20', 'Synthetic Customer', 'زيت تجريبي', 1.5, 100, '2025-01-05'],
            ['20', 'Synthetic Customer', 'Synthetic One', 1.5, 100, '2025-01-05'],
        ],
        'retail_payments' => [['no_s', 'kst', 'da_s'], ['20', 300, '2025-01-05']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $oil = Product::where('sku', 'SYN-OIL')->firstOrFail();
    expect((float) $oil->current_stock)->toBe(2.5)
        ->and($oil->unit)->toBe(App\Enums\ProductUnit::Liter)
        ->and((float) Product::where('sku', 'SYN-ONE')->value('current_stock'))->toBe(2.0)
        ->and(reviewReasons())->toContain('fractional balance of a piece product rounded down');
});

test('the stock journal ends on the imported book stock', function () {
    runLegacyImport($this)->assertSuccessful();

    $journal = StockMovement::where('product_id', Product::where('sku', 'SYN-ONE')->value('id'))->latest('id')->first();
    expect((float) $journal->stock_after)->toBe(1.0)
        ->and((float) Product::where('sku', 'SYN-ONE')->value('current_stock'))->toBe(1.0);
});

test('everyone named on a sales document becomes a customer, even with no invoice', function () {
    legacyFixture($this->path, [
        'retail_payments' => [['no_s', 'to', 'kst', 'da_s'], ['20', 'Synthetic Customer', 100, '2025-01-05'], ['0', 'Only Paid Once', 50, '2025-01-06']],
        'retail_returns' => [[...LEGACY_LINE_HEADER, 'bonus'], ['3', 'Only Returned Once', 'Synthetic One', 1, 100, '2025-01-06', 0]],
    ]);

    runLegacyImport($this)->assertSuccessful();

    expect(Customer::where('name', 'Only Paid Once')->exists())->toBeTrue()
        ->and(Customer::where('name', 'Only Returned Once')->exists())->toBeTrue()
        ->and((float) Customer::where('name', 'Only Paid Once')->value('current_credit_balance'))->toBe(-50.0)
        ->and((float) Customer::where('name', 'Only Returned Once')->value('current_credit_balance'))->toBe(-100.0)
        ->and(Supplier::where('name', 'Only Paid Once')->exists())->toBeFalse();
});

test('the supplier ledger follows dates, not the order of the sheets, with same-day purchases first', function () {
    legacyFixture($this->path, [
        'purchases' => [
            LEGACY_LINE_HEADER,
            ['12', 'Synthetic Supplier', 'Synthetic One', 1, 200, '2025-03-01'],
            ['10', 'Synthetic Supplier', 'Synthetic One', 1, 100, '2025-01-01'],
        ],
        'supplier_payments' => [
            LEGACY_PAYMENT_HEADER,
            ['0', 'Synthetic Supplier', 50, '2025-03-01', 'late one'],
            ['0', 'Synthetic Supplier', 30, '2025-01-15', 'early one'],
        ],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $entries = SupplierLedgerEntry::where('supplier_id', legacySupplier()->id)->orderBy('id')->get();
    expect($entries->pluck('entry_type')->all())->toBe(['purchase_invoice', 'supplier_payment', 'purchase_invoice', 'supplier_payment'])
        ->and($entries->map(fn ($e) => (float) $e->balance_after)->all())->toBe([100.0, 70.0, 270.0, 220.0])
        ->and($entries->map(fn ($e) => (float) $e->balance_before)->all())->toBe([0.0, 100.0, 70.0, 270.0])
        ->and($entries->map(fn ($e) => $e->created_at->toDateString())->all())->toBe(['2025-01-01', '2025-01-15', '2025-03-01', '2025-03-01'])
        ->and((float) legacySupplier()->current_balance)->toBe(220.0);
});

test('a payment naming a purchase settles it before on-account payments allocate FIFO', function () {
    legacyFixture($this->path, [
        'purchases' => [
            LEGACY_LINE_HEADER,
            ['10', 'Synthetic Supplier', 'Synthetic One', 1, 100, '2025-01-01'],
            ['11', 'Synthetic Supplier', 'Synthetic One', 1, 100, '2025-01-02'],
        ],
        'supplier_payments' => [
            LEGACY_PAYMENT_HEADER,
            ['0', 'Synthetic Supplier', 60, '2025-01-03', 'on account first by date'],
            ['10', 'Synthetic Supplier', 100, '2025-01-04', 'names invoice 10'],
        ],
    ]);

    runLegacyImport($this)->assertSuccessful();

    expect(legacyPurchase('10')->payment_status)->toBe('paid')
        ->and((float) legacyPurchase('10')->remaining_amount)->toBe(0.0)
        ->and(legacyPurchase('11')->payment_status)->toBe('partially_paid')
        ->and((float) legacyPurchase('11')->paid_amount)->toBe(60.0)
        ->and((float) legacyPurchase('11')->remaining_amount)->toBe(40.0)
        ->and((float) legacySupplier()->current_balance)->toBe(40.0);
});

test('payments above every open purchase leave a negative balance and are kept whole', function () {
    legacyFixture($this->path, [
        'purchases' => [LEGACY_LINE_HEADER, ['10', 'Synthetic Supplier', 'Synthetic One', 1, 100, '2025-01-01']],
        'supplier_payments' => [LEGACY_PAYMENT_HEADER, ['0', 'Synthetic Supplier', 130, '2025-01-02', 'overpaid']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    expect((float) legacySupplier()->current_balance)->toBe(-30.0)
        ->and(legacyPurchase('10')->payment_status)->toBe('paid')
        ->and((float) SupplierLedgerEntry::where('entry_type', 'supplier_payment')->sum('amount'))->toBe(130.0);
});

test('supplier payment keeps its original text, and method follows it', function () {
    legacyFixture($this->path, [
        'supplier_payments' => [
            LEGACY_PAYMENT_HEADER,
            ['0', 'Synthetic Supplier', 10, '2025-01-03', 'خردة'],
            ['0', 'Synthetic Supplier', 10, '2025-01-04', 'دفعة انستا باي'],
        ],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $payments = SupplierLedgerEntry::where('entry_type', 'supplier_payment')->orderBy('id')->get();
    expect($payments->pluck('notes')->all())->toBe(['خردة', 'دفعة انستا باي'])
        ->and($payments->pluck('payment_method')->all())->toBe(['cash', 'bank_transfer']);
});

test('a payment naming an unknown purchase is posted on account and reported', function () {
    legacyFixture($this->path, [
        'supplier_payments' => [LEGACY_PAYMENT_HEADER, ['999', 'Synthetic Supplier', 20, '2025-01-03', 'x']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    expect((float) legacySupplier()->current_balance)->toBe(40.0)
        ->and(reviewReasons())->toContain('purchase not found, posted on account');
});

test('customer debt is what remains after retail payments, with no ledger row for a paid invoice', function () {
    legacyFixture($this->path, [
        'retail' => [
            LEGACY_LINE_HEADER,
            ['20', 'Synthetic Customer', 'Synthetic One', 1, 100, '2025-01-05'],
            ['21', 'Synthetic Customer', 'Synthetic One', 1, 200, '2025-01-06'],
            ['22', 'Synthetic Customer', 'Synthetic One', 1, 50, '2025-01-07'],
        ],
        'retail_payments' => [
            ['no_s', 'kst', 'da_s'],
            ['20', 100, '2025-01-05'],
            ['21', 150, '2025-01-06'],
        ],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $customer = Customer::where('name', 'Synthetic Customer')->firstOrFail();
    $paid = Invoice::where('invoice_number', 'INV-R-20')->firstOrFail();
    $entries = CreditLedgerEntry::where('customer_id', $customer->id)->orderBy('id')->get();

    expect((float) $customer->current_credit_balance)->toBe(100.0)
        ->and($entries->map(fn ($e) => (float) $e->amount)->all())->toBe([50.0, 50.0])
        ->and((float) $entries->last()->balance_after)->toBe(100.0)
        ->and(CreditLedgerEntry::where('invoice_id', $paid->id)->exists())->toBeFalse();
});

test('invoice payment method and status follow what was paid', function () {
    legacyFixture($this->path, [
        'retail' => [
            LEGACY_LINE_HEADER,
            ['20', 'Synthetic Customer', 'Synthetic One', 1, 100, '2025-01-05'],
            ['21', 'Synthetic Customer', 'Synthetic One', 1, 100, '2025-01-05'],
            ['22', 'Synthetic Customer', 'Synthetic One', 1, 100, '2025-01-05'],
        ],
        'retail_payments' => [['no_s', 'kst', 'da_s'], ['20', 100, '2025-01-05'], ['21', 40, '2025-01-05']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $invoice = fn (string $number) => Invoice::where('invoice_number', 'INV-R-'.$number)->firstOrFail();
    expect($invoice('20')->payment_method)->toBe('cash')
        ->and($invoice('20')->getRawOriginal('status'))->toBe('paid')
        ->and($invoice('21')->payment_method)->toBe('credit')
        ->and($invoice('21')->getRawOriginal('status'))->toBe('partially_paid')
        ->and($invoice('22')->payment_method)->toBe('credit')
        ->and($invoice('22')->getRawOriginal('status'))->toBe('unpaid');
});

test('retail quantities keep their fractions', function () {
    legacyFixture($this->path, [
        'retail' => [LEGACY_LINE_HEADER, ['20', 'Synthetic Customer', 'Synthetic One', 1.5, 100, '2025-01-05']],
        'retail_payments' => [['no_s', 'kst', 'da_s'], ['20', 150, '2025-01-05']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $invoice = Invoice::where('invoice_number', 'INV-R-20')->firstOrFail();
    expect((float) $invoice->final_amount)->toBe(150.0)
        ->and((float) $invoice->items()->value('quantity'))->toBe(1.5);
});

test('historical rows keep their own dates, so nothing lands in today\'s figures', function () {
    legacyFixture($this->path, [
        'supplier_payments' => [LEGACY_PAYMENT_HEADER, ['10', 'Synthetic Supplier', 60, '2025-01-09', 'x']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $invoice = Invoice::where('invoice_number', 'INV-R-20')->firstOrFail();
    expect(legacyPurchase('10')->created_at->toDateString())->toBe('2025-01-02')
        ->and($invoice->created_at->toDateString())->toBe('2025-01-05')
        ->and(InvoicePayment::where('invoice_id', $invoice->id)->first()->created_at->toDateString())->toBe('2025-01-05')
        ->and(SupplierLedgerEntry::where('entry_type', 'supplier_payment')->first()->created_at->toDateString())->toBe('2025-01-09')
        ->and(DB::table('invoice_items')->where('invoice_id', $invoice->id)->value('created_at'))->toStartWith('2025-01-05')
        ->and(DB::table('purchase_invoice_items')->where('purchase_invoice_id', legacyPurchase('10')->id)->value('created_at'))->toStartWith('2025-01-02');
});

test('a missing date falls back to the old marker and is reported, never today', function () {
    legacyFixture($this->path, [
        'purchases' => [LEGACY_LINE_HEADER, ['10', 'Synthetic Supplier', 'Synthetic One', 1, 100, '']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    expect(legacyPurchase('10')->created_at->toDateString())->toBe('2008-12-31')
        ->and(reviewReasons())->toContain('missing date, 2008-12-31 used');
});

test('a corrupt line is excluded and explained, and the run still succeeds', function () {
    legacyFixture($this->path, [
        'purchases' => [
            LEGACY_LINE_HEADER,
            ['10', 'Synthetic Supplier', 'Synthetic One', 2, 30, '2025-01-02'],
            ['10', 'Synthetic Supplier', 'Synthetic One', 103304, 103304, '2025-01-02'],
        ],
    ]);

    runLegacyImport($this)->assertSuccessful();

    expect((float) legacyPurchase('10')->final_amount)->toBe(60.0)
        ->and(reviewReasons())->toContain('corrupt quantity excluded');
});

test('a line above 200,000 is kept at its real value and reported', function () {
    legacyFixture($this->path, [
        'purchases' => [LEGACY_LINE_HEADER, ['10', 'Synthetic Supplier', 'Synthetic One', 3, 100000, '2025-01-02']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    expect((float) legacyPurchase('10')->final_amount)->toBe(300000.0)
        ->and(reviewReasons())->toContain('line value above 200,000 kept as is');
});

test('a line naming a product missing from the catalog keeps its value and creates the product, inactive', function () {
    legacyFixture($this->path, [
        'purchases' => [
            LEGACY_LINE_HEADER,
            ['10', 'Synthetic Supplier', 'Synthetic One', 2, 30, '2025-01-02'],
            ['10', 'Synthetic Supplier', 'Not In Catalog', 1, 500, '2025-01-02'],
        ],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $product = Product::withoutGlobalScopes()->where('name', 'Not In Catalog')->firstOrFail();
    expect((float) legacyPurchase('10')->final_amount)->toBe(560.0)
        ->and((bool) $product->is_active)->toBeFalse()
        ->and((float) $product->current_stock)->toBe(1.0)
        ->and(reviewReasons())->toContain('product created from a document, inactive');
});

test('an unexplained difference makes the command fail', function () {
    // Two catalog rows with one code merge into a single product and nothing reports it.
    legacyFixture($this->path, [
        'catalog' => [
            ['code', 'code2', 'name', 'company', 'pr_run1', 'pr_r2un1', 'qu_min'],
            ['SYN-ONE', '', 'Synthetic One', 'Synthetic Brand', 100, 90, 2],
            ['SYN-ONE', '', 'Synthetic One Again', 'Synthetic Brand', 100, 90, 2],
        ],
        'purchases' => [LEGACY_LINE_HEADER],
        'retail' => [LEGACY_LINE_HEADER],
        'retail_payments' => [['no_s', 'kst', 'da_s']],
    ]);

    runLegacyImport($this)->expectsOutputToContain('Unexplained difference in Products')->assertFailed();
});

test('cost is the latest purchase by date, not the last row of the sheet, and ignores zero prices', function () {
    legacyFixture($this->path, [
        'purchases' => [
            LEGACY_LINE_HEADER,
            ['12', 'Synthetic Supplier', 'Synthetic One', 1, 0, '2025-05-01'],
            ['11', 'Synthetic Supplier', 'Synthetic One', 1, 55, '2025-03-01'],
            ['10', 'Synthetic Supplier', 'Synthetic One', 9, 20, '2025-01-01'],
        ],
    ]);

    runLegacyImport($this)->assertSuccessful();

    expect((float) Product::where('sku', 'SYN-ONE')->value('cost_price'))->toBe(55.0);
});

test('catalog prices map retail and wholesale, and cost comes from purchases only', function () {
    legacyFixture($this->path, [
        'catalog' => [
            ['code', 'code2', 'name', 'company', 'pr_run1', 'pr_r2un1', 'qu_min'],
            ['SYN-ONE', '', 'Synthetic One', 'Synthetic Brand', 100, 90, 2],
            ['SYN-HIGH', '', 'Synthetic High', 'Synthetic Brand', 100, 500, 0],
            ['SYN-ZERO', '', 'Synthetic Zero', 'Synthetic Brand', 0, 0, 0],
        ],
        'purchases' => [
            LEGACY_LINE_HEADER,
            ['10', 'Synthetic Supplier', 'Synthetic One', 1, 30, '2025-01-02'],
            ['11', 'Synthetic Supplier', 'Synthetic One', 3, 40, '2025-01-03'],
        ],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $product = fn (string $sku) => Product::withoutGlobalScopes()->where('sku', $sku)->firstOrFail();
    expect((float) $product('SYN-ONE')->retail_price)->toBe(100.0)
        ->and((float) $product('SYN-ONE')->wholesale_price)->toBe(90.0)
        ->and((float) $product('SYN-ONE')->cost_price)->toBe(40.0)
        ->and((float) $product('SYN-HIGH')->wholesale_price)->toBe(100.0)
        ->and((float) $product('SYN-HIGH')->cost_price)->toBe(0.0)
        ->and((bool) $product('SYN-ONE')->is_active)->toBeTrue()
        ->and((bool) $product('SYN-ZERO')->is_active)->toBeFalse()
        ->and(reviewReasons())->toContain('wholesale price zero or above retail, retail price used')
        ->and(reviewReasons())->toContain('no purchase found, cost left at 0')
        ->and(reviewReasons())->toContain('zero retail price, product left inactive');
});

test('is_battery needs a capacity token, not just the letters ah in a name', function () {
    legacyFixture($this->path, [
        'catalog' => [
            ['code', 'code2', 'name', 'company', 'pr_run1', 'pr_r2un1', 'qu_min'],
            ['SYN-ONE', '', 'Synthetic One', 'b', 100, 90, 0],
            ['SYN-CAP', '', 'Synthetic 70 AH', 'b', 100, 90, 0],
            ['SYN-AR', '', 'بطارية تجريبية', 'b', 100, 90, 0],
            ['SYN-MAH', '', 'Mahle filter', 'b', 100, 90, 0],
        ],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $isBattery = fn (string $sku) => (bool) Product::where('sku', $sku)->value('is_battery');
    expect($isBattery('SYN-CAP'))->toBeTrue()
        ->and($isBattery('SYN-AR'))->toBeTrue()
        ->and($isBattery('SYN-MAH'))->toBeFalse();
});

test('supplier links come from real purchases, with the latest cost and supplier', function () {
    legacyFixture($this->path, [
        'purchases' => [
            LEGACY_LINE_HEADER,
            ['10', 'Synthetic Supplier', 'Synthetic One', 1, 30, '2025-01-02'],
            ['11', 'Synthetic Supplier', 'Synthetic One', 1, 40, '2025-02-02'],
            ['12', 'Other Synthetic Supplier', 'Synthetic One', 1, 35, '2025-03-02'],
        ],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $productId = Product::where('sku', 'SYN-ONE')->value('id');
    $link = fn (string $name) => DB::table('supplier_products')
        ->where('product_id', $productId)
        ->where('supplier_id', legacySupplier($name)->id)
        ->first();

    expect((float) $link('Synthetic Supplier')->last_purchase_price)->toBe(40.0)
        ->and((bool) $link('Synthetic Supplier')->is_primary_supplier)->toBeFalse()
        ->and((float) $link('Other Synthetic Supplier')->last_purchase_price)->toBe(35.0)
        ->and((bool) $link('Other Synthetic Supplier')->is_primary_supplier)->toBeTrue()
        ->and(reviewReasons())->toContain('supplier created from a document (not a supplier in contacts)');
});

test('a retail voucher of a million or more is a typing error: excluded and reported', function () {
    legacyFixture($this->path, [
        'retail_payments' => [['no_s', 'to', 'kst', 'da_s'], ['0', 'Synthetic Customer', 45000000, '2025-01-06'], ['20', 'Synthetic Customer', 100, '2025-01-05']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $row = collect(latestLegacyReview()['rows'])->firstWhere('reason', 'corrupt payment amount excluded');
    expect((float) InvoicePayment::sum('amount'))->toBe(100.0)
        ->and((float) $row['amount'])->toBe(45000000.0)
        ->and((float) Customer::where('name', 'Synthetic Customer')->value('current_credit_balance'))->toBe(0.0);
});

test('a retail payment without an invoice settles the oldest open invoices, and is in the ledger in full', function () {
    legacyFixture($this->path, [
        'retail' => [
            LEGACY_LINE_HEADER,
            ['20', 'Synthetic Customer', 'Synthetic One', 1, 100, '2025-01-05'],
            ['21', 'Synthetic Customer', 'Synthetic One', 1, 100, '2025-01-06'],
        ],
        'retail_payments' => [['no_s', 'to', 'kst', 'da_s'], ['0', 'Synthetic Customer', 130, '2025-01-07']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $invoice = fn (string $number) => Invoice::where('invoice_number', 'INV-R-'.$number)->firstOrFail();
    $customer = Customer::where('name', 'Synthetic Customer')->firstOrFail();
    $entries = CreditLedgerEntry::where('customer_id', $customer->id)->orderBy('id')->get();

    expect($invoice('20')->getRawOriginal('status'))->toBe('paid')
        ->and((float) $invoice('21')->remaining_amount)->toBe(70.0)
        ->and((float) InvoicePayment::where('invoice_id', $invoice('21')->id)->sum('amount'))->toBe(30.0)
        ->and($entries->pluck('entry_type')->all())->toBe(['invoice_debt', 'invoice_debt', 'payment_collection'])
        ->and((float) $entries->last()->amount)->toBe(130.0)
        ->and((float) $customer->current_credit_balance)->toBe(70.0)
        ->and(reviewReasons())->toContain('payment without an invoice, posted on account');
});

test('a retail payment above everything open leaves the customer in credit', function () {
    legacyFixture($this->path, [
        'retail_payments' => [['no_s', 'to', 'kst', 'da_s'], ['20', 'Synthetic Customer', 130, '2025-01-05']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $customer = Customer::where('name', 'Synthetic Customer')->firstOrFail();
    expect((float) InvoicePayment::sum('amount'))->toBe(100.0)
        ->and((float) $customer->current_credit_balance)->toBe(-30.0)
        ->and((float) CreditLedgerEntry::where('customer_id', $customer->id)->where('entry_type', 'payment_collection')->sum('amount'))->toBe(30.0);
});

test('a sales return reduces the customer balance and the oldest open invoice, without a payment', function () {
    legacyFixture($this->path, [
        'retail_payments' => [['no_s', 'to', 'kst', 'da_s']],
        'retail_returns' => [LEGACY_LINE_HEADER, ['3', 'Synthetic Customer', 'Synthetic One', 1, 40, '2025-01-06']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $invoice = Invoice::where('invoice_number', 'INV-R-20')->firstOrFail();
    $customer = Customer::where('name', 'Synthetic Customer')->firstOrFail();
    expect((float) $invoice->remaining_amount)->toBe(60.0)
        ->and((float) $invoice->paid_amount)->toBe(0.0)
        ->and($invoice->getRawOriginal('status'))->toBe('partially_paid')
        ->and((float) $customer->current_credit_balance)->toBe(60.0)
        ->and(CreditLedgerEntry::where('customer_id', $customer->id)->where('entry_type', 'refund')->exists())->toBeTrue()
        ->and(InvoicePayment::where('invoice_id', $invoice->id)->exists())->toBeFalse();
});

test('a purchase return reduces the supplier balance, FIFO, and is not a payment', function () {
    legacyFixture($this->path, [
        'purchase_returns' => [LEGACY_LINE_HEADER, ['1', 'Synthetic Supplier', 'Synthetic One', 1, 25, '2025-01-03']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $entry = SupplierLedgerEntry::where('entry_type', 'purchase_return')->firstOrFail();
    expect((float) legacySupplier()->current_balance)->toBe(35.0)
        ->and((float) legacyPurchase('10')->remaining_amount)->toBe(35.0)
        ->and((float) legacyPurchase('10')->paid_amount)->toBe(0.0)
        ->and(legacyPurchase('10')->payment_status)->toBe('partially_paid')
        ->and((float) $entry->amount)->toBe(25.0);
});

test('a discount follows the old formula (kst + otherdis - taxprof) and settles the purchase it names', function () {
    legacyFixture($this->path, [
        'purchases' => [
            LEGACY_LINE_HEADER,
            ['10', 'Synthetic Supplier', 'Synthetic One', 1, 100, '2025-01-01'],
            ['11', 'Synthetic Supplier', 'Synthetic One', 1, 100, '2025-01-02'],
        ],
        'discounts' => [LEGACY_DISCOUNT_HEADER, ['11', 'Synthetic Supplier', 10, 5, 3, '2025-01-03']],
    ]);

    runLegacyImport($this)->assertSuccessful();

    $entry = SupplierLedgerEntry::where('entry_type', 'adjustment')->firstOrFail();
    expect((float) $entry->amount)->toBe(12.0)
        ->and($entry->purchase_invoice_id)->toBe(legacyPurchase('11')->id)
        ->and((float) legacyPurchase('11')->remaining_amount)->toBe(88.0)
        ->and((float) legacyPurchase('10')->remaining_amount)->toBe(100.0)
        ->and((float) legacySupplier()->current_balance)->toBe(188.0);
});

test('a corrupt discount is excluded and reported, without breaking the totals', function () {
    legacyFixture($this->path, [
        'discounts' => [
            LEGACY_DISCOUNT_HEADER,
            ['0', 'Synthetic Supplier', '6.33333333333333E+53', 0, 0, '2025-01-03'],
            ['0', 'Synthetic Supplier', 10, 0, 0, '2025-01-03'],
        ],
    ]);

    runLegacyImport($this)->assertSuccessful();

    expect((float) legacySupplier()->current_balance)->toBe(50.0)
        ->and(reviewReasons())->toContain('corrupt discount amount excluded');
});

test('a party on both sides nets out like the old program, and every party matches its formula', function () {
    legacyFixture($this->path, [
        'contacts' => [['autono', 'kindtel', 'to', 'tel', 'tel2', 'tel3'], ['1', 'مورد', 'Both Sides', '', '', '']],
        'purchases' => [LEGACY_LINE_HEADER, ['10', 'Both Sides', 'Synthetic One', 2, 100, '2025-01-02']],
        'supplier_payments' => [LEGACY_PAYMENT_HEADER, ['0', 'Both Sides', 50, '2025-01-03', 'x']],
        'purchase_returns' => [LEGACY_LINE_HEADER, ['1', 'Both Sides', 'Synthetic One', 1, 10, '2025-01-03']],
        'discounts' => [LEGACY_DISCOUNT_HEADER, ['0', 'Both Sides', 5, 0, 0, '2025-01-03']],
        'retail' => [LEGACY_LINE_HEADER, ['20', 'Both Sides', 'Synthetic One', 1, 70, '2025-01-05']],
        'retail_payments' => [['no_s', 'to', 'kst', 'da_s'], ['0', 'Both Sides', 20, '2025-01-06']],
    ]);

    runLegacyImport($this)
        ->expectsOutputToContain('Parties with a different balance: 0 of 1.')
        ->assertSuccessful();

    // owes us: sales 70 - paid 20 = 50; we owe: 200 - 50 - 10 - 5 = 135
    expect((float) Customer::where('name', 'Both Sides')->value('current_credit_balance'))->toBe(50.0)
        ->and((float) legacySupplier('Both Sides')->current_balance)->toBe(135.0);
});

test('the review report holds no names of people', function () {
    runLegacyImport($this)->assertSuccessful();

    expect(json_encode(latestLegacyReview(), JSON_UNESCAPED_UNICODE))
        ->not->toContain('Synthetic Customer')
        ->not->toContain('Synthetic Invalid Phone');
});

test('a dry run over every step changes nothing and still prints the reconciliation', function () {
    $existing = Product::first();
    $existing->update(['cost_price' => 777]);
    $supplierBalance = Supplier::create(['name' => 'Existing Supplier', 'company_name' => 'Existing Supplier', 'current_balance' => 123]);
    $counts = fn () => [
        Product::count(),
        Supplier::count(),
        Customer::count(),
        Invoice::count(),
        PurchaseInvoice::count(),
        SupplierLedgerEntry::count(),
        CreditLedgerEntry::count(),
        InvoicePayment::count(),
        DB::table('supplier_products')->count(),
        StockMovement::count(),
        (float) Product::sum('current_stock'),
    ];
    $before = $counts();
    $reports = glob(storage_path('app/legacy-import-review-*.json'));

    runLegacyImport($this, ['--dry-run' => true])
        ->expectsOutputToContain('Legacy reconciliation')
        ->expectsOutputToContain('All consistency checks passed')
        ->assertSuccessful();

    expect($counts())->toBe($before)
        ->and((float) $existing->fresh()->cost_price)->toBe(777.0)
        ->and((float) $supplierBalance->fresh()->current_balance)->toBe(123.0)
        ->and(glob(storage_path('app/legacy-import-review-*.json')))->toBe($reports)
        ->and(DB::transactionLevel())->toBe(1); // the RefreshDatabase wrapper only
});

test('Egyptian mobiles are normalized and invalid values cleared', function () {
    runLegacyImport($this, ['--step' => 'customers'])->assertSuccessful();

    expect(Customer::where('name', 'Synthetic Customer')->value('phone'))->toBe('01012345678')
        ->and(Customer::where('name', 'Synthetic Invalid Phone')->value('phone'))->toBeNull();
});

test('employees take their department and title from the source', function () {
    legacyFixture($this->path, [
        'employees' => [
            ['to', 'code', 'staf', 'dep', 'kst', 'da_s', 'da_s2', 'tel'],
            ['Synthetic Worker', '7', 'Synthetic Title', 'Synthetic Dept', 5000, '2020-01-01', '', ''],
            ['', '8', '', '', 0, '', '', ''],
        ],
    ]);

    runLegacyImport($this, ['--step' => 'employees'])->assertSuccessful();

    $employee = App\Models\Employee::where('employee_code', 'LEGACY-7')->firstOrFail();
    expect($employee->jobTitle->title)->toBe('Synthetic Title')
        ->and((string) $employee->hire_date)->toStartWith('2020-01-01')
        ->and(reviewReasons())->toContain('employee without a name or code');
});

test('wipe is refused in production before touching the database', function () {
    $this->app['env'] = 'production';

    $this->artisan('app:import-legacy-data', ['--step' => 'wipe'])
        ->expectsOutputToContain('Refusing to wipe')
        ->assertFailed();

    expect(Branch::where('code', 'MAIN')->exists())->toBeTrue()
        ->and(User::count())->toBeGreaterThan(0);
});

test('--fresh is refused in production too', function () {
    $this->app['env'] = 'production';

    runLegacyImport($this, ['--fresh' => true])
        ->expectsOutputToContain('Refusing to wipe')
        ->assertFailed();

    expect(PurchaseInvoice::where('invoice_number', 'like', 'LEG-P-%')->exists())->toBeFalse();
});
