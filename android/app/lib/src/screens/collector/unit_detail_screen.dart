import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/info_row.dart';
import '../../widgets/state_views.dart';
import 'collection_letters_screen.dart';
import 'collector_reminder_screen.dart';
import 'payment_collection_screen.dart';
import 'ptp_form_screen.dart';
import 'visit_form_screen.dart';

class UnitDetailScreen extends StatefulWidget {
  const UnitDetailScreen({
    required this.apiClient,
    required this.unitId,
    super.key,
  });

  final ApiClient apiClient;
  final String unitId;

  @override
  State<UnitDetailScreen> createState() => _UnitDetailScreenState();
}

class _UnitDetailScreenState extends State<UnitDetailScreen> {
  late Future<_UnitDetailData> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_UnitDetailData> _load() async {
    final unitResult = await widget.apiClient.get('units/${widget.unitId}');
    final unit = asMap(unitResult.data);
    final residentId = unit['resident_id']?.toString();

    List<dynamic> visits = const [];
    List<dynamic> promises = const [];
    if (residentId != null) {
      final visitsResult = await widget.apiClient.get(
        'residents/$residentId/visits',
        query: {'unit_id': widget.unitId, 'per_page': 10},
      );
      visits = asList(visitsResult.data);
      final promisesResult = await widget.apiClient.get(
        'residents/$residentId/payment-promises',
        query: {'unit_id': widget.unitId, 'per_page': 10},
      );
      promises = asList(promisesResult.data);
    }
    return _UnitDetailData(unit: unit, visits: visits, promises: promises);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.unitId)),
      body: FutureBuilder<_UnitDetailData>(
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
          final result = snapshot.data!;
          final unit = result.unit;
          final resident = asMap(unit['resident']);
          final billings = asList(unit['billings']).map(asMap).toList();
          final outstanding = billings.where(
            (billing) => billing['status_id'] != '02' && billing['status_id'] != '04',
          );
          final totalOutstanding = outstanding.fold<double>(
            0,
            (sum, billing) =>
                sum +
                (num.tryParse(
                          asMap(billing['penalty_detail'])['total_amount']
                                  ?.toString() ??
                              '',
                        ) ??
                        0)
                    .toDouble(),
          );

          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                DutaCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SectionHeader(title: 'Informasi Unit'),
                      const SizedBox(height: AppSpacing.lg),
                      InfoRows(
                        items: {
                          'Unit': unit['id'],
                          'Cluster': asMap(unit['cluster'])['name'],
                          'Blok': '${unit['block']}-${unit['lot_number']}',
                          'Penghuni': resident['name'],
                          'Telepon': resident['phone'],
                          'Total Tunggakan': money(totalOutstanding),
                        },
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                _ActionsGrid(apiClient: widget.apiClient, unit: unit, onChanged: _refresh),
                const SizedBox(height: AppSpacing.lg),
                DutaCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SectionHeader(title: 'Kunjungan Terakhir'),
                      const SizedBox(height: AppSpacing.md),
                      if (result.visits.isEmpty)
                        const Text('Belum ada kunjungan tercatat.')
                      else
                        for (final visit in result.visits.take(5))
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
                      const SectionHeader(title: 'Janji Pembayaran'),
                      const SizedBox(height: AppSpacing.md),
                      if (result.promises.isEmpty)
                        const Text('Belum ada janji pembayaran tercatat.')
                      else
                        for (final promise in result.promises.take(5))
                          _ListLine(
                            title: money(asMap(promise)['promised_amount']),
                            subtitle:
                                '${dateOnly(asMap(promise)['promised_date'])} — ${compact(asMap(promise)['status'])}',
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

class _UnitDetailData {
  const _UnitDetailData({
    required this.unit,
    required this.visits,
    required this.promises,
  });

  final Map<String, dynamic> unit;
  final List<dynamic> visits;
  final List<dynamic> promises;
}

class _ActionsGrid extends StatelessWidget {
  const _ActionsGrid({
    required this.apiClient,
    required this.unit,
    required this.onChanged,
  });

  final ApiClient apiClient;
  final Map<String, dynamic> unit;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: AppSpacing.md,
      runSpacing: AppSpacing.md,
      children: [
        _ActionButton(
          icon: Icons.event_note_outlined,
          label: 'Catat Kunjungan',
          onTap: () async {
            await Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) =>
                    VisitFormScreen(apiClient: apiClient, unit: unit),
              ),
            );
            onChanged();
          },
        ),
        _ActionButton(
          icon: Icons.handshake_outlined,
          label: 'Janji Bayar',
          onTap: () async {
            await Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) =>
                    PtpFormScreen(apiClient: apiClient, unit: unit),
              ),
            );
            onChanged();
          },
        ),
        _ActionButton(
          icon: Icons.payments_outlined,
          label: 'Proses Bayar',
          onTap: () async {
            await Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) =>
                    PaymentCollectionScreen(apiClient: apiClient, unit: unit),
              ),
            );
            onChanged();
          },
        ),
        _ActionButton(
          icon: Icons.chat_outlined,
          label: 'Pengingat WA',
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) =>
                  CollectorReminderScreen(apiClient: apiClient, unit: unit),
            ),
          ),
        ),
        _ActionButton(
          icon: Icons.description_outlined,
          label: 'Surat Penagihan',
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => CollectionLettersScreen(
                apiClient: apiClient,
                unitId: unit['id'].toString(),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
      child: SizedBox(
        width: 84,
        child: Column(
          children: [
            IconBadge(icon: icon),
            const SizedBox(height: AppSpacing.xs),
            Text(
              label,
              textAlign: TextAlign.center,
              maxLines: 2,
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 11),
            ),
          ],
        ),
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
