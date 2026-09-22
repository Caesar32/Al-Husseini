<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Models\Category;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Warranty;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\InitialDataSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->admin = User::first();
    $this->actingAs($this->admin);

    $this->branch = Branch::firstOrCreate(
        ['code' => 'MAIN'],
        [
            'name' => 'فرع دمياط الجديدة',
            'city' => 'دمياط الجديدة',
            'address' => 'شارع المحجوب - بجوار الكنيسة والبنك الأهلي',
            'phone' => '01012345678',
            'is_active' => true,
        ]
    );

    $this->dept = Department::firstOrCreate(
        ['name' => 'الصيانة الفنية والبطاريات'],
        ['code' => 'TECH']
    );

    $this->job = JobTitle::firstOrCreate(
        ['department_id' => $this->dept->id, 'title' => 'فني كهرباء وبطاريات سيارات'],
        ['code' => 'BAT-TECH']
    );
});

test('unauthenticated guest cannot access global search endpoint', function () {
    auth()->logout();

    $response = $this->getJson(route('admin.global_search', ['q' => 'أحمد']));
    $response->assertStatus(401);
});

test('search returns empty results when query is less than two characters', function () {
    $response = $this->getJson(route('admin.global_search', ['q' => 'أ']));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'total_count' => 0,
                'sections' => [],
            ],
        ]);
});

test('search accurately indexes and finds employee by name, code and handles arabic normalization', function () {
    $emp = Employee::create([
        'branch_id' => $this->branch->id,
        'job_title_id' => $this->job->id,
        'employee_code' => 'EMP-IDX-001',
        'full_name' => 'أحمد إبراهيم الدسوقي',
        'national_id' => '29501011234567',
        'phone' => '01099887766',
        'hire_date' => '2025-01-01',
        'status' => 'active',
    ]);

    // 1. البحث بالألف المجردة "احمد" بدلاً من "أحمد"
    $res1 = $this->getJson(route('admin.global_search', ['q' => 'احمد']));
    $res1->assertOk();
    $data1 = $res1->json('data');
    expect($data1['total_count'])->toBeGreaterThanOrEqual(1)
        ->and($data1['sections']['employees']['items'][0]['id'])->toBe($emp->id);

    // 2. البحث بكود الموظف
    $res2 = $this->getJson(route('admin.global_search', ['q' => 'EMP-IDX']));
    $res2->assertOk();
    $data2 = $res2->json('data');
    expect($data2['sections']['employees']['items'][0]['id'])->toBe($emp->id);
});

test('search finds customer and customer vehicle through indexes', function () {
    $customer = Customer::create([
        'name' => 'محمد كمال الدين',
        'phone' => '01234567890',
        'national_id' => '29001011234567',
        'tier' => 'vip',
        'is_active' => true,
    ]);

    $vehicle = CustomerVehicle::create([
        'customer_id' => $customer->id,
        'plate_number' => 'ط س ر 1234',
        'car_brand' => 'تويوتا',
        'car_model' => 'كورولا',
        'model_year' => 2022,
        'chassis_number' => 'JT1234567890COR',
    ]);

    // البحث عن العميل
    $resCustomer = $this->getJson(route('admin.global_search', ['q' => 'كمال']));
    $resCustomer->assertOk();
    $dataCustomer = $resCustomer->json('data');
    expect($dataCustomer['sections']['customers']['items'][0]['id'])->toBe($customer->id);

    // البحث عن المركبة برقم الشاسيه
    $resVehicle = $this->getJson(route('admin.global_search', ['q' => 'JT123456']));
    $resVehicle->assertOk();
    $dataVehicle = $resVehicle->json('data');
    expect($dataVehicle['sections']['vehicles']['items'][0]['id'])->toBe($vehicle->id);
});

test('search finds product, battery, invoice and warranty via multi-sector indexing', function () {
    $category = Category::firstOrCreate(
        ['slug' => 'batteries-dry'],
        ['name' => 'بطاريات جافة']
    );

    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'BAT-CHL-70',
        'barcode' => '6221234567890',
        'name' => 'بطارية كلورايد جولد 70 أمبير',
        'brand' => 'Chloride',
        'capacity_ah' => '70Ah',
        'voltage' => '12V',
        'cost_price' => 2000,
        'retail_price' => 2800,
        'current_stock' => 15,
        'is_battery' => true,
        'is_active' => true,
    ]);

    $customer = Customer::create([
        'name' => 'حسام محمود المنصوري',
        'phone' => '01122334455',
        'tier' => 'standard',
        'is_active' => true,
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-2026-9901',
        'branch_id' => $this->branch->id,
        'customer_id' => $customer->id,
        'cashier_id' => $this->admin->id,
        'subtotal' => 2800,
        'final_amount' => 2800,
        'paid_amount' => 2800,
        'status' => 'paid',
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 2800,
        'total_price' => 2800,
        'battery_serial_number' => 'SN-CHL-998877',
    ]);

    $warranty = Warranty::create([
        'invoice_item_id' => $item->id,
        'customer_id' => $customer->id,
        'serial_number' => 'WR-SN-CHL-998877',
        'start_date' => '2026-09-01',
        'end_date' => '2027-09-01',
        'status' => 'active',
    ]);

    // 1. البحث عن المنتج بالماركة أو السعة
    $resProduct = $this->getJson(route('admin.global_search', ['q' => 'كلورايد']));
    $resProduct->assertOk();
    $dataProduct = $resProduct->json('data');
    expect($dataProduct['sections']['products']['items'][0]['id'])->toBe($product->id);

    // 2. البحث عن الفاتورة برقمها
    $resInvoice = $this->getJson(route('admin.global_search', ['q' => 'INV-2026-9901']));
    $resInvoice->assertOk();
    $dataInvoice = $resInvoice->json('data');
    expect($dataInvoice['sections']['invoices']['items'][0]['id'])->toBe($invoice->id);

    // 3. البحث عن الضمان بالسيريال
    $resWarranty = $this->getJson(route('admin.global_search', ['q' => 'WR-SN-CHL']));
    $resWarranty->assertOk();
    $dataWarranty = $resWarranty->json('data');
    expect($dataWarranty['sections']['warranties']['items'][0]['id'])->toBe($warranty->id);
});
