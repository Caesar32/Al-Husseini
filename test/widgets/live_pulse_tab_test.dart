import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/network/api_failure.dart';
import 'package:alhusseini_app/core/network/api_service.dart';
import 'package:alhusseini_app/core/storage/auth_storage.dart';
import 'package:alhusseini_app/data/models/live_pulse_data.dart';
import 'package:alhusseini_app/data/models/period_metrics.dart';
import 'package:alhusseini_app/data/repositories/dashboard_repository.dart';
import 'package:alhusseini_app/presentation/screens/tabs/live_pulse_tab.dart';
import 'package:alhusseini_app/presentation/widgets/state/stale_data_banner.dart';
import 'package:alhusseini_app/providers/dashboard_provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    GoogleFonts.config.allowRuntimeFetching = false;
  });

  testWidgets('يعرض الكاش والوردية الحالية', (tester) async {
    final harness = await _createHarness();
    addTearDown(harness.dispose);

    await tester.pumpWidget(_buildApp(harness.provider));
    await tester.pump();
    await tester.pump();

    expect(find.text('34,800.00'), findsOneWidget);
    expect(find.text('محمود'), findsOneWidget);
  });

  testWidgets('يعرض رسالة الخطأ عند فشل التحميل الأمامي', (tester) async {
    final harness = await _createHarness(
      liveFailure: const NetworkFailure(
        message: 'تعذر تحميل النبض',
      ),
    );
    addTearDown(harness.dispose);

    await tester.pumpWidget(_buildApp(harness.provider));
    await tester.pump();
    await tester.pump();

    expect(find.text('تعذر تحميل النبض'), findsOneWidget);
  });

  testWidgets('يعرض تنبيه البيانات القديمة بعد فشل التحديث الخلفي', (tester) async {
    var now = DateTime(2026, 10, 17, 10);
    final harness = await _createHarness(clock: () => now);
    addTearDown(harness.dispose);

    await tester.pumpWidget(_buildApp(harness.provider));
    await tester.pump();
    await tester.pump();

    now = now.add(const Duration(seconds: 31));
    harness.repository.liveFailure = const NetworkFailure(
      message: 'فشل التحديث الخلفي',
    );
    await harness.provider.fetchLivePulse(isBackground: true);
    await tester.pump();

    expect(find.byType(StaleDataBanner), findsOneWidget);
    expect(find.text('فشل التحديث الخلفي'), findsOneWidget);
  });
}

Widget _buildApp(DashboardProvider provider) {
  return MaterialApp(
    home: Directionality(
      textDirection: TextDirection.rtl,
      child: ChangeNotifierProvider.value(
        value: provider,
        child: const LivePulseTab(),
      ),
    ),
  );
}

Future<_Harness> _createHarness({
  DateTime Function()? clock,
  ApiFailure? liveFailure,
}) async {
  SharedPreferences.setMockInitialValues({});
  final preferences = await SharedPreferences.getInstance();
  final apiService = ApiService(
    AuthStorage(preferences),
    client: MockClient((_) async => http.Response('', 200)),
  );
  final repository = _FakeDashboardRepository(
    apiService,
    liveFailure: liveFailure,
  );
  final provider = DashboardProvider(
    repository,
    clock: clock,
  );

  return _Harness(apiService, repository, provider);
}

class _Harness {
  const _Harness(this.apiService, this.repository, this.provider);

  final ApiService apiService;
  final _FakeDashboardRepository repository;
  final DashboardProvider provider;

  Future<void> dispose() async {
    provider.dispose();
    await apiService.dispose();
  }
}

class _FakeDashboardRepository extends DashboardRepository {
  _FakeDashboardRepository(
    super.apiService, {
    this.liveFailure,
  });

  final LivePulseData liveData = LivePulseData.fromJson({
    'safe_cash_now': {
      'raw': 34800,
      'formatted': '34.8 ألف ج.م',
      'exact': '34,800.00 ج.م',
      'label': 'الكاش الفعلي بالخزينة',
    },
    'sales_today': {
      'raw': 72500,
      'formatted': '72.5 ألف ج.م',
      'exact': '72,500.00 ج.م',
      'invoices_count': 18,
      'compared_to_yesterday': '+12.5%',
    },
    'active_shift': {
      'is_open': true,
      'cashier_name': 'محمود',
      'opened_at': '08:00',
      'duration_hours': '2',
    },
    'workshop_attendance': {
      'present_count': 8,
      'total_employees': 10,
      'attendance_rate': '80%',
    },
    'critical_alerts': {
      'low_stock_count': 2,
      'returns_today_count': 1,
      'unsettled_credit_count': 3,
    },
  });

  final PeriodMetrics metricsData = PeriodMetrics.fromJson({
    'period': 'today',
    'period_label': 'اليوم',
    'revenue': {
      'raw': 72500,
      'compact': '72.5 ألف ج.م',
      'exact': '72,500.00 ج.م',
    },
    'total_sales_count': 18,
    'credit_collected': {
      'raw': 12500,
      'compact': '12.5 ألف ج.م',
      'exact': '12,500.00 ج.م',
    },
  });

  ApiFailure? liveFailure;

  @override
  Future<LivePulseData> getLivePulse({int? branchId}) async {
    final failure = liveFailure;
    if (failure != null) {
      liveFailure = null;
      throw failure;
    }

    return liveData;
  }

  @override
  Future<PeriodMetrics> getPeriodMetrics(
    String period, {
    int? branchId,
  }) async {
    return metricsData;
  }
}
