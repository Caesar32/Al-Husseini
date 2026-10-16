import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/network/api_failure.dart';
import 'package:alhusseini_app/core/network/api_service.dart';
import 'package:alhusseini_app/core/network/envelope.dart';
import 'package:alhusseini_app/core/storage/auth_storage.dart';
import 'package:alhusseini_app/data/models/paged.dart';
import 'package:alhusseini_app/data/models/recent_invoice.dart';
import 'package:alhusseini_app/data/models/return_summary.dart';
import 'package:alhusseini_app/data/repositories/sales_repository.dart';
import 'package:alhusseini_app/providers/async_state.dart';
import 'package:alhusseini_app/providers/sales_provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late ApiService apiService;
  late _FakeSalesRepository repository;

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    final preferences = await SharedPreferences.getInstance();

    apiService = ApiService(
      AuthStorage(preferences),
      client: MockClient(
        (_) async => http.Response('', 200),
      ),
    );
    repository = _FakeSalesRepository(apiService);
    addTearDown(apiService.dispose);
  });

  test('refresh replaces invoice items', () async {
    final provider = SalesProvider(repository);
    addTearDown(provider.dispose);

    repository.invoicePages[1] = _invoicePage(
      [_invoice(1)],
      page: 1,
      hasMore: true,
    );
    await provider.fetchRecentInvoices(branchId: 3);

    repository.invoicePages[1] = _invoicePage(
      [_invoice(2)],
      page: 1,
      hasMore: false,
    );
    await provider.fetchRecentInvoices(branchId: 4);

    expect(provider.recentInvoices.map((invoice) => invoice.id), [2]);
    expect(repository.invoiceBranches, [3, 4]);
  });

  test('loadMore appends items and deduplicates ids', () async {
    final provider = SalesProvider(repository);
    addTearDown(provider.dispose);

    repository.invoicePages[1] = _invoicePage(
      [_invoice(1), _invoice(2)],
      page: 1,
      hasMore: true,
    );
    repository.invoicePages[2] = _invoicePage(
      [_invoice(2), _invoice(3)],
      page: 2,
      hasMore: false,
    );

    await provider.fetchRecentInvoices(branchId: 5);
    await provider.loadMoreInvoices();
    await provider.loadMoreInvoices();

    expect(provider.recentInvoices.map((invoice) => invoice.id), [1, 2, 3]);
    expect(repository.invoicePagesRequested, [1, 2]);
    expect(provider.hasMoreInvoices, isFalse);
  });

  test('concurrent loadMore calls make one request', () async {
    final provider = SalesProvider(repository);
    addTearDown(provider.dispose);

    repository.invoicePages[1] = _invoicePage(
      [_invoice(1)],
      page: 1,
      hasMore: true,
    );
    await provider.fetchRecentInvoices();

    final completer = Completer<Paged<InvoiceSummary>>();
    repository.invoiceCompleter = completer;

    final first = provider.loadMoreInvoices();
    final second = provider.loadMoreInvoices();

    expect(repository.invoicePagesRequested, [1, 2]);
    expect(provider.isLoadingMoreInvoices, isTrue);

    completer.complete(
      _invoicePage([_invoice(2)], page: 2, hasMore: false),
    );
    await Future.wait([first, second]);

    expect(provider.recentInvoices.map((invoice) => invoice.id), [1, 2]);
    expect(repository.invoicePagesRequested, [1, 2]);
    expect(provider.isLoadingMoreInvoices, isFalse);
  });

  test('hasMore false prevents further invoice requests', () async {
    final provider = SalesProvider(repository);
    addTearDown(provider.dispose);

    repository.invoicePages[1] = _invoicePage(
      [_invoice(1)],
      page: 1,
      hasMore: false,
    );

    await provider.fetchRecentInvoices();
    await provider.loadMoreInvoices();

    expect(repository.invoicePagesRequested, [1]);
  });

  test('loadMore failure keeps items and exposes its message', () async {
    final provider = SalesProvider(repository);
    addTearDown(provider.dispose);

    repository.invoicePages[1] = _invoicePage(
      [_invoice(1)],
      page: 1,
      hasMore: true,
    );
    repository.invoiceFailures[2] = const NetworkFailure(
      message: 'فشل التحميل',
    );

    await provider.fetchRecentInvoices();
    await provider.loadMoreInvoices();

    expect(provider.recentInvoices.map((invoice) => invoice.id), [1]);
    expect(provider.loadMoreError, 'فشل التحميل');
    expect(provider.isLoadingMoreInvoices, isFalse);
  });

  test('search filtering remains available', () async {
    final provider = SalesProvider(repository);
    addTearDown(provider.dispose);

    repository.invoicePages[1] = _invoicePage(
      [
        _invoice(1, invoiceNumber: 'INV-100'),
        _invoice(2, invoiceNumber: 'INV-200'),
      ],
      page: 1,
      hasMore: false,
    );

    await provider.fetchRecentInvoices();
    provider.setSearchQuery('200');

    expect(provider.recentInvoices.map((invoice) => invoice.id), [2]);
  });

  test('returns paginate using ReturnSummary values', () async {
    final provider = SalesProvider(repository);
    addTearDown(provider.dispose);

    repository.returnPages[1] = _returnPage(
      [_returnSummary(1)],
      page: 1,
      hasMore: true,
    );
    repository.returnPages[2] = _returnPage(
      [_returnSummary(2)],
      page: 2,
      hasMore: false,
    );

    await provider.fetchRecentReturns(branchId: 8);
    await provider.loadMoreReturns();

    expect(provider.recentReturns.map((summary) => summary.id), [1, 2]);
    expect(repository.returnBranches, [8, 8]);
    expect(provider.hasMoreReturns, isFalse);
  });

  test('detail failure sets detailError', () async {
    final provider = SalesProvider(repository);
    addTearDown(provider.dispose);

    repository.detailFailure = const NetworkFailure(
      message: 'تعذر تحميل الفاتورة',
    );

    await provider.fetchInvoiceDetail(12);

    expect(provider.selectedInvoiceDetail, isNull);
    expect(provider.detailError, 'تعذر تحميل الفاتورة');
    expect(provider.detailState, isA<AsyncFailure<InvoiceDetail>>());
  });
}

class _FakeSalesRepository extends SalesRepository {
  _FakeSalesRepository(super.apiService);

  final Map<int, Paged<InvoiceSummary>> invoicePages =
      <int, Paged<InvoiceSummary>>{};
  final Map<int, Paged<ReturnSummary>> returnPages =
      <int, Paged<ReturnSummary>>{};
  final Map<int, ApiFailure> invoiceFailures = <int, ApiFailure>{};

  final List<int> invoicePagesRequested = <int>[];
  final List<int?> invoiceBranches = <int?>[];
  final List<int?> returnBranches = <int?>[];

  Completer<Paged<InvoiceSummary>>? invoiceCompleter;
  ApiFailure? detailFailure;

  @override
  Future<Paged<InvoiceSummary>> getRecentInvoices({
    int page = 1,
    int perPage = 20,
    int? branchId,
  }) async {
    invoicePagesRequested.add(page);
    invoiceBranches.add(branchId);

    final failure = invoiceFailures.remove(page);
    if (failure != null) {
      throw failure;
    }

    final completer = invoiceCompleter;
    if (completer != null) {
      invoiceCompleter = null;
      return completer.future;
    }

    return invoicePages[page] ??
        _invoicePage(const <InvoiceSummary>[], page: page, hasMore: false);
  }

  @override
  Future<InvoiceDetail> getInvoiceDetail(int id) async {
    final failure = detailFailure;
    if (failure != null) {
      throw failure;
    }

    return InvoiceDetail.fromJson({
      'id': id,
      'invoice_number': 'INV-$id',
    });
  }

  @override
  Future<Paged<ReturnSummary>> getRecentReturns({
    int page = 1,
    int perPage = 20,
    int? branchId,
  }) async {
    returnPagesRequested.add(page);
    returnBranches.add(branchId);

    return returnPages[page] ??
        _returnPage(const <ReturnSummary>[], page: page, hasMore: false);
  }

  final List<int> returnPagesRequested = <int>[];
}

InvoiceSummary _invoice(
  int id, {
  String? invoiceNumber,
}) {
  return InvoiceSummary.fromJson({
    'id': id,
    'invoice_number': invoiceNumber ?? 'INV-$id',
    'customer_name': 'عميل $id',
    'customer_phone': '010000000$id',
    'final_amount': {'raw': id},
  });
}

ReturnSummary _returnSummary(int id) {
  return ReturnSummary.fromJson({
    'id': id,
    'invoice_number': 'INV-$id',
    'status': 'refunded',
    'refunded_amount': {'raw': id},
    'original_amount': {'raw': id * 2},
  });
}

Paged<InvoiceSummary> _invoicePage(
  List<InvoiceSummary> items, {
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

Paged<ReturnSummary> _returnPage(
  List<ReturnSummary> items, {
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
