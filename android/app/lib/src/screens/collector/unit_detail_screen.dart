import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../theme/app_status_colors.dart';
import '../../utils/formatters.dart';
import '../../utils/visit_labels.dart';
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
        // Wider than the rows shown, so older visits still waiting for a
        // signature stay reachable (see _shownVisits).
        query: {'unit_id': widget.unitId, 'per_page': 30},
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
    // Block body on purpose: an arrow closure would hand the Future back to
    // setState, which asserts in debug builds and skips the rebuild.
    setState(() {
      _future = _load();
    });
    await _future;
  }

  /// Reopens a visit that still waits for the resident's signature so the
  /// collector can finish it, then reloads the unit so its state is current.
  Future<void> _resumeVisit(
    Map<String, dynamic> unit,
    Map<String, dynamic> visit,
  ) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => VisitFormScreen(
          apiClient: widget.apiClient,
          unit: unit,
          visit: visit,
        ),
      ),
    );
    // Not awaited: a failed reload is shown by the FutureBuilder's error view.
    if (!mounted) return;
    setState(() {
      _future = _load();
    });
  }

  /// The five newest visits plus any older one still waiting for the
  /// resident's signature: this list is where such a visit is resumed, so it
  /// must not drop out as newer visits come in.
  static List<Map<String, dynamic>> _shownVisits(List<dynamic> visits) => [
    for (final (index, visit) in visits.map(asMap).indexed)
      if (index < 5 || visitAwaitingSignature(visit)) visit,
  ];

  Widget _visitLine(Map<String, dynamic> unit, Map<String, dynamic> visit) {
    final awaiting = visitAwaitingSignature(visit);
    return _ListLine(
      title: compact(visit['purpose']),
      subtitle:
          '${dateTime(visit['visit_date'])} — ${visitStatusLabel(visit['status'])}',
      badge: awaiting ? const _AwaitingSignatureChip() : null,
      // Only the signed state is marked. A "Selesai" visit that is neither
      // awaiting nor signed is a legacy one, finished all the same, so it
      // stays neutral as on the web and the supervisor screen.
      trailing:
          !awaiting &&
              visit['status'] == 'completed' &&
              visitHasSignature(visit)
          ? const _SignedBadge()
          : null,
      onTap: awaiting ? () => _resumeVisit(unit, visit) : null,
    );
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
            (billing) =>
                billing['status_id'] != '02' && billing['status_id'] != '04',
          );
          final totalOutstanding = outstanding.fold<double>(
            0,
            (sum, billing) =>
                sum +
                (num.tryParse(
                          asMap(
                                billing['penalty_detail'],
                              )['total_amount']?.toString() ??
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
                _ActionsGrid(
                  apiClient: widget.apiClient,
                  unit: unit,
                  onChanged: _refresh,
                ),
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
                        for (final visit in _shownVisits(result.visits))
                          _visitLine(unit, visit),
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
                builder: (_) => PtpFormScreen(apiClient: apiClient, unit: unit),
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
  const _ListLine({
    required this.title,
    required this.subtitle,
    this.badge,
    this.trailing,
    this.onTap,
  });

  final String title;
  final String subtitle;
  final Widget? badge;
  final Widget? trailing;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final line = Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
                if (badge != null) ...[
                  const SizedBox(height: AppSpacing.xs),
                  badge!,
                ],
              ],
            ),
          ),
          ?trailing,
          if (onTap != null)
            Icon(
              Icons.chevron_right_rounded,
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
        ],
      ),
    );
    if (onTap == null) return line;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
      child: line,
    );
  }
}

/// Marks a "Selesai" visit the resident has signed.
class _SignedBadge extends StatelessWidget {
  const _SignedBadge();

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: visitSignedLabel,
      child: Icon(
        Icons.check_circle,
        size: 18,
        color: Theme.of(context).colorScheme.primary,
      ),
    );
  }
}

/// Marks a "Selesai" visit that is saved but not finished yet because the
/// resident has not signed.
class _AwaitingSignatureChip extends StatelessWidget {
  const _AwaitingSignatureChip();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final pair =
        theme.extension<AppStatusColors>()?.warning ??
        StatusColorPair(
          container: theme.colorScheme.errorContainer,
          onContainer: theme.colorScheme.onErrorContainer,
        );
    return DecoratedBox(
      decoration: BoxDecoration(
        color: pair.container,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.hourglass_top_rounded,
              size: 12,
              color: pair.onContainer,
            ),
            const SizedBox(width: 4),
            Text(
              visitAwaitingSignatureLabel,
              style: TextStyle(
                color: pair.onContainer,
                fontSize: 11,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
