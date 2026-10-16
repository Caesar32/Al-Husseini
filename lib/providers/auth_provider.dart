import 'dart:async';

import 'package:flutter/foundation.dart';

import '../core/network/api_failure.dart';
import '../data/models/auth_user.dart';
import '../data/repositories/auth_repository.dart';
import 'async_state.dart';

enum AuthStatus { uninitialized, authenticated, unauthenticated, authenticating }

class AuthProvider extends ChangeNotifier {
  final AuthRepository _repository;
  late final StreamSubscription<void> _sessionExpiredSubscription;

  AuthStatus _status = AuthStatus.uninitialized;
  AuthUser? _currentUser;
  String? _errorMessage;
  bool _sessionExpired = false;

  AuthProvider(this._repository) {
    _sessionExpiredSubscription =
        _repository.onSessionExpired.listen((_) => _handleSessionExpired());
    _checkInitialAuth();
  }

  AuthStatus get status => _status;
  AuthUser? get currentUser => _currentUser;
  String? get errorMessage => _errorMessage;
  bool get isAuthenticated => _status == AuthStatus.authenticated;
  bool get sessionExpired => _sessionExpired;

  void _checkInitialAuth() {
    if (_repository.isAuthenticated()) {
      _currentUser = _repository.getSavedUser();
      _status = AuthStatus.authenticated;
    } else {
      _status = AuthStatus.unauthenticated;
    }
    notifyListeners();
  }

  void _handleSessionExpired() {
    if (_status != AuthStatus.authenticated) return;

    _currentUser = null;
    _status = AuthStatus.unauthenticated;
    _sessionExpired = true;
    _errorMessage = 'انتهت جلسة الدخول. يرجى تسجيل الدخول مجدداً.';
    notifyListeners();
  }

  Future<bool> login(String login, String password) async {
    _status = AuthStatus.authenticating;
    _errorMessage = null;
    notifyListeners();

    try {
      _currentUser = await _repository.login(login, password);
      _status = AuthStatus.authenticated;
      _sessionExpired = false;
      notifyListeners();
      return true;
    } catch (e) {
      _errorMessage = toApiFailure(e).message;
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    await _repository.logout();
    _currentUser = null;
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  Future<void> validateSession() async {
    if (!isAuthenticated) return;

    try {
      _currentUser = await _repository.getProfile();
      notifyListeners();
    } on UnauthorizedFailure {
      // The API service already clears storage and notifies the session listener.
    } on ApiFailure {
      // Keep the cached user when a temporary API failure prevents validation.
    } catch (_) {
      // Keep the cached user for unexpected offline or startup failures.
    }
  }

  Future<void> refreshProfile() => validateSession();

  void acknowledgeSessionExpired() {
    _sessionExpired = false;
  }

  String get baseUrl => _repository.getBaseUrl();

  Future<void> updateBaseUrl(String url) async {
    await _repository.setBaseUrl(url);
    notifyListeners();
  }

  @override
  void dispose() {
    _sessionExpiredSubscription.cancel();
    super.dispose();
  }
}
