import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../screens/services_screen.dart';
import '../screens/supervisor/supervisor_approval_detail_screen.dart';
import '../screens/supervisor/supervisor_collector_detail_screen.dart';
import '../utils/formatters.dart';
import 'app_notification.dart';
import 'notification_repository.dart';

class NotificationTarget {
  const NotificationTarget({required this.label, required this.builder});

  final String label;
  final WidgetBuilder builder;
}

/// Maps a notification's backend `reference` (resource key + id) onto a screen of this
/// app. The web app does the same with its own routes, so the rule "payment
/// notification -> payment" is decided by the backend once and only the last hop is
/// per-client. Returns null when there is nothing to open (no reference, the record
/// was deleted, or this app has no screen for it) - the detail screen still opens.
NotificationTarget? resolveNotificationTarget({
  required NotificationSource source,
  required ApiClient apiClient,
  required AppNotification notification,
}) {
  final reference = notification.reference;
  if (reference == null || !reference.available) return null;

  NotificationTarget serviceTab(String label, ServiceTab tab) =>
      NotificationTarget(
        label: 'Buka ${reference.resourceLabel}',
        builder: (_) => Scaffold(
          appBar: AppBar(title: Text(label)),
          body: ServicesScreen(apiClient: apiClient, initialTab: tab),
        ),
      );

  switch (source) {
    case NotificationSource.resident:
      return switch (reference.resource) {
        'payment' => serviceTab('Pembayaran', ServiceTab.payments),
        'invoice' => serviceTab('Tagihan', ServiceTab.bills),
        'receipt' => serviceTab('Dokumen', ServiceTab.documents),
        'complaint' => serviceTab('Komplain', ServiceTab.complaints),
        _ => null,
      };
    case NotificationSource.supervisor:
      switch (reference.resource) {
        case 'approval':
          return NotificationTarget(
            label: 'Buka ${reference.resourceLabel}',
            builder: (_) => SupervisorApprovalDetailScreen(
              apiClient: apiClient,
              approvalId: reference.id,
            ),
          );
        case 'collector':
          final collectorId = asMap(
            notification.raw['related_collector'],
          )['id'];
          if (collectorId == null) return null;
          return NotificationTarget(
            label: 'Buka ${reference.resourceLabel}',
            builder: (_) => SupervisorCollectorDetailScreen(
              apiClient: apiClient,
              collectorId: collectorId.toString(),
            ),
          );
      }
      return null;
    case NotificationSource.staff:
      return null;
  }
}
