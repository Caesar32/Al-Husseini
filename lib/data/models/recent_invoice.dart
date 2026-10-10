String _invoiceStatusLabel(String status) {
  const labels = {
    'paid': 'مدفوعة',
    'partially_paid': 'مدفوعة جزئياً',
    'unpaid': 'غير مدفوعة',
    'cancelled': 'ملغاة',
    'refunded': 'مسترجعة',
    'partially_refunded': 'مسترجعة جزئياً',
  };
  return labels[status] ?? status;
}

String _paymentMethodLabel(String method) {
  const labels = {
    'cash': 'نقدي',
    'card': 'بطاقة',
    'bank_transfer': 'تحويل بنكي',
    'credit': 'آجل',
  };
  return labels[method] ?? method;
}

class InvoiceSummary {
  final int id;
  final String invoiceNumber;
  final String createdAt;
  final String timeAgo;
  final double netAmount;
  final String formattedNetAmount;
  final String paymentStatus;
  final String paymentStatusLabel;
  final String paymentMethod;
  final String paymentMethodLabel;
  final String customerName;
  final String customerPhone;
  final int itemsCount;
  final bool hasWarrantyBattery;
  final String cashierName;

  InvoiceSummary({
    required this.id,
    required this.invoiceNumber,
    required this.createdAt,
    required this.timeAgo,
    required this.netAmount,
    required this.formattedNetAmount,
    required this.paymentStatus,
    required this.paymentStatusLabel,
    required this.paymentMethod,
    required this.paymentMethodLabel,
    required this.customerName,
    required this.customerPhone,
    required this.itemsCount,
    required this.hasWarrantyBattery,
    required this.cashierName,
  });

  factory InvoiceSummary.fromJson(Map<String, dynamic> json) {
    final finalAmount = json['final_amount'] as Map<String, dynamic>? ?? {};
    final status = json['status'] ?? 'paid';
    final paymentMethod = json['payment_method'] ?? 'cash';

    return InvoiceSummary(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      invoiceNumber: json['invoice_number'] ?? '#--',
      createdAt: json['created_at'] ?? '',
      timeAgo: json['time'] ?? '',
      netAmount: (finalAmount['raw'] ?? 0.0).toDouble(),
      formattedNetAmount: finalAmount['formatted'] ?? '${finalAmount['raw'] ?? 0} ج.م',
      paymentStatus: status,
      paymentStatusLabel: _invoiceStatusLabel(status),
      paymentMethod: paymentMethod,
      paymentMethodLabel: _paymentMethodLabel(paymentMethod),
      customerName: json['customer_name'] ?? 'عميل نقدي',
      customerPhone: json['customer_phone'] ?? '',
      itemsCount: json['items_count'] ?? 0,
      hasWarrantyBattery: json['has_warranty'] ?? false,
      cashierName: json['cashier_name'] ?? '',
    );
  }
}

class InvoiceDetail {
  final int id;
  final String invoiceNumber;
  final String createdAt;
  final String status;
  final String statusLabel;
  final double subtotal;
  final double taxAmount;
  final double discountAmount;
  final double netAmount;
  final double paidAmount;
  final double remainingAmount;
  final String paymentMethod;
  final String paymentMethodLabel;
  final String? notes;
  final InvoiceCustomerInfo? customer;
  final String? cashierName;
  final String? technicianName;
  final InvoiceVehicleInfo? vehicle;
  final List<InvoiceItemDetail> items;
  final ScrapBatteryDetail? scrapBattery;
  final List<InvoicePaymentDetail> payments;

  InvoiceDetail({
    required this.id,
    required this.invoiceNumber,
    required this.createdAt,
    required this.status,
    required this.statusLabel,
    required this.subtotal,
    required this.taxAmount,
    required this.discountAmount,
    required this.netAmount,
    required this.paidAmount,
    required this.remainingAmount,
    required this.paymentMethod,
    required this.paymentMethodLabel,
    this.notes,
    this.customer,
    this.cashierName,
    this.technicianName,
    this.vehicle,
    required this.items,
    this.scrapBattery,
    required this.payments,
  });

  factory InvoiceDetail.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'] as List? ?? [];
    final rawPayments = json['payments'] as List? ?? [];
    final financials = json['financials'] as Map<String, dynamic>? ?? {};
    final status = json['status'] ?? 'paid';
    final paymentMethod = json['payment_method'] ?? 'cash';

    double financial(String key) =>
        ((financials[key] as Map<String, dynamic>?)?['raw'] as num? ?? 0.0).toDouble();

    return InvoiceDetail(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      invoiceNumber: json['invoice_number'] ?? '#--',
      createdAt: json['created_at'] ?? '',
      status: status,
      statusLabel: _invoiceStatusLabel(status),
      subtotal: financial('subtotal'),
      taxAmount: financial('tax_amount'),
      discountAmount: financial('discount_amount'),
      netAmount: financial('final_amount'),
      paidAmount: financial('paid_amount'),
      remainingAmount: financial('remaining_amount'),
      paymentMethod: paymentMethod,
      paymentMethodLabel: _paymentMethodLabel(paymentMethod),
      notes: json['notes'],
      customer: json['customer'] != null ? InvoiceCustomerInfo.fromJson(json['customer']) : null,
      cashierName: json['cashier_name'] ?? json['cashier']?['name'],
      technicianName: json['technician_name'] ?? json['technician']?['name'],
      vehicle: json['vehicle'] != null ? InvoiceVehicleInfo.fromJson(json['vehicle']) : null,
      items: rawItems.map((e) => InvoiceItemDetail.fromJson(e)).toList(),
      scrapBattery: json['scrap_battery'] != null ? ScrapBatteryDetail.fromJson(json['scrap_battery']) : null,
      payments: rawPayments.map((e) => InvoicePaymentDetail.fromJson(e)).toList(),
    );
  }
}

class InvoiceCustomerInfo {
  final int id;
  final String name;
  final String phone;
  final double currentCreditBalance;

  InvoiceCustomerInfo({
    required this.id,
    required this.name,
    required this.phone,
    required this.currentCreditBalance,
  });

  factory InvoiceCustomerInfo.fromJson(Map<String, dynamic> json) {
    return InvoiceCustomerInfo(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      name: json['name'] ?? '',
      phone: json['phone'] ?? '',
      currentCreditBalance: (json['current_credit_balance'] ?? 0.0).toDouble(),
    );
  }
}

class InvoiceVehicleInfo {
  final String plateNumber;
  final String make;
  final String model;

  InvoiceVehicleInfo({
    required this.plateNumber,
    required this.make,
    required this.model,
  });

  factory InvoiceVehicleInfo.fromJson(Map<String, dynamic> json) {
    return InvoiceVehicleInfo(
      plateNumber: json['plate_number'] ?? '',
      make: json['make'] ?? '',
      model: json['model'] ?? '',
    );
  }
}

class InvoiceItemDetail {
  final int id;
  final String productName;
  final String productSku;
  final int quantity;
  final double unitPrice;
  final double totalPrice;
  final bool isBattery;
  final String? batterySerialNumber;
  final int? warrantyDurationMonths;

  InvoiceItemDetail({
    required this.id,
    required this.productName,
    required this.productSku,
    required this.quantity,
    required this.unitPrice,
    required this.totalPrice,
    required this.isBattery,
    this.batterySerialNumber,
    this.warrantyDurationMonths,
  });

  factory InvoiceItemDetail.fromJson(Map<String, dynamic> json) {
    final unitPrice = json['unit_price'] as Map<String, dynamic>? ?? {};
    final totalPrice = json['total_price'] as Map<String, dynamic>? ?? {};

    return InvoiceItemDetail(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      productName: json['product_name'] ?? 'منتج',
      productSku: json['sku'] ?? '',
      quantity: json['quantity'] ?? 1,
      unitPrice: (unitPrice['raw'] as num? ?? 0.0).toDouble(),
      totalPrice: (totalPrice['raw'] as num? ?? 0.0).toDouble(),
      isBattery: json['is_battery'] ?? false,
      batterySerialNumber: json['battery_serial_number'],
      warrantyDurationMonths: json['warranty_duration_months'],
    );
  }
}

class ScrapBatteryDetail {
  final double capacityAh;
  final double scrapValue;

  ScrapBatteryDetail({
    required this.capacityAh,
    required this.scrapValue,
  });

  factory ScrapBatteryDetail.fromJson(Map<String, dynamic> json) {
    return ScrapBatteryDetail(
      capacityAh: (json['capacity_ah'] as num? ?? 0.0).toDouble(),
      scrapValue: (json['scrap_value'] as num? ?? 0.0).toDouble(),
    );
  }
}

class InvoicePaymentDetail {
  final String method;
  final String methodLabel;
  final double amount;
  final String date;

  InvoicePaymentDetail({
    required this.method,
    required this.methodLabel,
    required this.amount,
    required this.date,
  });

  factory InvoicePaymentDetail.fromJson(Map<String, dynamic> json) {
    final method = json['method'] ?? json['payment_method'] ?? 'cash';

    return InvoicePaymentDetail(
      method: method,
      methodLabel: _paymentMethodLabel(method),
      amount: (json['amount'] as num? ?? 0.0).toDouble(),
      date: json['date'] ?? json['created_at'] ?? '',
    );
  }
}
