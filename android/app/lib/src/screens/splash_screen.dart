import 'package:flutter/material.dart';

import '../constants/app_spacing.dart';
import '../widgets/app_logo.dart';
import '../widgets/site_identity_scope.dart';

class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final appName = SiteIdentityScope.of(context).appName;
    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [colors.primary, colors.primaryContainer, colors.surface],
          ),
        ),
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(AppSpacing.xl),
                decoration: BoxDecoration(
                  color: colors.surface,
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: colors.shadow.withValues(alpha: 0.18),
                      blurRadius: 32,
                      offset: const Offset(0, 12),
                    ),
                  ],
                ),
                child: const AppLogo(height: 64, alignment: Alignment.center),
              ),
              const SizedBox(height: AppSpacing.xxl),
              SizedBox(
                width: 28,
                height: 28,
                child: CircularProgressIndicator(
                  strokeWidth: 2.6,
                  color: colors.onPrimaryContainer,
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              Text(
                'Menyiapkan $appName',
                style: TextStyle(
                  color: colors.onPrimaryContainer,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
