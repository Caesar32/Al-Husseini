import '../../core/constants/api_constants.dart';
import '../../core/network/api_service.dart';
import '../models/live_pulse_data.dart';
import '../models/period_metrics.dart';

class DashboardRepository {
  final ApiService _api;

  DashboardRepository(this._api);

  Future<LivePulseData> getLivePulse({int? branchId}) async {
    final Map<String, String> query = {};
    if (branchId != null) {
      query['branch_id'] = branchId.toString();
    }

    final response = await _api.get(ApiConstants.livePulse, queryParameters: query);
    final data = response['data'] as Map<String, dynamic>;
    return LivePulseData.fromJson(data);
  }

  Future<PeriodMetrics> getPeriodMetrics(String period, {int? branchId}) async {
    final Map<String, String> query = {'period': period};
    if (branchId != null) {
      query['branch_id'] = branchId.toString();
    }

    final response = await _api.get(ApiConstants.periodMetrics, queryParameters: query);
    final data = response['data'] as Map<String, dynamic>;
    return PeriodMetrics.fromJson(data);
  }
}
