import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';

import 'package:alhusseini_app/data/models/recent_invoice.dart';
import 'package:alhusseini_app/presentation/widgets/invoice_detail_sheet.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    GoogleFonts.config.allowRuntimeFetching = false;
  });

  testWidgets('يعرض تفاصيل الفاتورة والمدفوعات', (tester) async {
    tester.view.physicalSize = const Size(800, 2400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    final invoice = InvoiceDetail.fromJson({
      'id': 1,
      'invoice_number': 'INV-2026-001',
      'created_at': '2026-01-02 10:00',
      'status': 'paid',
      'financials': {
        'subtotal': {
          'raw': 1200,
          'formatted': '1,200.00 ج.م',
        },
        'tax_amount': {
          'raw': 0,
          'formatted': '0.00 ج.م',
        },
        'discount_amount': {
          'raw': 50,
          'formatted': '50.00 ج.م',
        },
        'scrap_deduction_amount': {
          'raw': 100,
          'formatted': '100.00 ج.م',
        },
        'final_amount': {
          'raw': 1050,
          'formatted': '1,050.00 ج.م',
        },
        'paid_amount': {
          'raw': 800,
          'formatted': '800.00 ج.م',
        },
        'remaining_amount': {
          'raw': 250,
          'formatted': '250.00 ج.م',
        },
        'refunded_amount': {
          'raw': 100,
          'formatted': '100.00 ج.م',
        },
      },
      'items': [
        {
          'id': 1,
          'product_name': 'بطارية 70 أمبير',
          'sku': 'BAT-70',
          'quantity': 2,
          'returned_quantity': 1,
          'is_battery': false,
          'unit_price': {
            'raw': 500,
            'formatted': '500.00 ج.م',
          },
          'total_price': {
            'raw': 1000,
            'formatted': '1,000.00 ج.م',
          },
        },
        {
          'id': 2,
          'product_name': 'زيت محرك',
          'sku': 'OIL-1',
          'quantity': 1,
          'returned_quantity': 0,
          'is_battery': false,
          'unit_price': {
            'raw': 200,
            'formatted': '200.00 ج.م',
          },
          'total_price': {
            'raw': 200,
            'formatted': '200.00 ج.م',
          },
        },
      ],
      'scrap_batteries': [
        {
          'capacity_ah': '70Ah',
          'scrap_value': 75,
          'lead_weight_kg': 10,
        },
        {
          'capacity_ah': '55Ah',
          'scrap_value': 50,
          'lead_weight_kg': 8,
        },
      ],
      'payments': [
        {
          'payment_method': 'cash',
          'amount': 800,
          'formatted': '800.00 ج.م',
          'created_at': '2026-01-02 10:30',
        },
      ],
    });

    await tester.pumpWidget(
      MaterialApp(
        home: Directionality(
          textDirection: TextDirection.rtl,
          child: Scaffold(
            body: InvoiceDetailSheet(invoice: invoice),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('فاتورة رقم INV-2026-001'), findsOneWidget);
    expect(find.text('بطارية 70 أمبير'), findsOneWidget);
    expect(find.text('زيت محرك'), findsOneWidget);
    expect(find.textContaining('(مرتجع: 1)'), findsOneWidget);
    expect(find.text('خصم الخردة'), findsOneWidget);
    expect(find.text('المسترد'), findsOneWidget);
    expect(find.text('المدفوعات'), findsOneWidget);
    expect(find.text('نقدي'), findsOneWidget);
    expect(find.text('800.00'), findsOneWidget);
  });
}
