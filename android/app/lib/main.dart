import 'package:flutter/material.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'src/api/api_client.dart';
import 'src/app/duta_residence_app.dart';
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
  );
  apiClient.onUnauthorized = sessionController.expireSession;
  await sessionController.bootstrap();

  runApp(
    DutaResidenceApp(
      apiClient: apiClient,
      sessionController: sessionController,
      themeController: themeController,
    ),
  );
}
