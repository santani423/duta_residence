import 'dart:async';

import 'package:flutter/material.dart';

import '../api/api_client.dart';
import 'notification_detail_screen.dart';
import 'notification_repository.dart';

/// Single entry point for "the user tapped a notification outside the app's own UI":
/// an OS push notification, a local notification, or a deep link. Whatever delivers the
/// event calls [handlePayload]; this class decides *when* it is safe to navigate.
///
/// Expected payload (string-keyed, e.g. an FCM `data` map):
///   `notification_id`  required - id of the row in the user's inbox
///   `source`           optional - `resident` | `staff` | `supervisor`; defaults to the
///                      inbox of the signed-in role
///
/// The three app states are all covered by the same rule - hold the payload until there
/// is a navigator AND an authenticated, unlocked session, then open the detail screen:
///   * foreground / background: the navigator exists and the session is live, so it opens
///     immediately, on top of whatever screen the user is on.
///   * terminated: the platform hands the launch payload to [handlePayload] during
///     startup, before login/bootstrap finishes; it is held and opened once
///     [updateSession] reports the user is ready. If the user must log in first, they
///     land on the notification right after doing so.
///
/// The same notification delivered twice (e.g. FCM `onMessageOpenedApp` plus
/// `getInitialMessage`) is opened once.
class NotificationLinkHandler {
  NotificationLinkHandler({
    required this.navigatorKey,
    required this._apiClient,
    this._dedupeWindow = const Duration(seconds: 5),
  });

  final GlobalKey<NavigatorState> navigatorKey;
  final ApiClient _apiClient;
  final Duration _dedupeWindow;

  bool _sessionReady = false;
  String? _role;
  _Pending? _pending;
  String? _lastOpenedKey;
  DateTime? _lastOpenedAt;

  /// Called whenever auth/lock state changes (see DutaResidenceApp).
  void updateSession({required bool ready, String? role}) {
    _sessionReady = ready;
    _role = role;
    _flush();
  }

  /// Returns false when the payload carries nothing we can open.
  bool handlePayload(Map<String, Object?> payload) {
    final id = (payload['notification_id'] ?? payload['id'])?.toString();
    if (id == null || id.isEmpty) return false;

    final sourceName = payload['source']?.toString();
    _pending = _Pending(id: id, sourceName: sourceName);
    _flush();
    return true;
  }

  void _flush() {
    final pending = _pending;
    final navigator = navigatorKey.currentState;
    if (pending == null || !_sessionReady || navigator == null) return;

    final source =
        NotificationSource.values
            .where((value) => value.name == pending.sourceName)
            .firstOrNull ??
        NotificationSource.forRole(_role);

    _pending = null;

    final key = '${source.name}:${pending.id}';
    final now = DateTime.now();
    if (_lastOpenedKey == key &&
        _lastOpenedAt != null &&
        now.difference(_lastOpenedAt!) < _dedupeWindow) {
      return;
    }
    _lastOpenedKey = key;
    _lastOpenedAt = now;

    unawaited(
      navigator.push(
        MaterialPageRoute<void>(
          builder: (_) => NotificationDetailScreen(
            apiClient: _apiClient,
            source: source,
            notificationId: pending.id,
          ),
        ),
      ),
    );
  }
}

class _Pending {
  const _Pending({required this.id, this.sourceName});

  final String id;
  final String? sourceName;
}
