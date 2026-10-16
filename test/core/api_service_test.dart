import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/network/api_failure.dart';
import 'package:alhusseini_app/core/network/api_service.dart';
import 'package:alhusseini_app/core/storage/auth_storage.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late AuthStorage storage;

  setUp(() async {
    SharedPreferences.setMockInitialValues({
      'owner_auth_token': 't',
      'owner_user_data': '{}',
    });

    final preferences = await SharedPreferences.getInstance();
    storage = AuthStorage(preferences);
  });

  test('200 JSON returns data and sends headers and query parameters',
      () async {
    late http.Request request;

    final service = ApiService(
      storage,
      client: MockClient((incomingRequest) async {
        request = incomingRequest;
        return http.Response('{"data":"ok"}', 200);
      }),
    );
    addTearDown(() => service.dispose());

    final result = await service.get(
      '/items?existing=yes',
      queryParameters: {'page': '2'},
    );

    expect(result, {'data': 'ok'});
    expect(request.headers['authorization'], 'Bearer t');
    expect(request.headers['accept'], 'application/json');
    expect(request.url.queryParameters['existing'], 'yes');
    expect(request.url.queryParameters['page'], '2');
  });

  test('200 empty body returns null', () async {
    final service = ApiService(
      storage,
      client: MockClient(
        (_) async => http.Response('', 200),
      ),
    );
    addTearDown(() => service.dispose());

    expect(await service.get('/empty'), isNull);
  });

  test('200 invalid JSON throws BadResponseFailure', () async {
    final service = ApiService(
      storage,
      client: MockClient(
        (_) async => http.Response('<html>error</html>', 200),
      ),
    );
    addTearDown(() => service.dispose());

    final failure = await _captureFailure(service.get('/invalid'));

    expect(failure, isA<BadResponseFailure>());
  });

  test('401 clears storage and emits one unauthorized event', () async {
    final service = ApiService(
      storage,
      client: MockClient(
        (_) async => http.Response(
          '{"code":"OWNER_ACCESS_REVOKED","message":"x"}',
          401,
        ),
      ),
    );
    addTearDown(() => service.dispose());

    final unauthorizedEvent = expectLater(
      service.onUnauthorized,
      emits(null),
    );

    final failure = await _captureFailure(service.get('/protected'));
    await unauthorizedEvent;

    expect(failure, isA<UnauthorizedFailure>());
    expect(failure.code, 'OWNER_ACCESS_REVOKED');
    expect(storage.getToken(), isNull);
  });

  test('403 returns ForbiddenFailure with server code', () async {
    final service = ApiService(
      storage,
      client: MockClient(
        (_) async => http.Response(
          '{"code":"OWNER_ROLE_REQUIRED"}',
          403,
        ),
      ),
    );
    addTearDown(() => service.dispose());

    final failure = await _captureFailure(service.get('/forbidden'));

    expect(failure, isA<ForbiddenFailure>());
    expect(failure.code, 'OWNER_ROLE_REQUIRED');
  });

  test('404 returns NotFoundFailure', () async {
    final service = ApiService(
      storage,
      client: MockClient(
        (_) async => http.Response('', 404),
      ),
    );
    addTearDown(() => service.dispose());

    final failure = await _captureFailure(service.get('/missing'));

    expect(failure, isA<NotFoundFailure>());
  });

  test('422 returns field errors and their first message', () async {
    final service = ApiService(
      storage,
      client: MockClient(
        (_) async => http.Response(
          '{"errors":{"login":["bad"]}}',
          422,
        ),
      ),
    );
    addTearDown(() => service.dispose());

    final failure = await _captureFailure(service.get('/validate'));

    expect(failure, isA<ValidationFailure>());
    final validationFailure = failure as ValidationFailure;
    expect(validationFailure.message, 'bad');
    expect(validationFailure.fieldErrors, {
      'login': ['bad'],
    });
  });

  test('422 with a non-map errors value returns ValidationFailure', () async {
    final service = ApiService(
      storage,
      client: MockClient(
        (_) async => http.Response(
          '{"errors":["bad"]}',
          422,
        ),
      ),
    );
    addTearDown(() => service.dispose());

    final failure = await _captureFailure(service.get('/validate'));

    expect(failure, isA<ValidationFailure>());
  });

  test('429 parses retry-after seconds', () async {
    final service = ApiService(
      storage,
      client: MockClient(
        (_) async => http.Response(
          '{"message":"retry"}',
          429,
          headers: {'retry-after': '30'},
        ),
      ),
    );
    addTearDown(() => service.dispose());

    final failure = await _captureFailure(service.get('/limited'));

    expect(failure, isA<RateLimitedFailure>());
    final rateLimitedFailure = failure as RateLimitedFailure;
    expect(rateLimitedFailure.retryAfter, const Duration(seconds: 30));
  });

  test('500 returns ServerFailure with status code', () async {
    final service = ApiService(
      storage,
      client: MockClient(
        (_) async => http.Response('{"message":"server"}', 500),
      ),
    );
    addTearDown(() => service.dispose());

    final failure = await _captureFailure(service.get('/server'));

    expect(failure, isA<ServerFailure>());
    expect(failure.statusCode, 500);
  });

  test('502 HTML body returns ServerFailure', () async {
    final service = ApiService(
      storage,
      client: MockClient(
        (_) async => http.Response('<html>bad gateway</html>', 502),
      ),
    );
    addTearDown(() => service.dispose());

    final failure = await _captureFailure(service.get('/gateway'));

    expect(failure, isA<ServerFailure>());
    expect(failure.statusCode, 502);
  });

  test('SocketException returns NetworkFailure', () async {
    final service = ApiService(
      storage,
      client: MockClient((_) async {
        throw const SocketException('offline');
      }),
    );
    addTearDown(() => service.dispose());

    final failure = await _captureFailure(service.get('/network'));

    expect(failure, isA<NetworkFailure>());
  });

  test('ClientException returns NetworkFailure', () async {
    final service = ApiService(
      storage,
      client: MockClient((_) async {
        throw http.ClientException('offline');
      }),
    );
    addTearDown(() => service.dispose());

    final failure = await _captureFailure(service.get('/network'));

    expect(failure, isA<NetworkFailure>());
  });
}

Future<ApiFailure> _captureFailure(Future<dynamic> request) async {
  try {
    await request;
  } on ApiFailure catch (failure) {
    return failure;
  }

  fail('Expected an ApiFailure');
}
