import 'period_metrics.dart';

class CreditOverviewData {
  final MoneyValue totalOutstanding;
  final MoneyValue collectedToday;
  final int debtorsCount;
  final List<DebtorItem> topDebtors;

  CreditOverviewData({
    required this.totalOutstanding,
    required this.collectedToday,
    required this.debtorsCount,
    required this.topDebtors,
  });

  factory CreditOverviewData.fromJson(Map<String, dynamic> json) {
    final rawList = json['top_debtors'] as List? ?? [];

    return CreditOverviewData(
      totalOutstanding: MoneyValue.fromJson(json['total_outstanding'] ?? {}),
      collectedToday: MoneyValue.fromJson(json['collected_today'] ?? {}),
      debtorsCount: json['debtors_count'] ?? 0,
      topDebtors: rawList.map((e) => DebtorItem.fromJson(e)).toList(),
    );
  }
}

class DebtorItem {
  final int id;
  final String name;
  final String phone;
  final double currentCreditBalance;
  final String formattedCreditBalance;
  final double? creditLimit;
  final String? formattedCreditLimit;

  DebtorItem({
    required this.id,
    required this.name,
    required this.phone,
    required this.currentCreditBalance,
    required this.formattedCreditBalance,
    this.creditLimit,
    this.formattedCreditLimit,
  });

  factory DebtorItem.fromJson(Map<String, dynamic> json) {
    final balance = json['current_credit_balance'] as Map<String, dynamic>? ?? {};
    final limit = json['credit_limit'] as Map<String, dynamic>?;

    return DebtorItem(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      name: json['name'] ?? '',
      phone: json['phone'] ?? '',
      currentCreditBalance: (balance['raw'] ?? 0.0).toDouble(),
      formattedCreditBalance: balance['exact'] ?? balance['compact'] ?? '${balance['raw'] ?? 0} ج.م',
      creditLimit: limit != null ? (limit['raw'] ?? 0.0).toDouble() : null,
      formattedCreditLimit: limit?['formatted'],
    );
  }
}
