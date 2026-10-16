import 'json_read.dart';

class CashDrawerShift {
  final String cashierName;
  final String shiftStatus;
  final String? openedAt;
  final String? duration;
  final double openingBalance;
  final double cashSales;
  final double cardSales;
  final double bankTransferSales;
  final double creditCollectionsCash;
  final double expensesPaid;
  final double expectedDrawerCash;
  final String auditNotes;
  final Map<String, String> formatted;

  const CashDrawerShift({
    required this.cashierName,
    required this.shiftStatus,
    this.openedAt,
    this.duration,
    required this.openingBalance,
    required this.cashSales,
    required this.cardSales,
    required this.bankTransferSales,
    required this.creditCollectionsCash,
    required this.expensesPaid,
    required this.expectedDrawerCash,
    required this.auditNotes,
    required this.formatted,
  });

  factory CashDrawerShift.fromJson(Map<String, dynamic> json) {
    final formattedMap = readMap(json['formatted']).map(
      (key, value) => MapEntry(key, readString(value, fallback: 'null')),
    );

    return CashDrawerShift(
      cashierName: readString(json['cashier_name'], fallback: 'كاشير المحل'),
      shiftStatus: readString(json['shift_status'], fallback: 'open'),
      openedAt: readStringOrNull(json['opened_at']),
      duration: readStringOrNull(json['duration']),
      openingBalance: readDouble(json['opening_balance']),
      cashSales: readDouble(json['cash_sales']),
      cardSales: readDouble(json['card_sales']),
      bankTransferSales: readDouble(json['bank_transfer_sales']),
      creditCollectionsCash: readDouble(json['credit_collections_cash']),
      expensesPaid: readDouble(json['expenses_paid']),
      expectedDrawerCash: readDouble(json['expected_drawer_cash']),
      auditNotes: readString(json['audit_notes']),
      formatted: formattedMap,
    );
  }

  bool get hasActiveShift => shiftStatus != 'no_active_shift';
}
