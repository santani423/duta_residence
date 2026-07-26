import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/info_row.dart';
import '../../widgets/state_views.dart';
import '../../widgets/status_badge.dart';

class SupervisorCollectorDetailScreen extends StatefulWidget {
  const SupervisorCollectorDetailScreen({
    required this.apiClient,
    required this.collectorId,
    super.key,
  });

  final ApiClient apiClient;
  final String collectorId;

  @override
  State<SupervisorCollectorDetailScreen> createState() =>
      _SupervisorCollectorDetailScreenState();
}

class _SupervisorCollectorDetailScreenState
    extends State<SupervisorCollectorDetailScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final result = await widget.apiClient.get(
      'supervisor/collectors/${widget.collectorId}',
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
      appBar: AppBar(title: const Text('Detail Kolektor')),
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
          final collector = asMap(data['collector']);
          final profile = asMap(collector['collector_profile']);
          final summary = asMap(data['summary']);
          final location = asMap(data['latest_location']);
          final visits = asList(data['recent_visits']);
          final promises = asList(data['recent_payment_promises']);
          final complaints = asList(data['recent_complaints']);

          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                DutaCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              compact(collector['name']),
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w800),
                            ),
                          ),
                          if (profile['account_status'] != null)
                            StatusBadge(profile['account_status']),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      InfoRows(
                        items: {
                          'Kode Kolektor': profile['collector_code'],
                          'WhatsApp': profile['whatsapp_number'],
                          'Status Kepegawaian': profile['employment_status'],
                          'Lokasi Terakhir': location.isEmpty
                              ? null
                              : dateTime(location['recorded_at']),
                        },
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                DutaCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SectionHeader(title: 'Ringkasan'),
                      const SizedBox(height: AppSpacing.lg),
                      InfoRows(
                        items: {
                          'Total Penghuni': summary['total_residents'],
                          'Total Unit': summary['total_units'],
                          'Total Tunggakan': money(
                            summary['total_outstanding'],
                          ),
                          'Faktur Tertunggak': summary['total_outstanding_invoices'],
                          'Total Terkumpul': money(
                            summary['total_payments_collected'],
                          ),
                          'Total Janji Bayar': summary['total_payment_promises'],
                          'Janji Gagal': summary['broken_promises'],
                          'Total Kunjungan': summary['total_visits'],
                          'Total Komplain': summary['total_complaints'],
                        },
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                DutaCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SectionHeader(title: 'Kunjungan Terakhir'),
                      const SizedBox(height: AppSpacing.md),
                      if (visits.isEmpty)
                        const Text('Belum ada kunjungan tercatat.')
                      else
                        for (final visit in visits.take(5))
                          _ListLine(
                            title: compact(asMap(visit)['purpose']),
                            subtitle:
                                '${dateTime(asMap(visit)['visit_date'])} — ${compact(asMap(visit)['status'])}',
                          ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                DutaCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SectionHeader(title: 'Janji Pembayaran Terakhir'),
                      const SizedBox(height: AppSpacing.md),
                      if (promises.isEmpty)
                        const Text('Belum ada janji pembayaran tercatat.')
                      else
                        for (final promise in promises.take(5))
                          _ListLine(
                            title:
                                '${money(asMap(promise)['promised_amount'])} — ${compact(asMap(asMap(promise)['unit'])['id'])}',
                            subtitle:
                                '${dateOnly(asMap(promise)['promised_date'])} — ${compact(asMap(promise)['status'])}',
                          ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                DutaCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SectionHeader(title: 'Komplain Terakhir'),
                      const SizedBox(height: AppSpacing.md),
                      if (complaints.isEmpty)
                        const Text('Belum ada komplain tercatat.')
                      else
                        for (final complaint in complaints.take(5))
                          _ListLine(
                            title: compact(asMap(complaint)['category']),
                            subtitle:
                                '${dateTime(asMap(complaint)['created_at'])} — ${compact(asMap(complaint)['status'])}',
                          ),
                    ],
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

class _ListLine extends StatelessWidget {
  const _ListLine({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
          Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}
