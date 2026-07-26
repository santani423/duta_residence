import 'dart:io';

import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/state_views.dart';
import '../../widgets/status_badge.dart';

const _periods = [
  ('daily', 'Harian'),
  ('weekly', 'Mingguan'),
  ('monthly', 'Bulanan'),
];

class SupervisorReportsScreen extends StatefulWidget {
  const SupervisorReportsScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<SupervisorReportsScreen> createState() =>
      _SupervisorReportsScreenState();
}

class _SupervisorReportsScreenState extends State<SupervisorReportsScreen> {
  late Future<List<dynamic>> _future;
  bool _requesting = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final result = await widget.apiClient.get(
      'report-exports',
      query: {'per_page': 30},
    );
    return asList(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _requestReport() async {
    final periodType = await showDialog<String>(
      context: context,
      builder: (_) => const _RequestReportDialog(),
    );
    if (periodType == null || !mounted) return;
    setState(() => _requesting = true);
    try {
      final now = DateTime.now();
      final periodStart = switch (periodType) {
        'daily' => now,
        'weekly' => now.subtract(Duration(days: now.weekday - 1)),
        _ => DateTime(now.year, now.month, 1),
      };
      await widget.apiClient.postJson('report-exports', {
        'type': 'collector_performance',
        'period_type': periodType,
        'period_start':
            '${periodStart.year.toString().padLeft(4, '0')}-${periodStart.month.toString().padLeft(2, '0')}-${periodStart.day.toString().padLeft(2, '0')}',
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Permintaan laporan sedang diproses.')),
        );
      }
      await _refresh();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _requesting = false);
    }
  }

  Future<void> _download(Map<String, dynamic> report) async {
    try {
      final bytes = await widget.apiClient.downloadBytes(
        'report-exports/${report['id']}/download',
      );
      final directory = await getTemporaryDirectory();
      final file = File('${directory.path}/laporan-${report['id']}.csv');
      await file.writeAsBytes(bytes, flush: true);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Laporan tersimpan: ${file.path}')),
        );
      }
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
    return Scaffold(
      appBar: AppBar(
        title: const Text('Laporan'),
        actions: [
          IconButton(
            icon: _requesting
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.add_rounded),
            onPressed: _requesting ? null : _requestReport,
          ),
        ],
      ),
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
            return RefreshIndicator(
              onRefresh: _refresh,
              child: ListView(
                children: const [
                  SizedBox(height: 120),
                  EmptyView(
                    message:
                        'Belum ada laporan. Ketuk tombol + untuk membuat laporan baru.',
                  ),
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
                final report = asMap(items[index]);
                final isCompleted = report['status'] == 'completed';
                return DutaCard(
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    compact(report['type']),
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ),
                                if (report['status'] != null)
                                  StatusBadge(report['status']),
                              ],
                            ),
                            const SizedBox(height: AppSpacing.xs),
                            Text(
                              dateTime(report['created_at']),
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.download_outlined),
                        onPressed: isCompleted ? () => _download(report) : null,
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

class _RequestReportDialog extends StatefulWidget {
  const _RequestReportDialog();

  @override
  State<_RequestReportDialog> createState() => _RequestReportDialogState();
}

class _RequestReportDialogState extends State<_RequestReportDialog> {
  String _periodType = 'monthly';

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Buat Laporan Performa Kolektor'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          DropdownButtonFormField<String>(
            initialValue: _periodType,
            items: [
              for (final (value, label) in _periods)
                DropdownMenuItem(value: value, child: Text(label)),
            ],
            onChanged: (value) =>
                setState(() => _periodType = value ?? _periodType),
            decoration: const InputDecoration(labelText: 'Periode'),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(_periodType),
          child: const Text('Buat'),
        ),
      ],
    );
  }
}
