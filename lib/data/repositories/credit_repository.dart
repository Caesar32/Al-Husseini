import '../../core/constants/api_constants.dart';
import '../../core/network/api_service.dart';
import '../models/credit_overview.dart';

class CreditRepository {
  final ApiService _api;

  CreditRepository(this._api);

  Future<CreditOverviewData> getCreditOverview() async {
    final response = await _api.get(ApiConstants.creditOverview);
    final data = response['data'] as Map<String, dynamic>;
    return CreditOverviewData.fromJson(data);
  }
}
