import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/state_views.dart';
import 'supervisor_tunggakan_detail_screen.dart';

/// Cluster-level arrears ("tunggakan") analysis. Clusters have no
/// geo-boundary data, so this list itself doubles as the "heatmap" - rows
/// are sorted by the backend's priority_score, and each row's outstanding
/// percentage is color-graded like a simple heat bar.
class SupervisorTunggakanScreen extends StatefulWidget {
  const SupervisorTunggakanScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<SupervisorTunggakanScreen> createState() =>
      _SupervisorTunggakanScreenState();
}

class _SupervisorTunggakanScreenState
    extends State<SupervisorTunggakanScreen> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final result = await widget.apiClient.get('supervisor/tunggakan');
    return asList(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Color _heatColor(BuildContext context, num percentage) {
    final colors = Theme.of(context).colorScheme;
    if (percentage >= 50) return colors.error;
    if (percentage >= 25) return colors.tertiary;
    return colors.primary;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Analisa Tunggakan')),
      body: FutureBuilder<List<dynamic>>(
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
            return const EmptyView(
              message: 'Belum ada data tunggakan untuk cluster Anda.',
            );
          }
          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView.separated(
              padding: const EdgeInsets.all(AppSpacing.lg),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
              itemBuilder: (context, index) {
                final row = asMap(items[index]);
                final percentage =
                    num.tryParse(row['percentage_unit_menunggak']?.toString() ?? '') ?? 0;
                return DutaCard(
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => SupervisorTunggakanDetailScreen(
                        apiClient: widget.apiClient,
                        clusterId: row['cluster_id'].toString(),
                        clusterName: compact(row['cluster_name']),
                      ),
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              compact(row['cluster_name']),
                              style: const TextStyle(fontWeight: FontWeight.w800),
                            ),
                          ),
                          Text(
                            '${percentage.toStringAsFixed(1)}%',
                            style: TextStyle(
                              fontWeight: FontWeight.w900,
                              color: _heatColor(context, percentage),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(AppSpacing.pill),
                        child: LinearProgressIndicator(
                          value: (percentage / 100).clamp(0, 1).toDouble(),
                          minHeight: 8,
                          color: _heatColor(context, percentage),
                          backgroundColor: Theme.of(
                            context,
                          ).colorScheme.surfaceContainerHighest,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      Text(
                        '${compact(row['unit_menunggak'])} dari ${compact(row['total_unit_aktif'])} unit menunggak — ${money(row['total_tunggakan'])}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                      Text(
                        'Janji Gagal: ${compact(row['broken_promise_count'])} · Komplain: ${compact(row['violation_count'])}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
