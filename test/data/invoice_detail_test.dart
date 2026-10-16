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
}

Map<String, dynamic> _financials() {
  return {
    'subtotal': {'raw': 100.0},
    'tax_amount': {'raw': 15.0},
    'discount_amount': {'raw': 0.0},
    'final_amount': {'raw': 115.0},
    'paid_amount': {'raw': 115.0},
    'remaining_amount': {'raw': 0.0},
  };
}
