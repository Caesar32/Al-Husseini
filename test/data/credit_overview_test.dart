import 'package:flutter_test/flutter_test.dart';

import 'package:alhusseini_app/data/models/credit_overview.dart';

void main() {
  group('CreditOverviewData.fromJson', () {
    test('reads the complete server shape', () {
      final overview = CreditOverviewData.fromJson({
        'total_outstanding': {
          'raw': 12500,
          'compact': '12.50K ج.م',
          'exact': '12,500.00 ج.م',
        },
        'collected_today': {
          'raw': 1800,
          'compact': '1.80K ج.م',
          'exact': '1,800.00 ج.م',
        },
        'debtors_count': 4,
        'top_debtors': [
          {
            'id': 9,
            'name': 'علي حسن',
            'phone': '01000000000',
            'current_credit_balance': {
              'raw': 2500,
              'compact': '2.50K ج.م',
              'exact': '2,500.00 ج.م',
            },
            'credit_limit': {
              'raw': 5000,
              'formatted': '5,000.00 ج.م',
            },
            'utilization_rate': '50%',
          },
        ],
      });

      expect(overview.totalOutstanding.raw, 12500.0);
      expect(overview.totalOutstanding.compact, '12.50K ج.م');
      expect(overview.collectedToday.exact, '1,800.00 ج.م');
      expect(overview.debtorsCount, 4);
      expect(overview.topDebtors, hasLength(1));
      expect(overview.topDebtors.single.name, 'علي حسن');
      expect(overview.topDebtors.single.creditLimit, 5000.0);
      expect(
        overview.topDebtors.single.formattedCreditLimit,
        '5,000.00 ج.م',
      );
      expect(overview.topDebtors.single.utilizationRate, '50%');
    });

    test('uses defaults for null and missing values', () {
      final overview = CreditOverviewData.fromJson({
        'total_outstanding': null,
        'collected_today': null,
        'debtors_count': null,
        'top_debtors': null,
      });

      expect(overview.totalOutstanding.raw, 0.0);
      expect(overview.totalOutstanding.compact, '0 ج.م');
      expect(overview.totalOutstanding.exact, '0.00 ج.م');
      expect(overview.collectedToday.raw, 0.0);
      expect(overview.debtorsCount, 0);
      expect(overview.topDebtors, isEmpty);
    });

    test('accepts numeric strings and treats a zero limit as absent', () {
      final overview = CreditOverviewData.fromJson({
        'debtors_count': '2',
        'top_debtors': [
          {
            'id': '1',
            'name': 'عميل',
            'phone': 101,
            'current_credit_balance': {'raw': '1250.50'},
            'credit_limit': {
              'raw': '0',
              'formatted': '0.00 ج.م',
            },
            'utilization_rate': null,
          },
          {
            'id': '2',
            'current_credit_balance': {'raw': '500'},
            'credit_limit': {
              'raw': '5000',
              'formatted': '5,000.00 ج.م',
            },
          },
        ],
      });

      expect(overview.debtorsCount, 2);
      expect(overview.topDebtors[0].id, 1);
      expect(overview.topDebtors[0].phone, '101');
      expect(overview.topDebtors[0].currentCreditBalance, 1250.5);
      expect(overview.topDebtors[0].creditLimit, isNull);
      expect(overview.topDebtors[0].formattedCreditLimit, isNull);
      expect(overview.topDebtors[1].creditLimit, 5000.0);
      expect(
        overview.topDebtors[1].formattedCreditLimit,
        '5,000.00 ج.م',
      );
    });
  });
}
