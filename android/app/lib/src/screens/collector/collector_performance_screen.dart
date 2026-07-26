import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/info_row.dart';
import '../../widgets/state_views.dart';

const _periods = [
  ('daily', 'Harian'),
  ('weekly', 'Mingguan'),
  ('monthly', 'Bulanan'),
];

class CollectorPerformanceScreen extends StatefulWidget {
  const CollectorPerformanceScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<CollectorPerformanceScreen> createState() =>
      _CollectorPerformanceScreenState();
}

class _CollectorPerformanceScreenState
    extends State<CollectorPerformanceScreen> {
  String _periodType = 'monthly';
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final now = DateTime.now();
    final periodStart = switch (_periodType) {
      'daily' => now,
      'weekly' => now.subtract(Duration(days: now.weekday - 1)),
      _ => DateTime(now.year, now.month, 1),
    };
    final result = await widget.apiClient.get(
      'collector-performance/me',
      query: {
        'period_type': _periodType,
        'period_start':
            '${periodStart.year.toString().padLeft(4, '0')}-${periodStart.month.toString().padLeft(2, '0')}-${periodStart.day.toString().padLeft(2, '0')}',
      },
    );
    return asMap(result.data);
  }

  void _refresh() {
    setState(() => _future = _load());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Target & Performa')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: SegmentedButton<String>(
              segments: [
                for (final (value, label) in _periods)
                  ButtonSegment(value: value, label: Text(label)),
              ],
              selected: {_periodType},
              onSelectionChanged: (value) {
                setState(() => _periodType = value.first);
                _refresh();
              },
            ),
          ),
          Expanded(
            child: FutureBuilder<Map<String, dynamic>>(
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
                final achievement = data['achievement_percent'];
                return ListView(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  children: [
                    DutaCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const SectionHeader(title: 'Ringkasan'),
                          const SizedBox(height: AppSpacing.lg),
                          InfoRows(
                            items: {
                              'Terkumpul': money(data['collected_amount']),
                              'Target': money(data['target_amount']),
                              'Pencapaian': achievement == null
                                  ? '-'
                                  : '$achievement%',
                              'Jumlah Kunjungan': compact(
                                data['visit_count'],
                              ),
                              'Target Kunjungan': compact(
                                data['target_visit_count'],
                              ),
                            },
                          ),
                        ],
                      ),
                    ),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
