import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/network/api_failure.dart';
import 'package:alhusseini_app/core/network/api_service.dart';
import 'package:alhusseini_app/core/storage/auth_storage.dart';
import 'package:alhusseini_app/data/models/credit_overview.dart';
import 'package:alhusseini_app/data/models/period_metrics.dart';
import 'package:alhusseini_app/data/repositories/credit_repository.dart';
import 'package:alhusseini_app/providers/async_state.dart';
import 'package:alhusseini_app/providers/credit_provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late ApiService apiService;
  late _FakeCreditRepository repository;

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    final preferences = await SharedPreferences.getInstance();

    apiService = ApiService(
      AuthStorage(preferences),
      client: MockClient((_) async => http.Response('', 200)),
    );
    repository = _FakeCreditRepository(apiService);
    addTearDown(apiService.dispose);
  });

  test('success exposes the credit overview', () async {
    final provider = CreditProvider(repository);
    addTearDown(provider.dispose);

    await provider.fetchCreditOverview();

    expect(provider.overviewData, same(repository.value));
    expect(provider.state, isA<AsyncData<CreditOverviewData>>());
    expect(provider.isLoading, isFalse);
  });

  test('failure exposes the API failure message', () async {
    final provider = CreditProvider(repository);
    addTearDown(provider.dispose);

    repository.failure = const NetworkFailure(message: 'فشل التحميل');

    await provider.fetchCreditOverview();

    expect(provider.error, 'فشل التحميل');
    expect(provider.state, isA<AsyncFailure<CreditOverviewData>>());
  });

  test('previous data is kept while reloading', () async {
    final provider = CreditProvider(repository);
    addTearDown(provider.dispose);

    await provider.fetchCreditOverview();

    final completer = Completer<CreditOverviewData>();
    repository.completer = completer;

    final reload = provider.fetchCreditOverview();

    expect(provider.isLoading, isTrue);
    expect(provider.overviewData, same(repository.value));

    completer.complete(repository.value);
    await reload;

    expect(provider.overviewData, same(repository.value));
  });
}

class _FakeCreditRepository extends CreditRepository {
  _FakeCreditRepository(super.api);

  final CreditOverviewData value = CreditOverviewData(
    totalOutstanding: const MoneyValue(
      raw: 1000,
      compact: '1000',
      exact: '1000',
    ),
    collectedToday: const MoneyValue(
      raw: 100,
      compact: '100',
      exact: '100',
    ),
    debtorsCount: 2,
    topDebtors: const <DebtorItem>[],
  );

  ApiFailure? failure;
  Completer<CreditOverviewData>? completer;

  @override
  Future<CreditOverviewData> getCreditOverview() async {
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
