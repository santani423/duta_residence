import 'package:local_auth/local_auth.dart';

/// Thin wrapper over `local_auth` so the rest of the app never touches the
/// plugin directly - keeps biometric prompt wording and error handling in
/// one place, and makes the session controller easy to test.
class BiometricAuthService {
  final LocalAuthentication _auth = LocalAuthentication();

  /// Whether this device has usable biometric hardware with at least one
  /// fingerprint/face enrolled. Callers should hide the "Login Biometrik"
  /// toggle entirely when this returns false rather than show it disabled.
  Future<bool> isSupported() async {
    try {
      final deviceSupported = await _auth.isDeviceSupported();
      final canCheck = await _auth.canCheckBiometrics;
      return deviceSupported && canCheck;
    } catch (_) {
      return false;
    }
  }

  /// Prompts the OS biometric UI. Returns false (never throws) on failure,
  /// cancellation, lockout, or missing hardware so callers can just branch
  /// on the boolean result.
  Future<bool> authenticate({
    String reason = 'Verifikasi identitas Anda untuk melanjutkan.',
  }) async {
    try {
      return await _auth.authenticate(
        localizedReason: reason,
        options: const AuthenticationOptions(
          stickyAuth: true,
          biometricOnly: true,
        ),
      );
    } catch (_) {
      return false;
    }
  }
}
