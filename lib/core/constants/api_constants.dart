import 'package:flutter/foundation.dart';

class ApiConstants {
  ApiConstants._();

  // Base URL (Can be overridden dynamically or via environment)
  static String get defaultBaseUrl {
    if (kIsWeb) {
      return 'http://127.0.0.1:8000/api';
    }
    if (defaultTargetPlatform == TargetPlatform.android) {
      return 'http://10.0.2.2:8000/api';
    }
    return 'http://127.0.0.1:8000/api';
  }
  static const String liveProductionUrl = 'https://alhusseini-auto.com/api';

  // API Version Prefix
  static const String v1OwnerPrefix = '/v1/owner';

  // Authentication Endpoints
  static const String login = '$v1OwnerPrefix/auth/login';
  static const String logout = '$v1OwnerPrefix/auth/logout';
  static const String me = '$v1OwnerPrefix/auth/me';
  static const String deviceToken = '$v1OwnerPrefix/auth/device-token';

  // Executive Dashboard Endpoints
  static const String livePulse = '$v1OwnerPrefix/dashboard/live';
  static const String periodMetrics = '$v1OwnerPrefix/dashboard/periods';

  // Sales Feed & Invoice Detail
  static const String recentInvoices = '$v1OwnerPrefix/sales/recent-invoices';
  static const String invoiceDetail = '$v1OwnerPrefix/sales/invoices'; // + /{id}
  static const String recentReturns = '$v1OwnerPrefix/sales/returns';

  // Cash Drawer & Shift Surveillance
  static const String currentShift = '$v1OwnerPrefix/cash-drawer/current-shift';

  // Operational Health: Low Stock
  static const String inventoryAlerts = '$v1OwnerPrefix/inventory/alerts';

  // Staff & Workshop Attendance
  static const String todayAttendance = '$v1OwnerPrefix/staff/today-attendance';

  // Receivables & Customer Credit (الآجل)
  static const String creditOverview = '$v1OwnerPrefix/credit/overview';

  // Polling Intervals
  static const Duration livePulsePollingInterval = Duration(seconds: 10);
  static const Duration connectionTimeout = Duration(seconds: 15);
}
