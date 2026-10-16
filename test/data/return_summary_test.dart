import 'package:flutter_test/flutter_test.dart';
import 'package:alhusseini_app/data/models/return_summary.dart';

void main() {
  test('parses a complete return resource', () {
    final summary = ReturnSummary.fromJson({
      'id': '8',
      'invoice_number': 'INV-8',
      'customer_name': 'أحمد',
      'customer_phone': '01000000000',
      'cashier_name': 'محمد',
      'status': 'partially_refunded',
      'time': '11:15 ص',
      'updated_at': '2026-01-02T11:15:00Z',
      'refunded_amount': {
        'raw': '250.50',
        'formatted': '250.50 ج.م',
      },
      'original_amount': {
        'raw': '1000',
        'formatted': '1,000.00 ج.م',
      },
      'returned_items': [
        {
          'product_name': 'بطارية',
          'returned_quantity': '1',
          'unit_price': '250.5',
          'total_refunded': '250.5',
          'formatted_total': '250.50 ج.م',
        },
      ],
    });

    expect(summary.id, 8);
    expect(summary.invoiceNumber, 'INV-8');
    expect(summary.customerName, 'أحمد');
    expect(summary.customerPhone, '01000000000');
    expect(summary.statusLabel, 'مسترجعة جزئياً');
    expect(summary.refundedAmount.raw, 250.5);
    expect(summary.originalAmount.display, '1,000.00 ج.م');
    expect(summary.returnedItems, hasLength(1));
    expect(summary.returnedItems.single.productName, 'بطارية');
    expect(summary.returnedItems.single.returnedQuantity, 1);
    expect(summary.returnedItems.single.unitPrice.display, '250.50 ج.م');
    expect(summary.returnedItems.single.totalRefunded.display, '250.50 ج.م');
  });

  test('uses defaults and an empty item list when returned items are missing', () {
    final summary = ReturnSummary.fromJson({
      'id': 9,
      'status': 'refunded',
      'customer_name': null,
      'customer_phone': null,
      'returned_items': null,
    });

    expect(summary.customerName, 'عميل نقدي');
    expect(summary.customerPhone, '');
    expect(summary.cashierName, 'كاشير');
    expect(summary.statusLabel, 'مسترجعة');
    expect(summary.returnedItems, isEmpty);
    expect(summary.refundedAmount.raw, 0);
    expect(summary.originalAmount.raw, 0);
  });
}

