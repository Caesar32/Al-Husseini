import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/network/api_failure.dart';
import 'package:alhusseini_app/core/network/api_service.dart';
import 'package:alhusseini_app/core/storage/auth_storage.dart';
import 'package:alhusseini_app/data/models/staff_attendance.dart';
import 'package:alhusseini_app/data/repositories/staff_repository.dart';
import 'package:alhusseini_app/providers/async_state.dart';
import 'package:alhusseini_app/providers/staff_provider.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late ApiService apiService;
  late _FakeStaffRepository repository;

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    final preferences = await SharedPreferences.getInstance();

    apiService = ApiService(
      AuthStorage(preferences),
      client: MockClient((_) async => http.Response('', 200)),
    );
    repository = _FakeStaffRepository(apiService);
    addTearDown(apiService.dispose);
  });

  test('success exposes the attendance summary', () async {
    final provider = StaffProvider(repository);
    addTearDown(provider.dispose);

    await provider.fetchTodayAttendance();

    expect(provider.attendanceSummary, same(repository.value));
    expect(provider.state, isA<AsyncData<StaffAttendanceSummary>>());
    expect(provider.isLoading, isFalse);
  });

  test('failure exposes the API failure message', () async {
    final provider = StaffProvider(repository);
    addTearDown(provider.dispose);

    repository.failure = const NetworkFailure(message: 'فشل التحميل');

    await provider.fetchTodayAttendance();

    expect(provider.error, 'فشل التحميل');
    expect(provider.state, isA<AsyncFailure<StaffAttendanceSummary>>());
  });

  test('previous data is kept while reloading', () async {
    final provider = StaffProvider(repository);
    addTearDown(provider.dispose);

    await provider.fetchTodayAttendance();

    final completer = Completer<StaffAttendanceSummary>();
    repository.completer = completer;

    final reload = provider.fetchTodayAttendance();

    expect(provider.isLoading, isTrue);
    expect(provider.attendanceSummary, same(repository.value));

    completer.complete(repository.value);
    await reload;

    expect(provider.attendanceSummary, same(repository.value));
  });
}

class _FakeStaffRepository extends StaffRepository {
  _FakeStaffRepository(super.api);

  final StaffAttendanceSummary value = const StaffAttendanceSummary(
    totalEmployees: 3,
    presentCount: 2,
    lateCount: 1,
    absentCount: 1,
    attendanceRate: '66%',
    staffList: <StaffMemberAttendance>[],
  );

  ApiFailure? failure;
  Completer<StaffAttendanceSummary>? completer;

  @override
  Future<StaffAttendanceSummary> getTodayAttendance({int? branchId}) async {
    final pending = completer;
    if (pending != null) {
      completer = null;
      return pending.future;
    }

    final currentFailure = failure;
    if (currentFailure != null) {
      throw currentFailure;
    }

    return value;
  }
}
