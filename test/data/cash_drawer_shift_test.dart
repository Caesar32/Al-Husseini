import 'package:flutter_test/flutter_test.dart';

import 'package:alhusseini_app/data/models/cash_drawer_shift.dart';

void main() {
  group('CashDrawerShift.fromJson', () {
    test('reads the complete server shape', () {
      final shift = CashDrawerShift.fromJson({
        'cashier_name': 'محمد',
        'shift_status': 'open',
        'opened_at': '08:30 ص',
        'duration': 'ساعتان',
        'opening_balance': 100.5,
        'cash_sales': 1250,
        'card_sales': '300.25',
        'bank_transfer_sales': 75,
        'credit_collections_cash': 50,
        'expenses_paid': 10,
        'expected_drawer_cash': 1390.5,
        'audit_notes': 'لا توجد ملاحظات',
        'formatted': {
          'opening_balance': '100.50 ج.م',
          'cash_sales': '1,250.00 ج.م',
          'expected_drawer_cash': '1,390.50 ج.م',
        },
      });

      expect(shift.cashierName, 'محمد');
      expect(shift.shiftStatus, 'open');
      expect(shift.openedAt, '08:30 ص');
      expect(shift.duration, 'ساعتان');
      expect(shift.openingBalance, 100.5);
      expect(shift.cashSales, 1250.0);
      expect(shift.cardSales, 300.25);
      expect(shift.bankTransferSales, 75.0);
      expect(shift.creditCollectionsCash, 50.0);
      expect(shift.expensesPaid, 10.0);
      expect(shift.expectedDrawerCash, 1390.5);
      expect(shift.auditNotes, 'لا توجد ملاحظات');
      expect(shift.formatted['cash_sales'], '1,250.00 ج.م');
      expect(shift.hasActiveShift, isTrue);
    });

    test('uses defaults for null and missing values', () {
      final shift = CashDrawerShift.fromJson({
        'cashier_name': null,
        'shift_status': null,
        'opened_at': null,
        'duration': null,
        'opening_balance': null,
        'cash_sales': null,
        'formatted': null,
      });

      expect(shift.cashierName, 'كاشير المحل');
      expect(shift.shiftStatus, 'open');
      expect(shift.openedAt, isNull);
      expect(shift.duration, isNull);
      expect(shift.openingBalance, 0.0);
      expect(shift.cashSales, 0.0);
      expect(shift.cardSales, 0.0);
      expect(shift.auditNotes, '');
      expect(shift.formatted, isEmpty);
      expect(shift.hasActiveShift, isTrue);
    });

    test('accepts numeric strings and detects inactive shifts', () {
      final shift = CashDrawerShift.fromJson({
        'shift_status': 'no_active_shift',
        'opening_balance': '100.50',
        'cash_sales': '1,250.25',
        'expected_drawer_cash': '1350.75',
      });

      expect(shift.openingBalance, 100.5);
      expect(shift.cashSales, 1250.25);
      expect(shift.expectedDrawerCash, 1350.75);
      expect(shift.hasActiveShift, isFalse);
    });
  });
}
