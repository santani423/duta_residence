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

class SupervisorTargetsScreen extends StatefulWidget {
  const SupervisorTargetsScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<SupervisorTargetsScreen> createState() =>
      _SupervisorTargetsScreenState();
}

class _SupervisorTargetsScreenState extends State<SupervisorTargetsScreen> {
  String _periodType = 'monthly';
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final now = DateTime.now();
    final periodStart = switch (_periodType) {
      'daily' => now,
      'weekly' => now.subtract(Duration(days: now.weekday - 1)),
      _ => DateTime(now.year, now.month, 1),
    };
    final result = await widget.apiClient.get(
      'supervisor/targets',
      query: {
        'period_type': _periodType,
        'period_start':
            '${periodStart.year.toString().padLeft(4, '0')}-${periodStart.month.toString().padLeft(2, '0')}-${periodStart.day.toString().padLeft(2, '0')}',
      },
    );
    return asList(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Target & Pencapaian Kolektor')),
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
                  return const EmptyView(
                    message: 'Belum ada target untuk periode ini.',
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
                      final row = asMap(items[index]);
                      final collector = asMap(row['collector']);
                      final achievement = asMap(row['achievement']);
                      final percent = achievement['achievement_percent'];
                      return DutaCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              compact(collector['name']),
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: AppSpacing.md),
                            InfoRows(
                              items: {
                                'Terkumpul': money(
                                  achievement['collected_amount'],
                                ),
                                'Target': money(
                                  achievement['target_amount'],
                                ),
                                'Pencapaian': percent == null
                                    ? '-'
                                    : '$percent%',
                                'Jumlah Kunjungan': achievement['visit_count'],
                              },
                            ),
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
      ),
    );
  }
}
