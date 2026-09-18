import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../constants/app_spacing.dart';
import '../widgets/state_views.dart';
import 'app_notification.dart';
import 'notification_detail_screen.dart';
import 'notification_repository.dart';
import 'notification_tile.dart';

/// The notification center body shared by the resident, collector and supervisor apps.
/// Handles loading / empty / error states, pull-to-refresh, "mark all read", and opens
/// the detail screen on tap. Re-fetches when returning from a detail screen and when the
/// app comes back to the foreground, so read state changed on the web shows up here.
class NotificationListView extends StatefulWidget {
  const NotificationListView({
    required this.apiClient,
    required this.source,
    this.filters = const {},
    this.header,
    this.trailingBuilder,
    super.key,
  });

  final ApiClient apiClient;
  final NotificationSource source;

  /// Extra query params (e.g. supervisor `unhandled_only`). Changing them refetches.
  final Map<String, Object?> filters;
  final Widget? header;
  final Widget? Function(AppNotification notification)? trailingBuilder;

  @override
  State<NotificationListView> createState() => _NotificationListViewState();
}

class _NotificationListViewState extends State<NotificationListView>
    with WidgetsBindingObserver {
  late final NotificationRepository _repository = NotificationRepository(
    widget.apiClient,
    widget.source,
  );
  late Future<NotificationPage> _future = _load();

  Future<NotificationPage> _load() => _repository.list(filters: widget.filters);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didUpdateWidget(covariant NotificationListView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.filters.toString() != widget.filters.toString()) _reload();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _reload();
  }

  void _reload() {
    if (!mounted) return;
    setState(() {
      _future = _load();
    });
  }

  Future<void> _refresh() async {
    _reload();
    try {
      await _future;
    } on Object {
      // Surfaced by the FutureBuilder's error state.
    }
  }

  Future<void> _open(AppNotification notification) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => NotificationDetailScreen(
          apiClient: widget.apiClient,
          source: widget.source,
          notificationId: notification.id,
          initial: notification,
        ),
      ),
    );
    _reload();
  }

  Future<void> _readAll() async {
    try {
      await _repository.markAllRead();
      _reload();
    } on ApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<NotificationPage>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting &&
            !snapshot.hasData) {
          return const LoadingView();
        }
        if (snapshot.hasError) {
          final message = snapshot.error is ApiException
              ? (snapshot.error as ApiException).message
              : snapshot.error.toString();
          return ErrorView(message: message, onRetry: _reload);
        }
        final page = snapshot.data!;
        final items = page.items;
        // Row 0 is the title bar, row 1 the optional caller header, then the items.
        final offset = widget.header == null ? 1 : 2;

        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.separated(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(AppSpacing.lg),
            itemCount: (items.isEmpty ? 1 : items.length) + offset,
            separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
            itemBuilder: (context, index) {
              if (index == 0) return _Header(page: page, onReadAll: _readAll);
              if (index == 1 && widget.header != null) return widget.header!;
              if (items.isEmpty) {
                return const EmptyView(message: 'Belum ada notifikasi.');
              }
              final notification = items[index - offset];
              return NotificationTile(
                notification: notification,
                onTap: () => _open(notification),
                trailing: widget.trailingBuilder?.call(notification),
              );
            },
          ),
        );
      },
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.page, required this.onReadAll});

  final NotificationPage page;
  final VoidCallback onReadAll;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            page.unreadCount > 0
                ? 'Pusat Notifikasi (${page.unreadCount} baru)'
                : 'Pusat Notifikasi',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w900,
              letterSpacing: -0.3,
            ),
          ),
        ),
        FilledButton.tonalIcon(
          onPressed: page.unreadCount > 0 ? onReadAll : null,
          icon: const Icon(Icons.done_all_rounded),
          label: const Text('Tandai'),
        ),
      ],
    );
  }
}
