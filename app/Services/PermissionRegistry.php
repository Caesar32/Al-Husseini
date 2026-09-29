<?php

namespace App\Services;

class PermissionRegistry
{
    /**
     * Get all permissions categorized into logical operational groups
     *
     * @return array<string, array{
     *     title: string,
     *     icon: string,
     *     badge: string,
     *     permissions: array<string, array{label: string, desc: string, is_sensitive?: bool}>
     * }>
     */
    public static function getGroupedPermissions(): array
    {
        return [
            'pos_sales' => [
                'title' => 'نقاط البيع والمبيعات (POS & Sales)',
                'icon' => 'ri-shopping-cart-2-line',
                'badge' => 'bg-success-subtle text-success',
                'permissions' => [
                    'pos.access' => [
                        'label' => 'دخول شاشة الكاشير (POS)',
                        'desc' => 'السماح بفتح شاشة البيع المباشر ونقاط البيع',
                    ],
                    'invoices.view' => [
                        'label' => 'استعراض فواتير المبيعات',
                        'desc' => 'عرض سجل الفواتير الصادرة وتفاصيل البيع',
                    ],
                    'invoices.create' => [
                        'label' => 'إصدار فواتير بيع',
                        'desc' => 'حفظ واعتماد فواتير بيع جديدة في النظام',
                    ],
                    'invoices.print' => [
                        'label' => 'طباعة الإيصالات والفواتير',
                        'desc' => 'طباعة بون الكاشير وشهادات المبيعات',
                    ],
                    'invoices.discount' => [
                        'label' => 'منح الخصومات وتعديل الأسعار',
                        'desc' => 'تطبيق خصم نقدي أو تخفيض سعر بيع الصنف دون كود المشرف',
                        'is_sensitive' => true,
                    ],
                    'invoices.cancel' => [
                        'label' => 'إلغاء ومرتجع الفواتير',
                        'desc' => 'إجراء المرتجعات المالية وإلغاء فواتير المبيعات',
                        'is_sensitive' => true,
                    ],
                ],
            ],

            'customers_credit' => [
                'title' => 'العملاء والمديونيات (Customers & Credit)',
                'icon' => 'ri-user-shared-line',
                'badge' => 'bg-warning-subtle text-warning',
                'permissions' => [
                    'customers.view' => [
                        'label' => 'عرض بيانات العملاء',
                        'desc' => 'الاطلاع على قائمة العملاء وسياراتهم',
                    ],
                    'customers.create' => [
                        'label' => 'إضافة عميل جديد',
                        'desc' => 'تسجيل عميل جديد وبيانات مركبته',
                    ],
                    'customers.edit' => [
                        'label' => 'تعديل بيانات العملاء',
                        'desc' => 'تحديث أرقام الهواتف وبيانات المركبات',
                    ],
                    'customers.delete' => [
                        'label' => 'حذف العملاء',
                        'desc' => 'حذف سجل العميل نهائياً من النظام',
                        'is_sensitive' => true,
                    ],
                    'credit.view' => [
                        'label' => 'الاطلاع على حسابات الآجل',
                        'desc' => 'متابعة مديونيات العملاء وكشوف الحساب',
                    ],
                    'credit.settle' => [
                        'label' => 'تحصيل وسداد الديون',
                        'desc' => 'استلام دفعات نقدية/بنكية لتسوية رصيد الآجل',
                    ],
                    'credit.adjust_limit' => [
                        'label' => 'تعديل سقف الائتمان',
                        'desc' => 'زيادة أو تقليص الحد الائتماني المسموح به للعميل',
                        'is_sensitive' => true,
                    ],
                ],
            ],

            'inventory_scrap' => [
                'title' => 'المخزون والكهنة (Inventory & Scrap)',
                'icon' => 'ri-battery-2-charge-line',
                'badge' => 'bg-info-subtle text-info',
                'permissions' => [
                    'products.view' => [
                        'label' => 'عرض المنتجات والمخزون',
                        'desc' => 'الاطلاع على أرصدة البطاريات والمنتجات',
                    ],
                    'products.create' => [
                        'label' => 'إضافة منتجات جديدة',
                        'desc' => 'تعريف أصناف وبطاريات جديدة في الكتالوج',
                    ],
                    'products.edit' => [
                        'label' => 'تعديل بيانات المنتجات',
                        'desc' => 'تحديث أسعار البيع والتكلفة ومستويات إعادة الطلب',
                    ],
                    'products.delete' => [
                        'label' => 'حذف المنتجات',
                        'desc' => 'إلغاء وحذف أصناف من المخزن',
                        'is_sensitive' => true,
                    ],
                    'scrap.view' => [
                        'label' => 'متابعة مخزن الكهنة',
                        'desc' => 'استعراض رصيد بطاريات الخردة والرصاص',
                    ],
                    'scrap.transfer' => [
                        'label' => 'بيع ونقل الكهنة للمصاهر',
                        'desc' => 'إخراج وتصفية دفعات الكهنة إلى مصانع التدوير',
                    ],
                ],
            ],

            'purchases_suppliers' => [
                'title' => 'المشتريات والموردين (Purchases & Suppliers)',
                'icon' => 'ri-truck-line',
                'badge' => 'bg-primary-subtle text-primary',
                'permissions' => [
                    'suppliers.view' => [
                        'label' => 'عرض دليل الموردين',
                        'desc' => 'الاطلاع على بيانات الشركات والموردين وكشوف الحساب',
                    ],
                    'suppliers.create' => [
                        'label' => 'إضافة مورد جديد',
                        'desc' => 'تسجيل شركات بطاريات وموردين جدد',
                    ],
                    'suppliers.edit' => [
                        'label' => 'تعديل بيانات الموردين',
                        'desc' => 'تحديث أرقام الاتصال وشروط التعامل',
                    ],
                    'suppliers.delete' => [
                        'label' => 'حذف الموردين',
                        'desc' => 'حذف ملف مورد من النظام',
                        'is_sensitive' => true,
                    ],
                    'purchases.view' => [
                        'label' => 'استعراض فواتير المشتريات',
                        'desc' => 'الاطلاع على الشحنات الواردة وتكلفة الشراء',
                    ],
                    'purchases.create' => [
                        'label' => 'تسجيل فواتير شراء وتوريد',
                        'desc' => 'إدخال شحنات وبطاريات واردة إلى المخازن',
                    ],
                    'purchases.settle_payment' => [
                        'label' => 'صرف دفعات للموردين',
                        'desc' => 'تسجيل مدفوعات نقدية وبنكية للموردين',
                        'is_sensitive' => true,
                    ],
                ],
            ],

            'warranties' => [
                'title' => 'الضمان وخدمات ما بعد البيع (Warranties)',
                'icon' => 'ri-shield-check-line',
                'badge' => 'bg-secondary-subtle text-secondary',
                'permissions' => [
                    'warranties.view' => [
                        'label' => 'الاستعلام عن الضمانات',
                        'desc' => 'فحص سيريال البطارية والتحقق من سريان الضمان',
                    ],
                    'warranties.claim' => [
                        'label' => 'تسجيل طلب استبدال/ضمان',
                        'desc' => 'فتح مطالبة صيانة وبطارية تالفة تحت الفحص',
                    ],
                    'warranties.approve_replace' => [
                        'label' => 'اعتماد الاستبدال والتسوية مع المورد',
                        'desc' => 'الموافقة على صرف بطارية بديلة ومطالبة الشركة المصنعة',
                        'is_sensitive' => true,
                    ],
                ],
            ],

            'hr_workforce' => [
                'title' => 'الموارد البشرية والرواتب (HR & Workforce)',
                'icon' => 'ri-user-star-line',
                'badge' => 'bg-danger-subtle text-danger',
                'permissions' => [
                    'employees.view' => [
                        'label' => 'عرض سجل الموظفين',
                        'desc' => 'الاطلاع على بيانات الفنيين والموظفين والرواتب',
                    ],
                    'employees.create' => [
                        'label' => 'إضافة وتعيين موظفين',
                        'desc' => 'تسجيل موظف جديد وبنيته المالية',
                    ],
                    'employees.edit' => [
                        'label' => 'تعديل بيانات الموظفين',
                        'desc' => 'تحديث الرواتب والوظائف ومواعيد الشيفتات',
                    ],
                    'employees.delete' => [
                        'label' => 'حذف الموظفين',
                        'desc' => 'أرشفة وحذف ملف موظف من النظام',
                        'is_sensitive' => true,
                    ],
                    'attendance.view' => [
                        'label' => 'متابعة الحضور والانصراف',
                        'desc' => 'استعراض سجلات البصمة والتأخيرات اليومية',
                    ],
                    'attendance.manual_punch' => [
                        'label' => 'تسجيل حضور وانصراف يدوي',
                        'desc' => 'تسجيل بصمة يدوية أو تسجيل غياب لموظف',
                    ],
                    'deductions.manage' => [
                        'label' => 'إدارة الجزاءات والخصومات',
                        'desc' => 'تطبيق وتعديل الجزاءات على الموظفين',
                    ],
                    'leaves.manage' => [
                        'label' => 'إدارة الإجازات',
                        'desc' => 'الموافقة أو رفض طلبات الإجازات',
                    ],
                    'payroll.generate' => [
                        'label' => 'استخراج وحساب مسير الرواتب',
                        'desc' => 'توليد مسير الرواتب الشهري وحساب العمولات',
                    ],
                    'payroll.approve' => [
                        'label' => 'اعتماد مسير الرواتب',
                        'desc' => 'الموافقة النهائية على مبالغ الرواتب المستحقة',
                        'is_sensitive' => true,
                    ],
                    'payroll.disburse' => [
                        'label' => 'صرف وتحويل الرواتب',
                        'desc' => 'إثبات تحويل وصرف الرواتب للموظفين',
                        'is_sensitive' => true,
                    ],
                ],
            ],

            'reports' => [
                'title' => 'التقارير والإحصائيات (Reports & Analytics)',
                'icon' => 'ri-file-chart-line',
                'badge' => 'bg-dark-subtle text-dark',
                'permissions' => [
                    'reports.sales' => [
                        'label' => 'تقارير المبيعات والأصناف الأكثر طلباً',
                        'desc' => 'متابعة حركة المبيعات اليومية والشهرية',
                    ],
                    'reports.financial' => [
                        'label' => 'التقارير المالية والأرباح',
                        'desc' => 'الاطلاع على هوامش الربح وصافي أرباح المنشأة',
                        'is_sensitive' => true,
                    ],
                    'reports.hr' => [
                        'label' => 'تقارير الموارد البشرية والغياب',
                        'desc' => 'معدلات الالتزام والغياب وتكلفة الأجور',
                    ],
                ],
            ],

            'system_admin' => [
                'title' => 'الإدارة العامة والمنشأة (System Administration)',
                'icon' => 'ri-settings-4-line',
                'badge' => 'bg-info-subtle text-info',
                'permissions' => [
                    'dashboard.view' => [
                        'label' => 'عرض لوحة المؤشرات الرئيسية (Dashboard)',
                        'desc' => 'الاطلاع على الـ KPIs وإحصائيات العمل العامة',
                    ],
                    'notifications.view' => [
                        'label' => 'استعراض الإشعارات والتنبيهات',
                        'desc' => 'متابعة تنبيهات انخفاض المخزون والطلبات',
                    ],
                    'roles.manage' => [
                        'label' => 'إدارة الأدوار وتحديد الصلاحيات',
                        'desc' => 'إنشاء وتعديل الأدوار والصلاحيات الممنوحة للمستخدمين',
                        'is_sensitive' => true,
                    ],
                    'users.manage' => [
                        'label' => 'إدارة حسابات المستخدمين',
                        'desc' => 'إنشاء مستخدمين وتعيين أدوارهم وتفعيل/تعطيل الحسابات',
                        'is_sensitive' => true,
                    ],
                    'settings.manage' => [
                        'label' => 'إدارة إعدادات المنشأة والنظام',
                        'desc' => 'تعديل بيانات الشركة والضرائب وسياسات الكهنة',
                        'is_sensitive' => true,
                    ],
                    'branches.view' => [
                        'label' => 'استعراض الفروع',
                        'desc' => 'الاطلاع على فروع المؤسسة',
                    ],
                    'branches.create' => [
                        'label' => 'إضافة فروع جديدة',
                        'desc' => 'فتح فرع جديد في النظام',
                    ],
                    'branches.edit' => [
                        'label' => 'تعديل الفروع',
                        'desc' => 'تعديل عناوين وأرقام الفروع',
                    ],
                    'branches.delete' => [
                        'label' => 'حذف الفروع',
                        'desc' => 'إلغاء فرع من النظام',
                        'is_sensitive' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * Map system role slugs to friendly Arabic labels and badges
     */
    public static function getRoleMetadata(string $roleName): array
    {
        return match ($roleName) {
            'super-admin' => [
                'label' => 'مشرف عام النظام',
                'badge' => 'bg-danger-subtle text-danger',
                'icon' => 'ri-shield-star-line',
                'description' => 'صلاحيات مطلقة غير مقيدة على كافة أقسام وإعدادات النظام',
            ],
            'branch-manager' => [
                'label' => 'مدير الفرع',
                'badge' => 'bg-primary-subtle text-primary',
                'icon' => 'ri-user-settings-line',
                'description' => 'إدارة عمليات البيع والمخزون وحضور الموظفين والاعتمادات بالفرع',
            ],
            'accountant' => [
                'label' => 'محاسب مالي',
                'badge' => 'bg-success-subtle text-success',
                'icon' => 'ri-money-dollar-circle-line',
                'description' => 'متابعة التحصيلات وفواتير المشتريات ومستحقات الموردين والتقارير',
            ],
            'cashier' => [
                'label' => 'كاشير مبيعات',
                'badge' => 'bg-warning-subtle text-warning',
                'icon' => 'ri-shopping-cart-2-line',
                'description' => 'إصدار الفواتير الفورية، استلام النقدية، وفحص الضمان للعملاء',
            ],
            'workshop-supervisor' => [
                'label' => 'مشرف الورشة والصيانة',
                'badge' => 'bg-info-subtle text-info',
                'icon' => 'ri-tools-line',
                'description' => 'فحص البطاريات، مطالبات الضمان، متابعة مخزن الكهنة وحضور الفنيين',
            ],
            default => [
                'label' => $roleName,
                'badge' => 'bg-secondary-subtle text-secondary',
                'icon' => 'ri-user-follow-line',
                'description' => 'دور مخصص بالنظام',
            ],
        };
    }
}
