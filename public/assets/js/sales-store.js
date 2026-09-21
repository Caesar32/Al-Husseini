/**
 * Al-Husseini Car Battery Center - Sales, POS, Customers & Credit ("الآجل") Store (v2)
 * مركز الحسيني لبطاريات وزيوت وصيانة السيارات
 * مخصص لنظام المبيعات، الفواتير، دليل العملاء، الزيوت، الشحوم، وخدمات الصيانة
 */

(function () {
    'use strict';

    const STORAGE_KEYS = {
        PRODUCTS: 'alhusseini_battery_products_v2',
        CUSTOMERS: 'alhusseini_battery_customers_v2',
        INVOICES: 'alhusseini_battery_invoices_v2',
        CREDIT_PAYMENTS: 'alhusseini_battery_credit_payments_v2',
        INITIALIZED: 'alhusseini_battery_sales_init_v2'
    };

    // Realistic Inventory: Batteries, Oils, Greases, and Workshop Repair Services
    const SEED_PRODUCTS = [
        // ==========================================
        // 1. بطاريات السيارات (Batteries)
        // ==========================================
        {
            id: 'PROD-101',
            barcode: '6221001010018',
            brand: 'كلورايد (Chloride)',
            name: 'بطارية كلورايد جولد Chloride Gold 70A',
            amp: '70 أمبير',
            type: 'جافة - كالسيوم',
            priceNew: 3850,
            priceWithOld: 3300,
            scrapValue: 550,
            warrantyMonths: 18,
            stock: 24,
            category: 'بطاريات',
            unit: 'بطارية',
            status: 'in_stock'
        },
        {
            id: 'PROD-102',
            barcode: '6221001010025',
            brand: 'فارتا (Varta)',
            name: 'بطارية فارتا بلو ديناميك Varta Blue Dynamic 60A',
            amp: '60 أمبير',
            type: 'جافة ألماني',
            priceNew: 4100,
            priceWithOld: 3600,
            scrapValue: 500,
            warrantyMonths: 24,
            stock: 18,
            category: 'بطاريات',
            unit: 'بطارية',
            status: 'in_stock'
        },
        {
            id: 'PROD-103',
            barcode: '6221001010032',
            brand: 'فارتا (Varta)',
            name: 'بطارية فارتا سيلفر EFB Varta Start-Stop 70A',
            amp: '70 أمبير',
            type: 'EFB للسيارات الذكية Start-Stop',
            priceNew: 5400,
            priceWithOld: 4850,
            scrapValue: 550,
            warrantyMonths: 24,
            stock: 12,
            category: 'بطاريات',
            unit: 'بطارية',
            status: 'in_stock'
        },
        {
            id: 'PROD-104',
            barcode: '6221001010049',
            brand: 'إيه سي ديلكو (ACDelco)',
            name: 'بطارية إيه سي ديلكو ACDelco Maintenance Free 55A',
            amp: '55 أمبير',
            type: 'جافة بدون صيانة',
            priceNew: 3400,
            priceWithOld: 2950,
            scrapValue: 450,
            warrantyMonths: 12,
            stock: 30,
            category: 'بطاريات',
            unit: 'بطارية',
            status: 'in_stock'
        },
        {
            id: 'PROD-105',
            barcode: '6221001010056',
            brand: 'النسر (Al-Nasr)',
            name: 'بطارية النسر كالسيو مصر Al-Nasr Calcium 70A',
            amp: '70 أمبير',
            type: 'جافة كلسيوم محلي',
            priceNew: 3200,
            priceWithOld: 2650,
            scrapValue: 550,
            warrantyMonths: 12,
            stock: 28,
            category: 'بطاريات',
            unit: 'بطارية',
            status: 'in_stock'
        },
        {
            id: 'PROD-106',
            barcode: '6221001010063',
            brand: 'هانكوك (Hankook)',
            name: 'بطارية هانكوك كوريا Hankook SMF 80A',
            amp: '80 أمبير',
            type: 'جافة كوري مختومة',
            priceNew: 4600,
            priceWithOld: 3950,
            scrapValue: 650,
            warrantyMonths: 18,
            stock: 15,
            category: 'بطاريات',
            unit: 'بطارية',
            status: 'in_stock'
        },
        {
            id: 'PROD-107',
            barcode: '6221001010070',
            brand: 'كلورايد (Chloride)',
            name: 'بطارية كلورايد نقل ثقيل وتريلات Chloride Heavy 150A',
            amp: '150 أمبير',
            type: 'سائلة شحن ثقيل (مياه نار)',
            priceNew: 8200,
            priceWithOld: 6900,
            scrapValue: 1300,
            warrantyMonths: 12,
            stock: 8,
            category: 'بطاريات',
            unit: 'بطارية',
            status: 'in_stock'
        },

        // ==========================================
        // 2. زيوت المحركات والفتيس (Oils & Lubricants)
        // ==========================================
        {
            id: 'PROD-201',
            barcode: '6222002010017',
            brand: 'موبيل (Mobil)',
            name: 'زيت موبيل 1 تخليقي بالكامل Mobil 1 FS 5W-40 (جالون 4 لتر)',
            amp: 'تخليقي 10,000 كم',
            type: 'لزوجة 5W-40 موتور',
            priceNew: 1850,
            priceWithOld: 1850,
            scrapValue: 0,
            warrantyMonths: 0,
            stock: 45,
            category: 'زيوت',
            unit: 'جالون 4L',
            status: 'in_stock'
        },
        {
            id: 'PROD-202',
            barcode: '6222002010024',
            brand: 'شل (Shell)',
            name: 'زيت شل هيلكس الترا Shell Helix Ultra 5W-30 (جالون 4 لتر)',
            amp: 'تخليقي 10,000 كم',
            type: 'لزوجة 5W-30 غاز وبنزين',
            priceNew: 1920,
            priceWithOld: 1920,
            scrapValue: 0,
            warrantyMonths: 0,
            stock: 35,
            category: 'زيوت',
            unit: 'جالون 4L',
            status: 'in_stock'
        },
        {
            id: 'PROD-203',
            barcode: '6222002010031',
            brand: 'كاسترول (Castrol)',
            name: 'زيت كاسترول ماجناتيك Castrol Magnatec 10W-40 (جالون 4 لتر)',
            amp: 'نصف تخليقي 7,000 كم',
            type: 'لزوجة 10W-40 ذكي',
            priceNew: 1450,
            priceWithOld: 1450,
            scrapValue: 0,
            warrantyMonths: 0,
            stock: 40,
            category: 'زيوت',
            unit: 'جالون 4L',
            status: 'in_stock'
        },
        {
            id: 'PROD-204',
            barcode: '6222002010048',
            brand: 'موبيل (Mobil)',
            name: 'زيت موبيل سوبر Mobil Super XHP 20W-50 (جالون 4 لتر)',
            amp: 'معدني 5,000 كم',
            type: 'لزوجة 20W-50 شاق',
            priceNew: 890,
            priceWithOld: 890,
            scrapValue: 0,
            warrantyMonths: 0,
            stock: 50,
            category: 'زيوت',
            unit: 'جالون 4L',
            status: 'in_stock'
        },
        {
            id: 'PROD-205',
            barcode: '6222002010055',
            brand: 'موبيل (Mobil)',
            name: 'زيت فتيس أوتوماتيك ودريكسيون باور Mobil ATF 320 (1 لتر)',
            amp: 'زيت نقل حركة',
            type: 'ATF Dexron III',
            priceNew: 340,
            priceWithOld: 340,
            scrapValue: 0,
            warrantyMonths: 0,
            stock: 60,
            category: 'زيوت',
            unit: 'عبوة 1L',
            status: 'in_stock'
        },
        {
            id: 'PROD-206',
            barcode: '6222002010062',
            brand: 'بوش (Bosch)',
            name: 'فلتر زيت محرك أصلي بوش لمختلف السيارات الياباني والكوري',
            amp: 'فلتر زيت صلب',
            type: 'مانع شوائب ورواسب',
            priceNew: 160,
            priceWithOld: 160,
            scrapValue: 0,
            warrantyMonths: 0,
            stock: 80,
            category: 'زيوت',
            unit: 'قطعة',
            status: 'in_stock'
        },

        // ==========================================
        // 3. الشحوم وسوائل الصيانة (Greases & Fluids)
        // ==========================================
        {
            id: 'PROD-301',
            barcode: '6223003010016',
            brand: 'موبيل (Mobil)',
            name: 'مياه تبريد ردياتير موبيل كولانت بلس حمراء Mobil Coolant (4 لتر)',
            amp: 'مركزة 33%',
            type: 'مياه ردياتير أصلية',
            priceNew: 380,
            priceWithOld: 380,
            scrapValue: 0,
            warrantyMonths: 0,
            stock: 30,
            category: 'شحوم وسوائل',
            unit: 'جركن 4L',
            status: 'in_stock'
        },
        {
            id: 'PROD-302',
            barcode: '6223003010023',
            brand: 'كاسترول (Castrol)',
            name: 'سائل فرامل باكم كاسترول أصلي Castrol Brake Fluid DOT 4 (500 مل)',
            amp: 'باكم DOT 4',
            type: 'مقاوم للغليان والتآكل',
            priceNew: 145,
            priceWithOld: 145,
            scrapValue: 0,
            warrantyMonths: 0,
            stock: 45,
            category: 'شحوم وسوائل',
            unit: 'عبوة 500ml',
            status: 'in_stock'
        },
        {
            id: 'PROD-303',
            barcode: '6223003010030',
            brand: 'إيبرو (Abro)',
            name: 'شحم إيبرو أمريكي رصاص وعفشة ورولمان بلي عالي الحرارة (علبة 450 جم)',
            amp: 'شحم حراري أزرق',
            type: 'ليثيوم عالي الضغط',
            priceNew: 190,
            priceWithOld: 190,
            scrapValue: 0,
            warrantyMonths: 0,
            stock: 55,
            category: 'شحوم وسوائل',
            unit: 'علبة',
            status: 'in_stock'
        },
        {
            id: 'PROD-304',
            barcode: '6223003010047',
            brand: 'الحسيني كيميكالز',
            name: 'مياه نار معالجة مخففة لملء وتجديد البطاريات السائلة (جركن 5 لتر)',
            amp: 'حامض كبريتيك 1.28',
            type: 'محلول شحن بطاريات',
            priceNew: 120,
            priceWithOld: 120,
            scrapValue: 0,
            warrantyMonths: 0,
            stock: 40,
            category: 'شحوم وسوائل',
            unit: 'جركن 5L',
            status: 'in_stock'
        },
        {
            id: 'PROD-305',
            barcode: '6223003010054',
            brand: 'دابليو دي (WD-40)',
            name: 'بخاخ مزيل صدأ وشحم عازل رطوبة لأقطاب البطارية WD-40 (400 مل)',
            amp: 'عازل ومنظف كابلات',
            type: 'متعدد الاستخدامات',
            priceNew: 220,
            priceWithOld: 220,
            scrapValue: 0,
            warrantyMonths: 0,
            stock: 35,
            category: 'شحوم وسوائل',
            unit: 'عبوة',
            status: 'in_stock'
        },

        // ==========================================
        // 4. خدمات التصليح والصيانة بالورشة (Services)
        // ==========================================
        {
            id: 'PROD-401',
            barcode: '6224004010015',
            brand: 'ورشة الكهرباء',
            name: 'خدمة فحص وإصلاح شحن الدينامو وتغيير فحمات وكتاوت',
            amp: 'مصنعية ورشة',
            type: 'كهرباء ودينامو سيارات',
            priceNew: 250,
            priceWithOld: 250,
            scrapValue: 0,
            warrantyMonths: 3,
            stock: 999,
            category: 'خدمات وصيانة',
            unit: 'خدمة',
            status: 'in_stock'
        },
        {
            id: 'PROD-402',
            barcode: '6224004010022',
            brand: 'ورشة الصيانة',
            name: 'خدمة تغيير زيت المحرك وفلتر الزيت وفحص دورة التبريد وضغط الإطارات',
            amp: 'خدمة سريعة',
            type: 'تغيير زيوت وفلاتر',
            priceNew: 100,
            priceWithOld: 100,
            scrapValue: 0,
            warrantyMonths: 0,
            stock: 999,
            category: 'خدمات وصيانة',
            unit: 'خدمة',
            status: 'in_stock'
        },
        {
            id: 'PROD-403',
            barcode: '6224004010039',
            brand: 'ورشة الفحص الذكي',
            name: 'كشف أعطال كمبيوتر وبرمجة البطارية بنظام OBD (Start-Stop)',
            amp: 'فحص كمبيوتر',
            type: 'إلغاء أخطاء وبرمجة EFB',
            priceNew: 150,
            priceWithOld: 150,
            scrapValue: 0,
            warrantyMonths: 0,
            stock: 999,
            category: 'خدمات وصيانة',
            unit: 'خدمة',
            status: 'in_stock'
        },
        {
            id: 'PROD-404',
            barcode: '6224004010046',
            brand: 'ورشة شحن البطاريات',
            name: 'صيانة وإصلاح أقطاب بطارية مع إعادة شحن سريع وتغيير محاليل',
            amp: 'إصلاح بطاريات',
            type: 'شحن بطيء 12 ساعة وتجديد رصاص',
            priceNew: 180,
            priceWithOld: 180,
            scrapValue: 0,
            warrantyMonths: 1,
            stock: 999,
            category: 'خدمات وصيانة',
            unit: 'خدمة',
            status: 'in_stock'
        },
        {
            id: 'PROD-405',
            barcode: '6224004010053',
            brand: 'طوارئ الطريق',
            name: 'خدمة إنقاذ وتوصيل وتركيب بطارية متنقل على الطريق (ونش طوارئ)',
            amp: 'إنقاذ سريع',
            type: 'انتقال وتركيب خارج المركز',
            priceNew: 300,
            priceWithOld: 300,
            scrapValue: 0,
            warrantyMonths: 0,
            stock: 999,
            category: 'خدمات وصيانة',
            unit: 'خدمة',
            status: 'in_stock'
        }
    ];

    // Seed Customers
    const SEED_CUSTOMERS = [
        {
            id: 'CUST-101',
            name: 'الحاج فتحي عبد المولى',
            phone: '01012345678',
            carModel: 'تويوتا كورولا 2021',
            carPlate: 'س س ج 1425',
            type: 'ملاكي',
            notes: 'عميل منتظم بمركز المعادي، يفضل كلورايد جولد وزيت موبيل 1',
            creditBalance: 1200,
            totalPurchases: 7450,
            joinDate: '2023-05-10'
        },
        {
            id: 'CUST-102',
            name: 'المهندس أحمد الشريف',
            phone: '01198765432',
            carModel: 'كيا سبورتاج 2023',
            carPlate: 'ق د ب 8963',
            type: 'ملاكي',
            notes: 'سيارة سمارت تحتاج بطارية EFB Start-Stop وزيت شل الترا',
            creditBalance: 0,
            totalPurchases: 5400,
            joinDate: '2024-01-15'
        },
        {
            id: 'CUST-103',
            name: 'الأسطى كرم (ورشة كرم لصيانة السيارات)',
            phone: '01233445566',
            carModel: 'ورشة وميكانيكا (عميل تجاري)',
            carPlate: 'ورشة تجارية',
            type: 'ورش وشركات',
            notes: 'يسحب بطاريات وزيوت وشحوم لحساب زبائن الورشة ويسدد آجل',
            creditBalance: 4500,
            totalPurchases: 22800,
            joinDate: '2022-08-01'
        },
        {
            id: 'CUST-104',
            name: 'كابتن إيهاب النوبي',
            phone: '01066778899',
            carModel: 'هيونداي إلنترا 2019 (أوبر)',
            carPlate: 'أ ب ج 7741',
            type: 'تاكسي وأوبر',
            notes: 'دفع 2,500 ج.م كاش ومتبقي عليه 800 ج.م على الراتب القادم',
            creditBalance: 800,
            totalPurchases: 3300,
            joinDate: '2024-03-20'
        },
        {
            id: 'CUST-105',
            name: 'الحاج سعيد عبد القادر',
            phone: '01122334455',
            carModel: 'شيفروليه جامبو نقل بضائع',
            carPlate: 'د ن ط 5512',
            type: 'نقل وتريلات',
            notes: 'اشترى بطارية 150 أمبير وزيت محرك ديزل 20W-50 وقسط الباقي',
            creditBalance: 3000,
            totalPurchases: 6900,
            joinDate: '2023-11-04'
        },
        {
            id: 'CUST-106',
            name: 'دكتورة سارة محمود عبد الرحمن',
            phone: '01555667788',
            carModel: 'نيسان صني 2022',
            carPlate: 'ي م ك 3321',
            type: 'ملاكي',
            notes: 'دفع كامل المبلغ عبر إنستاباي واستلمت الضمان المعتمد',
            creditBalance: 0,
            totalPurchases: 3600,
            joinDate: '2024-06-12'
        }
    ];

    // Seed Invoices (Rich mix of batteries, oils, and services)
    const SEED_INVOICES = [
        {
            id: 'INV-2026-001',
            invoiceNo: 'BAT-2026/101',
            date: '2026-09-18',
            time: '11:30 ص',
            customerId: 'CUST-101',
            customerName: 'الحاج فتحي عبد المولى',
            customerPhone: '01012345678',
            carModel: 'تويوتا كورولا 2021',
            carPlate: 'س س ج 1425',
            items: [
                {
                    productId: 'PROD-101',
                    brand: 'كلورايد',
                    name: 'بطارية كلورايد جولد 70A',
                    category: 'بطاريات',
                    unitPrice: 3850,
                    qty: 1,
                    hasTradeIn: true,
                    scrapDiscount: 550,
                    finalPrice: 3300
                }
            ],
            subtotal: 3850,
            scrapDiscountTotal: 550,
            extraDiscount: 0,
            totalAmount: 3300,
            paymentMethod: 'credit',
            paidAmount: 2100,
            remainingCredit: 1200,
            creditDueDate: '2026-10-01',
            status: 'partial',
            serialNumber: 'BAT-CHL-99824',
            warrantyExpiry: '2028-03-18',
            sellerName: 'إبراهيم حسن (كبير البائعين)',
            notes: 'تم فحص الدينامو مجاناً (شحن 14.1 فولت ممتاز)'
        },
        {
            id: 'INV-2026-002',
            invoiceNo: 'BAT-2026/102',
            date: '2026-09-19',
            time: '02:15 م',
            customerId: 'CUST-102',
            customerName: 'المهندس أحمد الشريف',
            customerPhone: '01198765432',
            carModel: 'كيا سبورتاج 2023',
            carPlate: 'ق د ب 8963',
            items: [
                {
                    productId: 'PROD-103',
                    brand: 'فارتا',
                    name: 'بطارية فارتا سيلفر EFB 70A',
                    category: 'بطاريات',
                    unitPrice: 5400,
                    qty: 1,
                    hasTradeIn: true,
                    scrapDiscount: 550,
                    finalPrice: 4850
                },
                {
                    productId: 'PROD-403',
                    brand: 'ورشة الفحص',
                    name: 'برمجة كمبيوتر السيارة بنظام OBD',
                    category: 'خدمات وصيانة',
                    unitPrice: 150,
                    qty: 1,
                    hasTradeIn: false,
                    scrapDiscount: 0,
                    finalPrice: 150
                }
            ],
            subtotal: 5550,
            scrapDiscountTotal: 550,
            extraDiscount: 0,
            totalAmount: 5000,
            paymentMethod: 'instapay',
            paidAmount: 5000,
            remainingCredit: 0,
            creditDueDate: null,
            status: 'paid',
            serialNumber: 'BAT-VAR-77142',
            warrantyExpiry: '2028-09-19',
            sellerName: 'مصطفى عادل الشافعي',
            notes: 'تمت برمجة كمبيوتر السيارة لبطارية EFB + تنظيف الأقطاب'
        },
        {
            id: 'INV-2026-003',
            invoiceNo: 'BAT-2026/103',
            date: '2026-09-19',
            time: '04:45 م',
            customerId: 'CUST-103',
            customerName: 'الأسطى كرم (ورشة كرم لصيانة السيارات)',
            customerPhone: '01233445566',
            carModel: 'ورشة وميكانيكا (عميل تجاري)',
            carPlate: 'ورشة تجارية',
            items: [
                {
                    productId: 'PROD-102',
                    brand: 'فارتا',
                    name: 'بطارية فارتا بلو 60A',
                    category: 'بطاريات',
                    unitPrice: 4100,
                    qty: 1,
                    hasTradeIn: false,
                    scrapDiscount: 0,
                    finalPrice: 4100
                },
                {
                    productId: 'PROD-201',
                    brand: 'موبيل',
                    name: 'زيت موبيل 1 تخليقي 5W-40',
                    category: 'زيوت',
                    unitPrice: 1850,
                    qty: 1,
                    hasTradeIn: false,
                    scrapDiscount: 0,
                    finalPrice: 1850
                },
                {
                    productId: 'PROD-303',
                    brand: 'إيبرو',
                    name: 'شحم إيبرو حراري رصاص',
                    category: 'شحوم وسوائل',
                    unitPrice: 190,
                    qty: 2,
                    hasTradeIn: false,
                    scrapDiscount: 0,
                    finalPrice: 380
                }
            ],
            subtotal: 6330,
            scrapDiscountTotal: 0,
            extraDiscount: 330,
            totalAmount: 6000,
            paymentMethod: 'credit',
            paidAmount: 1500,
            remainingCredit: 4500,
            creditDueDate: '2026-09-28',
            status: 'partial',
            serialNumber: 'BAT-VAR-6019',
            warrantyExpiry: '2028-09-19',
            sellerName: 'إبراهيم حسن (كبير البائعين)',
            notes: 'مسحوبات ورشة بطارية وزيوت وشحوم بالآجل'
        },
        {
            id: 'INV-2026-004',
            invoiceNo: 'BAT-2026/104',
            date: '2026-09-20',
            time: '10:10 ص',
            customerId: 'CUST-104',
            customerName: 'كابتن إيهاب النوبي',
            customerPhone: '01066778899',
            carModel: 'هيونداي إلنترا 2019 (أوبر)',
            carPlate: 'أ ب ج 7741',
            items: [
                {
                    productId: 'PROD-101',
                    brand: 'كلورايد',
                    name: 'بطارية كلورايد جولد 70A',
                    category: 'بطاريات',
                    unitPrice: 3850,
                    qty: 1,
                    hasTradeIn: true,
                    scrapDiscount: 550,
                    finalPrice: 3300
                }
            ],
            subtotal: 3850,
            scrapDiscountTotal: 550,
            extraDiscount: 0,
            totalAmount: 3300,
            paymentMethod: 'credit',
            paidAmount: 2500,
            remainingCredit: 800,
            creditDueDate: '2026-10-05',
            status: 'partial',
            serialNumber: 'BAT-CHL-1084',
            warrantyExpiry: '2028-03-20',
            sellerName: 'مصطفى عادل الشافعي',
            notes: 'سداد 2500 ج.م نقداً و800 ج.م آجل'
        },
        {
            id: 'INV-2026-005',
            invoiceNo: 'BAT-2026/105',
            date: '2026-09-20',
            time: '11:05 ص',
            customerId: 'CUST-106',
            customerName: 'دكتورة سارة محمود عبد الرحمن',
            customerPhone: '01555667788',
            carModel: 'نيسان صني 2022',
            carPlate: 'ي م ك 3321',
            items: [
                {
                    productId: 'PROD-201',
                    brand: 'موبيل',
                    name: 'زيت موبيل 1 تخليقي 5W-40 (4L)',
                    category: 'زيوت',
                    unitPrice: 1850,
                    qty: 1,
                    hasTradeIn: false,
                    scrapDiscount: 0,
                    finalPrice: 1850
                },
                {
                    productId: 'PROD-206',
                    brand: 'بوش',
                    name: 'فلتر زيت محرك أصلي بوش',
                    category: 'زيوت',
                    unitPrice: 160,
                    qty: 1,
                    hasTradeIn: false,
                    scrapDiscount: 0,
                    finalPrice: 160
                },
                {
                    productId: 'PROD-402',
                    brand: 'ورشة الصيانة',
                    name: 'خدمة تغيير زيت وفلتر وفحص تبريد',
                    category: 'خدمات وصيانة',
                    unitPrice: 100,
                    qty: 1,
                    hasTradeIn: false,
                    scrapDiscount: 0,
                    finalPrice: 100
                }
            ],
            subtotal: 2110,
            scrapDiscountTotal: 0,
            extraDiscount: 0,
            totalAmount: 2110,
            paymentMethod: 'cash',
            paidAmount: 2110,
            remainingCredit: 0,
            creditDueDate: null,
            status: 'paid',
            serialNumber: 'SERVICE-2026-0920',
            warrantyExpiry: '2027-09-20',
            sellerName: 'إبراهيم حسن (كبير البائعين)',
            notes: 'تغيير زيت موبيل 1 وفلتر بوش أصلي وتصفير عداد الزيت'
        }
    ];

    // Seed Credit Collection Payments
    const SEED_PAYMENTS = [
        {
            id: 'PAY-101',
            receiptNo: 'REC-2026/001',
            invoiceId: 'INV-2026-001',
            customerId: 'CUST-101',
            customerName: 'الحاج فتحي عبد المولى',
            date: '2026-09-19',
            amount: 800,
            method: 'cash',
            receivedBy: 'الحاج محمود الحسيني',
            notes: 'سداد دفعة من حساب الآجل لبطارية الكورولا'
        },
        {
            id: 'PAY-102',
            receiptNo: 'REC-2026/002',
            invoiceId: 'INV-2026-003',
            customerId: 'CUST-103',
            customerName: 'الأسطى كرم (ورشة كرم)',
            date: '2026-09-20',
            amount: 1500,
            method: 'instapay',
            receivedBy: 'الحاج محمود الحسيني',
            notes: 'تحويل إنستاباي دفعة حساب الورشة'
        }
    ];

    function initStore() {
        if (!localStorage.getItem(STORAGE_KEYS.INITIALIZED)) {
            localStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify(SEED_PRODUCTS));
            localStorage.setItem(STORAGE_KEYS.CUSTOMERS, JSON.stringify(SEED_CUSTOMERS));
            localStorage.setItem(STORAGE_KEYS.INVOICES, JSON.stringify(SEED_INVOICES));
            localStorage.setItem(STORAGE_KEYS.CREDIT_PAYMENTS, JSON.stringify(SEED_PAYMENTS));
            localStorage.setItem(STORAGE_KEYS.INITIALIZED, 'true');
        } else {
            // Auto-Migration: Ensure all stored products have barcodes
            try {
                let prods = JSON.parse(localStorage.getItem(STORAGE_KEYS.PRODUCTS)) || [];
                let modified = false;
                prods.forEach((p, idx) => {
                    if (!p.barcode || !p.barcode.trim()) {
                        const seedMatch = SEED_PRODUCTS.find(s => s.id === p.id);
                        p.barcode = seedMatch ? seedMatch.barcode : `622${(idx + 1).toString().padStart(10, '0')}`;
                        modified = true;
                    }
                });
                if (modified) {
                    localStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify(prods));
                }
            } catch (e) {
                console.error('Barcode migration error:', e);
            }
        }
    }

    initStore();

    function notifyChange() {
        window.dispatchEvent(new CustomEvent('alhusseini-sales-updated'));
    }

    window.AlHusseiniSales = {
        // Categories list
        getCategories: function () {
            return ['بطاريات', 'زيوت', 'شحوم وسوائل', 'خدمات وصيانة'];
        },

        // --- PRODUCTS, OILS, FLUIDS & SERVICES ---
        getProducts: function (category = null) {
            try {
                const list = JSON.parse(localStorage.getItem(STORAGE_KEYS.PRODUCTS)) || [];
                if (category && category !== 'all') {
                    return list.filter(p => p.category === category);
                }
                return list;
            } catch (e) {
                return [];
            }
        },

        getProductById: function (id) {
            return this.getProducts().find(p => p.id === id) || null;
        },

        getProductByBarcode: function (barcode) {
            if (!barcode) return null;
            const clean = barcode.toString().trim().toLowerCase();
            return this.getProducts().find(p => p.barcode && p.barcode.toString().trim().toLowerCase() === clean) || null;
        },

        generateBarcode: function (category = 'بطاريات') {
            const catPrefixes = {
                'بطاريات': '6221',
                'زيوت': '6222',
                'شحوم وسوائل': '6223',
                'خدمات وصيانة': '6224'
            };
            const prefix = catPrefixes[category] || '6229';
            const randomDigits = Math.floor(10000000 + Math.random() * 90000000).toString();
            const raw12 = prefix + randomDigits;
            // Calculate EAN-13 check digit
            let sum = 0;
            for (let i = 0; i < 12; i++) {
                sum += parseInt(raw12[i], 10) * (i % 2 === 0 ? 1 : 3);
            }
            const checkDigit = (10 - (sum % 10)) % 10;
            return raw12 + checkDigit;
        },

        saveProduct: function (productData) {
            let list = this.getProducts();
            // Ensure product always has a barcode
            if (!productData.barcode || !productData.barcode.trim()) {
                productData.barcode = this.generateBarcode(productData.category);
            }
            if (productData.id) {
                const idx = list.findIndex(p => p.id === productData.id);
                if (idx !== -1) {
                    list[idx] = { ...list[idx], ...productData };
                }
            } else {
                const newId = `PROD-${Date.now().toString().slice(-4)}`;
                list.unshift({ id: newId, status: 'in_stock', ...productData });
            }
            localStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify(list));
            notifyChange();
            return true;
        },

        deleteProduct: function (id) {
            let list = this.getProducts().filter(p => p.id !== id);
            localStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify(list));
            notifyChange();
            return true;
        },

        // --- CUSTOMERS & VEHICLES ---
        getCustomers: function () {
            try {
                return JSON.parse(localStorage.getItem(STORAGE_KEYS.CUSTOMERS)) || [];
            } catch (e) {
                return [];
            }
        },

        getCustomerById: function (id) {
            return this.getCustomers().find(c => c.id === id) || null;
        },

        saveCustomer: function (custData) {
            let list = this.getCustomers();
            if (custData.id) {
                const idx = list.findIndex(c => c.id === custData.id);
                if (idx !== -1) {
                    list[idx] = { ...list[idx], ...custData };
                }
            } else {
                const newId = `CUST-${100 + list.length + 1}`;
                const newCust = {
                    id: newId,
                    creditBalance: 0,
                    totalPurchases: 0,
                    joinDate: new Date().toISOString().split('T')[0],
                    ...custData
                };
                list.unshift(newCust);
                localStorage.setItem(STORAGE_KEYS.CUSTOMERS, JSON.stringify(list));
                notifyChange();
                return newCust;
            }
            localStorage.setItem(STORAGE_KEYS.CUSTOMERS, JSON.stringify(list));
            notifyChange();
            return custData;
        },

        // --- INVOICES & SALES ---
        getInvoices: function () {
            try {
                return JSON.parse(localStorage.getItem(STORAGE_KEYS.INVOICES)) || [];
            } catch (e) {
                return [];
            }
        },

        getInvoiceById: function (id) {
            return this.getInvoices().find(i => i.id === id || i.invoiceNo === id) || null;
        },

        createInvoice: function (invoiceData) {
            const list = this.getInvoices();
            const invCount = list.length + 1;
            const newId = `INV-${Date.now()}`;
            const newInvoiceNo = `BAT-${new Date().getFullYear()}/${String(invCount).padStart(3, '0')}`;
            const now = new Date();

            const fullInvoice = {
                id: newId,
                invoiceNo: newInvoiceNo,
                date: invoiceData.date || now.toISOString().split('T')[0],
                time: now.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }),
                status: invoiceData.remainingCredit > 0 ? (invoiceData.paidAmount > 0 ? 'partial' : 'unpaid_credit') : 'paid',
                ...invoiceData
            };

            list.unshift(fullInvoice);
            localStorage.setItem(STORAGE_KEYS.INVOICES, JSON.stringify(list));

            // Update Customer total purchases and credit balance
            if (fullInvoice.customerId) {
                let customers = this.getCustomers();
                const cIdx = customers.findIndex(c => c.id === fullInvoice.customerId);
                if (cIdx !== -1) {
                    customers[cIdx].totalPurchases = (Number(customers[cIdx].totalPurchases) || 0) + Number(fullInvoice.totalAmount);
                    if (fullInvoice.remainingCredit > 0) {
                        customers[cIdx].creditBalance = (Number(customers[cIdx].creditBalance) || 0) + Number(fullInvoice.remainingCredit);
                    }
                    localStorage.setItem(STORAGE_KEYS.CUSTOMERS, JSON.stringify(customers));
                }
            }

            // Deduct stock for physical products (not services)
            let products = this.getProducts();
            (fullInvoice.items || []).forEach(item => {
                const pIdx = products.findIndex(p => p.id === item.productId);
                if (pIdx !== -1 && products[pIdx].category !== 'خدمات وصيانة' && products[pIdx].stock > 0) {
                    products[pIdx].stock -= (item.qty || 1);
                }
            });
            localStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify(products));

            notifyChange();
            return fullInvoice;
        },

        // --- CREDIT & RECEIVABLES ("الآجل") ---
        getCreditSummary: function () {
            const customers = this.getCustomers();
            const invoices = this.getInvoices();
            const payments = this.getCreditPayments();

            const creditCustomers = customers.filter(c => (Number(c.creditBalance) || 0) > 0);
            const totalCreditOutstanding = creditCustomers.reduce((sum, c) => sum + Number(c.creditBalance), 0);
            const totalCollectedThisMonth = payments.reduce((sum, p) => sum + Number(p.amount), 0);

            return {
                totalCreditOutstanding: totalCreditOutstanding,
                creditCustomersCount: creditCustomers.length,
                totalCollectedThisMonth: totalCollectedThisMonth,
                totalCustomers: customers.length,
                creditCustomers: creditCustomers
            };
        },

        getCreditPayments: function () {
            try {
                return JSON.parse(localStorage.getItem(STORAGE_KEYS.CREDIT_PAYMENTS)) || [];
            } catch (e) {
                return [];
            }
        },

        recordCreditPayment: function (paymentData) {
            const customer = this.getCustomerById(paymentData.customerId);
            if (!customer) return { success: false, error: 'العميل غير موجود' };

            const amountPaid = Number(paymentData.amount);
            if (amountPaid <= 0) return { success: false, error: 'المبلغ غير صالح' };

            let payments = this.getCreditPayments();
            const receiptNo = `REC-${new Date().getFullYear()}/${String(payments.length + 1).padStart(3, '0')}`;
            const newPayment = {
                id: `PAY-${Date.now()}`,
                receiptNo: receiptNo,
                date: paymentData.date || new Date().toISOString().split('T')[0],
                time: new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }),
                customerId: paymentData.customerId,
                customerName: customer.name,
                invoiceId: paymentData.invoiceId || null,
                amount: amountPaid,
                method: paymentData.method || 'cash',
                receivedBy: paymentData.receivedBy || 'الحاج محمود الحسيني',
                notes: paymentData.notes || 'سداد من حساب الآجل'
            };

            payments.unshift(newPayment);
            localStorage.setItem(STORAGE_KEYS.CREDIT_PAYMENTS, JSON.stringify(payments));

            // Reduce Customer Credit Balance
            let customers = this.getCustomers();
            const cIdx = customers.findIndex(c => c.id === paymentData.customerId);
            if (cIdx !== -1) {
                customers[cIdx].creditBalance = Math.max(0, (Number(customers[cIdx].creditBalance) || 0) - amountPaid);
                localStorage.setItem(STORAGE_KEYS.CUSTOMERS, JSON.stringify(customers));
            }

            // Update matching invoice if invoiceId provided
            if (paymentData.invoiceId) {
                let invoices = this.getInvoices();
                const invIdx = invoices.findIndex(i => i.id === paymentData.invoiceId);
                if (invIdx !== -1) {
                    invoices[invIdx].paidAmount = (Number(invoices[invIdx].paidAmount) || 0) + amountPaid;
                    invoices[invIdx].remainingCredit = Math.max(0, (Number(invoices[invIdx].remainingCredit) || 0) - amountPaid);
                    if (invoices[invIdx].remainingCredit === 0) {
                        invoices[invIdx].status = 'paid';
                    }
                    localStorage.setItem(STORAGE_KEYS.INVOICES, JSON.stringify(invoices));
                }
            }

            notifyChange();
            return { success: true, payment: newPayment };
        },

        // --- EXPORT TO CSV (ARABIC UTF-8 BOM) ---
        exportToCSV: function (filename, headers, rows) {
            let csvContent = '\uFEFF'; // UTF-8 BOM
            csvContent += headers.map(h => `"${h.replace(/"/g, '""')}"`).join(',') + '\r\n';

            rows.forEach(row => {
                csvContent += row.map(cell => {
                    const str = (cell === null || cell === undefined) ? '' : String(cell);
                    return `"${str.replace(/"/g, '""')}"`;
                }).join(',') + '\r\n';
            });

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', `${filename}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },

        formatCurrency: function (amount) {
            return `${Number(amount || 0).toLocaleString('ar-EG')} ج.م`;
        }
    };
})();
