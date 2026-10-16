import 'package:flutter/foundation.dart';

import '../core/network/api_failure.dart';
import '../data/models/recent_invoice.dart';
import '../data/models/return_summary.dart';
import '../data/repositories/sales_repository.dart';
import 'async_state.dart';

class SalesProvider extends ChangeNotifier {
  final SalesRepository _repository;

  AsyncState<List<InvoiceSummary>> _invoices =
      const AsyncIdle<List<InvoiceSummary>>();
  int _invoicesPage = 1;
  bool _invoicesHasMore = false;
  bool _isLoadingMoreInvoices = false;
  int? _branchId;

  AsyncState<List<ReturnSummary>> _returns =
      const AsyncIdle<List<ReturnSummary>>();
  int _returnsPage = 1;
  bool _returnsHasMore = false;
  bool _isLoadingMoreReturns = false;
  int? _returnsBranchId;

  AsyncState<InvoiceDetail> _detail = const AsyncIdle<InvoiceDetail>();

  ApiFailure? _loadMoreError;

  String _searchQuery = '';

  SalesProvider(this._repository);

  AsyncState<List<InvoiceSummary>> get invoicesState => _invoices;
  AsyncState<List<ReturnSummary>> get returnsState => _returns;
  AsyncState<InvoiceDetail> get detailState => _detail;

  List<InvoiceSummary> get recentInvoices {
    final invoices = _invoices.value ?? const <InvoiceSummary>[];

    if (_searchQuery.isEmpty) {
      return invoices;
    }

    final query = _searchQuery.toLowerCase();
    return invoices.where((invoice) {
      return invoice.invoiceNumber.toLowerCase().contains(query) ||
          invoice.customerName.toLowerCase().contains(query) ||
          invoice.customerPhone.contains(query);
    }).toList();
  }

  List<ReturnSummary> get recentReturns =>
      _returns.value ?? const <ReturnSummary>[];

  InvoiceDetail? get selectedInvoiceDetail => _detail.value;

  bool get isLoadingInvoices => _invoices.isLoading;
  bool get isLoadingReturns => _returns.isLoading;
  bool get isLoadingDetail => _detail.isLoading;

  String? get invoicesError => _invoices.failure?.message;
  String? get returnsError => _returns.failure?.message;
  String? get detailError => _detail.failure?.message;

  bool get hasMoreInvoices => _invoicesHasMore;
  bool get hasMoreReturns => _returnsHasMore;
  bool get isLoadingMoreInvoices => _isLoadingMoreInvoices;
  bool get isLoadingMoreReturns => _isLoadingMoreReturns;
  String? get loadMoreError => _loadMoreError?.message;

  void setSearchQuery(String query) {
    _searchQuery = query;
    notifyListeners();
  }

  Future<void> fetchRecentInvoices({
    int page = 1,
    int? branchId,
  }) async {
    _branchId = branchId;

    if (_invoices.isLoading || _isLoadingMoreInvoices) {
      return;
    }

    if (page == 1) {
      _loadMoreError = null;
    }

    final previous = _invoices.value;
    _invoices = _invoices.toLoading();
    notifyListeners();

    try {
      final result = await _repository.getRecentInvoices(
        page: page,
        branchId: branchId,
      );
      final items = page == 1
          ? result.items
          : _appendInvoices(
              previous ?? const <InvoiceSummary>[],
              result.items,
            );

      _invoices = AsyncData(items);
      _invoicesPage = result.meta.currentPage;
      _invoicesHasMore = result.hasMore;
    } catch (error) {
      _invoices = _invoices.toFailure(toApiFailure(error));
    } finally {
      notifyListeners();
    }
  }

  Future<void> loadMoreInvoices() async {
    if (!_invoicesHasMore ||
        _invoices.isLoading ||
        _isLoadingMoreInvoices) {
      return;
    }

    _isLoadingMoreInvoices = true;
    _loadMoreError = null;
    notifyListeners();

    try {
      final result = await _repository.getRecentInvoices(
        page: _invoicesPage + 1,
        branchId: _branchId,
      );
      final current = _invoices.value ?? const <InvoiceSummary>[];

      _invoices = AsyncData(_appendInvoices(current, result.items));
      _invoicesPage = result.meta.currentPage;
      _invoicesHasMore = result.hasMore;
    } catch (error) {
      _loadMoreError = toApiFailure(error);
    } finally {
      _isLoadingMoreInvoices = false;
      notifyListeners();
    }
  }

  Future<void> fetchInvoiceDetail(int id) async {
    _detail = const AsyncLoading<InvoiceDetail>();
    notifyListeners();

    try {
      final value = await _repository.getInvoiceDetail(id);
      _detail = AsyncData(value);
    } catch (error) {
      _detail = AsyncFailure<InvoiceDetail>(toApiFailure(error));
    } finally {
      notifyListeners();
    }
  }

  Future<void> fetchRecentReturns({
    int page = 1,
    int? branchId,
  }) async {
    _returnsBranchId = branchId;

    if (_returns.isLoading || _isLoadingMoreReturns) {
      return;
    }

    if (page == 1) {
      _loadMoreError = null;
    }

    final previous = _returns.value;
    _returns = _returns.toLoading();
    notifyListeners();

    try {
      final result = await _repository.getRecentReturns(
        page: page,
        branchId: branchId,
      );
      final items = page == 1
          ? result.items
          : _appendReturns(
              previous ?? const <ReturnSummary>[],
              result.items,
            );

      _returns = AsyncData(items);
      _returnsPage = result.meta.currentPage;
      _returnsHasMore = result.hasMore;
    } catch (error) {
      _returns = _returns.toFailure(toApiFailure(error));
    } finally {
      notifyListeners();
    }
  }

  Future<void> loadMoreReturns() async {
    if (!_returnsHasMore || _returns.isLoading || _isLoadingMoreReturns) {
      return;
    }

    _isLoadingMoreReturns = true;
    _loadMoreError = null;
    notifyListeners();

    try {
      final result = await _repository.getRecentReturns(
        page: _returnsPage + 1,
        branchId: _returnsBranchId,
      );
      final current = _returns.value ?? const <ReturnSummary>[];

      _returns = AsyncData(_appendReturns(current, result.items));
      _returnsPage = result.meta.currentPage;
      _returnsHasMore = result.hasMore;
    } catch (error) {
      _loadMoreError = toApiFailure(error);
    } finally {
      _isLoadingMoreReturns = false;
      notifyListeners();
    }
  }

  List<InvoiceSummary> _appendInvoices(
    List<InvoiceSummary> current,
    List<InvoiceSummary> incoming,
  ) {
    final result = List<InvoiceSummary>.from(current);
    final ids = current.map((invoice) => invoice.id).toSet();

    for (final invoice in incoming) {
      if (ids.add(invoice.id)) {
        result.add(invoice);
      }
    }

    return result;
  }

  List<ReturnSummary> _appendReturns(
    List<ReturnSummary> current,
    List<ReturnSummary> incoming,
  ) {
    final result = List<ReturnSummary>.from(current);
    final ids = current.map((summary) => summary.id).toSet();

    for (final summary in incoming) {
      if (ids.add(summary.id)) {
        result.add(summary);
      }
    }

    return result;
  }
}
