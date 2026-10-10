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

  CashDrawerShift({
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
    final rawFormatted = json['formatted'] as Map<String, dynamic>? ?? {};
    final formattedMap = rawFormatted.map((k, v) => MapEntry(k, v.toString()));

    return CashDrawerShift(
      cashierName: json['cashier_name'] ?? 'كاشير المحل',
      shiftStatus: json['shift_status'] ?? 'open',
      openedAt: json['opened_at'],
      duration: json['duration'],
      openingBalance: (json['opening_balance'] ?? 0.0).toDouble(),
      cashSales: (json['cash_sales'] ?? 0.0).toDouble(),
      cardSales: (json['card_sales'] ?? 0.0).toDouble(),
      bankTransferSales: (json['bank_transfer_sales'] ?? 0.0).toDouble(),
      creditCollectionsCash: (json['credit_collections_cash'] ?? 0.0).toDouble(),
      expensesPaid: (json['expenses_paid'] ?? 0.0).toDouble(),
      expectedDrawerCash: (json['expected_drawer_cash'] ?? 0.0).toDouble(),
      auditNotes: json['audit_notes'] ?? '',
      formatted: formattedMap,
    );
  }
}
