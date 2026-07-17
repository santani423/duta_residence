import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../models/user_session.dart';
import '../services/biometric_auth_service.dart';
import '../storage/preferences_store.dart';
import '../storage/token_store.dart';

enum SessionStatus { booting, authenticated, unauthenticated, connectionError }

const _allowedRole = 'customer';
const _roleMismatchMessage =
    'Akun ini bukan akun penghuni. Silakan gunakan portal staf untuk masuk.';

class SessionController extends ChangeNotifier {
  SessionController({
    required this._apiClient,
    required this._tokenStore,
    required this._preferencesStore,
    required this._biometricAuth,
  });

  final ApiClient _apiClient;
  final TokenStore _tokenStore;
  final PreferencesStore _preferencesStore;
  final BiometricAuthService _biometricAuth;
  SessionStatus _status = SessionStatus.booting;
  UserSession? _user;
  String? _message;
  bool _biometricEnabled = false;
  bool _biometricUnlocked = false;

  SessionStatus get status => _status;
  UserSession? get user => _user;
  String? get message => _message;
  bool get biometricEnabled => _biometricEnabled;

  /// True once we're logged in but the biometric gate (if turned on) hasn't
  /// been cleared yet for this app session - the UI should show the lock
  /// screen instead of the resident portal while this is true.
  bool get requiresBiometricUnlock =>
      _status == SessionStatus.authenticated &&
      _biometricEnabled &&
      !_biometricUnlocked;

  Future<bool> canUseBiometrics() => _biometricAuth.isSupported();

  Future<bool> unlockWithBiometrics() async {
    final ok = await _biometricAuth.authenticate();
    if (ok) {
      _biometricUnlocked = true;
      notifyListeners();
    }
    return ok;
  }

  Future<void> setBiometricEnabled(bool enabled) async {
    await _preferencesStore.saveBiometricEnabled(enabled);
    _biometricEnabled = enabled;
    notifyListeners();
  }

  Future<void> bootstrap() async {
    final token = await _tokenStore.read();
    if (token == null || token.isEmpty) {
      _setUnauthenticated();
      notifyListeners();
      return;
    }

    try {
      final result = await _apiClient.get('auth/me');
      final user = UserSession.fromJson(
        Map<String, dynamic>.from(result.data as Map),
      );
      if (user.role != _allowedRole) {
        await _forceLogout();
        _setUnauthenticated(_roleMismatchMessage);
        notifyListeners();
        return;
      }
      _user = user;
      _status = SessionStatus.authenticated;
      _message = null;
      _biometricEnabled = await _preferencesStore.readBiometricEnabled();
      _biometricUnlocked = false;
    } on ApiException catch (error) {
      if (error.statusCode == null) {
        // Connectivity failure (timeout, DNS, TLS, dropped connection) —
        // keep the saved token so the user isn't forced to log in again
        // once the network or server recovers.
        _status = SessionStatus.connectionError;
        _message = error.message;
      } else {
        // The server explicitly rejected the session (401/403/419/etc).
        await _tokenStore.clear();
        _setUnauthenticated(error.message);
      }
    } catch (_) {
      // Anything unexpected (malformed response, etc.) must not crash
      // startup or leave the app stuck on the splash screen forever.
      _status = SessionStatus.connectionError;
      _message = 'Terjadi kesalahan tak terduga. Silakan coba lagi.';
    }
    notifyListeners();
  }

  Future<void> login(String username, String password) async {
    final result = await _apiClient.postJson('auth/login', {
      'username': username,
      'password': password,
    });
    final data = Map<String, dynamic>.from(result.data as Map);
    final user = UserSession.fromJson(
      Map<String, dynamic>.from(data['user'] as Map),
    );
    await _tokenStore.save(data['token'].toString());

    if (user.role != _allowedRole) {
      await _forceLogout();
      _setUnauthenticated(_roleMismatchMessage);
      notifyListeners();
      throw const ApiException(_roleMismatchMessage);
    }

    _user = user;
    _status = SessionStatus.authenticated;
    _message = null;
    // Just typed the password manually - no need to also demand a biometric
    // check right away, only on the next cold start of the app.
    _biometricEnabled = await _preferencesStore.readBiometricEnabled();
    _biometricUnlocked = true;
    notifyListeners();
  }

  Future<void> _forceLogout() async {
    try {
      await _apiClient.postJson('auth/logout', {});
    } on ApiException {
      // Local session must still be cleared if the remote token is already invalid.
    }
    await _tokenStore.clear();
  }

  Future<void> logout() async {
    await _forceLogout();
    _setUnauthenticated();
    notifyListeners();
  }

  Future<void> expireSession() async {
    await _tokenStore.clear();
    _setUnauthenticated('Sesi Anda berakhir. Silakan login kembali.');
    notifyListeners();
  }

  void _setUnauthenticated([String? message]) {
    _user = null;
    _status = SessionStatus.unauthenticated;
    _message = message;
    _biometricUnlocked = false;
  }
}
