import '../../core/constants/api_constants.dart';
import '../../core/network/api_service.dart';
import '../models/staff_attendance.dart';

class StaffRepository {
  final ApiService _api;

  StaffRepository(this._api);

  Future<StaffAttendanceSummary> getTodayAttendance({int? branchId}) async {
    final query = <String, String>{};
    if (branchId != null) {
      query['branch_id'] = branchId.toString();
    }

    final response = await _api.get(ApiConstants.todayAttendance, queryParameters: query);
    return StaffAttendanceSummary.fromJson(response);
  }
}
