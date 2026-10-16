import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/network/api_failure.dart';
import 'package:alhusseini_app/core/network/api_service.dart';
import 'package:alhusseini_app/core/storage/auth_storage.dart';
import 'package:alhusseini_app/data/models/cash_drawer_shift.dart';
import 'package:alhusseini_app/data/repositories/cash_drawer_repository.dart';
import 'package:alhusseini_app/providers/async_state.dart';
import 'package:alhusseini_app/providers/cash_drawer_provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late ApiService apiService;
  late _FakeCashDrawerRepository repository;

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    final preferences = await SharedPreferences.getInstance();

    apiService = ApiService(
      AuthStorage(preferences),
      client: MockClient((_) async => http.Response('', 200)),
    );
    repository = _FakeCashDrawerRepository(apiService);
    addTearDown(apiService.dispose);
  });

  test('success exposes the current shift', () async {
    final provider = CashDrawerProvider(repository);
    addTearDown(provider.dispose);

    await provider.fetchCurrentShift();

    expect(provider.currentShift, same(repository.value));
    expect(provider.state, isA<AsyncData<CashDrawerShift>>());
    expect(provider.isLoading, isFalse);
  });

  test('failure exposes the API failure message', () async {
    final provider = CashDrawerProvider(repository);
    addTearDown(provider.dispose);

    repository.failure = const NetworkFailure(message: 'فشل التحميل');

    await provider.fetchCurrentShift();

    expect(provider.error, 'فشل التحميل');
    expect(provider.state, isA<AsyncFailure<CashDrawerShift>>());
  });

  test('previous data is kept while reloading', () async {
    final provider = CashDrawerProvider(repository);
    addTearDown(provider.dispose);

    await provider.fetchCurrentShift();

    final completer = Completer<CashDrawerShift>();
    repository.completer = completer;

    final reload = provider.fetchCurrentShift();

    expect(provider.isLoading, isTrue);
    expect(provider.currentShift, same(repository.value));

    completer.complete(repository.value);
    await reload;

    expect(provider.currentShift, same(repository.value));
  });
}

class _FakeCashDrawerRepository extends CashDrawerRepository {
  _FakeCashDrawerRepository(super.api);

  final CashDrawerShift value = const CashDrawerShift(
    cashierName: 'أحمد',
    shiftStatus: 'open',
    openingBalance: 100,
    cashSales: 200,
    cardSales: 50,
    bankTransferSales: 25,
    creditCollectionsCash: 10,
    expensesPaid: 5,
    expectedDrawerCash: 305,
    auditNotes: '',
    formatted: <String, String>{},
  );

  ApiFailure? failure;
  Completer<CashDrawerShift>? completer;

  @override
  Future<CashDrawerShift> getCurrentShift({int? branchId}) async {
    final pending = completer;
    if (pending != null) {
      completer = null;
      return pending.future;
    }

    final currentFailure = failure;
    if (currentFailure != null) {
      throw currentFailure;
    }

    return value;
  }
}
