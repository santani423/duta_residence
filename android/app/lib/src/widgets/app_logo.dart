import 'package:flutter/material.dart';

import '../constants/app_assets.dart';
import '../constants/app_config.dart';

class AppLogo extends StatelessWidget {
  const AppLogo({
    this.height = 58,
    this.alignment = Alignment.centerLeft,
    super.key,
  });

  final double height;
  final Alignment alignment;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: alignment,
      child: Image.asset(
        AppAssets.logo,
        height: height,
        fit: BoxFit.contain,
        errorBuilder: (context, error, stackTrace) {
          return Text(
            AppConfig.appName,
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
          );
        },
      ),
    );
  }
}
