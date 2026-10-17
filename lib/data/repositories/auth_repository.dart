import '../../core/constants/api_constants.dart';
import '../../core/network/api_failure.dart';
import '../../core/network/api_service.dart';
import '../../core/network/envelope.dart';
import '../../core/storage/auth_storage.dart';
import '../models/auth_user.dart';
import '../models/json_read.dart';

class AuthRepository {
  final ApiService _api;
  final AuthStorage _storage;

  AuthRepository(this._api, this._storage);

  Stream<void> get onSessionExpired => _api.onUnauthorized;

  Future<AuthUser> login(String login, String password, {String? deviceName}) async {
    final response = await _api.post(
      ApiConstants.login,
      body: {
        'login': login,
        'password': password,
        'device_name': deviceName ?? 'Executive-Mobile-App',
      },
    );

    final data = requireDataMap(response);
    final rawToken = data['token'];
    final token = readString(rawToken);
    if (rawToken is! String || token.isEmpty) {
      throw const BadResponseFailure();
    }

    final userJson = readMap(data['user']);
    if (userJson.isEmpty) {
      throw const BadResponseFailure();
    }

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
    final userJson = requireDataMap(response);
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
    if (_api.baseUrl == url) return;

    await _storage.saveCustomBaseUrl(url);
    await _storage.clearAll();
  }
}
