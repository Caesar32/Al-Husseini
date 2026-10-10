import 'package:flutter/foundation.dart';
import '../data/models/staff_attendance.dart';
import '../data/repositories/staff_repository.dart';

class StaffProvider extends ChangeNotifier {
  final StaffRepository _repository;

  StaffAttendanceSummary? _attendanceSummary;
  bool _isLoading = false;
  String? _error;

  StaffProvider(this._repository);

  StaffAttendanceSummary? get attendanceSummary => _attendanceSummary;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<void> fetchTodayAttendance({int? branchId}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      _attendanceSummary = await _repository.getTodayAttendance(branchId: branchId);
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}
