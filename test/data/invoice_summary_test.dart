import 'package:flutter_test/flutter_test.dart';
import 'package:alhusseini_app/data/models/recent_invoice.dart';

void main() {
  test('parses a complete invoice summary resource', () {
    final summary = InvoiceSummary.fromJson({
      'id': '12',
      'invoice_number': 'INV-12',
      'time': '10:30 ص',
      'created_at': '2026-01-02T10:30:00Z',
      'customer_name': 'أحمد',
      'customer_phone': '01000000000',
      'cashier_name': 'محمد',
      'final_amount': {
        'raw': '1250.50',
        'formatted': '1,250.50 ج.م',
      },
      'items_count': '2',
      'payment_method': 'card',
      'status': 'paid',
      'has_warranty': '1',
    });

    expect(summary.id, 12);
    expect(summary.invoiceNumber, 'INV-12');
    expect(summary.netAmount.raw, 1250.5);
    expect(summary.netAmount.display, '1,250.50 ج.م');
    expect(summary.customerPhone, '01000000000');
    expect(summary.itemsCount, 2);
    expect(summary.hasWarrantyBattery, isTrue);
    expect(summary.paymentStatusLabel, 'مدفوعة');
    expect(summary.paymentMethodLabel, 'بطاقة');
  });

  test('uses tolerant defaults for null and missing summary values', () {
    final summary = InvoiceSummary.fromJson({
      'id': 'not-a-number',
      'customer_phone': null,
      'final_amount': null,
      'status': null,
      'payment_method': null,
    });

    expect(summary.id, 0);
    expect(summary.invoiceNumber, '#--');
    expect(summary.customerName, 'عميل نقدي');
    expect(summary.customerPhone, '');
    expect(summary.netAmount.raw, 0);
    expect(summary.paymentStatus, 'paid');
    expect(summary.paymentMethod, 'cash');
  });
}

