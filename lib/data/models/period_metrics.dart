import 'json_read.dart';

class PeriodMetrics {
  final String period;
  final String periodLabel;
  final MoneyValue revenue;
  final int totalSalesCount;
  final MoneyValue creditCollected;

  const PeriodMetrics({
    required this.period,
    required this.periodLabel,
    required this.revenue,
    required this.totalSalesCount,
    required this.creditCollected,
  });

  factory PeriodMetrics.fromJson(Map<String, dynamic> json) {
    return PeriodMetrics(
      period: readString(json['period'], fallback: 'today'),
      periodLabel: readString(json['period_label'], fallback: 'اليوم'),
      revenue: MoneyValue.fromJson(readMap(json['revenue'])),
      totalSalesCount: readInt(json['total_sales_count']),
      creditCollected: MoneyValue.fromJson(
        readMap(json['credit_collected']),
      ),
    );
  }
}

class MoneyValue {
  final double raw;
  final String compact;
  final String exact;

  const MoneyValue({
    required this.raw,
    required this.compact,
    required this.exact,
  });

  factory MoneyValue.fromJson(Map<String, dynamic> json) {
    return MoneyValue(
      raw: readDouble(json['raw']),
      compact: readString(json['compact'], fallback: '0 ج.م'),
      exact: readString(json['exact'], fallback: '0.00 ج.م'),
    );
  }
}
