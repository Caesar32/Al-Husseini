import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/network/api_failure.dart';
import 'package:alhusseini_app/core/network/api_service.dart';
import 'package:alhusseini_app/core/network/envelope.dart';
import 'package:alhusseini_app/core/storage/auth_storage.dart';
import 'package:alhusseini_app/data/models/cash_drawer_shift.dart';
import 'package:alhusseini_app/data/models/credit_overview.dart';
import 'package:alhusseini_app/data/models/inventory_alert.dart';
import 'package:alhusseini_app/data/models/paged.dart';
import 'package:alhusseini_app/data/models/staff_attendance.dart';
import 'package:alhusseini_app/data/repositories/cash_drawer_repository.dart';
import 'package:alhusseini_app/data/repositories/credit_repository.dart';
import 'package:alhusseini_app/data/repositories/dashboard_repository.dart';
import 'package:alhusseini_app/data/repositories/inventory_repository.dart';
import 'package:alhusseini_app/data/repositories/staff_repository.dart';
import 'package:alhusseini_app/providers/cash_drawer_provider.dart';
import 'package:alhusseini_app/providers/credit_provider.dart';
import 'package:alhusseini_app/providers/dashboard_provider.dart';
import 'package:alhusseini_app/providers/inventory_provider.dart';
import 'package:alhusseini_app/providers/staff_provider.dart';
import 'package:alhusseini_app/presentation/screens/tabs/cash_drawer_tab.dart';
import 'package:alhusseini_app/presentation/screens/tabs/credit_receivables_tab.dart';
import 'package:alhusseini_app/presentation/screens/tabs/inventory_alerts_tab.dart';
import 'package:alhusseini_app/presentation/screens/tabs/staff_attendance_tab.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    GoogleFonts.config.allowRuntimeFetching = false;
  });

  late ApiService apiService;

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    final preferences = await SharedPreferences.getInstance();
    apiService = ApiService(
      AuthStorage(preferences),
      client: MockClient((_) async => http.Response('', 200)),
    );
    addTearDown(apiService.dispose);
  });

  testWidgets('يعرض تبويب المخزون بيانات المنتج ويحمّل المزيد', (
    tester,
  ) async {
    final repository = _FakeInventoryRepository(apiService);
    final provider = InventoryProvider(repository);
    addTearDown(provider.dispose);

    final firstPage = List<InventoryAlertItem>.generate(
      20,
      (index) => _inventoryItem(index + 1),
    );
    repository.pages[1] = _page(firstPage, page: 1, hasMore: true);
    repository.pages[2] = _page(
      [_inventoryItem(21)],
      page: 2,
      hasMore: false,
    );

    await tester.pumpWidget(
      _wrap(
        ChangeNotifierProvider.value(
          value: provider,
          child: const InventoryAlertsTab(),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    expect(find.text('منتج المخزون 1'), findsOneWidget);

    await tester.drag(
      find.byType(ListView),
      const Offset(0, -3000),
    );
    await tester.pump();
    await tester.pump();

    expect(repository.requestedPages, contains(2));
    expect(find.text('منتج المخزون 21'), findsOneWidget);
  });

  testWidgets('يعرض تبويب الائتمان اسم المدين', (tester) async {
    final repository = _FakeCreditRepository(apiService);
    final provider = CreditProvider(repository);
    addTearDown(provider.dispose);

    await tester.pumpWidget(
      _wrap(
        ChangeNotifierProvider.value(
          value: provider,
          child: const CreditReceivablesTab(),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    expect(find.text('عميل اختبار'), findsOneWidget);
  });

  testWidgets('يعرض تبويب الموظفين اسم الموظف', (tester) async {
    final repository = _FakeStaffRepository(apiService);
    final provider = StaffProvider(repository);
    final dashboard = DashboardProvider(_FakeDashboardRepository(apiService));
    addTearDown(provider.dispose);
    addTearDown(dashboard.dispose);

    await tester.pumpWidget(
      _wrap(
        MultiProvider(
          providers: [
            ChangeNotifierProvider.value(value: dashboard),
            ChangeNotifierProvider.value(value: provider),
          ],
          child: const StaffAttendanceTab(),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    expect(find.text('موظف اختبار'), findsOneWidget);
  });

  testWidgets('يعرض تبويب درج الكاش اسم الكاشير', (tester) async {
    final repository = _FakeCashDrawerRepository(apiService);
    final provider = CashDrawerProvider(repository);
    final dashboard = DashboardProvider(_FakeDashboardRepository(apiService));
    addTearDown(provider.dispose);
    addTearDown(dashboard.dispose);

    await tester.pumpWidget(
      _wrap(
        MultiProvider(
          providers: [
            ChangeNotifierProvider.value(value: dashboard),
            ChangeNotifierProvider.value(value: provider),
          ],
          child: const CashDrawerTab(),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    expect(find.text('كاشير اختبار'), findsOneWidget);
  });

  testWidgets('يعرض تبويب الائتمان رسالة الخطأ وزر إعادة المحاولة', (
    tester,
  ) async {
    final repository = _FakeCreditRepository(apiService)
      ..failure = const NetworkFailure(message: 'فشل تحميل المستحقات');
    final provider = CreditProvider(repository);
    addTearDown(provider.dispose);

    await tester.pumpWidget(
      _wrap(
        ChangeNotifierProvider.value(
          value: provider,
          child: const CreditReceivablesTab(),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    expect(find.text('فشل تحميل المستحقات'), findsOneWidget);
    expect(find.byType(ElevatedButton), findsOneWidget);

    await tester.tap(find.byType(ElevatedButton));
    await tester.pump();
    expect(repository.calls, 2);
  });
}

Widget _wrap(Widget child) {
  return MaterialApp(
    home: Directionality(
      textDirection: TextDirection.rtl,
      child: child,
    ),
  );
}

class _FakeInventoryRepository extends InventoryRepository {
  _FakeInventoryRepository(super.api);

  final Map<int, Paged<InventoryAlertItem>> pages =
      <int, Paged<InventoryAlertItem>>{};
  final List<int> requestedPages = <int>[];

  @override
  Future<Paged<InventoryAlertItem>> getAlerts({
    int page = 1,
    int perPage = 20,
    bool batteriesOnly = false,
  }) async {
    requestedPages.add(page);
    return pages[page] ?? _page(const [], page: page, hasMore: false);
  }
}

class _FakeCreditRepository extends CreditRepository {
  _FakeCreditRepository(super.api);

  ApiFailure? failure;
  int calls = 0;

  @override
  Future<CreditOverviewData> getCreditOverview() async {
    calls++;
    final currentFailure = failure;
    if (currentFailure != null) {
      throw currentFailure;
    }

    return CreditOverviewData.fromJson({
      'total_outstanding': {
        'raw': 1000,
        'compact': '1000 ج.م',
        'exact': '1000 ج.م',
      },
      'collected_today': {
        'raw': 100,
        'compact': '100 ج.م',
        'exact': '100 ج.م',
      },
      'debtors_count': 1,
      'top_debtors': [
        {
          'id': 1,
          'name': 'عميل اختبار',
          'phone': '01000000000',
          'current_credit_balance': {
            'raw': 250,
            'exact': '250 ج.م',
          },
          'credit_limit': {
            'raw': 500,
            'formatted': '500 ج.م',
          },
          'utilization_rate': '50%',
        },
      ],
    });
  }
}

class _FakeStaffRepository extends StaffRepository {
  _FakeStaffRepository(super.api);

  @override
  Future<StaffAttendanceSummary> getTodayAttendance({int? branchId}) async {
    return StaffAttendanceSummary.fromJson({
      'summary': {
        'total_employees': 1,
        'present_count': 1,
        'late_count': 0,
        'absent_count': 0,
        'attendance_rate': '100%',
      },
      'data': [
        {
          'employee_id': 1,
          'employee_code': 'EMP-1',
          'name': 'موظف اختبار',
          'role': 'فني',
          'status': 'present',
          'status_label': 'حاضر',
          'check_in_time': '08:00',
          'late_minutes': 0,
        },
      ],
    });
  }
}

class _FakeCashDrawerRepository extends CashDrawerRepository {
  _FakeCashDrawerRepository(super.api);

  @override
  Future<CashDrawerShift> getCurrentShift({int? branchId}) async {
    return CashDrawerShift.fromJson({
      'cashier_name': 'كاشير اختبار',
      'shift_status': 'open',
      'opened_at': '08:00',
      'opening_balance': 100,
      'cash_sales': 200,
      'card_sales': 50,
      'bank_transfer_sales': 25,
      'credit_collections_cash': 10,
      'expenses_paid': 5,
      'expected_drawer_cash': 305,
      'audit_notes': 'مراجعة اختبار',
      'formatted': {
        'expected_drawer_cash': '305 ج.م',
        'cash_sales': '200 ج.م',
        'credit_collections_cash': '10 ج.م',
        'card_sales': '50 ج.م',
        'bank_transfer_sales': '25 ج.م',
        'expenses_paid': '5 ج.م',
      },
    });
  }
}

class _FakeDashboardRepository extends DashboardRepository {
  _FakeDashboardRepository(super.api);
}

InventoryAlertItem _inventoryItem(int id) {
  return InventoryAlertItem.fromJson({
    'id': id,
    'name': 'منتج المخزون $id',
    'sku': 'SKU-$id',
    'current_stock': 1,
    'reorder_threshold': 5,
    'depletion_ratio': 0.2,
    'status': 'critical_low',
    'status_label': 'منخفض',
    'cost_price': {'raw': 10},
    'retail_price': {'raw': 15},
    'is_battery': id.isEven,
    'category_name': 'بطاريات',
  });
}

Paged<InventoryAlertItem> _page(
  List<InventoryAlertItem> items, {
  required int page,
  required bool hasMore,
}) {
  return Paged(
    items,
    PaginationMeta(
      currentPage: page,
      lastPage: hasMore ? page + 1 : page,
      perPage: 20,
      total: items.length,
      hasMore: hasMore,
    ),
  );
}
