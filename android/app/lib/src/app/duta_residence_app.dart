import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../screens/biometric_lock_screen.dart';
import '../screens/collector/collector_home_shell.dart';
import '../screens/home_shell.dart';
import '../screens/login_screen.dart';
import '../screens/splash_screen.dart';
import '../screens/supervisor/supervisor_home_shell.dart';
import '../state/session_controller.dart';
import '../state/site_identity_controller.dart';
import '../state/theme_controller.dart';
import '../theme/app_theme.dart';
import '../widgets/site_identity_scope.dart';
import '../widgets/state_views.dart';

class DutaResidenceApp extends StatelessWidget {
  const DutaResidenceApp({
    required this.apiClient,
    required this.sessionController,
    required this.themeController,
    required this.siteIdentityController,
    super.key,
  });

  final ApiClient apiClient;
  final SessionController sessionController;
  final ThemeController themeController;
  final SiteIdentityController siteIdentityController;

  @override
  Widget build(BuildContext context) {
    return SiteIdentityScope(
      controller: siteIdentityController,
      child: AnimatedBuilder(
        animation: Listenable.merge([
          sessionController,
          themeController,
          siteIdentityController,
        ]),
        builder: (context, _) {
          return MaterialApp(
            title: siteIdentityController.appName,
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
              SessionStatus.authenticated =>
                sessionController.requiresBiometricUnlock
                    ? BiometricLockScreen(sessionController: sessionController)
                    : sessionController.user?.role == 'collector'
                    ? CollectorHomeShell(
                        apiClient: apiClient,
                        sessionController: sessionController,
                        themeController: themeController,
                      )
                    : sessionController.user?.role == 'supervisor'
                    ? SupervisorHomeShell(
                        apiClient: apiClient,
                        sessionController: sessionController,
                        themeController: themeController,
                      )
                    : HomeShell(
                        apiClient: apiClient,
                        sessionController: sessionController,
                        themeController: themeController,
                      ),
            },
          );
        },
      ),
    );
  }
}
