import 'json_read.dart';
import 'period_metrics.dart';

class CreditOverviewData {
  final MoneyValue totalOutstanding;
  final MoneyValue collectedToday;
  final int debtorsCount;
  final List<DebtorItem> topDebtors;

  const CreditOverviewData({
    required this.totalOutstanding,
    required this.collectedToday,
    required this.debtorsCount,
    required this.topDebtors,
  });

  factory CreditOverviewData.fromJson(Map<String, dynamic> json) {
    return CreditOverviewData(
      totalOutstanding: MoneyValue.fromJson(
        readMap(json['total_outstanding']),
      ),
      collectedToday: MoneyValue.fromJson(
        readMap(json['collected_today']),
      ),
      debtorsCount: readInt(json['debtors_count']),
      topDebtors: readModelList(
        json['top_debtors'],
        DebtorItem.fromJson,
      ),
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
  final String? utilizationRate;

  const DebtorItem({
    required this.id,
    required this.name,
    required this.phone,
    required this.currentCreditBalance,
    required this.formattedCreditBalance,
    this.creditLimit,
    this.formattedCreditLimit,
    this.utilizationRate,
  });

  factory DebtorItem.fromJson(Map<String, dynamic> json) {
    final balance = readMap(json['current_credit_balance']);
    final limit = readMap(json['credit_limit']);
    final limitRaw = readDouble(limit['raw']);
    final hasCreditLimit = limit.isNotEmpty && limitRaw > 0;

    return DebtorItem(
      id: readInt(json['id']),
      name: readString(json['name']),
      phone: readString(json['phone']),
      currentCreditBalance: readDouble(balance['raw']),
      formattedCreditBalance: readStringOrNull(balance['exact']) ??
          readStringOrNull(balance['compact']) ??
          '${readString(balance['raw'], fallback: '0')} ج.م',
      creditLimit: hasCreditLimit ? limitRaw : null,
      formattedCreditLimit:
          hasCreditLimit ? readStringOrNull(limit['formatted']) : null,
      utilizationRate: readStringOrNull(json['utilization_rate']),
    );
  }
}
