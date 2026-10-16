import '../../core/constants/api_constants.dart';
import '../../core/network/api_service.dart';
import '../../core/network/envelope.dart';
import '../models/inventory_alert.dart';
import '../models/json_read.dart';
import '../models/paged.dart';

class InventoryRepository {
  final ApiService _api;

  InventoryRepository(this._api);

  Future<Paged<InventoryAlertItem>> getAlerts({
    int page = 1,
    int perPage = 20,
    bool batteriesOnly = false,
  }) async {
    final query = <String, String>{
      'page': page.toString(),
      'per_page': perPage.toString(),
    };

    if (batteriesOnly) {
      query['batteries_only'] = '1';
    }

    final response = await _api.get(
      ApiConstants.inventoryAlerts,
      queryParameters: query,
    );
    final items = readModelList<InventoryAlertItem>(
      requireDataList(response),
      InventoryAlertItem.fromJson,
    );

    return Paged(
      items,
      readPagination(response, requestedPage: page),
    );
  }
}
