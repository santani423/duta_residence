import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/info_row.dart';
import '../../widgets/state_views.dart';

class SupervisorTunggakanDetailScreen extends StatefulWidget {
  const SupervisorTunggakanDetailScreen({
    required this.apiClient,
    required this.clusterId,
    required this.clusterName,
    super.key,
  });

  final ApiClient apiClient;
  final String clusterId;
  final String clusterName;

  @override
  State<SupervisorTunggakanDetailScreen> createState() =>
      _SupervisorTunggakanDetailScreenState();
}

class _SupervisorTunggakanDetailScreenState
    extends State<SupervisorTunggakanDetailScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final result = await widget.apiClient.get(
      'supervisor/tunggakan/${widget.clusterId}',
    );
    return asMap(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.clusterName)),
      body: FutureBuilder<Map<String, dynamic>>(
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
          final data = snapshot.data ?? {};
          final analysis = asMap(data['analysis']);
          final units = asList(data['units']);

          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                DutaCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SectionHeader(title: 'Ringkasan Cluster'),
                      const SizedBox(height: AppSpacing.lg),
                      InfoRows(
                        items: {
                          'Total Unit Aktif': analysis['total_unit_aktif'],
                          'Unit Menunggak': analysis['unit_menunggak'],
                          'Persentase Menunggak':
                              '${compact(analysis['percentage_unit_menunggak'])}%',
                          'Total Tunggakan': money(analysis['total_tunggakan']),
                          'Janji Gagal': analysis['broken_promise_count'],
                          'Komplain': analysis['violation_count'],
                        },
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                Text(
                  'Unit Menunggak',
                  style: Theme.of(
                    context,
                  ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: AppSpacing.md),
                if (units.isEmpty)
                  const Text('Tidak ada unit yang menunggak di cluster ini.')
                else
                  for (final row in units)
                    Padding(
                      padding: const EdgeInsets.only(bottom: AppSpacing.md),
                      child: DutaCard(
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    compact(asMap(asMap(row)['unit'])['id']),
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                  Text(
                                    compact(
                                      asMap(asMap(asMap(row)['unit'])['resident'])['name'],
                                    ),
                                    style: Theme.of(context).textTheme.bodySmall,
                                  ),
                                  Text(
                                    '${compact(asMap(row)['invoice_count'])} faktur tertunggak',
                                    style: Theme.of(context).textTheme.bodySmall,
                                  ),
                                ],
                              ),
                            ),
                            Text(
                              money(asMap(row)['total_outstanding']),
                              style: const TextStyle(fontWeight: FontWeight.w800),
                            ),
                          ],
                        ),
                      ),
                    ),
              ],
            ),
          );
        },
      ),
    );
  }
}
