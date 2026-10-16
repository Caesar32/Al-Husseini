import 'package:flutter/foundation.dart';

import '../data/models/credit_overview.dart';
import '../data/repositories/credit_repository.dart';
import 'async_state.dart';

class CreditProvider extends ChangeNotifier {
  final CreditRepository _repository;

  AsyncState<CreditOverviewData> _state =
      const AsyncIdle<CreditOverviewData>();

  CreditProvider(this._repository);

  AsyncState<CreditOverviewData> get state => _state;

  CreditOverviewData? get overviewData => _state.value;
  bool get isLoading => _state.isLoading;
  String? get error => _state.failure?.message;

  Future<void> fetchCreditOverview() async {
    _state = _state.toLoading();
    notifyListeners();

    try {
      final value = await _repository.getCreditOverview();
      _state = AsyncData(value);
    } catch (error) {
      _state = _state.toFailure(toApiFailure(error));
    } finally {
      notifyListeners();
    }
  }
}
