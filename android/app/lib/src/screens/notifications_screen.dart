import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../notifications/notification_list_view.dart';
import '../notifications/notification_repository.dart';

class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  Widget build(BuildContext context) {
    return NotificationListView(
      apiClient: apiClient,
      source: NotificationSource.resident,
    );
  }
}
