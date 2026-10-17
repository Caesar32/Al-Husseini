import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/network/api_service.dart';
import 'package:alhusseini_app/core/storage/auth_storage.dart';
import 'package:alhusseini_app/core/storage/secure_key_value_store.dart';
import 'package:alhusseini_app/data/repositories/auth_repository.dart';
import 'package:alhusseini_app/data/repositories/cash_drawer_repository.dart';
import 'package:alhusseini_app/data/repositories/credit_repository.dart';
import 'package:alhusseini_app/data/repositories/dashboard_repository.dart';
import 'package:alhusseini_app/data/repositories/inventory_repository.dart';
import 'package:alhusseini_app/data/repositories/sales_repository.dart';
import 'package:alhusseini_app/data/repositories/staff_repository.dart';
import 'package:alhusseini_app/main.dart';
import 'package:alhusseini_app/presentation/screens/login_screen.dart';
import 'package:alhusseini_app/presentation/screens/main_surveillance_layout.dart';
import 'package:alhusseini_app/providers/auth_provider.dart';
import 'package:alhusseini_app/providers/cash_drawer_provider.dart';
import 'package:alhusseini_app/providers/credit_provider.dart';
import 'package:alhusseini_app/providers/dashboard_provider.dart';
import 'package:alhusseini_app/providers/inventory_provider.dart';
import 'package:alhusseini_app/providers/sales_provider.dart';
import 'package:alhusseini_app/providers/staff_provider.dart';

late AuthStorage _storage;
late ApiService _apiService;

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    GoogleFonts.config.allowRuntimeFetching = false;
  });

  testWidgets('يعرض شاشة تسجيل الدخول عند غياب الرمز', (tester) async {
    await tester.pumpWidget(
      await buildApp(
        client: _mockClient(),
      ),
    );

    await _pumpPastSplash(tester);

    expect(find.byType(LoginScreen), findsOneWidget);

    await _cleanup(tester);
  });

  testWidgets('يعرض لوحة المراقبة عند نجاح التحقق من الجلسة', (tester) async {
    await tester.pumpWidget(
      await buildApp(
        token: 'valid-token',
        client: _mockClient(),
      ),
    );

    await _pumpPastSplash(tester);

    expect(find.byType(MainSurveillanceLayout), findsOneWidget);

    await _cleanup(tester);
  });

  testWidgets('يعرض تسجيل الدخول ويمسح الرمز عند انتهاء الجلسة', (tester) async {
    await tester.pumpWidget(
      await buildApp(
        token: 'expired-token',
        client: _mockClient(meStatus: 401),
      ),
    );

    await _pumpPastSplash(tester);

    expect(find.byType(LoginScreen), findsOneWidget);
    expect(_storage.getToken(), isNull);

    await _cleanup(tester);
  });
}

Future<Widget> buildApp({
  String? token,
  required MockClient client,
}) async {
  SharedPreferences.setMockInitialValues({});
  final prefs = await SharedPreferences.getInstance();

  _storage = AuthStorage(
    prefs,
    secureStore: InMemorySecureKeyValueStore(),
    initialToken: token,
  );
  _apiService = ApiService(_storage, client: client);

  final authRepository = AuthRepository(_apiService, _storage);
  final dashboardRepository = DashboardRepository(_apiService);
  final salesRepository = SalesRepository(_apiService);
  final cashDrawerRepository = CashDrawerRepository(_apiService);
  final inventoryRepository = InventoryRepository(_apiService);
  final staffRepository = StaffRepository(_apiService);
  final creditRepository = CreditRepository(_apiService);

  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => AuthProvider(authRepository)),
      ChangeNotifierProvider(
        create: (_) => DashboardProvider(
          dashboardRepository,
          storage: _storage,
        ),
      ),
      ChangeNotifierProvider(create: (_) => SalesProvider(salesRepository)),
      ChangeNotifierProvider(
        create: (_) => CashDrawerProvider(cashDrawerRepository),
      ),
      ChangeNotifierProvider(
        create: (_) => InventoryProvider(inventoryRepository),
      ),
      ChangeNotifierProvider(create: (_) => StaffProvider(staffRepository)),
      ChangeNotifierProvider(create: (_) => CreditProvider(creditRepository)),
    ],
    child: const AlHusseiniExecutiveApp(),
  );
}

MockClient _mockClient({int meStatus = 200}) {
  return MockClient((request) async {
    final path = request.url.path;

    if (path == '/api/v1/owner/auth/me') {
      final body = meStatus == 200
          ? <String, dynamic>{
              'status': 'success',
              'data': {
                'id': 1,
                'name': 'مالك المنشأة',
                'email': 'owner@example.com',
                'phone': '01000000000',
                'role': 'owner',
              },
            }
          : <String, dynamic>{
              'message': 'انتهت جلسة الدخول',
            };

      return _jsonResponse(body, meStatus);
    }

    if (path == '/api/v1/owner/dashboard/live') {
      return _jsonResponse({
        'status': 'success',
        'data': <String, dynamic>{},
      }, 200);
    }

    if (path == '/api/v1/owner/dashboard/periods') {
      return _jsonResponse({
        'data': <String, dynamic>{},
      }, 200);
    }

    return _jsonResponse({
      'data': <dynamic>[],
    }, 200);
  });
}

http.Response _jsonResponse(Map<String, dynamic> body, int statusCode) {
  return http.Response.bytes(
    utf8.encode(jsonEncode(body)),
    statusCode,
    headers: {'content-type': 'application/json'},
  );
}

Future<void> _pumpPastSplash(WidgetTester tester) async {
  await tester.pump();
  await tester.pump(const Duration(seconds: 3));
  await tester.pump(const Duration(seconds: 3));
  await tester.pump(const Duration(seconds: 3));
  await tester.pump();
}

Future<void> _cleanup(WidgetTester tester) async {
  final context = tester.element(find.byType(AlHusseiniExecutiveApp));
  Provider.of<DashboardProvider>(
    context,
    listen: false,
  ).stopLivePolling();

  await tester.pump();
  await tester.pumpWidget(const SizedBox());
  await tester.pump();
  await _apiService.dispose();
}
