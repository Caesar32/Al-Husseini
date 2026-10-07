import 'package:flutter/foundation.dart';
import '../data/models/auth_user.dart';
import '../data/repositories/auth_repository.dart';

enum AuthStatus { uninitialized, authenticated, unauthenticated, authenticating }

class AuthProvider extends ChangeNotifier {
  final AuthRepository _repository;

  AuthStatus _status = AuthStatus.uninitialized;
  AuthUser? _currentUser;
  String? _errorMessage;

  AuthProvider(this._repository) {
    _checkInitialAuth();
  }

  AuthStatus get status => _status;
  AuthUser? get currentUser => _currentUser;
  String? get errorMessage => _errorMessage;
  bool get isAuthenticated => _status == AuthStatus.authenticated;

  void _checkInitialAuth() {
    if (_repository.isAuthenticated()) {
      _currentUser = _repository.getSavedUser();
      _status = AuthStatus.authenticated;
    } else {
      _status = AuthStatus.unauthenticated;
    }
    notifyListeners();
  }

  Future<bool> login(String login, String password) async {
    _status = AuthStatus.authenticating;
    _errorMessage = null;
    notifyListeners();

    try {
      _currentUser = await _repository.login(login, password);
      _status = AuthStatus.authenticated;
      notifyListeners();
      return true;
    } catch (e) {
      _errorMessage = e.toString();
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

  Future<void> refreshProfile() async {
    try {
      _currentUser = await _repository.getProfile();
      notifyListeners();
    } catch (_) {}
  }

  String get baseUrl => _repository.getBaseUrl();

  Future<void> updateBaseUrl(String url) async {
    await _repository.setBaseUrl(url);
    notifyListeners();
  }
}
