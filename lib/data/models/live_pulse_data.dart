import 'json_read.dart';

class LivePulseData {
  final SafeCashData safeCashNow;
  final SalesTodayData salesToday;
  final ActiveShiftData activeShift;
  final WorkshopAttendanceData workshopAttendance;
  final CriticalAlertsData criticalAlerts;

  const LivePulseData({
    required this.safeCashNow,
    required this.salesToday,
    required this.activeShift,
    required this.workshopAttendance,
    required this.criticalAlerts,
  });

  factory LivePulseData.fromJson(Map<String, dynamic> json) {
    return LivePulseData(
      safeCashNow: SafeCashData.fromJson(
        readMap(json['safe_cash_now']),
      ),
      salesToday: SalesTodayData.fromJson(
        readMap(json['sales_today']),
      ),
      activeShift: ActiveShiftData.fromJson(
        readMap(json['active_shift']),
      ),
      workshopAttendance: WorkshopAttendanceData.fromJson(
        readMap(json['workshop_attendance']),
      ),
      criticalAlerts: CriticalAlertsData.fromJson(
        readMap(json['critical_alerts']),
      ),
    );
  }
}

class SafeCashData {
  final double raw;
  final String formatted;
  final String exact;
  final String label;

  const SafeCashData({
    required this.raw,
    required this.formatted,
    required this.exact,
    required this.label,
  });

  factory SafeCashData.fromJson(Map<String, dynamic> json) {
    return SafeCashData(
      raw: readDouble(json['raw']),
      formatted: readString(json['formatted'], fallback: '0 ج.م'),
      exact: readString(json['exact'], fallback: '0.00 ج.م'),
      label: readString(json['label'], fallback: 'الكاش الفعلي بالخزينة'),
    );
  }
}

class SalesTodayData {
  final double raw;
  final String formatted;
  final String exact;
  final int invoicesCount;
  final String comparedToYesterday;

  const SalesTodayData({
    required this.raw,
    required this.formatted,
    required this.exact,
    required this.invoicesCount,
    required this.comparedToYesterday,
  });

  factory SalesTodayData.fromJson(Map<String, dynamic> json) {
    return SalesTodayData(
      raw: readDouble(json['raw']),
      formatted: readString(json['formatted'], fallback: '0 ج.م'),
      exact: readString(json['exact'], fallback: '0.00 ج.م'),
      invoicesCount: readInt(json['invoices_count']),
      comparedToYesterday: readString(
        json['compared_to_yesterday'],
        fallback: '+0.0%',
      ),
    );
  }
}

class ActiveShiftData {
  final bool isOpen;
  final String cashierName;
  final String openedAt;
  final String durationHours;

  const ActiveShiftData({
    required this.isOpen,
    required this.cashierName,
    required this.openedAt,
    required this.durationHours,
  });

  factory ActiveShiftData.fromJson(Map<String, dynamic> json) {
    return ActiveShiftData(
      isOpen: readBool(json['is_open']),
      cashierName: readString(json['cashier_name'], fallback: 'لا توجد وردية'),
      openedAt: readString(json['opened_at'], fallback: '--:--'),
      durationHours: readString(json['duration_hours'], fallback: '--'),
    );
  }
}

class WorkshopAttendanceData {
  final int presentCount;
  final int totalEmployees;
  final String attendanceRate;

  const WorkshopAttendanceData({
    required this.presentCount,
    required this.totalEmployees,
    required this.attendanceRate,
  });

  factory WorkshopAttendanceData.fromJson(Map<String, dynamic> json) {
    return WorkshopAttendanceData(
      presentCount: readInt(json['present_count']),
      totalEmployees: readInt(json['total_employees']),
      attendanceRate: readString(json['attendance_rate'], fallback: '0%'),
    );
  }
}

class CriticalAlertsData {
  final int lowStockCount;
  final int returnsTodayCount;
  final int unsettledCreditCount;

  const CriticalAlertsData({
    required this.lowStockCount,
    required this.returnsTodayCount,
    required this.unsettledCreditCount,
  });

  factory CriticalAlertsData.fromJson(Map<String, dynamic> json) {
    return CriticalAlertsData(
      lowStockCount: readInt(json['low_stock_count']),
      returnsTodayCount: readInt(json['returns_today_count']),
      unsettledCreditCount: readInt(json['unsettled_credit_count']),
    );
  }
}
