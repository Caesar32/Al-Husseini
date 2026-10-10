import 'package:flutter/foundation.dart';
import '../data/models/inventory_alert.dart';
import '../data/repositories/inventory_repository.dart';

class InventoryProvider extends ChangeNotifier {
  final InventoryRepository _repository;

  List<InventoryAlertItem> _alerts = [];
  bool _isLoading = false;
  String? _error;
  bool _batteriesOnly = false;

  InventoryProvider(this._repository);

  List<InventoryAlertItem> get alerts => _alerts;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get batteriesOnly => _batteriesOnly;

  void toggleBatteriesFilter(bool batteriesOnly) {
    _batteriesOnly = batteriesOnly;
    fetchAlerts();
  }

  Future<void> fetchAlerts({int page = 1}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      _alerts = await _repository.getAlerts(page: page, batteriesOnly: _batteriesOnly);
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}
