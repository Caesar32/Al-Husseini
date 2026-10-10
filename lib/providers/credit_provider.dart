import 'package:flutter/foundation.dart';
import '../data/models/credit_overview.dart';
import '../data/repositories/credit_repository.dart';

class CreditProvider extends ChangeNotifier {
  final CreditRepository _repository;

  CreditOverviewData? _overviewData;
  bool _isLoading = false;
  String? _error;

  CreditProvider(this._repository);

  CreditOverviewData? get overviewData => _overviewData;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<void> fetchCreditOverview() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      _overviewData = await _repository.getCreditOverview();
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}
