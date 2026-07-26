import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/state_views.dart';
import '../../widgets/status_badge.dart';

const _statusLabels = {
  'pending': 'Menunggu',
  'fulfilled': 'Terpenuhi',
  'broken': 'Tidak Terpenuhi',
  'rescheduled': 'Dijadwalkan Ulang',
};

class SupervisorPaymentPromisesScreen extends StatefulWidget {
  const SupervisorPaymentPromisesScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<SupervisorPaymentPromisesScreen> createState() =>
      _SupervisorPaymentPromisesScreenState();
}

class _SupervisorPaymentPromisesScreenState
    extends State<SupervisorPaymentPromisesScreen> {
  late Future<List<dynamic>> _future;
  String? _status;
  bool _nearingDue = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final result = await widget.apiClient.get(
      'supervisor/payment-promises',
      query: {
        'per_page': 50,
        if (_status != null) 'status': _status,
        if (_nearingDue) 'nearing_due': 1,
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
      appBar: AppBar(title: const Text('Janji Pembayaran')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.lg,
              AppSpacing.lg,
              AppSpacing.lg,
              0,
            ),
            child: Wrap(
              spacing: AppSpacing.sm,
              runSpacing: AppSpacing.sm,
              children: [
                FilterChip(
                  label: const Text('Segera Jatuh Tempo'),
                  selected: _nearingDue,
                  onSelected: (value) {
                    setState(() => _nearingDue = value);
                    _refresh();
                  },
                ),
                for (final entry in _statusLabels.entries)
                  ChoiceChip(
                    label: Text(entry.value),
                    selected: _status == entry.key,
                    onSelected: (selected) {
                      setState(() => _status = selected ? entry.key : null);
                      _refresh();
                    },
                  ),
              ],
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
                        EmptyView(message: 'Belum ada janji pembayaran.'),
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
                      final unit = asMap(item['unit']);
                      return DutaCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    '${compact(unit['id'])} — ${compact(asMap(unit['cluster'])['name'])}',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ),
                                if (item['status'] != null)
                                  StatusBadge(item['status']),
                              ],
                            ),
                            Text(
                              compact(asMap(unit['resident'])['name']),
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                            const SizedBox(height: AppSpacing.xs),
                            Text(
                              money(item['promised_amount']),
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            Text(
                              'Janji: ${dateOnly(item['promised_date'])}',
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
          ),
        ],
      ),
    );
  }
}
