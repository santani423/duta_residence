import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../constants/app_spacing.dart';
import '../utils/formatters.dart';
import '../widgets/duta_card.dart';
import '../widgets/state_views.dart';
import '../widgets/status_badge.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final result = await widget.apiClient.get(
      'customer/notifications',
      query: {'per_page': 30},
    );
    return asList(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _read(String id) async {
    try {
      await widget.apiClient.postJson('customer/notifications/$id/read', {});
      await _refresh();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _readAll() async {
    try {
      await widget.apiClient.postJson('customer/notifications/read-all', {});
      await _refresh();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const LoadingView();
        }
        if (snapshot.hasError) {
          final message = snapshot.error is ApiException
              ? (snapshot.error as ApiException).message
              : snapshot.error.toString();
          return ErrorView(message: message, onRetry: () => _refresh());
        }
        final items = snapshot.data ?? [];
        if (items.isEmpty) {
          return const EmptyView(message: 'Tidak ada notifikasi.');
        }
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.separated(
            padding: const EdgeInsets.all(AppSpacing.lg),
            itemCount: items.length + 1,
            separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
            itemBuilder: (context, index) {
              if (index == 0) {
                return Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Pusat Notifikasi',
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                    ),
                    FilledButton.tonalIcon(
                      onPressed: _readAll,
                      icon: const Icon(Icons.done_all_rounded),
                      label: const Text('Tandai'),
                    ),
                  ],
                );
              }
              final item = asMap(items[index - 1]);
              final readStatus =
                  item['read_status'] ??
                  (item['read_at'] == null ? 'unread' : 'read');
              return DutaCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            compact(
                              item['title'] ?? item['subject'] ?? item['type'],
                            ),
                            style: Theme.of(context).textTheme.titleMedium
                                ?.copyWith(fontWeight: FontWeight.w900),
                          ),
                        ),
                        StatusBadge(readStatus),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.md),
                    Text(
                      compact(
                        item['message'] ?? item['body'] ?? item['description'],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            dateTime(item['created_at']),
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ),
                        if (readStatus != 'read')
                          TextButton(
                            onPressed: () => _read(compact(item['id'])),
                            child: const Text('Dibaca'),
                          ),
                      ],
                    ),
                  ],
                ),
              );
            },
          ),
        );
      },
    );
  }
}
