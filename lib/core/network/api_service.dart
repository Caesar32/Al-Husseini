import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../constants/api_constants.dart';
import '../storage/auth_storage.dart';
import 'api_failure.dart';

class ApiService {
  final AuthStorage _storage;
  final http.Client _client;
  final StreamController<void> _unauthorizedController =
      StreamController<void>.broadcast();

  ApiService(this._storage, {http.Client? client})
      : _client = client ?? http.Client();

  String get baseUrl =>
      _storage.getCustomBaseUrl(ApiConstants.defaultBaseUrl);

  Stream<void> get onUnauthorized => _unauthorizedController.stream;

  Future<void> dispose() => _unauthorizedController.close();

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

  Future<dynamic> get(
    String endpoint, {
    Map<String, String>? queryParameters,
  }) {
    final uri = _buildUri(endpoint, queryParameters);

    return _send(
      () => _client.get(uri, headers: _buildHeaders()),
    );
  }

  Future<dynamic> post(
    String endpoint, {
    Map<String, dynamic>? body,
  }) {
    final uri = _buildUri(endpoint, null);

    return _send(
      () => _client.post(
        uri,
        headers: _buildHeaders(),
        body: body != null ? jsonEncode(body) : null,
      ),
    );
  }

  Future<dynamic> _send(
    Future<http.Response> Function() request,
  ) async {
    try {
      final response = await request().timeout(ApiConstants.connectionTimeout);
      return await _processResponse(response);
    } on TimeoutException {
      throw const TimeoutFailure();
    } on SocketException {
      throw const NetworkFailure();
    } on http.ClientException {
      throw const NetworkFailure();
    } on HandshakeException {
      throw const NetworkFailure(
        message: 'تعذر إنشاء اتصال آمن بالخادم.',
      );
    } on ApiFailure {
      rethrow;
    } catch (_) {
      throw const BadResponseFailure();
    }
  }

  Uri _buildUri(String endpoint, Map<String, String>? queryParameters) {
    final String cleanBase = baseUrl.endsWith('/')
        ? baseUrl.substring(0, baseUrl.length - 1)
        : baseUrl;
    final String cleanEndpoint =
        endpoint.startsWith('/') ? endpoint : '/$endpoint';
    final String fullPath = '$cleanBase$cleanEndpoint';

    final Uri parsedUri = Uri.parse(fullPath);
    if (queryParameters != null && queryParameters.isNotEmpty) {
      final newQueryParams = Map<String, String>.from(parsedUri.queryParameters)
        ..addAll(queryParameters);
      return parsedUri.replace(queryParameters: newQueryParams);
    }

    return parsedUri;
  }

  Future<dynamic> _processResponse(http.Response response) async {
    final bodyIsEmpty = response.body.isEmpty;
    dynamic body;
    var validJson = true;

    if (!bodyIsEmpty) {
      try {
        body = jsonDecode(response.body);
      } on FormatException {
        validJson = false;
      }
    }

    final statusCode = response.statusCode;

    if (statusCode >= 200 && statusCode < 300) {
      if (!bodyIsEmpty && !validJson) {
        throw const BadResponseFailure();
      }

      return body;
    }

    if (statusCode == 401) {
      await _storage.clearAll();
      _unauthorizedController.add(null);

      final message = _serverMessage(body);
      if (message == null) {
        throw UnauthorizedFailure(code: _serverCode(body));
      }

      throw UnauthorizedFailure(
        code: _serverCode(body),
        message: message,
      );
    }

    if (statusCode == 403) {
      final message = _serverMessage(body);
      if (message == null) {
        throw ForbiddenFailure(code: _serverCode(body));
      }

      throw ForbiddenFailure(
        code: _serverCode(body),
        message: message,
      );
    }

    if (statusCode == 404) {
      throw const NotFoundFailure();
    }

    if (statusCode == 422) {
      final fieldErrors = _fieldErrors(body);
      String? message;

      for (final errors in fieldErrors.values) {
        if (errors.isNotEmpty) {
          message = errors.first;
          break;
        }
      }

      message ??= _serverMessage(body);

      if (message == null) {
        throw ValidationFailure(fieldErrors: fieldErrors);
      }

      throw ValidationFailure(
        message: message,
        fieldErrors: fieldErrors,
      );
    }

    if (statusCode == 429) {
      final retryAfterHeader = _headerValue(response, 'retry-after');
      final retryAfterSeconds = retryAfterHeader == null
          ? null
          : int.tryParse(retryAfterHeader.trim());

      throw RateLimitedFailure(
        retryAfter: retryAfterSeconds == null
            ? null
            : Duration(seconds: retryAfterSeconds),
      );
    }

    throw ServerFailure(
      statusCode: statusCode,
      message: _serverMessage(body),
    );
  }

  String? _serverCode(dynamic body) {
    if (body is Map) {
      final code = body['code'];
      if (code is String) {
        return code;
      }
    }

    return null;
  }

  String? _serverMessage(dynamic body) {
    if (body is Map) {
      final message = body['message'];
      if (message is String && message.isNotEmpty) {
        return message;
      }
    }

    return null;
  }

  Map<String, List<String>> _fieldErrors(dynamic body) {
    final fieldErrors = <String, List<String>>{};

    if (body is! Map) {
      return fieldErrors;
    }

    final errors = body['errors'];
    if (errors is! Map) {
      return fieldErrors;
    }

    for (final entry in errors.entries) {
      final values = <String>[];
      final value = entry.value;

      if (value is List) {
        for (final item in value) {
          values.add(item is String ? item : item.toString());
        }
      } else if (value is String) {
        values.add(value);
      } else if (value != null) {
        values.add(value.toString());
      }

      fieldErrors[entry.key.toString()] = values;
    }

    return fieldErrors;
  }

  String? _headerValue(http.Response response, String name) {
    for (final entry in response.headers.entries) {
      if (entry.key.toLowerCase() == name.toLowerCase()) {
        return entry.value;
      }
    }

    return null;
  }
}
