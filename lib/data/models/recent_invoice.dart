import 'json_read.dart';

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
  final Money netAmount;
  final String paymentStatus;
  final String paymentStatusLabel;
  final String paymentMethod;
  final String paymentMethodLabel;
  final String customerName;
  final String customerPhone;
  final int itemsCount;
  final bool hasWarrantyBattery;
  final String cashierName;

  const InvoiceSummary({
    required this.id,
    required this.invoiceNumber,
    required this.createdAt,
    required this.timeAgo,
    required this.netAmount,
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
    final status = readString(json['status'], fallback: 'paid');
    final paymentMethod = readString(json['payment_method'], fallback: 'cash');

    return InvoiceSummary(
      id: readInt(json['id']),
      invoiceNumber: readString(json['invoice_number'], fallback: '#--'),
      createdAt: readString(json['created_at']),
      timeAgo: readString(json['time']),
      netAmount: Money.fromJson(json['final_amount']),
      paymentStatus: status,
      paymentStatusLabel: _invoiceStatusLabel(status),
      paymentMethod: paymentMethod,
      paymentMethodLabel: _paymentMethodLabel(paymentMethod),
      customerName: readString(json['customer_name'], fallback: 'عميل نقدي'),
      customerPhone: readString(json['customer_phone']),
      itemsCount: readInt(json['items_count']),
      hasWarrantyBattery: readBool(json['has_warranty']),
      cashierName: readString(json['cashier_name']),
    );
  }
}

class InvoiceDetail {
  final int id;
  final String invoiceNumber;
  final String createdAt;
  final String status;
  final String statusLabel;
  final Money subtotal;
  final Money taxAmount;
  final Money discountAmount;
  final Money scrapDeductionAmount;
  final Money netAmount;
  final Money paidAmount;
  final Money remainingAmount;
  final Money refundedAmount;
  final String paymentMethod;
  final String paymentMethodLabel;
  final String? notes;
  final InvoiceCustomerInfo? customer;
  final String? cashierName;
  final String? technicianName;
  final InvoiceVehicleInfo? vehicle;
  final List<InvoiceItemDetail> items;
  final List<ScrapBatteryDetail> scrapBatteries;
  final List<InvoicePaymentDetail> payments;

  const InvoiceDetail({
    required this.id,
    required this.invoiceNumber,
    required this.createdAt,
    required this.status,
    required this.statusLabel,
    required this.subtotal,
    required this.taxAmount,
    required this.discountAmount,
    required this.scrapDeductionAmount,
    required this.netAmount,
    required this.paidAmount,
    required this.remainingAmount,
    required this.refundedAmount,
    required this.paymentMethod,
    required this.paymentMethodLabel,
    this.notes,
    this.customer,
    this.cashierName,
    this.technicianName,
    this.vehicle,
    required this.items,
    required this.scrapBatteries,
    required this.payments,
  });

  factory InvoiceDetail.fromJson(Map<String, dynamic> json) {
    final financials = readMap(json['financials']);
    final scrapBatteries = readList(json['scrap_batteries']);
    final legacyScrapBattery = readMap(json['scrap_battery']);
    final scrapSource = scrapBatteries.isNotEmpty
        ? scrapBatteries
        : legacyScrapBattery.isNotEmpty
            ? [legacyScrapBattery]
            : const <dynamic>[];
    final status = readString(json['status'], fallback: 'paid');
    final paymentMethod = readString(json['payment_method'], fallback: 'cash');
    final customer = json['customer'] is Map ? readMap(json['customer']) : null;
    final vehicle = json['vehicle'] is Map ? readMap(json['vehicle']) : null;

    Money financial(String key) => Money.fromJson(financials[key]);

    return InvoiceDetail(
      id: readInt(json['id']),
      invoiceNumber: readString(json['invoice_number'], fallback: '#--'),
      createdAt: readString(json['created_at']),
      status: status,
      statusLabel: _invoiceStatusLabel(status),
      subtotal: financial('subtotal'),
      taxAmount: financial('tax_amount'),
      discountAmount: financial('discount_amount'),
      scrapDeductionAmount: financial('scrap_deduction_amount'),
      netAmount: financial('final_amount'),
      paidAmount: financial('paid_amount'),
      remainingAmount: financial('remaining_amount'),
      refundedAmount: financial('refunded_amount'),
      paymentMethod: paymentMethod,
      paymentMethodLabel: _paymentMethodLabel(paymentMethod),
      notes: readStringOrNull(json['notes']),
      customer: customer == null ? null : InvoiceCustomerInfo.fromJson(customer),
      cashierName: readStringOrNull(json['cashier_name']) ??
          readStringOrNull(readMap(json['cashier'])['name']),
      technicianName: readStringOrNull(json['technician_name']) ??
          readStringOrNull(readMap(json['technician'])['name']),
      vehicle: vehicle == null ? null : InvoiceVehicleInfo.fromJson(vehicle),
      items: readModelList(json['items'], InvoiceItemDetail.fromJson),
      scrapBatteries: readModelList(scrapSource, ScrapBatteryDetail.fromJson),
      payments: readModelList(json['payments'], InvoicePaymentDetail.fromJson),
    );
  }
}

class InvoiceCustomerInfo {
  final int id;
  final String name;
  final String phone;
  final double currentCreditBalance;

  const InvoiceCustomerInfo({
    required this.id,
    required this.name,
    required this.phone,
    required this.currentCreditBalance,
  });

  factory InvoiceCustomerInfo.fromJson(Map<String, dynamic> json) {
    return InvoiceCustomerInfo(
      id: readInt(json['id']),
      name: readString(json['name']),
      phone: readString(json['phone']),
      currentCreditBalance: readDouble(json['current_credit_balance']),
    );
  }
}

class InvoiceVehicleInfo {
  final String plateNumber;
  final String make;
  final String model;

  const InvoiceVehicleInfo({
    required this.plateNumber,
    required this.make,
    required this.model,
  });

  factory InvoiceVehicleInfo.fromJson(Map<String, dynamic> json) {
    return InvoiceVehicleInfo(
      plateNumber: readString(json['plate_number']),
      make: readString(json['make']),
      model: readString(json['model']),
    );
  }
}

class InvoiceItemDetail {
  final int id;
  final String productName;
  final String productSku;
  final int quantity;
  final Money unitPrice;
  final Money totalPrice;
  final int returnedQuantity;
  final bool isBattery;
  final String? batterySerialNumber;
  final int? warrantyDurationMonths;

  const InvoiceItemDetail({
    required this.id,
    required this.productName,
    required this.productSku,
    required this.quantity,
    required this.unitPrice,
    required this.totalPrice,
    required this.returnedQuantity,
    required this.isBattery,
    this.batterySerialNumber,
    this.warrantyDurationMonths,
  });

  factory InvoiceItemDetail.fromJson(Map<String, dynamic> json) {
    return InvoiceItemDetail(
      id: readInt(json['id']),
      productName: readString(json['product_name'], fallback: 'منتج'),
      productSku: readString(json['sku']),
      quantity: readInt(json['quantity'], fallback: 1),
      unitPrice: Money.fromJson(json['unit_price']),
      totalPrice: Money.fromJson(json['total_price']),
      returnedQuantity: readInt(json['returned_quantity']),
      isBattery: readBool(json['is_battery']),
      batterySerialNumber: readStringOrNull(json['battery_serial_number']),
      warrantyDurationMonths: readIntOrNull(json['warranty_duration_months']),
    );
  }
}

class ScrapBatteryDetail {
  final String capacityAh;
  final double scrapValue;
  final double leadWeightKg;
  final String? status;

  const ScrapBatteryDetail({
    required this.capacityAh,
    required this.scrapValue,
    required this.leadWeightKg,
    this.status,
  });

  factory ScrapBatteryDetail.fromJson(Map<String, dynamic> json) {
    return ScrapBatteryDetail(
      capacityAh: readString(json['capacity_ah']),
      scrapValue: readDouble(json['scrap_value']),
      leadWeightKg: readDouble(json['lead_weight_kg']),
      status: readStringOrNull(json['status']),
    );
  }
}

class InvoicePaymentDetail {
  final String method;
  final String methodLabel;
  final Money amount;
  final String date;

  const InvoicePaymentDetail({
    required this.method,
    required this.methodLabel,
    required this.amount,
    required this.date,
  });

  factory InvoicePaymentDetail.fromJson(Map<String, dynamic> json) {
    final method = readStringOrNull(json['payment_method']) ??
        readStringOrNull(json['method']) ??
        'cash';
    final raw = readDouble(json['amount']);

    return InvoicePaymentDetail(
      method: method,
      methodLabel: _paymentMethodLabel(method),
      amount: Money(
        raw: raw,
        display: readStringOrNull(json['formatted']) ??
            '${raw.toStringAsFixed(2)} ج.م',
      ),
      date: readStringOrNull(json['created_at']) ??
          readStringOrNull(json['date']) ??
          '',
    );
  }
}

