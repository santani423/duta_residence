import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/state_views.dart';

class CollectorNotificationsScreen extends StatefulWidget {
  const CollectorNotificationsScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<CollectorNotificationsScreen> createState() =>
      _CollectorNotificationsScreenState();
}

class _CollectorNotificationsScreenState
    extends State<CollectorNotificationsScreen> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final result = await widget.apiClient.get('notifications');
    return asList(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _markRead(String id) async {
    await widget.apiClient.postJson('notifications/$id/read', {});
    await _refresh();
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
          return ErrorView(message: message, onRetry: _refresh);
        }
        final items = snapshot.data ?? const [];
        if (items.isEmpty) {
          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              children: const [
                SizedBox(height: 120),
                EmptyView(message: 'Belum ada notifikasi.'),
              ],
            ),
          );
        }
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.separated(
            padding: const EdgeInsets.all(AppSpacing.lg),
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
            itemBuilder: (context, index) {
              final item = asMap(items[index]);
              final isRead = item['read_at'] != null;
              return DutaCard(
                onTap: () => _markRead(item['id'].toString()),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            compact(item['title'] ?? item['type']),
                            style: Theme.of(context).textTheme.bodyLarge
                                ?.copyWith(
                                  fontWeight: isRead
                                      ? FontWeight.w600
                                      : FontWeight.w900,
                                ),
                          ),
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            compact(item['message'] ?? item['body']),
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            dateTime(item['created_at']),
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(
                                  color: Theme.of(
                                    context,
                                  ).colorScheme.onSurfaceVariant,
                                ),
                          ),
                        ],
                      ),
                    ),
                    if (!isRead)
                      Container(
                        width: 10,
                        height: 10,
                        margin: const EdgeInsets.only(top: 4),
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.primary,
                          shape: BoxShape.circle,
                        ),
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
