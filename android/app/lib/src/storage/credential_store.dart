import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Holds the last manually-entered login credentials, encrypted at rest via
/// the platform Keystore/Keychain (same backing as `TokenStore`), so a
/// biometric prompt on the login screen can trigger a real re-login without
/// retyping the password - most useful once the 8-hour API token expires.
///
/// Only ever populated while "Login Biometrik" is enabled, and wiped on
/// manual logout or when the toggle is turned off, so a real "log out"
/// always removes this fast path too.
class CredentialStore {
  static const _usernameKey = 'duta_biometric_username';
  static const _passwordKey = 'duta_biometric_password';
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  Future<void> save(String username, String password) async {
    await _storage.write(key: _usernameKey, value: username);
    await _storage.write(key: _passwordKey, value: password);
  }

  Future<({String username, String password})?> read() async {
    final username = await _storage.read(key: _usernameKey);
    final password = await _storage.read(key: _passwordKey);
    if (username == null || password == null) return null;
    return (username: username, password: password);
  }

  Future<void> clear() async {
    await _storage.delete(key: _usernameKey);
    await _storage.delete(key: _passwordKey);
  }
}
