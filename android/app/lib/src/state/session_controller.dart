import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../models/user_session.dart';
import '../services/biometric_auth_service.dart';
import '../storage/credential_store.dart';
import '../storage/preferences_store.dart';
import '../storage/token_store.dart';

enum SessionStatus { booting, authenticated, unauthenticated, connectionError }

const _allowedRoles = {'customer', 'collector'};
const _roleMismatchMessage =
    'Akun ini tidak didukung di aplikasi ini. Silakan gunakan portal staf untuk masuk.';

class SessionController extends ChangeNotifier {
  SessionController({
    required this._apiClient,
    required this._tokenStore,
    required this._preferencesStore,
    required this._biometricAuth,
    required this._credentialStore,
  });

  final ApiClient _apiClient;
  final TokenStore _tokenStore;
  final PreferencesStore _preferencesStore;
  final BiometricAuthService _biometricAuth;
  final CredentialStore _credentialStore;
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
    if (!enabled) {
      // Turning the setting off must remove the fast biometric re-login path
      // too, not just the app-open lock gate.
      await _credentialStore.clear();
    }
    notifyListeners();
  }

  /// Whether the login screen should offer a biometric-login icon: only
  /// once the toggle is on AND a manual login has actually captured
  /// credentials to replay (right after enabling the toggle there's nothing
  /// saved yet, since only a real login submits the plaintext password).
  Future<bool> hasBiometricLoginAvailable() async {
    if (!_biometricEnabled) return false;
    return await _credentialStore.read() != null;
  }

  /// Prompts the OS biometric UI, then replays the last saved credentials
  /// through a real network login - this is not a bypass, the server still
  /// issues a fresh token. Returns false on a cancelled/failed biometric
  /// check (no saved credentials counts as unavailable, not a failure).
  Future<bool> loginWithBiometrics() async {
    final creds = await _credentialStore.read();
    if (creds == null) return false;
    final verified = await _biometricAuth.authenticate(
      reason: 'Verifikasi identitas Anda untuk login.',
    );
    if (!verified) return false;
    try {
      await login(creds.username, creds.password);
      return true;
    } on ApiException catch (error) {
      if (error.statusCode != null) {
        // The server explicitly rejected these credentials (password
        // changed elsewhere, account disabled, etc.) - stop offering a
        // biometric login that would just fail again.
        await _credentialStore.clear();
      }
      rethrow;
    }
  }

  Future<void> bootstrap() async {
    // Loaded unconditionally (not just on the authenticated path below) so
    // the login screen can already answer `hasBiometricLoginAvailable()`
    // correctly even when bootstrap lands on `unauthenticated` - e.g. right
    // after the saved API token expired.
    _biometricEnabled = await _preferencesStore.readBiometricEnabled();

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
      if (!_allowedRoles.contains(user.role)) {
        await _forceLogout();
        _setUnauthenticated(_roleMismatchMessage);
        notifyListeners();
        return;
      }
      _user = user;
      _status = SessionStatus.authenticated;
      _message = null;
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

    if (!_allowedRoles.contains(user.role)) {
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
    if (_biometricEnabled) {
      // Captures the credentials that just proved valid so a future
      // biometric prompt (e.g. after the token expires) can replay them
      // instead of forcing the user to retype their password.
      await _credentialStore.save(username, password);
    }
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
    // A manual logout must be a real logout - the biometric quick re-login
    // path is not allowed to survive it, even if the toggle is still on.
    await _credentialStore.clear();
    _setUnauthenticated();
    notifyListeners();
  }

  Future<void> expireSession([String? code]) async {
    await _tokenStore.clear();
    // Deliberately keeps any saved biometric credentials - this is exactly
    // the case the login-screen fingerprint icon exists for, so the user
    // isn't forced to retype their password every time the 8-hour API
    // token expires.
    _setUnauthenticated(
      code == 'SESSION_REVOKED'
          ? 'Akun Anda digunakan di perangkat lain. Silakan login kembali.'
          : 'Sesi Anda berakhir. Silakan login kembali.',
    );
    notifyListeners();
  }

  void _setUnauthenticated([String? message]) {
    _user = null;
    _status = SessionStatus.unauthenticated;
    _message = message;
    _biometricUnlocked = false;
  }
}
