import 'package:flutter/foundation.dart';

import '../core/network/api_failure.dart';
import '../data/models/inventory_alert.dart';
import '../data/repositories/inventory_repository.dart';
import 'async_state.dart';

class InventoryProvider extends ChangeNotifier {
  final InventoryRepository _repository;

  AsyncState<List<InventoryAlertItem>> _state =
      const AsyncIdle<List<InventoryAlertItem>>();
  int _alertsPage = 1;
  bool _alertsHasMore = false;
  bool _isLoadingMoreAlerts = false;
  ApiFailure? _loadMoreFailure;
  bool _batteriesOnly = false;

  InventoryProvider(this._repository);

  AsyncState<List<InventoryAlertItem>> get state => _state;
  AsyncState<List<InventoryAlertItem>> get alertsState => _state;

  List<InventoryAlertItem> get alerts =>
      _state.value ?? const <InventoryAlertItem>[];
  bool get isLoading => _state.isLoading;
  String? get error => _state.failure?.message;
  bool get batteriesOnly => _batteriesOnly;

  bool get hasMoreAlerts => _alertsHasMore;
  bool get isLoadingMoreAlerts => _isLoadingMoreAlerts;
  String? get loadMoreError => _loadMoreFailure?.message;

  void toggleBatteriesFilter(bool batteriesOnly) {
    _batteriesOnly = batteriesOnly;
    fetchAlerts();
  }

  Future<void> fetchAlerts({int page = 1}) async {
    if (_state.isLoading || _isLoadingMoreAlerts) {
      return;
    }

    if (page == 1) {
      _loadMoreFailure = null;
    }

    final previous = _state.value;
    _state = _state.toLoading();
    notifyListeners();

    try {
      final result = await _repository.getAlerts(
        page: page,
        batteriesOnly: _batteriesOnly,
      );
      final items = page == 1
          ? result.items
          : _appendAlerts(
              previous ?? const <InventoryAlertItem>[],
              result.items,
            );

      _state = AsyncData(items);
      _alertsPage = result.meta.currentPage;
      _alertsHasMore = result.hasMore;
    } catch (error) {
      _state = _state.toFailure(toApiFailure(error));
    } finally {
      notifyListeners();
    }
  }

  Future<void> loadMoreAlerts() async {
    if (!_alertsHasMore || _state.isLoading || _isLoadingMoreAlerts) {
      return;
    }

    _isLoadingMoreAlerts = true;
    _loadMoreFailure = null;
    notifyListeners();

    try {
      final result = await _repository.getAlerts(
        page: _alertsPage + 1,
        batteriesOnly: _batteriesOnly,
      );
      final current = _state.value ?? const <InventoryAlertItem>[];

      _state = AsyncData(_appendAlerts(current, result.items));
      _alertsPage = result.meta.currentPage;
      _alertsHasMore = result.hasMore;
    } catch (error) {
      _loadMoreFailure = toApiFailure(error);
    } finally {
      _isLoadingMoreAlerts = false;
      notifyListeners();
    }
  }

  List<InventoryAlertItem> _appendAlerts(
    List<InventoryAlertItem> current,
    List<InventoryAlertItem> incoming,
  ) {
    final result = List<InventoryAlertItem>.from(current);
    final ids = current.map((item) => item.id).toSet();

    for (final item in incoming) {
      if (ids.add(item.id)) {
        result.add(item);
      }
    }

    return result;
  }
}
