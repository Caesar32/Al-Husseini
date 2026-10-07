class LivePulseData {
  final SafeCashData safeCashNow;
  final SalesTodayData salesToday;
  final ActiveShiftData activeShift;
  final WorkshopAttendanceData workshopAttendance;
  final CriticalAlertsData criticalAlerts;

  LivePulseData({
    required this.safeCashNow,
    required this.salesToday,
    required this.activeShift,
    required this.workshopAttendance,
    required this.criticalAlerts,
  });

  factory LivePulseData.fromJson(Map<String, dynamic> json) {
    return LivePulseData(
      safeCashNow: SafeCashData.fromJson(json['safe_cash_now'] ?? {}),
      salesToday: SalesTodayData.fromJson(json['sales_today'] ?? {}),
      activeShift: ActiveShiftData.fromJson(json['active_shift'] ?? {}),
      workshopAttendance: WorkshopAttendanceData.fromJson(json['workshop_attendance'] ?? {}),
      criticalAlerts: CriticalAlertsData.fromJson(json['critical_alerts'] ?? {}),
    );
  }
}

class SafeCashData {
  final double raw;
  final String formatted;
  final String exact;
  final String label;

  SafeCashData({
    required this.raw,
    required this.formatted,
    required this.exact,
    required this.label,
  });

  factory SafeCashData.fromJson(Map<String, dynamic> json) {
    return SafeCashData(
      raw: (json['raw'] ?? 0.0).toDouble(),
      formatted: json['formatted'] ?? '0 ج.م',
      exact: json['exact'] ?? '0.00 ج.م',
      label: json['label'] ?? 'الكاش الفعلي بالخزينة',
    );
  }
}

class SalesTodayData {
  final double raw;
  final String formatted;
  final String exact;
  final int invoicesCount;
  final String comparedToYesterday;

  SalesTodayData({
    required this.raw,
    required this.formatted,
    required this.exact,
    required this.invoicesCount,
    required this.comparedToYesterday,
  });

  factory SalesTodayData.fromJson(Map<String, dynamic> json) {
    return SalesTodayData(
      raw: (json['raw'] ?? 0.0).toDouble(),
      formatted: json['formatted'] ?? '0 ج.م',
      exact: json['exact'] ?? '0.00 ج.م',
      invoicesCount: json['invoices_count'] ?? 0,
      comparedToYesterday: json['compared_to_yesterday'] ?? '+0.0%',
    );
  }
}

class ActiveShiftData {
  final bool isOpen;
  final String cashierName;
  final String openedAt;
  final String durationHours;

  ActiveShiftData({
    required this.isOpen,
    required this.cashierName,
    required this.openedAt,
    required this.durationHours,
  });

  factory ActiveShiftData.fromJson(Map<String, dynamic> json) {
    return ActiveShiftData(
      isOpen: json['is_open'] ?? false,
      cashierName: json['cashier_name'] ?? 'لا توجد وردية',
      openedAt: json['opened_at'] ?? '--:--',
      durationHours: json['duration_hours'] ?? '--',
    );
  }
}

class WorkshopAttendanceData {
  final int presentCount;
  final int totalEmployees;
  final String attendanceRate;

  WorkshopAttendanceData({
    required this.presentCount,
    required this.totalEmployees,
    required this.attendanceRate,
  });

  factory WorkshopAttendanceData.fromJson(Map<String, dynamic> json) {
    return WorkshopAttendanceData(
      presentCount: json['present_count'] ?? 0,
      totalEmployees: json['total_employees'] ?? 0,
      attendanceRate: json['attendance_rate'] ?? '0%',
    );
  }
}

class CriticalAlertsData {
  final int lowStockCount;
  final int returnsTodayCount;
  final int unsettledCreditCount;

  CriticalAlertsData({
    required this.lowStockCount,
    required this.returnsTodayCount,
    required this.unsettledCreditCount,
  });

  factory CriticalAlertsData.fromJson(Map<String, dynamic> json) {
    return CriticalAlertsData(
      lowStockCount: json['low_stock_count'] ?? 0,
      returnsTodayCount: json['returns_today_count'] ?? 0,
      unsettledCreditCount: json['unsettled_credit_count'] ?? 0,
    );
  }
}
