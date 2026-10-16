import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/network/api_service.dart';
import 'package:alhusseini_app/core/storage/auth_storage.dart';
import 'package:alhusseini_app/data/repositories/auth_repository.dart';
import 'package:alhusseini_app/providers/auth_provider.dart';

const _savedUser = <String, dynamic>{
  'id': 1,
  'name': 'Owner',
  'email': '',
  'phone': '',
  'role': 'super-admin',
};

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('starts authenticated with saved user', () async {
    final context = await _createContext(
      MockClient((_) async => http.Response('', 200)),
    );
    addTearDown(() => _disposeContext(context));

    expect(context.provider.status, AuthStatus.authenticated);
    expect(context.provider.currentUser?.name, 'Owner');
  });

  test('401 expires the session during validation', () async {
    final context = await _createContext(
      MockClient((_) async => http.Response('', 401)),
    );
    addTearDown(() => _disposeContext(context));

    await context.provider.validateSession();
    await Future<void>.delayed(Duration.zero);

    expect(context.provider.status, AuthStatus.unauthenticated);
    expect(context.provider.sessionExpired, isTrue);
    expect(context.provider.currentUser, isNull);
  });

  test('successful validation updates the cached user', () async {
    final context = await _createContext(
      MockClient(
        (_) async => http.Response(
          jsonEncode({
            'data': {
              ..._savedUser,
              'name': 'Updated Owner',
            },
          }),
          200,
        ),
      ),
    );
    addTearDown(() => _disposeContext(context));

    await context.provider.validateSession();

    expect(context.provider.status, AuthStatus.authenticated);
    expect(context.provider.currentUser?.name, 'Updated Owner');
  });

  test('validation keeps the cached user when offline', () async {
    final context = await _createContext(
      MockClient((_) async {
        throw const SocketException('offline');
      }),
    );
    addTearDown(() => _disposeContext(context));

    await context.provider.validateSession();

    expect(context.provider.status, AuthStatus.authenticated);
    expect(context.provider.currentUser?.name, 'Owner');
  });

  test('login validation failure uses the API message', () async {
    final context = await _createContext(
      MockClient(
        (_) async => http.Response.bytes(
          utf8.encode('{"errors":{"login":["بيانات الدخول غير صحيحة."]}}'),
          422,
          headers: {'content-type': 'application/json'},
        ),
      ),
    );
    addTearDown(() => _disposeContext(context));

    final result = await context.provider.login('bad-login', 'bad-password');

    expect(result, isFalse);
    expect(context.provider.errorMessage, 'بيانات الدخول غير صحيحة.');
  });

  test('successful login authenticates and clears session expiry', () async {
    final context = await _createContext(
      MockClient((request) async {
        if (request.url.path.endsWith('/me')) {
          return http.Response('', 401);
        }

        return http.Response(
          jsonEncode({
            'data': {
              'token': 'new-token',
              'user': _savedUser,
            },
          }),
          200,
        );
      }),
    );
    addTearDown(() => _disposeContext(context));

    await context.provider.validateSession();
    await Future<void>.delayed(Duration.zero);

    expect(context.provider.sessionExpired, isTrue);

    final result = await context.provider.login('owner', 'password');

    expect(result, isTrue);
    expect(context.provider.status, AuthStatus.authenticated);
    expect(context.provider.sessionExpired, isFalse);
  });
}

Future<_AuthContext> _createContext(http.Client client) async {
  SharedPreferences.setMockInitialValues({
    'owner_auth_token': 't',
    'owner_user_data': jsonEncode(_savedUser),
  });

  final preferences = await SharedPreferences.getInstance();
  final storage = AuthStorage(preferences);
  final apiService = ApiService(storage, client: client);
  final repository = AuthRepository(apiService, storage);
  final provider = AuthProvider(repository);

  return _AuthContext(apiService, provider);
}

Future<void> _disposeContext(_AuthContext context) async {
  context.provider.dispose();
  await context.apiService.dispose();
}

class _AuthContext {
  const _AuthContext(this.apiService, this.provider);

  final ApiService apiService;
  final AuthProvider provider;
}
