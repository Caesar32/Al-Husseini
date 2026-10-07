import 'dart:async';
import 'package:flutter/foundation.dart';
import '../core/constants/api_constants.dart';
import '../data/models/live_pulse_data.dart';
import '../data/models/period_metrics.dart';
import '../data/repositories/dashboard_repository.dart';

class DashboardProvider extends ChangeNotifier {
  final DashboardRepository _repository;

  LivePulseData? _livePulse;
  PeriodMetrics? _periodMetrics;
  String _selectedPeriod = 'today';
  int? _selectedBranchId;

  bool _isLoadingLive = false;
  bool _isLoadingMetrics = false;
  String? _liveError;
  String? _metricsError;
  DateTime? _lastSyncTime;

  Timer? _pollingTimer;

  DashboardProvider(this._repository);

  LivePulseData? get livePulse => _livePulse;
  PeriodMetrics? get periodMetrics => _periodMetrics;
  String get selectedPeriod => _selectedPeriod;
  int? get selectedBranchId => _selectedBranchId;
  bool get isLoadingLive => _isLoadingLive;
  bool get isLoadingMetrics => _isLoadingMetrics;
  String? get liveError => _liveError;
  String? get metricsError => _metricsError;
  DateTime? get lastSyncTime => _lastSyncTime;

  void startLivePolling() {
    stopLivePolling();
    fetchLivePulse(isBackground: false);
    _pollingTimer = Timer.periodic(ApiConstants.livePulsePollingInterval, (_) {
      fetchLivePulse(isBackground: true);
    });
  }

  void stopLivePolling() {
    _pollingTimer?.cancel();
    _pollingTimer = null;
  }

  Future<void> fetchLivePulse({bool isBackground = false}) async {
    if (!isBackground) {
      _isLoadingLive = true;
      _liveError = null;
      notifyListeners();
    }

    try {
      _livePulse = await _repository.getLivePulse(branchId: _selectedBranchId);
      _lastSyncTime = DateTime.now();
      _liveError = null;
    } catch (e) {
      if (!isBackground) {
        _liveError = e.toString();
      }
    } finally {
      _isLoadingLive = false;
      notifyListeners();
    }
  }

  Future<void> fetchPeriodMetrics(String period) async {
    _selectedPeriod = period;
    _isLoadingMetrics = true;
    _metricsError = null;
    notifyListeners();

    try {
      _periodMetrics = await _repository.getPeriodMetrics(period, branchId: _selectedBranchId);
    } catch (e) {
      _metricsError = e.toString();
    } finally {
      _isLoadingMetrics = false;
      notifyListeners();
    }
  }

  void setSelectedBranch(int? branchId) {
    if (_selectedBranchId != branchId) {
      _selectedBranchId = branchId;
      fetchLivePulse(isBackground: false);
      fetchPeriodMetrics(_selectedPeriod);
    }
  }

  @override
  void dispose() {
    stopLivePolling();
    super.dispose();
  }
}
