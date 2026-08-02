import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../constants/app_config.dart';

/// Fetches the admin-editable app/company name (`SiteSetting.site_name` on
/// the backend) so it can replace the hardcoded `AppConfig.appName` strings
/// across the app. Public endpoint - no auth token required.
class SiteIdentityController extends ChangeNotifier {
  SiteIdentityController({required this._apiClient});

  final ApiClient _apiClient;
  String _appName = AppConfig.appName;

  String get appName => _appName;

  Future<void> bootstrap() async {
    try {
      final result = await _apiClient.get('site-identity');
      final name = (result.data as Map?)?['site_name']?.toString();
      if (name != null && name.trim().isNotEmpty) {
        _appName = name;
        notifyListeners();
      }
    } catch (_) {
      // Public, best-effort lookup - keep the AppConfig fallback silently on
      // failure, same resilience pattern as SessionController.bootstrap().
    }
  }
}
