import 'package:flutter_test/flutter_test.dart';
import 'package:alhusseini_app/data/models/recent_invoice.dart';

void main() {
  group('Invoice detail scrap battery parsing', () {
    test('parses multiple scrap batteries', () {
      final invoice = InvoiceDetail.fromJson({
        'id': 1,
        'invoice_number': 'INV-1',
        'financials': _financials(),
        'items': [],
        'payments': [],
        'scrap_batteries': [
          {
            'capacity_ah': '70Ah',
            'scrap_value': 150,
            'lead_weight_kg': 12.5,
            'status': 'in_stock',
          },
          {
            'capacity_ah': '55Ah',
            'scrap_value': '90.50',
          },
        ],
      });

      expect(invoice.scrapBatteries, hasLength(2));
      expect(invoice.scrapBatteries[0].capacityAh, '70Ah');
      expect(invoice.scrapBatteries[0].scrapValue, 150.0);
      expect(invoice.scrapBatteries[0].leadWeightKg, 12.5);
      expect(invoice.scrapBatteries[0].status, 'in_stock');
      expect(invoice.scrapBatteries[1].capacityAh, '55Ah');
      expect(invoice.scrapBatteries[1].scrapValue, 90.5);
      expect(invoice.scrapBatteries[1].leadWeightKg, 0.0);
      expect(invoice.scrapBatteries[1].status, isNull);
    });

    test('falls back to the legacy scrap battery field', () {
      final invoice = InvoiceDetail.fromJson({
        'id': 1,
        'invoice_number': 'INV-1',
        'financials': _financials(),
        'items': [],
        'payments': [],
        'scrap_battery': {
          'capacity_ah': '60Ah',
          'scrap_value': '75.25',
        },
      });

      expect(invoice.scrapBatteries, hasLength(1));
      expect(invoice.scrapBatteries.single.capacityAh, '60Ah');
      expect(invoice.scrapBatteries.single.scrapValue, 75.25);
    });

    test('returns an empty list when no scrap battery fields exist', () {
      final invoice = InvoiceDetail.fromJson({
        'id': 1,
        'invoice_number': 'INV-1',
        'financials': _financials(),
        'items': [],
        'payments': [],
      });

      expect(invoice.scrapBatteries, isEmpty);
    });

    test('parses a minimal invoice payload without throwing', () {
      final invoice = InvoiceDetail.fromJson({
        'id': 1,
        'invoice_number': 'INV-1',
        'financials': _financials(),
        'items': [],
        'payments': [],
      });

      expect(invoice.id, 1);
      expect(invoice.invoiceNumber, 'INV-1');
      expect(invoice.scrapBatteries, isEmpty);
    });
  });

  test('parses all invoice financials, nested staff, returned items, and payments', () {
    final invoice = InvoiceDetail.fromJson({
      'id': '42',
      'invoice_number': 'INV-42',
      'created_at': '2026-01-02T10:00:00Z',
      'status': 'partially_refunded',
      'payment_method': 'cash',
      'notes': 'ملاحظة',
      'customer': {
        'id': '7',
        'name': 'أحمد',
        'phone': '01000000000',
        'current_credit_balance': '12.5',
      },
      'cashier': {'id': 3, 'name': 'محمد'},
      'technician': {'id': 4, 'name': 'علي'},
      'vehicle': {
        'id': 9,
        'plate_number': 'س ب 123',
        'make': 'تويوتا',
        'model': 'يارس',
      },
      'financials': {
        'subtotal': {'raw': '1000', 'formatted': '1,000.00 ج.م'},
        'discount_amount': {'raw': '50', 'formatted': '50.00 ج.م'},
        'scrap_deduction_amount': {
          'raw': '25',
          'formatted': '25.00 ج.م',
        },
        'tax_amount': {'raw': '150', 'formatted': '150.00 ج.م'},
        'final_amount': {'raw': '1075', 'formatted': '1,075.00 ج.م'},
        'paid_amount': {'raw': '800', 'formatted': '800.00 ج.م'},
        'remaining_amount': {'raw': '275', 'formatted': '275.00 ج.م'},
        'refunded_amount': {'raw': '100', 'formatted': '100.00 ج.م'},
      },
      'items': [
        {
          'id': '11',
          'product_name': 'بطارية',
          'sku': 'BAT-1',
          'quantity': '2',
          'returned_quantity': '1',
          'is_battery': true,
          'battery_serial_number': 'SER-1',
          'warranty_duration_months': '12',
          'unit_price': {'raw': '500', 'formatted': '500.00 ج.م'},
          'total_price': {'raw': '1000', 'formatted': '1,000.00 ج.م'},
        },
      ],
      'payments': [
        {
          'id': 1,
          'payment_method': 'cash',
          'amount': '800',
          'formatted': '800.00 ج.م',
          'created_at': '2026-01-02T10:30:00Z',
        },
      ],
    });

    expect(invoice.id, 42);
    expect(invoice.statusLabel, 'مسترجعة جزئياً');
    expect(invoice.subtotal.raw, 1000);
    expect(invoice.taxAmount.raw, 150);
    expect(invoice.discountAmount.raw, 50);
    expect(invoice.scrapDeductionAmount.raw, 25);
    expect(invoice.netAmount.display, '1,075.00 ج.م');
    expect(invoice.paidAmount.raw, 800);
    expect(invoice.remainingAmount.raw, 275);
    expect(invoice.refundedAmount.raw, 100);
    expect(invoice.cashierName, 'محمد');
    expect(invoice.technicianName, 'علي');
    expect(invoice.customer?.name, 'أحمد');
    expect(invoice.vehicle?.plateNumber, 'س ب 123');
    expect(invoice.items.single.returnedQuantity, 1);
    expect(invoice.items.single.unitPrice.raw, 500);
    expect(invoice.payments.single.amount.raw, 800);
    expect(invoice.payments.single.date, '2026-01-02T10:30:00Z');
  });
}

Map<String, dynamic> _financials() {
  return {
    'subtotal': {'raw': 100.0},
    'tax_amount': {'raw': 15.0},
    'discount_amount': {'raw': 0.0},
    'scrap_deduction_amount': {'raw': 0.0},
    'final_amount': {'raw': 115.0},
    'paid_amount': {'raw': 115.0},
    'remaining_amount': {'raw': 0.0},
    'refunded_amount': {'raw': 0.0},
  };
}

