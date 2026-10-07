import 'package:flutter/foundation.dart';
import '../data/models/cash_drawer_shift.dart';
import '../data/repositories/cash_drawer_repository.dart';

class CashDrawerProvider extends ChangeNotifier {
  final CashDrawerRepository _repository;

  CashDrawerShift? _currentShift;
  bool _isLoading = false;
  String? _error;

  CashDrawerProvider(this._repository);

  CashDrawerShift? get currentShift => _currentShift;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<void> fetchCurrentShift({int? branchId}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      _currentShift = await _repository.getCurrentShift(branchId: branchId);
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}
