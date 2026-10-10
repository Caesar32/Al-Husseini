import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import '../constants/api_constants.dart';
import '../storage/auth_storage.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;

  ApiException(this.message, {this.statusCode});

  @override
  String toString() => message;
}

class ApiService {
  final AuthStorage _storage;
  final http.Client _client;

  ApiService(this._storage, {http.Client? client})
      : _client = client ?? http.Client();

  String get baseUrl =>
      _storage.getCustomBaseUrl(ApiConstants.defaultBaseUrl);

  Map<String, String> _buildHeaders() {
    final headers = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };

    final token = _storage.getToken();
    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }

    return headers;
  }

  Future<dynamic> get(String endpoint, {Map<String, String>? queryParameters}) async {
    final Uri uri = _buildUri(endpoint, queryParameters);

    try {
      final response = await _client
          .get(uri, headers: _buildHeaders())
          .timeout(ApiConstants.connectionTimeout);

      return _processResponse(response);
    } on TimeoutException {
      throw ApiException('تعذر الاتصال بالخادم (انتهت مهلة الانتظار).');
    } on SocketException {
      throw ApiException('لا يوجد اتصال بالإنترنت أو الخادم غير متاح حالياً.');
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException('حدث خطأ غير متوقع: ${e.toString()}');
    }
  }

  Future<dynamic> post(String endpoint, {Map<String, dynamic>? body}) async {
    final Uri uri = _buildUri(endpoint, null);

    try {
      final response = await _client
          .post(
            uri,
            headers: _buildHeaders(),
            body: body != null ? jsonEncode(body) : null,
          )
          .timeout(ApiConstants.connectionTimeout);

      return _processResponse(response);
    } on TimeoutException {
      throw ApiException('تعذر الاتصال بالخادم (انتهت مهلة الانتظار).');
    } on SocketException {
      throw ApiException('لا يوجد اتصال بالإنترنت أو الخادم غير متاح حالياً.');
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException('حدث خطأ غير متوقع: ${e.toString()}');
    }
  }

  Uri _buildUri(String endpoint, Map<String, String>? queryParameters) {
    final String cleanBase = baseUrl.endsWith('/') ? baseUrl.substring(0, baseUrl.length - 1) : baseUrl;
    final String cleanEndpoint = endpoint.startsWith('/') ? endpoint : '/$endpoint';
    final String fullPath = '$cleanBase$cleanEndpoint';

    final Uri parsedUri = Uri.parse(fullPath);
    if (queryParameters != null && queryParameters.isNotEmpty) {
      final newQueryParams = Map<String, String>.from(parsedUri.queryParameters)
        ..addAll(queryParameters);
      return parsedUri.replace(queryParameters: newQueryParams);
    }

    return parsedUri;
  }

  dynamic _processResponse(http.Response response) {
    dynamic bodyJson;
    try {
      bodyJson = jsonDecode(response.body);
    } catch (_) {
      bodyJson = null;
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return bodyJson;
    }

    if (response.statusCode == 401) {
      _storage.clearAll();
      throw ApiException('انتهت جلسة الدخول. يرجى تسجيل الدخول مجدداً.', statusCode: 401);
    }

    if (response.statusCode == 403) {
      final msg = bodyJson is Map && bodyJson['message'] != null
          ? bodyJson['message'].toString()
          : 'غير مصرح لك بالوصول لهذا المورد.';
      throw ApiException(msg, statusCode: 403);
    }

    if (response.statusCode == 422) {
      if (bodyJson is Map && bodyJson['errors'] != null) {
        final errorsMap = bodyJson['errors'] as Map<String, dynamic>;
        final firstErrorList = errorsMap.values.first;
        if (firstErrorList is List && firstErrorList.isNotEmpty) {
          throw ApiException(firstErrorList.first.toString(), statusCode: 422);
        }
      }
      final msg = bodyJson is Map && bodyJson['message'] != null
          ? bodyJson['message'].toString()
          : 'بيانات غير صالحة.';
      throw ApiException(msg, statusCode: 422);
    }

    final serverMessage = bodyJson is Map && bodyJson['message'] != null
        ? bodyJson['message'].toString()
        : 'رمز الاستجابة: ${response.statusCode}';
    throw ApiException(serverMessage, statusCode: response.statusCode);
  }
}
