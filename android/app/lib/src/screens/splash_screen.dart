import 'package:flutter/material.dart';

import '../constants/app_config.dart';
import '../constants/app_spacing.dart';
import '../widgets/app_logo.dart';

class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              colors.primaryContainer.withValues(alpha: 0.8),
              colors.surface,
              colors.tertiaryContainer.withValues(alpha: 0.35),
            ],
          ),
        ),
        child: const Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              AppLogo(height: 84, alignment: Alignment.center),
              SizedBox(height: AppSpacing.xl),
              CircularProgressIndicator(),
              SizedBox(height: AppSpacing.lg),
              Text('Menyiapkan ${AppConfig.appName}'),
            ],
          ),
        ),
      ),
    );
  }
}
