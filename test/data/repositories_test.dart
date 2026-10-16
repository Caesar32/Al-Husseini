import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/constants/api_constants.dart';
import 'package:alhusseini_app/core/network/api_service.dart';
import 'package:alhusseini_app/core/storage/auth_storage.dart';
import 'package:alhusseini_app/data/repositories/cash_drawer_repository.dart';
import 'package:alhusseini_app/data/repositories/credit_repository.dart';
import 'package:alhusseini_app/data/repositories/inventory_repository.dart';
import 'package:alhusseini_app/data/repositories/staff_repository.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('inventory repository parses pagination and battery filtering',
      () async {
    late http.Request request;
    final service = await _createService(
      MockClient((incomingRequest) async {
        request = incomingRequest;

        return _jsonResponse({
          'data': [
            {
              'id': 12,
              'name': 'بطارية',
              'sku': 'BAT-12',
              'current_stock': 2,
              'reorder_threshold': 5,
              'depletion_ratio': 0.4,
              'status': 'critical_low',
              'status_label': 'منخفض',
              'cost_price': {'raw': '100'},
              'retail_price': {'raw': '150'},
              'is_battery': true,
              'category_name': 'بطاريات',
            },
          ],
          'pagination': {
            'current_page': 2,
            'last_page': 3,
            'per_page': 20,
            'total': 41,
            'has_more': true,
          },
        });
      }),
    );
    addTearDown(service.dispose);

    final result = await InventoryRepository(service).getAlerts(
      page: 2,
      perPage: 20,
      batteriesOnly: true,
    );

    expect(result.items.single.id, 12);
    expect(result.meta.currentPage, 2);
    expect(result.hasMore, isTrue);
    expect(request.url.path, endsWith(ApiConstants.inventoryAlerts));
    expect(request.url.queryParameters['page'], '2');
    expect(request.url.queryParameters['per_page'], '20');
    expect(request.url.queryParameters['batteries_only'], '1');
    expect(request.url.queryParameters.containsKey('branch_id'), isFalse);
  });

  test('cash drawer repository parses the data map', () async {
    final service = await _createService(
      MockClient(
        (_) async => _jsonResponse({
          'data': {
            'cashier_name': 'أحمد',
            'shift_status': 'open',
            'opening_balance': 100,
            'cash_sales': 200,
            'card_sales': 50,
            'bank_transfer_sales': 25,
            'credit_collections_cash': 10,
            'expenses_paid': 5,
            'expected_drawer_cash': 305,
            'audit_notes': '',
            'formatted': {},
          },
        }),
      ),
    );
    addTearDown(service.dispose);

    final result = await CashDrawerRepository(service).getCurrentShift(
      branchId: 7,
    );

    expect(result.cashierName, 'أحمد');
  });

  test('staff repository reads summary and data from the root body',
      () async {
    final service = await _createService(
      MockClient(
        (_) async => _jsonResponse({
          'summary': {
            'total_employees': 3,
            'present_count': 2,
            'late_count': 1,
            'absent_count': 1,
            'attendance_rate': '66%',
          },
          'data': [
            {
              'employee_id': 1,
              'employee_code': 'EMP-1',
              'name': 'أحمد',
              'role': 'فني',
              'status': 'present',
              'status_label': 'حاضر',
              'late_minutes': 0,
            },
          ],
        }),
      ),
    );
    addTearDown(service.dispose);

    final result = await StaffRepository(service).getTodayAttendance(
      branchId: 3,
    );

    expect(result.totalEmployees, 3);
    expect(result.staffList.single.employeeId, 1);
  });

  test('credit repository parses the data map without a branch parameter',
      () async {
    late http.Request request;
    final service = await _createService(
      MockClient((incomingRequest) async {
        request = incomingRequest;

        return _jsonResponse({
          'data': {
            'total_outstanding': {
              'raw': 1000,
              'compact': '1000',
              'exact': '1000',
            },
            'collected_today': {
              'raw': 100,
              'compact': '100',
              'exact': '100',
            },
            'debtors_count': 2,
            'top_debtors': [],
          },
        });
      }),
    );
    addTearDown(service.dispose);

    final result = await CreditRepository(service).getCreditOverview();

    expect(result.totalOutstanding.raw, 1000);
    expect(result.debtorsCount, 2);
    expect(request.url.queryParameters, isEmpty);
  });
}

Future<ApiService> _createService(MockClient client) async {
  SharedPreferences.setMockInitialValues({});
  final preferences = await SharedPreferences.getInstance();

  return ApiService(
    AuthStorage(preferences),
    client: client,
  );
}

http.Response _jsonResponse(Map<String, dynamic> body) {
  return http.Response.bytes(
    utf8.encode(jsonEncode(body)),
    200,
  );
}
