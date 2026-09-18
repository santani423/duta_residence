import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../constants/app_spacing.dart';
import '../utils/formatters.dart';
import '../widgets/duta_card.dart';
import '../widgets/info_row.dart';
import '../widgets/state_views.dart';
import 'app_notification.dart';
import 'notification_repository.dart';
import 'notification_router.dart';

/// Full notification: title, message, category, time, sender, related context and, when
/// the related record still exists, a button that opens it. Opening the screen marks the
/// notification read; it can be flipped back to unread from here.
///
/// Also the landing screen for push notifications: it only needs a notification id, so
/// a cold-started app can open it from a payload alone (see NotificationLinkHandler).
class NotificationDetailScreen extends StatefulWidget {
  const NotificationDetailScreen({
    required this.apiClient,
    required this.source,
    required this.notificationId,
    this.initial,
    super.key,
  });

  final ApiClient apiClient;
  final NotificationSource source;
  final String notificationId;

  /// Row the user tapped, shown immediately while the full detail loads.
  final AppNotification? initial;

  @override
  State<NotificationDetailScreen> createState() =>
      _NotificationDetailScreenState();
}

class _NotificationDetailScreenState extends State<NotificationDetailScreen> {
  late final NotificationRepository _repository = NotificationRepository(
    widget.apiClient,
    widget.source,
  );
  AppNotification? _notification;
  ApiException? _error;
  bool _loading = true;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _notification = widget.initial;
    _load(markRead: true);
  }

  /// [markRead] is only true for the initial open (and retry): a pull-to-refresh must not
  /// silently undo a deliberate "mark as unread".
  Future<void> _load({bool markRead = false}) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      var notification = await _repository.get(widget.notificationId);
      if (markRead && !notification.isRead) {
        // Opening = reading. If this fails the detail is still shown, just unread.
        try {
          notification = await _repository.markRead(widget.notificationId);
        } on ApiException {
          // Non-fatal.
        }
      }
      if (!mounted) return;
      setState(() {
        _notification = notification;
        _loading = false;
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _loading = false;
      });
    }
  }

  Future<void> _toggleRead() async {
    final current = _notification;
    if (current == null || _busy) return;
    setState(() => _busy = true);
    try {
      final updated = current.isRead
          ? await _repository.markUnread(current.id)
          : await _repository.markRead(current.id);
      if (!mounted) return;
      setState(() => _notification = updated);
    } on ApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Detail Notifikasi')),
      body: _body(context),
    );
  }

  Widget _body(BuildContext context) {
    final notification = _notification;
    if (_error != null && notification == null) {
      // 404 also covers "not yours": the API never confirms other users' ids exist.
      final message = _error!.statusCode == 404
          ? 'Notifikasi tidak ditemukan atau bukan milik akun Anda.'
          : _error!.message;
      return ErrorView(message: message, onRetry: () => _load(markRead: true));
    }
    if (notification == null) return const LoadingView();

    final colors = Theme.of(context).colorScheme;
    final textTheme = Theme.of(context).textTheme;
    final reference = notification.reference;
    final target = resolveNotificationTarget(
      source: widget.source,
      apiClient: widget.apiClient,
      notification: notification,
    );
    final collector = asMap(notification.raw['related_collector'])['name'];

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          if (_loading) const LinearProgressIndicator(),
          DutaCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  notification.title,
                  style: textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: AppSpacing.xs),
                Text(
                  notification.isRead ? 'Sudah dibaca' : 'Belum dibaca',
                  style: textTheme.labelMedium?.copyWith(
                    color: notification.isRead
                        ? colors.onSurfaceVariant
                        : colors.primary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                SelectableText(
                  notification.message,
                  style: textTheme.bodyLarge,
                ),
              ],
            ),
          ),
          if (reference != null && !reference.available) ...[
            const SizedBox(height: AppSpacing.md),
            DutaCard(
              color: colors.errorContainer.withValues(alpha: 0.4),
              child: Row(
                children: [
                  Icon(Icons.info_outline_rounded, color: colors.error),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Text(
                      reference.message ?? 'Data terkait sudah tidak tersedia.',
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.md),
          DutaCard(
            child: InfoRows(
              items: {
                'Kategori': notification.categoryLabel,
                'Tanggal & waktu': dateTime(notification.createdAt),
                if (notification.senderName != null)
                  'Pengirim': notification.senderName,
                'Kolektor terkait': ?collector,
                if (notification.unitId != null) 'Unit': notification.unitId,
                if (widget.source == NotificationSource.supervisor) ...{
                  'Prioritas': notification.raw['priority'],
                  'Status penanganan': notification.raw['handled_status'],
                },
                if (reference != null)
                  'Terkait dengan':
                      '${reference.resourceLabel} #${reference.id}',
              },
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          if (target != null)
            FilledButton.icon(
              onPressed: () => Navigator.of(
                context,
              ).push(MaterialPageRoute(builder: target.builder)),
              icon: const Icon(Icons.open_in_new_rounded),
              label: Text(target.label),
            ),
          const SizedBox(height: AppSpacing.sm),
          OutlinedButton.icon(
            onPressed: _busy ? null : _toggleRead,
            icon: Icon(
              notification.isRead
                  ? Icons.mark_email_unread_outlined
                  : Icons.done_rounded,
            ),
            label: Text(
              notification.isRead ? 'Tandai belum dibaca' : 'Tandai dibaca',
            ),
          ),
        ],
      ),
    );
  }
}
