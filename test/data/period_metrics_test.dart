import 'package:flutter_test/flutter_test.dart';

import 'package:alhusseini_app/data/models/period_metrics.dart';

void main() {
  group('PeriodMetrics.fromJson', () {
    test('reads the complete server shape', () {
      final metrics = PeriodMetrics.fromJson({
        'period': 'month',
        'period_label': 'هذا الشهر',
        'revenue': {
          'raw': 125000,
          'compact': '125.00K ج.م',
          'exact': '125,000.00 ج.م',
        },
        'total_sales_count': 245,
        'credit_collected': {
          'raw': 18000,
          'compact': '18.00K ج.م',
          'exact': '18,000.00 ج.م',
        },
      });

      expect(metrics.period, 'month');
      expect(metrics.periodLabel, 'هذا الشهر');
      expect(metrics.revenue.raw, 125000.0);
      expect(metrics.revenue.compact, '125.00K ج.م');
      expect(metrics.totalSalesCount, 245);
      expect(metrics.creditCollected.exact, '18,000.00 ج.م');
    });

    test('uses defaults for null and missing values', () {
      final metrics = PeriodMetrics.fromJson({
        'period': null,
        'period_label': null,
        'revenue': null,
        'total_sales_count': null,
        'credit_collected': null,
      });

      expect(metrics.period, 'today');
      expect(metrics.periodLabel, 'اليوم');
      expect(metrics.revenue.raw, 0.0);
      expect(metrics.revenue.compact, '0 ج.م');
      expect(metrics.revenue.exact, '0.00 ج.م');
      expect(metrics.totalSalesCount, 0);
      expect(metrics.creditCollected.raw, 0.0);
    });

    test('accepts numeric strings in all numeric fields', () {
      final metrics = PeriodMetrics.fromJson({
        'period': 'week',
        'revenue': {
          'raw': '1234.50',
        },
        'total_sales_count': '12',
        'credit_collected': {
          'raw': '300.25',
        },
      });

      expect(metrics.period, 'week');
      expect(metrics.revenue.raw, 1234.5);
      expect(metrics.totalSalesCount, 12);
      expect(metrics.creditCollected.raw, 300.25);
    });
  });
}
