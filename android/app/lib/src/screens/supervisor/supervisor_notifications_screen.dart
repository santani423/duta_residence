import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../theme/app_status_colors.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/state_views.dart';

const _priorityLabels = {
  'low': 'Rendah',
  'normal': 'Normal',
  'high': 'Tinggi',
  'critical': 'Kritis',
};

const _handledLabels = {
  'open': 'Terbuka',
  'in_progress': 'Diproses',
  'handled': 'Selesai',
  'escalated': 'Dieskalasi',
};

class SupervisorNotificationsScreen extends StatefulWidget {
  const SupervisorNotificationsScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<SupervisorNotificationsScreen> createState() =>
      _SupervisorNotificationsScreenState();
}

class _SupervisorNotificationsScreenState
    extends State<SupervisorNotificationsScreen> {
  late Future<List<dynamic>> _future;
  bool _unhandledOnly = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final result = await widget.apiClient.get(
      'supervisor-notifications',
      query: {'per_page': 50, if (_unhandledOnly) 'unhandled_only': 1},
    );
    return asList(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _markRead(String id) async {
    try {
      await widget.apiClient.postJson('supervisor-notifications/$id/read', {});
      await _refresh();
    } on ApiException {
      // Silent - opening the item to read it should not surface an error.
    }
  }

  Future<void> _markHandled(String id) async {
    try {
      await widget.apiClient.postJson(
        'supervisor-notifications/$id/handled',
        {},
      );
      await _refresh();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _escalate(String id) async {
    final result = await showDialog<Map<String, String>>(
      context: context,
      builder: (_) => const _EscalateDialog(),
    );
    if (result == null || !mounted) return;
    final userId = result['escalated_to'];
    if (userId == null || userId.trim().isEmpty) return;
    try {
      await widget.apiClient.postJson('supervisor-notifications/$id/escalate', {
        'escalated_to': int.tryParse(userId.trim()) ?? userId.trim(),
        if ((result['note'] ?? '').trim().isNotEmpty) 'note': result['note'],
      });
      await _refresh();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  StatusColorPair _priorityColor(BuildContext context, String? priority) {
    final statusColors = Theme.of(context).extension<AppStatusColors>();
    final fallback = StatusColorPair(
      container: Theme.of(context).colorScheme.surfaceContainerHighest,
      onContainer: Theme.of(context).colorScheme.onSurfaceVariant,
    );
    return switch (priority) {
      'critical' => statusColors?.danger ?? fallback,
      'high' => statusColors?.warning ?? fallback,
      'normal' => statusColors?.info ?? fallback,
      _ => statusColors?.neutral ?? fallback,
    };
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.lg,
            AppSpacing.lg,
            AppSpacing.lg,
            0,
          ),
          child: Align(
            alignment: Alignment.centerLeft,
            child: FilterChip(
              label: const Text('Belum Ditangani'),
              selected: _unhandledOnly,
              onSelected: (value) {
                setState(() => _unhandledOnly = value);
                _refresh();
              },
            ),
          ),
        ),
        Expanded(
          child: FutureBuilder<List<dynamic>>(
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
                  separatorBuilder: (_, _) =>
                      const SizedBox(height: AppSpacing.md),
                  itemBuilder: (context, index) {
                    final item = asMap(items[index]);
                    final id = item['id'].toString();
                    final isRead = item['read_status'] == 'read';
                    final isHandled = item['handled_status'] == 'handled';
                    final pair = _priorityColor(
                      context,
                      item['priority']?.toString(),
                    );
                    return DutaCard(
                      onTap: isRead ? null : () => _markRead(id),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: Text(
                                  compact(item['title']),
                                  style: Theme.of(context).textTheme.bodyLarge
                                      ?.copyWith(
                                        fontWeight: isRead
                                            ? FontWeight.w600
                                            : FontWeight.w900,
                                      ),
                                ),
                              ),
                              DecoratedBox(
                                decoration: BoxDecoration(
                                  color: pair.container,
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 10,
                                    vertical: 4,
                                  ),
                                  child: Text(
                                    _priorityLabels[item['priority']] ??
                                        compact(item['priority']),
                                    style: TextStyle(
                                      color: pair.onContainer,
                                      fontSize: 11,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            compact(item['description']),
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            '${_handledLabels[item['handled_status']] ?? compact(item['handled_status'])} · ${dateTime(item['created_at'])}',
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(
                                  color: Theme.of(
                                    context,
                                  ).colorScheme.onSurfaceVariant,
                                ),
                          ),
                          if (!isHandled) ...[
                            const SizedBox(height: AppSpacing.md),
                            Wrap(
                              spacing: AppSpacing.sm,
                              children: [
                                OutlinedButton.icon(
                                  onPressed: () => _markHandled(id),
                                  icon: const Icon(
                                    Icons.check_circle_outline_rounded,
                                    size: 18,
                                  ),
                                  label: const Text('Tandai Selesai'),
                                ),
                                OutlinedButton.icon(
                                  onPressed: () => _escalate(id),
                                  icon: const Icon(
                                    Icons.upgrade_rounded,
                                    size: 18,
                                  ),
                                  label: const Text('Eskalasi'),
                                ),
                              ],
                            ),
                          ],
                        ],
                      ),
                    );
                  },
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _EscalateDialog extends StatefulWidget {
  const _EscalateDialog();

  @override
  State<_EscalateDialog> createState() => _EscalateDialogState();
}

class _EscalateDialogState extends State<_EscalateDialog> {
  final _userIdController = TextEditingController();
  final _noteController = TextEditingController();

  @override
  void dispose() {
    _userIdController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Eskalasi Notifikasi'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            controller: _userIdController,
            keyboardType: TextInputType.number,
            autofocus: true,
            decoration: const InputDecoration(
              labelText: 'ID Pengguna Tujuan',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            controller: _noteController,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Catatan (opsional)',
              border: OutlineInputBorder(),
            ),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(context).pop({
            'escalated_to': _userIdController.text,
            'note': _noteController.text,
          }),
          child: const Text('Eskalasi'),
        ),
      ],
    );
  }
}
