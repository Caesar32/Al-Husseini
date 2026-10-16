import 'json_read.dart';

String _returnStatusLabel(String status) {
  switch (status) {
    case 'refunded':
      return 'مسترجعة';
    case 'partially_refunded':
      return 'مسترجعة جزئياً';
    default:
      return status;
  }
}

class ReturnSummary {
  final int id;
  final String invoiceNumber;
  final String customerName;
  final String customerPhone;
  final String cashierName;
  final String status;
  final String statusLabel;
  final String time;
  final String updatedAt;
  final Money refundedAmount;
  final Money originalAmount;
  final List<ReturnedItem> returnedItems;

  const ReturnSummary({
    required this.id,
    required this.invoiceNumber,
    required this.customerName,
    required this.customerPhone,
    required this.cashierName,
    required this.status,
    required this.statusLabel,
    required this.time,
    required this.updatedAt,
    required this.refundedAmount,
    required this.originalAmount,
    required this.returnedItems,
  });

  factory ReturnSummary.fromJson(Map<String, dynamic> json) {
    final status = readString(json['status']);

    return ReturnSummary(
      id: readInt(json['id']),
      invoiceNumber: readString(json['invoice_number'], fallback: '#--'),
      customerName: readString(json['customer_name'], fallback: 'عميل نقدي'),
      customerPhone: readString(json['customer_phone']),
      cashierName: readString(json['cashier_name'], fallback: 'كاشير'),
      status: status,
      statusLabel: _returnStatusLabel(status),
      time: readString(json['time']),
      updatedAt: readString(json['updated_at']),
      refundedAmount: Money.fromJson(json['refunded_amount']),
      originalAmount: Money.fromJson(json['original_amount']),
      returnedItems: readModelList(json['returned_items'], ReturnedItem.fromJson),
    );
  }
}

class ReturnedItem {
  final String productName;
  final int returnedQuantity;
  final Money unitPrice;
  final Money totalRefunded;

  const ReturnedItem({
    required this.productName,
    required this.returnedQuantity,
    required this.unitPrice,
    required this.totalRefunded,
  });

  factory ReturnedItem.fromJson(Map<String, dynamic> json) {
    final unitPriceRaw = readDouble(json['unit_price']);
    final totalRefundedRaw = readDouble(json['total_refunded']);

    return ReturnedItem(
      productName: readString(json['product_name'], fallback: 'صنف غير محدد'),
      returnedQuantity: readInt(json['returned_quantity']),
      unitPrice: Money(
        raw: unitPriceRaw,
        display: '${unitPriceRaw.toStringAsFixed(2)} ج.م',
      ),
      totalRefunded: Money(
        raw: totalRefundedRaw,
        display: readStringOrNull(json['formatted_total']) ??
            '${totalRefundedRaw.toStringAsFixed(2)} ج.م',
      ),
    );
  }
}

