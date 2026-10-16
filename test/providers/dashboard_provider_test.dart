import 'dart:async';

import 'package:fake_async/fake_async.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/widgets.dart';
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/constants/api_constants.dart';
import 'package:alhusseini_app/core/network/api_failure.dart';
import 'package:alhusseini_app/core/network/api_service.dart';
import 'package:alhusseini_app/core/storage/auth_storage.dart';
import 'package:alhusseini_app/data/models/live_pulse_data.dart';
import 'package:alhusseini_app/data/models/period_metrics.dart';
import 'package:alhusseini_app/data/repositories/dashboard_repository.dart';
import 'package:alhusseini_app/providers/async_state.dart';
import 'package:alhusseini_app/providers/dashboard_provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('startLivePolling fetches immediately and every 10 seconds', () async {
    final context = await _createContext();
    addTearDown(context.dispose);

    fakeAsync((async) {
      context.provider.startLivePolling();
      async.flushMicrotasks();

      expect(context.repository.liveCalls, 1);

      async.elapse(const Duration(seconds: 25));
      async.flushMicrotasks();

      expect(context.repository.liveCalls, 3);
    });
  });

  test('stopLivePolling stops further calls', () async {
    final context = await _createContext();
    addTearDown(context.dispose);

    fakeAsync((async) {
      context.provider.startLivePolling();
      async.flushMicrotasks();
      context.provider.stopLivePolling();

      async.elapse(const Duration(seconds: 30));
      async.flushMicrotasks();

      expect(context.repository.liveCalls, 1);
    });
  });

  test('paused lifecycle halts calls and resumed lifecycle restarts them',
      () async {
    final context = await _createContext();
    addTearDown(context.dispose);

    fakeAsync((async) {
      context.provider.startLivePolling();
      async.flushMicrotasks();

      context.provider.didChangeAppLifecycleState(AppLifecycleState.paused);
      async.elapse(const Duration(seconds: 20));
      async.flushMicrotasks();

      expect(context.repository.liveCalls, 1);

      context.provider.didChangeAppLifecycleState(AppLifecycleState.resumed);
      async.flushMicrotasks();

      expect(context.repository.liveCalls, 2);

      async.elapse(const Duration(seconds: 10));
      async.flushMicrotasks();

      expect(context.repository.liveCalls, 3);
    });
  });

  test('background failure keeps the previous live pulse', () async {
    final context = await _createContext();
    addTearDown(context.dispose);

    await context.provider.fetchLivePulse();
    final previous = context.repository.liveValue;
    const failure = NetworkFailure(message: 'فشل الاتصال');
    context.repository.liveFailures.add(failure);

    await context.provider.fetchLivePulse(isBackground: true);

    expect(context.provider.livePulse, same(previous));
    expect(context.provider.liveState, isA<AsyncData<LivePulseData>>());
    expect(context.provider.lastPollFailure, same(failure));
    expect(context.provider.liveError, isNull);
  });

  test('foreground failure exposes the live error message', () async {
    final context = await _createContext();
    addTearDown(context.dispose);

    const failure = NetworkFailure(message: 'فشل الاتصال');
    context.repository.liveFailures.add(failure);

    await context.provider.fetchLivePulse();

    expect(context.provider.liveError, failure.message);
    expect(context.provider.liveState, isA<AsyncFailure<LivePulseData>>());
  });

  test('unauthorized failure stops polling', () async {
    final context = await _createContext();
    addTearDown(context.dispose);

    fakeAsync((async) {
      context.provider.startLivePolling();
      async.flushMicrotasks();

      context.repository.liveFailures.add(const UnauthorizedFailure());
      async.elapse(ApiConstants.livePulsePollingInterval);
      async.flushMicrotasks();

      async.elapse(const Duration(seconds: 20));
      async.flushMicrotasks();

      expect(context.repository.liveCalls, 2);
    });
  });

  test('isStale uses the injected clock', () async {
    var now = DateTime(2026, 10, 17);
    final context = await _createContext(clock: () => now);
    addTearDown(context.dispose);

    await context.provider.fetchLivePulse();

    expect(context.provider.isStale, isFalse);

    now = now.add(ApiConstants.livePulsePollingInterval * 3);
    expect(context.provider.isStale, isFalse);

    now = now.add(const Duration(microseconds: 1));
    expect(context.provider.isStale, isTrue);
  });

  test('legacy getters reflect the async states', () async {
    final context = await _createContext();
    addTearDown(context.dispose);

    final liveCompleter = Completer<LivePulseData>();
    context.repository.liveCompleter = liveCompleter;

    final liveFuture = context.provider.fetchLivePulse();

    expect(context.provider.isLoadingLive, isTrue);
    expect(context.provider.livePulse, isNull);
    expect(context.provider.liveState.isLoading, isTrue);

    liveCompleter.complete(context.repository.liveValue);
    await liveFuture;

    expect(context.provider.livePulse, same(context.repository.liveValue));
    expect(context.provider.liveState.value, same(context.repository.liveValue));
    expect(context.provider.isLoadingLive, isFalse);
    expect(context.provider.lastSyncTime, isNotNull);

    final metricsCompleter = Completer<PeriodMetrics>();
    context.repository.metricsCompleter = metricsCompleter;

    final metricsFuture = context.provider.fetchPeriodMetrics('week');

    expect(context.provider.selectedPeriod, 'week');
    expect(context.provider.isLoadingMetrics, isTrue);
    expect(context.provider.periodMetrics, isNull);

    metricsCompleter.complete(context.repository.metricsValue);
    await metricsFuture;

    expect(
      context.provider.periodMetrics,
      same(context.repository.metricsValue),
    );
    expect(context.provider.metricsState.value, same(context.repository.metricsValue));
    expect(context.provider.isLoadingMetrics, isFalse);

    context.provider.setSelectedBranch(2);
    expect(context.provider.selectedBranchId, 2);
  });
}

Future<_DashboardContext> _createContext({
  DateTime Function()? clock,
}) async {
  SharedPreferences.setMockInitialValues({});

  final preferences = await SharedPreferences.getInstance();
  final storage = AuthStorage(preferences);
  final apiService = ApiService(
    storage,
    client: MockClient((_) async {
      throw StateError('The fake repository should prevent network access.');
    }),
  );
  final repository = _FakeDashboardRepository(apiService);
  final provider = DashboardProvider(repository, clock: clock);

  return _DashboardContext(apiService, repository, provider);
}

class _DashboardContext {
  const _DashboardContext(this.apiService, this.repository, this.provider);

  final ApiService apiService;
  final _FakeDashboardRepository repository;
  final DashboardProvider provider;

  Future<void> dispose() async {
    provider.dispose();
    await apiService.dispose();
  }
}

class _FakeDashboardRepository extends DashboardRepository {
  _FakeDashboardRepository(super.apiService);

  final LivePulseData liveValue = LivePulseData.fromJson(const {});
  final PeriodMetrics metricsValue = PeriodMetrics.fromJson(const {});
  final List<ApiFailure> liveFailures = <ApiFailure>[];
  final List<ApiFailure> metricsFailures = <ApiFailure>[];

  Completer<LivePulseData>? liveCompleter;
  Completer<PeriodMetrics>? metricsCompleter;
  int liveCalls = 0;
  int metricsCalls = 0;

  @override
  Future<LivePulseData> getLivePulse({int? branchId}) async {
    liveCalls++;

    final completer = liveCompleter;
    if (completer != null) {
      return completer.future;
    }

    if (liveFailures.isNotEmpty) {
      throw liveFailures.removeAt(0);
    }

    return liveValue;
  }

  @override
  Future<PeriodMetrics> getPeriodMetrics(
    String period, {
    int? branchId,
  }) async {
    metricsCalls++;

    final completer = metricsCompleter;
    if (completer != null) {
      return completer.future;
    }

    if (metricsFailures.isNotEmpty) {
      throw metricsFailures.removeAt(0);
    }

    return metricsValue;
  }
}
