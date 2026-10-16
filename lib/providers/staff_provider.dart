import 'package:flutter/foundation.dart';

import '../data/models/staff_attendance.dart';
import '../data/repositories/staff_repository.dart';
import 'async_state.dart';

class StaffProvider extends ChangeNotifier {
  final StaffRepository _repository;

  AsyncState<StaffAttendanceSummary> _state =
      const AsyncIdle<StaffAttendanceSummary>();

  StaffProvider(this._repository);

  AsyncState<StaffAttendanceSummary> get state => _state;

  StaffAttendanceSummary? get attendanceSummary => _state.value;
  bool get isLoading => _state.isLoading;
  String? get error => _state.failure?.message;

  Future<void> fetchTodayAttendance({int? branchId}) async {
    _state = _state.toLoading();
    notifyListeners();

    try {
      final value = await _repository.getTodayAttendance(branchId: branchId);
      _state = AsyncData(value);
    } catch (error) {
      _state = _state.toFailure(toApiFailure(error));
    } finally {
      notifyListeners();
    }
  }
}
