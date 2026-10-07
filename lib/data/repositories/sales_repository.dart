import '../../core/constants/api_constants.dart';
import '../../core/network/api_service.dart';
import '../models/recent_invoice.dart';

class SalesRepository {
  final ApiService _api;

  SalesRepository(this._api);

  Future<List<InvoiceSummary>> getRecentInvoices({int page = 1, int perPage = 20, int? branchId}) async {
    final query = <String, String>{
      'page': page.toString(),
      'per_page': perPage.toString(),
    };
    if (branchId != null) {
      query['branch_id'] = branchId.toString();
    }

    final response = await _api.get(ApiConstants.recentInvoices, queryParameters: query);
    final list = response['data'] as List? ?? [];
    return list.map((e) => InvoiceSummary.fromJson(e)).toList();
  }

  Future<InvoiceDetail> getInvoiceDetail(int id) async {
    final response = await _api.get('${ApiConstants.invoiceDetail}/$id');
    final data = response['data'] as Map<String, dynamic>;
    return InvoiceDetail.fromJson(data);
  }

  Future<List<InvoiceSummary>> getRecentReturns({int page = 1, int perPage = 20, int? branchId}) async {
    final query = <String, String>{
      'page': page.toString(),
      'per_page': perPage.toString(),
    };
    if (branchId != null) {
      query['branch_id'] = branchId.toString();
    }

    final response = await _api.get(ApiConstants.recentReturns, queryParameters: query);
    final list = response['data'] as List? ?? [];
    return list.map((e) => InvoiceSummary.fromJson(e)).toList();
  }
}
