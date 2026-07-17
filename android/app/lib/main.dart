import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'src/api/api_client.dart';
import 'src/app/duta_residence_app.dart';
import 'src/services/biometric_auth_service.dart';
import 'src/state/session_controller.dart';
import 'src/state/theme_controller.dart';
import 'src/storage/preferences_store.dart';
import 'src/storage/token_store.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('id_ID');

  final preferencesStore = PreferencesStore();
  final themeController = ThemeController(preferencesStore);
  await themeController.load();

  final tokenStore = TokenStore();
  final apiClient = ApiClient(tokenStore: tokenStore);
  final sessionController = SessionController(
    apiClient: apiClient,
    tokenStore: tokenStore,
    preferencesStore: preferencesStore,
    biometricAuth: BiometricAuthService(),
  );
  apiClient.onUnauthorized = sessionController.expireSession;

  // `runApp` must not be blocked behind the `auth/me` network call: on a
  // slow/flaky connection (common right after a fresh release-build
  // install) that would leave the native launch screen frozen with no
  // Flutter UI, error message, or retry option at all. The Flutter UI
  // (starting on SplashScreen) mounts immediately instead, and bootstrap
  // resolves in the background — session_controller.bootstrap() always
  // settles into a concrete status and never throws.
  runApp(
    DutaResidenceApp(
      apiClient: apiClient,
      sessionController: sessionController,
      themeController: themeController,
    ),
  );

  unawaited(sessionController.bootstrap());
}
