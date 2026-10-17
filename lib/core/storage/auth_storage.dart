import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'secure_key_value_store.dart';

class AuthStorage {
  static const String _keyToken = 'owner_auth_token';
  static const String _keyUser = 'owner_user_data';
  static const String _keyBaseUrl = 'owner_api_base_url';
  static const String _keyBranchId = 'owner_selected_branch_id';

  final SharedPreferences _prefs;
  final SecureKeyValueStore _secure;
  String? _token;

  AuthStorage(
    this._prefs, {
    SecureKeyValueStore? secureStore,
    String? initialToken,
  })  : _secure = secureStore ?? InMemorySecureKeyValueStore(),
        _token = initialToken ?? _prefs.getString(_keyToken);

  static Future<AuthStorage> init({
    SecureKeyValueStore? secureStore,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    final store = secureStore ?? const FlutterSecureKeyValueStore();

    String? token;
    try {
      token = await store.read(_keyToken);
    } catch (_) {
      // Secure storage may be corrupted or unavailable; the user must log in again.
      token = null;
    }

    final legacyToken = prefs.getString(_keyToken);
    if (legacyToken != null && legacyToken.isNotEmpty) {
      if (token == null) {
        token = legacyToken;
        try {
          await store.write(_keyToken, legacyToken);
        } catch (_) {
          // Keep the token for this session only; the next launch asks for login.
        }
      }
    }
    await prefs.remove(_keyToken);

    return AuthStorage(
      prefs,
      secureStore: store,
      initialToken: token,
    );
  }

  String? getToken() {
    return _token;
  }

  Future<bool> saveToken(String token) async {
    _token = token;
    await _secure.write(_keyToken, token);
    return true;
  }

  Future<bool> clearToken() async {
    _token = null;
    await _secure.delete(_keyToken);
    await _prefs.remove(_keyToken);
    return true;
  }

  Map<String, dynamic>? getUser() {
    final raw = _prefs.getString(_keyUser);
    if (raw == null) return null;
    try {
      return jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      return null;
    }
  }

  Future<bool> saveUser(Map<String, dynamic> userData) async {
    return await _prefs.setString(_keyUser, jsonEncode(userData));
  }

  Future<bool> clearUser() async {
    return await _prefs.remove(_keyUser);
  }

  String getCustomBaseUrl(
    String fallback, {
    bool allowInsecure = kDebugMode,
  }) {
    final customUrl = _prefs.getString(_keyBaseUrl);
    if (customUrl == null) return fallback;

    final isHttps = Uri.tryParse(customUrl)?.scheme.toLowerCase() == 'https';
    if (!allowInsecure && !isHttps) return fallback;

    return customUrl;
  }

  Future<bool> saveCustomBaseUrl(String url) async {
    return await _prefs.setString(_keyBaseUrl, url);
  }

  int? getSelectedBranchId() {
    return _prefs.getInt(_keyBranchId);
  }

  Future<bool> saveSelectedBranchId(int? branchId) async {
    if (branchId == null) {
      return await _prefs.remove(_keyBranchId);
    }
    return await _prefs.setInt(_keyBranchId, branchId);
  }

  Future<void> clearAll() async {
    await clearToken();
    await _prefs.remove(_keyUser);
    await _prefs.remove(_keyBranchId);
  }
}
