<?php

namespace App\Services;

use App\Contracts\SearchServiceInterface;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\PurchaseInvoice;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use App\Models\Supplier;
use App\Models\User;

class SearchService implements SearchServiceInterface
{
    /** Permission required to see each result section (SEC-05). */
    public const SECTION_PERMISSIONS = [
        'employees'         => 'employees.view',
        'customers'         => 'customers.view',
        'vehicles'          => 'customers.view',
        'products'          => 'products.view',
        'invoices'          => 'invoices.view',
        'warranties'        => 'warranties.view',
        'suppliers'         => 'suppliers.view',
        'purchase_invoices' => 'purchases.view',
        'warranty_claims'   => 'warranties.view',
    ];

    /** @return list<string> sections the given user may search */
    public static function sectionsVisibleTo(?User $user): array
    {
        if (!$user) {
            return [];
        }

        return array_keys(array_filter(self::SECTION_PERMISSIONS, fn (string $permission) => $user->can($permission)));
    }

    /**
     * {@inheritDoc}
     */
    public function search(string $query, int $limitPerSection = 5, ?array $sections = null): array
    {
        $term = trim($query);

        if (mb_strlen($term) < 2) {
            return [
                'query' => $term,
                'total_count' => 0,
                'sections' => [],
            ];
        }

        $variants = $this->getSearchVariants($term);
        $cleanPhoneOrCode = preg_replace('/[^\d\w]/u', '', $term);

        // Sections the caller may not see are never queried.
        $allowed = fn (string $key) => $sections === null || in_array($key, $sections, true);

        $employees = $allowed('employees') ? $this->searchEmployees($term, $variants, $cleanPhoneOrCode, $limitPerSection) : [];
        $customers = $allowed('customers') ? $this->searchCustomers($term, $variants, $cleanPhoneOrCode, $limitPerSection) : [];
        $vehicles  = $allowed('vehicles') ? $this->searchVehicles($term, $variants, $cleanPhoneOrCode, $limitPerSection) : [];
        $products  = $allowed('products') ? $this->searchProducts($term, $variants, $cleanPhoneOrCode, $limitPerSection) : [];
        $invoices  = $allowed('invoices') ? $this->searchInvoices($term, $variants, $cleanPhoneOrCode, $limitPerSection) : [];
        $warranties = $allowed('warranties') ? $this->searchWarranties($term, $variants, $cleanPhoneOrCode, $limitPerSection) : [];
        $suppliers = $allowed('suppliers') ? $this->searchSuppliers($term, $variants, $cleanPhoneOrCode, $limitPerSection) : [];
        $purchaseInvoices = $allowed('purchase_invoices') ? $this->searchPurchaseInvoices($term, $variants, $cleanPhoneOrCode, $limitPerSection) : [];
        $warrantyClaims = $allowed('warranty_claims') ? $this->searchWarrantyClaims($term, $variants, $cleanPhoneOrCode, $limitPerSection) : [];

        $sections = array_filter([
            'employees' => [
                'title' => 'الموظفون وفريق العمل',
                'icon' => 'ri-user-star-line',
                'count' => count($employees),
                'items' => $employees,
            ],
            'customers' => [
                'title' => 'العملاء وقاعدة البيانات',
                'icon' => 'ri-user-shared-line',
                'count' => count($customers),
                'items' => $customers,
            ],
            'vehicles' => [
                'title' => 'مركبات العملاء والورشة',
                'icon' => 'ri-car-line',
                'count' => count($vehicles),
                'items' => $vehicles,
            ],
            'products' => [
                'title' => 'البطاريات والمنتجات',
                'icon' => 'ri-battery-charge-line',
                'count' => count($products),
                'items' => $products,
            ],
            'invoices' => [
                'title' => 'الفواتير وسندات البيع',
                'icon' => 'ri-file-list-3-line',
                'count' => count($invoices),
                'items' => $invoices,
            ],
            'warranties' => [
                'title' => 'شهادات الضمان الإلكتروني',
                'icon' => 'ri-shield-check-line',
                'count' => count($warranties),
                'items' => $warranties,
            ],
            'suppliers' => [
                'title' => 'الموردون وشركات البطاريات',
                'icon' => 'ri-truck-line',
                'count' => count($suppliers),
                'items' => $suppliers,
            ],
            'purchase_invoices' => [
                'title' => 'فواتير المشتريات والتوريدات',
                'icon' => 'ri-shopping-cart-2-line',
                'count' => count($purchaseInvoices),
                'items' => $purchaseInvoices,
            ],
            'warranty_claims' => [
                'title' => 'مطالبات وتذاكر استبدال الضمان',
                'icon' => 'ri-alarm-warning-line',
                'count' => count($warrantyClaims),
                'items' => $warrantyClaims,
            ],
        ], fn($section) => $section['count'] > 0);

        $totalCount = array_sum(array_column($sections, 'count'));

        return [
            'query' => $term,
            'total_count' => $totalCount,
            'sections' => $sections,
        ];
    }

    /**
     * البحث في جدول الموظفين
     */
    protected function searchEmployees(string $term, array $variants, string $clean, int $limit): array
    {
        $employees = Employee::with(['jobTitle', 'branch'])
            ->where(function ($q) use ($variants, $term, $clean) {
                foreach ($variants as $v) {
                    $q->orWhere('full_name', 'like', "%{$v}%");
                }
                $q->orWhere('employee_code', 'like', "%{$term}%")
                  ->orWhere('national_id', 'like', "%{$term}%")
                  ->orWhere('zkteco_pin', 'like', "%{$term}%");

                if (!empty($clean)) {
                    $q->orWhere('phone', 'like', "%{$clean}%");
                }
            })
            ->limit($limit)
            ->get();

        return $employees->map(function ($emp) {
            $badgeColor = match ($emp->status) {
                'active' => 'badge bg-success-subtle text-success',
                'on_leave' => 'badge bg-warning-subtle text-warning',
                default => 'badge bg-danger-subtle text-danger',
            };

            $statusText = match ($emp->status) {
                'active' => 'على رأس العمل',
                'on_leave' => 'في إجازة',
                default => 'موقوف / منهي',
            };

            return [
                'id' => $emp->id,
                'title' => $emp->full_name,
                'subtitle' => ($emp->jobTitle?->title ?? 'موظف') . " | كود: {$emp->employee_code} | هاتف: {$emp->phone}",
                'badge' => $statusText,
                'badge_class' => $badgeColor,
                'url' => route('admin.hr.employees', ['search' => $emp->employee_code, 'open_profile' => $emp->id]),
                'icon' => 'ri-user-line',
            ];
        })->toArray();
    }

    /**
     * البحث في جدول العملاء
     */
    protected function searchCustomers(string $term, array $variants, string $clean, int $limit): array
    {
        $customers = Customer::where(function ($q) use ($variants, $term, $clean) {
                foreach ($variants as $v) {
                    $q->orWhere('name', 'like', "%{$v}%");
                }
                $q->orWhere('national_id', 'like', "%{$term}%");

                if (!empty($clean)) {
                    $q->orWhere('phone', 'like', "%{$clean}%");
                }
            })
            ->limit($limit)
            ->get();

        return $customers->map(function ($cust) {
            $badgeColor = match ($cust->tier) {
                'vip' => 'badge bg-warning text-dark',
                'fleet' => 'badge bg-primary text-white',
                default => 'badge bg-secondary-subtle text-secondary',
            };

            return [
                'id' => $cust->id,
                'title' => $cust->name,
                'subtitle' => "هاتف: {$cust->phone}" . ($cust->current_credit_balance > 0 ? " | آجل: " . number_format($cust->current_credit_balance, 2) . " ج.م" : ""),
                'badge' => strtoupper($cust->tier),
                'badge_class' => $badgeColor,
                'url' => route('admin.sales.customers'),
                'icon' => 'ri-user-shared-line',
            ];
        })->toArray();
    }

    /**
     * البحث في مركبات العملاء
     */
    protected function searchVehicles(string $term, array $variants, string $clean, int $limit): array
    {
        $vehicles = CustomerVehicle::with('customer')
            ->where(function ($q) use ($variants, $term, $clean) {
                foreach ($variants as $v) {
                    $q->orWhere('plate_number', 'like', "%{$v}%")
                      ->orWhere('car_brand', 'like', "%{$v}%")
                      ->orWhere('car_model', 'like', "%{$v}%");
                }
                if (!empty($clean)) {
                    $q->orWhere('chassis_number', 'like', "%{$clean}%");
                }
            })
            ->limit($limit)
            ->get();

        return $vehicles->map(function ($v) {
            return [
                'id' => $v->id,
                'title' => "لوحة: {$v->plate_number} ({$v->car_brand} {$v->car_model})",
                'subtitle' => "المالك: " . ($v->customer?->name ?? 'غير محدد') . ($v->chassis_number ? " | شاسيه: {$v->chassis_number}" : ""),
                'badge' => $v->model_year ? "موديل {$v->model_year}" : 'مركبة',
                'badge_class' => 'badge bg-info-subtle text-info',
                'url' => route('admin.sales.customers'),
                'icon' => 'ri-car-line',
            ];
        })->toArray();
    }

    /**
     * البحث في المنتجات والبطاريات
     */
    protected function searchProducts(string $term, array $variants, string $clean, int $limit): array
    {
        $products = Product::with('category')
            ->where(function ($q) use ($variants, $term) {
                foreach ($variants as $v) {
                    $q->orWhere('name', 'like', "%{$v}%")
                      ->orWhere('brand', 'like', "%{$v}%");
                }
                $q->orWhere('sku', 'like', "%{$term}%")
                  ->orWhere('barcode', 'like', "%{$term}%")
                  ->orWhere('capacity_ah', 'like', "%{$term}%")
                  ->orWhereHas('suppliers', function ($sq) use ($term) {
                      $sq->where('supplier_sku', 'like', "%{$term}%");
                  });
            })
            ->limit($limit)
            ->get();

        return $products->map(function ($prod) {
            $stockBadge = $prod->current_stock <= $prod->reorder_threshold
                ? 'badge bg-danger-subtle text-danger'
                : 'badge bg-success-subtle text-success';

            return [
                'id' => $prod->id,
                'title' => $prod->name,
                'subtitle' => "كود: {$prod->sku} | السعر: " . number_format($prod->retail_price, 2) . " ج.م | " . ($prod->brand ? "ماركة: {$prod->brand}" : ""),
                'badge' => "المخزون: {$prod->current_stock}",
                'badge_class' => $stockBadge,
                'url' => route('admin.sales.products'),
                'icon' => $prod->is_battery ? 'ri-battery-charge-line' : 'ri-box-3-line',
            ];
        })->toArray();
    }

    /**
     * البحث في الفواتير
     */
    protected function searchInvoices(string $term, array $variants, string $clean, int $limit): array
    {
        $invoices = Invoice::with('customer')
            ->where(function ($q) use ($term, $variants) {
                $q->where('invoice_number', 'like', "%{$term}%")
                  ->orWhereHas('customer', function ($cq) use ($variants) {
                      foreach ($variants as $v) {
                          $cq->orWhere('name', 'like', "%{$v}%");
                      }
                  });
            })
            ->latest('id')
            ->limit($limit)
            ->get();

        return $invoices->map(function ($inv) {
            $badgeColor = match ($inv->status) {
                'paid' => 'badge bg-success-subtle text-success',
                'partially_paid' => 'badge bg-warning-subtle text-warning',
                'unpaid' => 'badge bg-danger-subtle text-danger',
                default => 'badge bg-secondary-subtle text-secondary',
            };

            return [
                'id' => $inv->id,
                'title' => "فاتورة رقم: {$inv->invoice_number}",
                'subtitle' => "العميل: " . ($inv->customer?->name ?? 'عميل نقدي') . " | القيمة: " . number_format($inv->final_amount, 2) . " ج.م",
                'badge' => $inv->status,
                'badge_class' => $badgeColor,
                'url' => route('admin.sales.invoices'),
                'icon' => 'ri-file-list-3-line',
            ];
        })->toArray();
    }

    /**
     * البحث في شهادات الضمان
     */
    protected function searchWarranties(string $term, array $variants, string $clean, int $limit): array
    {
        $warranties = Warranty::with('customer')
            ->where(function ($q) use ($term, $variants) {
                $q->where('serial_number', 'like', "%{$term}%")
                  ->orWhereHas('customer', function ($cq) use ($variants) {
                      foreach ($variants as $v) {
                          $cq->orWhere('name', 'like', "%{$v}%");
                      }
                  });
            })
            ->latest('id')
            ->limit($limit)
            ->get();

        return $warranties->map(function ($w) {
            $badgeColor = match ($w->status) {
                'active' => 'badge bg-success-subtle text-success',
                'expired' => 'badge bg-dark-subtle text-muted',
                'claimed' => 'badge bg-primary-subtle text-primary',
                default => 'badge bg-danger-subtle text-danger',
            };

            return [
                'id' => $w->id,
                'title' => "سيريال الضمان: {$w->serial_number}",
                'subtitle' => "العميل: " . ($w->customer?->name ?? 'غير محدد') . " | ينتهي في: {$w->end_date->format('Y-m-d')}",
                'badge' => $w->status === 'active' ? 'ساري' : $w->status,
                'badge_class' => $badgeColor,
                'url' => route('admin.sales.invoices'),
                'icon' => 'ri-shield-check-line',
            ];
        })->toArray();
    }

    /**
     * البحث في الموردين
     */
    protected function searchSuppliers(string $term, array $variants, string $clean, int $limit): array
    {
        $suppliers = Supplier::where(function ($q) use ($variants, $term, $clean) {
                foreach ($variants as $v) {
                    $q->orWhere('name', 'like', "%{$v}%")
                      ->orWhere('company_name', 'like', "%{$v}%");
                }
                $q->orWhere('tax_number', 'like', "%{$term}%")
                  ->orWhere('commercial_register', 'like', "%{$term}%");

                if (!empty($clean)) {
                    $q->orWhere('phone', 'like', "%{$clean}%");
                }
            })
            ->limit($limit)
            ->get();

        return $suppliers->map(function ($sup) {
            return [
                'id' => $sup->id,
                'title' => $sup->company_name,
                'subtitle' => "المسؤول: {$sup->name} | هاتف: {$sup->phone}",
                'badge' => $sup->is_active ? 'نشط' : 'معطل',
                'badge_class' => $sup->is_active ? 'badge bg-success-subtle text-success' : 'badge bg-danger-subtle text-danger',
                'url' => route('admin.dashboard'),
                'icon' => 'ri-truck-line',
            ];
        })->toArray();
    }

    /**
     * البحث في فواتير المشتريات والتوريد
     */
    protected function searchPurchaseInvoices(string $term, array $variants, string $clean, int $limit): array
    {
        $invoices = PurchaseInvoice::with('supplier')
            ->where(function ($q) use ($term, $variants) {
                $q->where('invoice_number', 'like', "%{$term}%")
                  ->orWhereHas('supplier', function ($sq) use ($variants) {
                      foreach ($variants as $v) {
                          $sq->orWhere('company_name', 'like', "%{$v}%")
                            ->orWhere('name', 'like', "%{$v}%");
                      }
                  });
            })
            ->latest('id')
            ->limit($limit)
            ->get();

        return $invoices->map(function ($inv) {
            $badgeColor = match ($inv->payment_status) {
                'paid' => 'badge bg-success-subtle text-success',
                'partially_paid' => 'badge bg-warning-subtle text-warning',
                'unpaid' => 'badge bg-danger-subtle text-danger',
                default => 'badge bg-secondary-subtle text-secondary',
            };

            return [
                'id' => $inv->id,
                'title' => "فاتورة مشتريات: {$inv->invoice_number}",
                'subtitle' => "المورد: " . ($inv->supplier?->company_name ?? 'غير محدد') . " | القيمة: " . number_format($inv->final_amount, 2) . " ج.م",
                'badge' => $inv->payment_status === 'paid' ? 'مسددة' : ($inv->payment_status === 'partially_paid' ? 'سداد جزئي' : 'غير مسددة'),
                'badge_class' => $badgeColor,
                'url' => route('admin.dashboard'),
                'icon' => 'ri-shopping-cart-2-line',
            ];
        })->toArray();
    }

    /**
     * البحث في تذاكر ومطالبات الضمان
     */
    protected function searchWarrantyClaims(string $term, array $variants, string $clean, int $limit): array
    {
        $claims = WarrantyClaim::with(['customer', 'replacementProduct'])
            ->where(function ($q) use ($term, $variants) {
                $q->where('claim_number', 'like', "%{$term}%")
                  ->orWhere('defective_battery_serial', 'like', "%{$term}%")
                  ->orWhere('replacement_battery_serial', 'like', "%{$term}%")
                  ->orWhereHas('customer', function ($cq) use ($variants) {
                      foreach ($variants as $v) {
                          $cq->orWhere('name', 'like', "%{$v}%");
                      }
                  });
            })
            ->latest('id')
            ->limit($limit)
            ->get();

        return $claims->map(function ($claim) {
            $badgeColor = match ($claim->decision) {
                'replaced' => 'badge bg-success-subtle text-success',
                'rejected' => 'badge bg-danger-subtle text-danger',
                'repaired', 'recharged' => 'badge bg-info-subtle text-info',
                default => 'badge bg-warning-subtle text-warning',
            };

            return [
                'id' => $claim->id,
                'title' => "تذكرة ضمان: " . ($claim->claim_number ?? "#{$claim->id}"),
                'subtitle' => "العميل: " . ($claim->customer?->name ?? 'غير محدد') . " | سيريالات: {$claim->defective_battery_serial}",
                'badge' => $claim->decision === 'replaced' ? 'استبدال فوري' : $claim->decision,
                'badge_class' => $badgeColor,
                'url' => route('admin.dashboard'),
                'icon' => 'ri-alarm-warning-line',
            ];
        })->toArray();
    }

    /**
     * توليد أشكال ومتغيرات الكلمة في اللغة العربية بمرونة تامة (Bidirectional Normalization)
     */
    protected function getSearchVariants(string $term): array
    {
        $clean = trim($term);
        // إزالة التشكيل
        $clean = preg_replace('/[\x{064B}-\x{065F}]/u', '', $clean);

        $variants = [$clean];

        // 1. معالجة الألف والهمزات في بداية الكلمة (أ، إ، آ <-> ا)
        if (str_starts_with($clean, 'ا')) {
            $sub = mb_substr($clean, 1);
            $variants[] = 'أ' . $sub;
            $variants[] = 'إ' . $sub;
            $variants[] = 'آ' . $sub;
        } elseif (str_starts_with($clean, 'أ') || str_starts_with($clean, 'إ') || str_starts_with($clean, 'آ')) {
            $sub = mb_substr($clean, 1);
            $variants[] = 'ا' . $sub;
        }

        // 2. معالجة التاء المربوطة والهاء في نهاية الكلمة (ة <-> ه)
        if (str_ends_with($clean, 'ة')) {
            $variants[] = mb_substr($clean, 0, -1) . 'ه';
        } elseif (str_ends_with($clean, 'ه')) {
            $variants[] = mb_substr($clean, 0, -1) . 'ة';
        }

        // 3. معالجة الياء والألف المقصورة في نهاية الكلمة (ي <-> ى)
        if (str_ends_with($clean, 'ي')) {
            $variants[] = mb_substr($clean, 0, -1) . 'ى';
        } elseif (str_ends_with($clean, 'ى')) {
            $variants[] = mb_substr($clean, 0, -1) . 'ي';
        }

        return array_values(array_unique(array_filter($variants)));
    }
}
