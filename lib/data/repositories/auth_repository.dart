import '../../core/constants/api_constants.dart';
import '../../core/network/api_service.dart';
import '../../core/storage/auth_storage.dart';
import '../models/auth_user.dart';

class AuthRepository {
  final ApiService _api;
  final AuthStorage _storage;

  AuthRepository(this._api, this._storage);

  Future<AuthUser> login(String login, String password, {String? deviceName}) async {
    final response = await _api.post(
      ApiConstants.login,
      body: {
        'login': login,
        'password': password,
        'device_name': deviceName ?? 'Executive-Mobile-App',
      },
    );

    final data = response['data'] as Map<String, dynamic>;
    final token = data['token'] as String;
    final userJson = data['user'] as Map<String, dynamic>;

    await _storage.saveToken(token);
    await _storage.saveUser(userJson);

    return AuthUser.fromJson(userJson);
  }

  Future<void> logout() async {
    try {
      await _api.post(ApiConstants.logout);
    } catch (_) {
      // Best effort logout
    } finally {
      await _storage.clearAll();
    }
  }

  Future<AuthUser?> getProfile() async {
    final response = await _api.get(ApiConstants.me);
    final userJson = response['data'] as Map<String, dynamic>;
    await _storage.saveUser(userJson);
    return AuthUser.fromJson(userJson);
  }

  Future<void> registerDeviceToken(String deviceToken, String platform) async {
    await _api.post(
      ApiConstants.deviceToken,
      body: {
        'device_token': deviceToken,
        'platform': platform,
        'device_name': 'Executive-Mobile',
      },
    );
  }

  bool isAuthenticated() {
    final token = _storage.getToken();
    return token != null && token.isNotEmpty;
  }

  AuthUser? getSavedUser() {
    final json = _storage.getUser();
    if (json == null) return null;
    return AuthUser.fromJson(json);
  }

  String getBaseUrl() => _api.baseUrl;

  Future<void> setBaseUrl(String url) async {
    await _storage.saveCustomBaseUrl(url);
  }
}
