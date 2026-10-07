import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';

class AuthStorage {
  static const String _keyToken = 'owner_auth_token';
  static const String _keyUser = 'owner_user_data';
  static const String _keyBaseUrl = 'owner_api_base_url';
  static const String _keyBranchId = 'owner_selected_branch_id';

  final SharedPreferences _prefs;

  AuthStorage(this._prefs);

  static Future<AuthStorage> init() async {
    final prefs = await SharedPreferences.getInstance();
    return AuthStorage(prefs);
  }

  String? getToken() {
    return _prefs.getString(_keyToken);
  }

  Future<bool> saveToken(String token) async {
    return await _prefs.setString(_keyToken, token);
  }

  Future<bool> clearToken() async {
    return await _prefs.remove(_keyToken);
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

  String getCustomBaseUrl(String fallback) {
    return _prefs.getString(_keyBaseUrl) ?? fallback;
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
    await _prefs.remove(_keyToken);
    await _prefs.remove(_keyUser);
    await _prefs.remove(_keyBranchId);
  }
}
