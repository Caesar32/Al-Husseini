# 🛡️ المرحلة 3: طبقة التحقق وقواعد الأمان والمدخلات (Form Requests)
### Phase 03: Form Requests, Validation Rules & Security Invariants

> **الهدف الفني:**
> بناء فئات Form Request صارمة تضمن سلامة البيانات المالية، ومنع تكرار السريالات، والتحقق المسبق من توفر المخزون، والتحقق الصارم من سقف الائتمان وموافقة المدير، وتطابق مجاميع الدفع المجزأ قبل وصول أي طلب لطبقة الخدمات.

---

## 📑 1. فئات طلبات التحقق المستهدفة (Form Requests Specification):

### 1. طلب تسجيل/تعديل مورد (`StoreSupplierRequest` & `UpdateSupplierRequest`):
* **المسار:** `app/Http/Requests/Admin/Suppliers/StoreSupplierRequest.php`
* **القواعد:**
  ```php
  return [
      'name'                => ['required', 'string', 'max:150'],
      'company_name'        => ['required', 'string', 'max:150'],
      'phone'               => ['required', 'string', 'max:30', Rule::unique('suppliers', 'phone')->ignore($this->supplier)],
      'alt_phone'           => ['nullable', 'string', 'max:30'],
      'email'               => ['nullable', 'email', 'max:100'],
      'tax_number'          => ['nullable', 'string', 'max:50'],
      'commercial_register' => ['nullable', 'string', 'max:50'],
      'address'             => ['nullable', 'string', 'max:255'],
      'credit_limit'        => ['required', 'numeric', 'min:0'],
      'is_active'           => ['boolean'],
  ];
  ```

---

### 2. طلب إنشاء فاتورة مشتريات وتوريد (`StorePurchaseInvoiceRequest`):
* **المسار:** `app/Http/Requests/Admin/Purchases/StorePurchaseInvoiceRequest.php`
* **القواعد:**
  ```php
  return [
      'supplier_id'             => ['required', 'exists:suppliers,id'],
      'invoice_number'          => ['required', 'string', 'max:50', 'unique:purchase_invoices,invoice_number'],
      'invoice_date'            => ['required', 'date'],
      'items'                   => ['required', 'array', 'min:1'],
      'items.*.product_id'      => ['required', 'exists:products,id'],
      'items.*.quantity'        => ['required', 'integer', 'min:1'],
      'items.*.unit_cost_price' => ['required', 'numeric', 'min:0'],
      'items.*.batch_number'    => ['nullable', 'string', 'max:50'],
      'items.*.supplier_sku'    => ['nullable', 'string', 'max:100'],
      'tax_amount'              => ['nullable', 'numeric', 'min:0'],
      'discount_amount'         => ['nullable', 'numeric', 'min:0'],
      'paid_amount'             => ['required', 'numeric', 'min:0'],
      'payment_method'          => ['required', 'in:cash,bank_transfer,cheque'],
      'notes'                   => ['nullable', 'string', 'max:1000'],
  ];
  ```

---

### 3. طلب فاتورة نقطة البيع السريعة والكاشير (`StorePosInvoiceRequest`):
* **المسار:** `app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php`
* **المعايير المتقدمة والقواعد الصارمة:**
  ```php
  return [
      'customer_id'             => ['nullable', 'exists:customers,id'],
      'customer_vehicle_id'     => ['nullable', 'exists:customer_vehicles,id'],
      'technician_id'           => ['nullable', 'exists:employees,id'],
      
      // تفاصيل بنود الفاتورة
      'items'                   => ['required', 'array', 'min:1'],
      'items.*.product_id'      => ['required', 'exists:products,id'],
      'items.*.quantity'        => ['required', 'integer', 'min:1'],
      'items.*.battery_serial'  => ['nullable', 'string', 'max:100', 'distinct', 'unique:warranties,battery_serial_number'],
      
      // بيانات الكهنة المستبدلة (إن وجدت)
      'has_scrap'               => ['boolean'],
      'scrap_capacity_ah'       => ['nullable', 'required_if:has_scrap,true', 'integer', 'min:30', 'max:250'],
      'scrap_count'             => ['nullable', 'required_if:has_scrap,true', 'integer', 'min:1'],
      
      // المدفوعات المجزأة والدفع
      'payments'                => ['required', 'array', 'min:1'],
      'payments.*.method'       => ['required', 'in:cash,card,bank_transfer,credit'],
      'payments.*.amount'       => ['required', 'numeric', 'min:0.01'],
      'payments.*.reference'    => ['nullable', 'string', 'max:100'],
      
      // رمز موافقة المدير في حال تجاوز سقف الائتمان
      'manager_override_code'   => ['nullable', 'string'],
      'notes'                   => ['nullable', 'string', 'max:500'],
  ];
  ```

* **التحقق المشروط المتقدم داخل `withValidator()`:**
  - التحقق من تطابق مجموع عناصر `payments.*.amount` مع صافي الفاتورة الإجمالي بعد خصم الكهنة.
  - التحقق من وجود رصيد ائتماني كافٍ للعميل إذا تم اختيار طريقة دفع `credit`، واشتراط مطابقة `manager_override_code` بكلمة مرور المدير إذا تجاوز الرصيد المسموح.
  - التحقق من إدخال `battery_serial` إجبارياً إذا كان الصنف بطارية (`is_battery = true`).

---

### 4. طلب معالجة استبدال الضمان للبطارية المعيبة (`ProcessWarrantyClaimRequest`):
* **المسار:** `app/Http/Requests/Admin/Warranties/ProcessWarrantyClaimRequest.php`
* **القواعد:**
  ```php
  return [
      'defective_serial'           => ['required', 'string', 'exists:warranties,battery_serial_number'],
      'replacement_product_id'     => ['required', 'exists:products,id'],
      'replacement_battery_serial' => ['required', 'string', 'max:100', 'unique:warranties,battery_serial_number'],
      'technician_inspection_notes'=> ['required', 'string', 'min:10', 'max:1000'],
      'settlement_action'          => ['required', 'in:instant_replace,reject'],
      'rejection_reason'           => ['nullable', 'required_if:settlement_action,reject', 'string', 'max:500'],
  ];
  ```

---

## ✅ معايير التحقق والاعتماد (Phase 03 Verification):
- [ ] كتابة اختبارات تحقق لرفض تكرار السريال في الفاتورة أو قاعدة البيانات.
- [ ] كتابة اختبار للتأكد من رفض الفاتورة في حال عدم تطابق مجموع الدفعات المجزأة مع المبلغ الصافي.
- [ ] كتابة اختبار يمنع تجاوز حد الائتمان بدون `manager_override_code` صحيح.
