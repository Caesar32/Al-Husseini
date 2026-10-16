import '../../core/constants/api_constants.dart';
import '../../core/network/api_service.dart';
import '../../core/network/envelope.dart';
import '../models/cash_drawer_shift.dart';

class CashDrawerRepository {
  final ApiService _api;

  CashDrawerRepository(this._api);

  Future<CashDrawerShift> getCurrentShift({int? branchId}) async {
    final query = <String, String>{};

    if (branchId != null) {
      query['branch_id'] = branchId.toString();
    }

    final response = await _api.get(
      ApiConstants.currentShift,
      queryParameters: query,
    );
    final data = requireDataMap(response);
    return CashDrawerShift.fromJson(data);
  }
}
