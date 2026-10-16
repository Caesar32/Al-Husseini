import 'dart:async';

import 'package:flutter/widgets.dart';

import '../core/constants/api_constants.dart';
import '../core/network/api_failure.dart';
import '../core/storage/auth_storage.dart';
import '../data/models/live_pulse_data.dart';
import '../data/models/period_metrics.dart';
import '../data/repositories/dashboard_repository.dart';
import 'async_state.dart';

class DashboardProvider extends ChangeNotifier with WidgetsBindingObserver {
  final DashboardRepository _repository;
  final DateTime Function() _now;
  final AuthStorage? _storage;

  AsyncState<LivePulseData> _live = const AsyncIdle();
  AsyncState<PeriodMetrics> _metrics = const AsyncIdle();

  String _selectedPeriod = 'today';
  int? _selectedBranchId;
  DateTime? _lastSyncTime;

  Timer? _pollingTimer;
  bool _pollingRequested = false;
  bool _observerRegistered = false;
  int _liveFetchesInFlight = 0;
  ApiFailure? _lastPollFailure;
  bool _disposed = false;

  DashboardProvider(
    this._repository, {
    DateTime Function()? clock,
    AuthStorage? storage,
  })  : _now = clock ?? DateTime.now,
        _storage = storage,
        _selectedBranchId = storage?.getSelectedBranchId();

  AsyncState<LivePulseData> get liveState => _live;
  AsyncState<PeriodMetrics> get metricsState => _metrics;

  /// Legacy accessor; prefer [liveState].
  LivePulseData? get livePulse => _live.value;

  /// Legacy accessor; prefer [liveState].
  PeriodMetrics? get periodMetrics => _metrics.value;

  /// Legacy accessor; prefer [liveState].
  String get selectedPeriod => _selectedPeriod;

  /// Legacy accessor; prefer [liveState].
  int? get selectedBranchId => _selectedBranchId;

  /// Legacy accessor; prefer [liveState].
  bool get isLoadingLive => _live.isLoading;

  /// Legacy accessor; prefer [liveState].
  bool get isLoadingMetrics => _metrics.isLoading;

  /// Legacy accessor; prefer [liveState].
  String? get liveError => _live.failure?.message;

  /// Legacy accessor; prefer [liveState].
  String? get metricsError => _metrics.failure?.message;

  /// Legacy accessor; prefer [liveState].
  DateTime? get lastSyncTime => _lastSyncTime;

  ApiFailure? get lastPollFailure => _lastPollFailure;

  bool get isStale =>
      _lastSyncTime != null &&
      _now().difference(_lastSyncTime!) >
          ApiConstants.livePulsePollingInterval * 3;

  /// Legacy accessor; prefer [liveState].
  void startLivePolling() {
    _pollingRequested = true;
    _registerObserver();

    _pollingTimer?.cancel();
    _pollingTimer = null;

    unawaited(fetchLivePulse(isBackground: false));
    _startPollingTimer();
  }

  /// Legacy accessor; prefer [liveState].
  void stopLivePolling() {
    _pollingRequested = false;
    _pollingTimer?.cancel();
    _pollingTimer = null;
    _removeObserver();
  }

  /// Legacy accessor; prefer [liveState].
  Future<void> fetchLivePulse({bool isBackground = false}) async {
    if (isBackground && _liveFetchesInFlight > 0) {
      return;
    }

    _liveFetchesInFlight++;
    if (!isBackground) {
      _live = _live.toLoading();
      _notifyListeners();
    }

    try {
      final value = await _repository.getLivePulse(branchId: _selectedBranchId);
      final fetchedAt = _now();

      _live = AsyncData(value, fetchedAt: fetchedAt);
      _lastSyncTime = fetchedAt;
      _lastPollFailure = null;
      _notifyListeners();
    } catch (error) {
      final failure = toApiFailure(error);

      if (isBackground) {
        _lastPollFailure = failure;
        if (failure is UnauthorizedFailure) {
          stopLivePolling();
        }
      } else {
        _live = _live.toFailure(failure);
        if (failure is UnauthorizedFailure) {
          stopLivePolling();
        }
      }

      _notifyListeners();
    } finally {
      _liveFetchesInFlight--;
    }
  }

  /// Legacy accessor; prefer [liveState].
  Future<void> fetchPeriodMetrics(String period) async {
    _selectedPeriod = period;
    _metrics = _metrics.toLoading();
    _notifyListeners();

    try {
      final value = await _repository.getPeriodMetrics(
        period,
        branchId: _selectedBranchId,
      );
      _metrics = AsyncData(value, fetchedAt: _now());
      _notifyListeners();
    } catch (error) {
      _metrics = _metrics.toFailure(toApiFailure(error));
      _notifyListeners();
    }
  }

  /// Legacy accessor; prefer [liveState].
  void setSelectedBranch(int? branchId) {
    if (_selectedBranchId != branchId) {
      _selectedBranchId = branchId;
      unawaited(_storage?.saveSelectedBranchId(branchId));
      unawaited(fetchLivePulse(isBackground: false));
      unawaited(fetchPeriodMetrics(_selectedPeriod));
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.hidden ||
        state == AppLifecycleState.detached) {
      _pollingTimer?.cancel();
      _pollingTimer = null;
    } else if (state == AppLifecycleState.resumed &&
        _pollingRequested &&
        _pollingTimer == null) {
      unawaited(fetchLivePulse(isBackground: true));
      _startPollingTimer();
    }
  }

  void _startPollingTimer() {
    _pollingTimer = Timer.periodic(
      ApiConstants.livePulsePollingInterval,
      (_) => unawaited(fetchLivePulse(isBackground: true)),
    );
  }

  void _registerObserver() {
    if (_observerRegistered) {
      return;
    }

    WidgetsBinding.instance.addObserver(this);
    _observerRegistered = true;
  }

  void _removeObserver() {
    if (!_observerRegistered) {
      return;
    }

    WidgetsBinding.instance.removeObserver(this);
    _observerRegistered = false;
  }

  void _notifyListeners() {
    if (!_disposed) {
      notifyListeners();
    }
  }

  @override
  void dispose() {
    stopLivePolling();
    _disposed = true;
    super.dispose();
  }
}
