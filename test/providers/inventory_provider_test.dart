import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/network/api_failure.dart';
import 'package:alhusseini_app/core/network/api_service.dart';
import 'package:alhusseini_app/core/storage/auth_storage.dart';
import 'package:alhusseini_app/data/models/inventory_alert.dart';
import 'package:alhusseini_app/data/models/paged.dart';
import 'package:alhusseini_app/core/network/envelope.dart';
import 'package:alhusseini_app/data/repositories/inventory_repository.dart';
import 'package:alhusseini_app/providers/async_state.dart';
import 'package:alhusseini_app/providers/inventory_provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late ApiService apiService;
  late _FakeInventoryRepository repository;

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    final preferences = await SharedPreferences.getInstance();

    apiService = ApiService(
      AuthStorage(preferences),
      client: MockClient((_) async => http.Response('', 200)),
    );
    repository = _FakeInventoryRepository(apiService);
    addTearDown(apiService.dispose);
  });

  test('fetches inventory alerts successfully', () async {
    final provider = InventoryProvider(repository);
    addTearDown(provider.dispose);

    repository.pages[1] = _page([_item(1)], page: 1, hasMore: false);

    await provider.fetchAlerts();

    expect(provider.alerts.map((item) => item.id), [1]);
    expect(provider.state, isA<AsyncData<List<InventoryAlertItem>>>());
    expect(provider.isLoading, isFalse);
  });

  test('failure exposes the API failure message', () async {
    final provider = InventoryProvider(repository);
    addTearDown(provider.dispose);

    repository.failures[1] = const NetworkFailure(message: 'فشل التحميل');

    await provider.fetchAlerts();

    expect(provider.error, 'فشل التحميل');
    expect(provider.state, isA<AsyncFailure<List<InventoryAlertItem>>>());
  });

  test('previous data is kept while refreshing', () async {
    final provider = InventoryProvider(repository);
    addTearDown(provider.dispose);

    repository.pages[1] = _page([_item(1)], page: 1, hasMore: false);
    await provider.fetchAlerts();

    final completer = Completer<Paged<InventoryAlertItem>>();
    repository.completer = completer;

    final refresh = provider.fetchAlerts();

    expect(provider.state.isLoading, isTrue);
    expect(provider.alerts.map((item) => item.id), [1]);

    completer.complete(_page([_item(2)], page: 1, hasMore: false));
    await refresh;

    expect(provider.alerts.map((item) => item.id), [2]);
  });

  test('load more appends, deduplicates, and stops at the last page',
      () async {
    final provider = InventoryProvider(repository);
    addTearDown(provider.dispose);

    repository.pages[1] = _page(
      [_item(1), _item(2)],
      page: 1,
      hasMore: true,
    );
    repository.pages[2] = _page(
      [_item(2), _item(3)],
      page: 2,
      hasMore: false,
    );

    await provider.fetchAlerts();
    await provider.loadMoreAlerts();
    await provider.loadMoreAlerts();

    expect(provider.alerts.map((item) => item.id), [1, 2, 3]);
    expect(repository.requestedPages, [1, 2]);
    expect(provider.hasMoreAlerts, isFalse);
  });
}

class _FakeInventoryRepository extends InventoryRepository {
  _FakeInventoryRepository(super.api);

  final Map<int, Paged<InventoryAlertItem>> pages =
      <int, Paged<InventoryAlertItem>>{};
  final Map<int, ApiFailure> failures = <int, ApiFailure>{};
  final List<int> requestedPages = <int>[];
  Completer<Paged<InventoryAlertItem>>? completer;

  @override
  Future<Paged<InventoryAlertItem>> getAlerts({
    int page = 1,
    int perPage = 20,
    bool batteriesOnly = false,
  }) async {
    requestedPages.add(page);

    final pending = completer;
    if (pending != null) {
      completer = null;
      return pending.future;
    }

    final failure = failures.remove(page);
    if (failure != null) {
      throw failure;
    }

    return pages[page] ?? _page(const [], page: page, hasMore: false);
  }
}

InventoryAlertItem _item(int id) {
  return InventoryAlertItem(
    id: id,
    name: 'صنف $id',
    sku: 'SKU-$id',
    barcode: null,
    currentStock: 1,
    reorderThreshold: 5,
    depletionRatio: 0.2,
    stockStatus: 'critical_low',
    stockStatusLabel: 'منخفض',
    costPrice: 10,
    retailPrice: 15,
    isBattery: id.isEven,
    categoryName: 'بطاريات',
  );
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
