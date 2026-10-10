import 'package:flutter/foundation.dart';
import '../data/models/recent_invoice.dart';
import '../data/repositories/sales_repository.dart';

class SalesProvider extends ChangeNotifier {
  final SalesRepository _repository;

  List<InvoiceSummary> _recentInvoices = [];
  List<InvoiceSummary> _recentReturns = [];
  InvoiceDetail? _selectedInvoiceDetail;

  bool _isLoadingInvoices = false;
  bool _isLoadingReturns = false;
  bool _isLoadingDetail = false;

  String? _invoicesError;
  String? _returnsError;
  String? _detailError;

  String _searchQuery = '';

  SalesProvider(this._repository);

  List<InvoiceSummary> get recentInvoices {
    if (_searchQuery.isEmpty) return _recentInvoices;
    final query = _searchQuery.toLowerCase();
    return _recentInvoices.where((inv) {
      return inv.invoiceNumber.toLowerCase().contains(query) ||
          inv.customerName.toLowerCase().contains(query) ||
          inv.customerPhone.contains(query);
    }).toList();
  }

  List<InvoiceSummary> get recentReturns => _recentReturns;
  InvoiceDetail? get selectedInvoiceDetail => _selectedInvoiceDetail;

  bool get isLoadingInvoices => _isLoadingInvoices;
  bool get isLoadingReturns => _isLoadingReturns;
  bool get isLoadingDetail => _isLoadingDetail;

  String? get invoicesError => _invoicesError;
  String? get returnsError => _returnsError;
  String? get detailError => _detailError;

  void setSearchQuery(String query) {
    _searchQuery = query;
    notifyListeners();
  }

  Future<void> fetchRecentInvoices({int page = 1, int? branchId}) async {
    _isLoadingInvoices = true;
    _invoicesError = null;
    notifyListeners();

    try {
      _recentInvoices = await _repository.getRecentInvoices(page: page, branchId: branchId);
    } catch (e) {
      _invoicesError = e.toString();
    } finally {
      _isLoadingInvoices = false;
      notifyListeners();
    }
  }

  Future<void> fetchInvoiceDetail(int id) async {
    _isLoadingDetail = true;
    _detailError = null;
    _selectedInvoiceDetail = null;
    notifyListeners();

    try {
      _selectedInvoiceDetail = await _repository.getInvoiceDetail(id);
    } catch (e) {
      _detailError = e.toString();
    } finally {
      _isLoadingDetail = false;
      notifyListeners();
    }
  }

  Future<void> fetchRecentReturns({int page = 1, int? branchId}) async {
    _isLoadingReturns = true;
    _returnsError = null;
    notifyListeners();

    try {
      _recentReturns = await _repository.getRecentReturns(page: page, branchId: branchId);
    } catch (e) {
      _returnsError = e.toString();
    } finally {
      _isLoadingReturns = false;
      notifyListeners();
    }
  }
}
