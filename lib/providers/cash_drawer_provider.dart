import 'package:flutter/foundation.dart';

import '../data/models/cash_drawer_shift.dart';
import '../data/repositories/cash_drawer_repository.dart';
import 'async_state.dart';

class CashDrawerProvider extends ChangeNotifier {
  final CashDrawerRepository _repository;

  AsyncState<CashDrawerShift> _state =
      const AsyncIdle<CashDrawerShift>();

  CashDrawerProvider(this._repository);

  AsyncState<CashDrawerShift> get state => _state;

  CashDrawerShift? get currentShift => _state.value;
  bool get isLoading => _state.isLoading;
  String? get error => _state.failure?.message;

  Future<void> fetchCurrentShift({int? branchId}) async {
    _state = _state.toLoading();
    notifyListeners();

    try {
      final value = await _repository.getCurrentShift(branchId: branchId);
      _state = AsyncData(value);
    } catch (error) {
      final failure = toApiFailure(error);
      _state = _state.toFailure(failure);
    } finally {
      notifyListeners();
    }
  }
}
