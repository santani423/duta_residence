import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../screens/home_shell.dart';
import '../screens/login_screen.dart';
import '../screens/splash_screen.dart';
import '../state/session_controller.dart';
import '../state/theme_controller.dart';
import '../theme/app_theme.dart';
import '../widgets/state_views.dart';

class DutaResidenceApp extends StatelessWidget {
  const DutaResidenceApp({
    required this.apiClient,
    required this.sessionController,
    required this.themeController,
    super.key,
  });

  final ApiClient apiClient;
  final SessionController sessionController;
  final ThemeController themeController;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: Listenable.merge([sessionController, themeController]),
      builder: (context, _) {
        return MaterialApp(
          title: 'Duta Residence',
          debugShowCheckedModeBanner: false,
          theme: AppTheme.light(),
          darkTheme: AppTheme.dark(),
          themeMode: themeController.mode,
          themeAnimationDuration: const Duration(milliseconds: 320),
          themeAnimationCurve: Curves.easeOutCubic,
          home: switch (sessionController.status) {
            SessionStatus.booting => const SplashScreen(),
            SessionStatus.connectionError => Scaffold(
              body: ErrorView(
                message:
                    sessionController.message ??
                    'Tidak dapat terhubung ke server.',
                onRetry: sessionController.bootstrap,
              ),
            ),
            SessionStatus.unauthenticated => LoginScreen(
              sessionController: sessionController,
            ),
            SessionStatus.authenticated => HomeShell(
              apiClient: apiClient,
              sessionController: sessionController,
              themeController: themeController,
            ),
          },
        );
      },
    );
  }
}
