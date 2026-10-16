import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/network/api_service.dart';
import 'package:alhusseini_app/core/storage/auth_storage.dart';
import 'package:alhusseini_app/data/repositories/sales_repository.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('parses paged invoices and sends pagination query parameters', () async {
    SharedPreferences.setMockInitialValues({});
    final preferences = await SharedPreferences.getInstance();
    final storage = AuthStorage(preferences);

    late http.Request request;
    final service = ApiService(
      storage,
      client: MockClient((incomingRequest) async {
        request = incomingRequest;

        return http.Response.bytes(
          utf8.encode(
            jsonEncode({
              'data': [
                {
                  'id': 12,
                  'invoice_number': 'INV-12',
                  'customer_name': 'أحمد',
                  'customer_phone': '01000000000',
                  'final_amount': {'raw': '1250.50'},
                },
              ],
              'pagination': {
                'current_page': 2,
                'last_page': 4,
                'per_page': 20,
                'total': 61,
                'has_more': true,
              },
            }),
          ),
          200,
        );
      }),
    );
    addTearDown(service.dispose);

    final repository = SalesRepository(service);
    final result = await repository.getRecentInvoices(
      page: 2,
      perPage: 20,
      branchId: 7,
    );

    expect(result.items, hasLength(1));
    expect(result.items.single.id, 12);
    expect(result.meta.currentPage, 2);
    expect(result.meta.lastPage, 4);
    expect(result.meta.perPage, 20);
    expect(result.meta.total, 61);
    expect(result.hasMore, isTrue);
    expect(request.url.queryParameters['page'], '2');
    expect(request.url.queryParameters['per_page'], '20');
    expect(request.url.queryParameters['branch_id'], '7');
  });

  test('parses paged returns and uses the requested page when needed',
      () async {
    SharedPreferences.setMockInitialValues({});
    final preferences = await SharedPreferences.getInstance();
    final storage = AuthStorage(preferences);

    late http.Request request;
    final service = ApiService(
      storage,
      client: MockClient((incomingRequest) async {
        request = incomingRequest;

        return http.Response.bytes(
          utf8.encode(
            jsonEncode({
              'data': [
                {
                  'id': 8,
                  'invoice_number': 'INV-8',
                  'status': 'refunded',
                  'refunded_amount': {'raw': '250.5'},
                  'original_amount': {'raw': '1000'},
                },
              ],
              'pagination': {
                'current_page': 3,
                'last_page': 3,
                'per_page': 10,
                'total': 21,
                'has_more': false,
              },
            }),
          ),
          200,
        );
      }),
    );
    addTearDown(service.dispose);

    final repository = SalesRepository(service);
    final result = await repository.getRecentReturns(
      page: 3,
      perPage: 10,
      branchId: 4,
    );

    expect(result.items.single.id, 8);
    expect(result.meta.currentPage, 3);
    expect(result.hasMore, isFalse);
    expect(request.url.queryParameters['page'], '3');
    expect(request.url.queryParameters['per_page'], '10');
    expect(request.url.queryParameters['branch_id'], '4');
  });

  test('parses invoice detail from the data map', () async {
    SharedPreferences.setMockInitialValues({});
    final preferences = await SharedPreferences.getInstance();
    final storage = AuthStorage(preferences);

    final service = ApiService(
      storage,
      client: MockClient((_) async {
        return http.Response.bytes(
          utf8.encode(
            jsonEncode({
              'data': {
                'id': 12,
                'invoice_number': 'INV-12',
              },
            }),
          ),
          200,
        );
      }),
    );
    addTearDown(service.dispose);

    final repository = SalesRepository(service);
    final result = await repository.getInvoiceDetail(12);

    expect(result.id, 12);
    expect(result.invoiceNumber, 'INV-12');
  });
}
