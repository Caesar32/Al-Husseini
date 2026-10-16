import '../../core/constants/api_constants.dart';
import '../../core/network/api_service.dart';
import '../../core/network/envelope.dart';
import '../models/json_read.dart';
import '../models/paged.dart';
import '../models/recent_invoice.dart';
import '../models/return_summary.dart';

class SalesRepository {
  final ApiService _api;

  SalesRepository(this._api);

  Future<Paged<InvoiceSummary>> getRecentInvoices({
    int page = 1,
    int perPage = 20,
    int? branchId,
  }) async {
    final query = <String, String>{
      'page': page.toString(),
      'per_page': perPage.toString(),
    };

    if (branchId != null) {
      query['branch_id'] = branchId.toString();
    }

    final response = await _api.get(
      ApiConstants.recentInvoices,
      queryParameters: query,
    );
    final items = readModelList<InvoiceSummary>(
      requireDataList(response),
      InvoiceSummary.fromJson,
    );

    return Paged(
      items,
      readPagination(response, requestedPage: page),
    );
  }

  Future<InvoiceDetail> getInvoiceDetail(int id) async {
    final response = await _api.get('${ApiConstants.invoiceDetail}/$id');
    final data = requireDataMap(response);
    return InvoiceDetail.fromJson(data);
  }

  Future<Paged<ReturnSummary>> getRecentReturns({
    int page = 1,
    int perPage = 20,
    int? branchId,
  }) async {
    final query = <String, String>{
      'page': page.toString(),
      'per_page': perPage.toString(),
    };

    if (branchId != null) {
      query['branch_id'] = branchId.toString();
    }

    final response = await _api.get(
      ApiConstants.recentReturns,
      queryParameters: query,
    );
    final items = readModelList<ReturnSummary>(
      requireDataList(response),
      ReturnSummary.fromJson,
    );

    return Paged(
      items,
      readPagination(response, requestedPage: page),
    );
  }
}
