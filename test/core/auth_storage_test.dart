import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:alhusseini_app/core/storage/auth_storage.dart';
import 'package:alhusseini_app/core/storage/secure_key_value_store.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('init migrates a legacy token into secure storage', () async {
    SharedPreferences.setMockInitialValues({
      'owner_auth_token': 'legacy-token',
    });
    final secureStore = InMemorySecureKeyValueStore();

    final storage = await AuthStorage.init(secureStore: secureStore);
    final preferences = await SharedPreferences.getInstance();

    expect(storage.getToken(), 'legacy-token');
    expect(await secureStore.read('owner_auth_token'), 'legacy-token');
    expect(preferences.getString('owner_auth_token'), isNull);
  });

  test('init prefers an existing secure token', () async {
    SharedPreferences.setMockInitialValues({
      'owner_auth_token': 'legacy-token',
    });
    final secureStore = InMemorySecureKeyValueStore();
    await secureStore.write('owner_auth_token', 'secure-token');

    final storage = await AuthStorage.init(secureStore: secureStore);
    final preferences = await SharedPreferences.getInstance();

    expect(storage.getToken(), 'secure-token');
    expect(await secureStore.read('owner_auth_token'), 'secure-token');
    expect(preferences.getString('owner_auth_token'), isNull);
  });

  test('saveToken writes to secure storage and not SharedPreferences', () async {
    SharedPreferences.setMockInitialValues({});
    final preferences = await SharedPreferences.getInstance();
    final secureStore = InMemorySecureKeyValueStore();
    final storage = AuthStorage(
      preferences,
      secureStore: secureStore,
    );

    expect(await storage.saveToken('secure-token'), isTrue);

    expect(storage.getToken(), 'secure-token');
    expect(await secureStore.read('owner_auth_token'), 'secure-token');
    expect(preferences.getString('owner_auth_token'), isNull);
  });

  test('clearAll empties the cache, secure storage, and preference keys',
      () async {
    SharedPreferences.setMockInitialValues({
      'owner_auth_token': 'legacy-token',
      'owner_user_data': '{}',
      'owner_selected_branch_id': 7,
    });
    final preferences = await SharedPreferences.getInstance();
    final secureStore = InMemorySecureKeyValueStore();
    await secureStore.write('owner_auth_token', 'secure-token');
    final storage = AuthStorage(
      preferences,
      secureStore: secureStore,
      initialToken: 'secure-token',
    );

    await storage.clearAll();

    expect(storage.getToken(), isNull);
    expect(await secureStore.read('owner_auth_token'), isNull);
    expect(preferences.getString('owner_auth_token'), isNull);
    expect(preferences.getString('owner_user_data'), isNull);
    expect(preferences.getInt('owner_selected_branch_id'), isNull);
  });

  test('init treats a secure storage read failure as no token', () async {
    SharedPreferences.setMockInitialValues({});
    final storage = await AuthStorage.init(
      secureStore: _ThrowingSecureKeyValueStore(),
    );

    expect(storage.getToken(), isNull);
  });

  test('getCustomBaseUrl ignores insecure URLs when disallowed', () async {
    SharedPreferences.setMockInitialValues({
      'owner_api_base_url': 'http://x/api',
    });
    final preferences = await SharedPreferences.getInstance();
    final storage = AuthStorage(preferences);

    expect(
      storage.getCustomBaseUrl(
        'https://fallback.example/api',
        allowInsecure: false,
      ),
      'https://fallback.example/api',
    );
    expect(
      storage.getCustomBaseUrl(
        'https://fallback.example/api',
        allowInsecure: true,
      ),
      'http://x/api',
    );
  });

  test('getCustomBaseUrl always returns HTTPS URLs', () async {
    SharedPreferences.setMockInitialValues({
      'owner_api_base_url': 'https://x/api',
    });
    final preferences = await SharedPreferences.getInstance();
    final storage = AuthStorage(preferences);

    expect(
      storage.getCustomBaseUrl(
        'https://fallback.example/api',
        allowInsecure: false,
      ),
      'https://x/api',
    );
  });
}

final class _ThrowingSecureKeyValueStore implements SecureKeyValueStore {
  @override
  Future<String?> read(String key) async {
    throw StateError('secure storage unavailable');
  }

  @override
  Future<void> write(String key, String value) async {
    throw StateError('secure storage unavailable');
  }

  @override
  Future<void> delete(String key) async {
    throw StateError('secure storage unavailable');
  }
}
