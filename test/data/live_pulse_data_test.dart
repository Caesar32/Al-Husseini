import 'package:flutter_test/flutter_test.dart';

import 'package:alhusseini_app/data/models/live_pulse_data.dart';

void main() {
  group('LivePulseData.fromJson', () {
    test('reads the complete server shape', () {
      final pulse = LivePulseData.fromJson({
        'safe_cash_now': {
          'raw': 2500.5,
          'formatted': '2.50K ج.م',
          'exact': '2,500.50 ج.م',
          'label': 'الكاش الفعلي في الخزينة الآن',
        },
        'sales_today': {
          'raw': 4800,
          'formatted': '4.80K ج.م',
          'exact': '4,800.00 ج.م',
          'invoices_count': 12,
          'compared_to_yesterday': '+15.5%',
        },
        'active_shift': {
          'is_open': true,
          'cashier_name': 'محمد',
          'opened_at': '08:30 ص',
          'duration_hours': 'ساعتان',
        },
        'workshop_attendance': {
          'present_count': 8,
          'total_employees': 10,
          'attendance_rate': '80%',
        },
        'critical_alerts': {
          'low_stock_count': 3,
          'returns_today_count': 1,
          'unsettled_credit_count': 5,
        },
      });

      expect(pulse.safeCashNow.raw, 2500.5);
      expect(pulse.safeCashNow.formatted, '2.50K ج.م');
      expect(pulse.salesToday.invoicesCount, 12);
      expect(pulse.salesToday.comparedToYesterday, '+15.5%');
      expect(pulse.activeShift.isOpen, isTrue);
      expect(pulse.activeShift.cashierName, 'محمد');
      expect(pulse.workshopAttendance.presentCount, 8);
      expect(pulse.criticalAlerts.unsettledCreditCount, 5);
    });

    test('uses defaults for missing and null nested values', () {
      final pulse = LivePulseData.fromJson({
        'safe_cash_now': null,
        'sales_today': null,
        'active_shift': null,
        'workshop_attendance': null,
        'critical_alerts': null,
      });

      expect(pulse.safeCashNow.raw, 0.0);
      expect(pulse.safeCashNow.formatted, '0 ج.م');
      expect(pulse.safeCashNow.exact, '0.00 ج.م');
      expect(pulse.safeCashNow.label, 'الكاش الفعلي بالخزينة');
      expect(pulse.salesToday.invoicesCount, 0);
      expect(pulse.salesToday.comparedToYesterday, '+0.0%');
      expect(pulse.activeShift.isOpen, isFalse);
      expect(pulse.activeShift.cashierName, 'لا توجد وردية');
      expect(pulse.activeShift.openedAt, '--:--');
      expect(pulse.activeShift.durationHours, '--');
      expect(pulse.workshopAttendance.attendanceRate, '0%');
      expect(pulse.criticalAlerts.lowStockCount, 0);
    });

    test('accepts numeric strings in nested values', () {
      final pulse = LivePulseData.fromJson({
        'safe_cash_now': {'raw': '100.25'},
        'sales_today': {'raw': '2000', 'invoices_count': '7'},
        'active_shift': {'is_open': '1'},
        'workshop_attendance': {
          'present_count': '3',
          'total_employees': '4',
        },
        'critical_alerts': {
          'low_stock_count': '2',
          'returns_today_count': '1',
          'unsettled_credit_count': '5',
        },
      });

      expect(pulse.safeCashNow.raw, 100.25);
      expect(pulse.salesToday.raw, 2000.0);
      expect(pulse.salesToday.invoicesCount, 7);
      expect(pulse.activeShift.isOpen, isTrue);
      expect(pulse.workshopAttendance.presentCount, 3);
      expect(pulse.criticalAlerts.unsettledCreditCount, 5);
    });
  });
}
