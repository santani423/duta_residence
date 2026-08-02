import 'package:flutter/material.dart';

import '../constants/app_assets.dart';
import '../widgets/site_identity_scope.dart';

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
    final appName = SiteIdentityScope.of(context).appName;
    return Align(
      alignment: alignment,
      child: Image.asset(
        AppAssets.logo,
        height: height,
        fit: BoxFit.contain,
        filterQuality: FilterQuality.high,
        semanticLabel: appName,
        errorBuilder: (context, error, stackTrace) {
          return Text(
            appName,
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
          );
        },
      ),
    );
  }
}
