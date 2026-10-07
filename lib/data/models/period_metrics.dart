class PeriodMetrics {
  final String period;
  final String periodLabel;
  final MoneyValue revenue;
  final int totalSalesCount;
  final MoneyValue creditCollected;

  PeriodMetrics({
    required this.period,
    required this.periodLabel,
    required this.revenue,
    required this.totalSalesCount,
    required this.creditCollected,
  });

  factory PeriodMetrics.fromJson(Map<String, dynamic> json) {
    return PeriodMetrics(
      period: json['period'] ?? 'today',
      periodLabel: json['period_label'] ?? 'اليوم',
      revenue: MoneyValue.fromJson(json['revenue'] ?? {}),
      totalSalesCount: json['total_sales_count'] ?? 0,
      creditCollected: MoneyValue.fromJson(json['credit_collected'] ?? {}),
    );
  }
}

class MoneyValue {
  final double raw;
  final String compact;
  final String exact;

  MoneyValue({
    required this.raw,
    required this.compact,
    required this.exact,
  });

  factory MoneyValue.fromJson(Map<String, dynamic> json) {
    return MoneyValue(
      raw: (json['raw'] ?? 0.0).toDouble(),
      compact: json['compact'] ?? '0 ج.م',
      exact: json['exact'] ?? '0.00 ج.م',
    );
  }
}
