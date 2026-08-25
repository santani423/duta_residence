import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../constants/app_spacing.dart';
import '../theme/app_status_colors.dart';
import '../utils/formatters.dart';
import '../widgets/duta_card.dart';
import '../widgets/fade_in.dart';
import '../widgets/info_row.dart';
import '../widgets/state_views.dart';

const _balanceTypeLabels = {
  'overpayment': 'Kelebihan Pembayaran',
  'refund_credit': 'Kredit Refund',
  'manual_credit': 'Penyesuaian Kredit',
  'balance_usage': 'Penggunaan Saldo',
  'manual_debit': 'Penyesuaian Debit',
  'refund_debit': 'Debit Refund',
  'reversal': 'Reversal',
};

String _balanceTypeLabel(Object? value) =>
    _balanceTypeLabels[value?.toString()] ?? titleCaseStatus(value);

class BalanceScreen extends StatefulWidget {
  const BalanceScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<BalanceScreen> createState() => _BalanceScreenState();
}

class _BalanceScreenState extends State<BalanceScreen> {
  late Future<_BalanceData> _future;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_BalanceData> _load() async {
    final summary = asMap(
      (await widget.apiClient.get('resident/balance')).data,
    );
    final ledger = asList(
      (await widget.apiClient.get(
        'resident/balance/ledger',
        query: {'per_page': 30},
      )).data,
    );
    return _BalanceData(
      available: (summary['available_balance'] as num?)?.toDouble() ?? 0,
      ledger: ledger,
    );
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _useBalance() async {
    try {
      final previewResult = await widget.apiClient.postJson(
        'resident/balance/preview',
        const {},
      );
      if (!mounted) return;
      final confirmed = await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        showDragHandle: true,
        builder: (context) => _UseBalanceSheet(
          preview: asMap(previewResult.data),
        ),
      );
      if (confirmed != true || _submitting) return;
      setState(() => _submitting = true);
      final result = await widget.apiClient.postJson(
        'resident/balance/use',
        const {},
      );
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(result.message)));
        await _refresh();
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Saldo Saya')),
      body: FutureBuilder<_BalanceData>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const LoadingView();
          }
          if (snapshot.hasError) {
            final message = snapshot.error is ApiException
                ? (snapshot.error as ApiException).message
                : snapshot.error.toString();
            return ErrorView(message: message, onRetry: () => _refresh());
          }
          final data = snapshot.data!;
          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                _BalanceHero(
                  available: data.available,
                  onUse: _submitting ? null : _useBalance,
                  busy: _submitting,
                ),
                const SizedBox(height: AppSpacing.lg),
                const SectionHeader(title: 'Riwayat Saldo'),
                const SizedBox(height: AppSpacing.md),
                if (data.ledger.isEmpty)
                  const EmptyView(message: 'Belum ada riwayat saldo.')
                else
                  for (var index = 0; index < data.ledger.length; index++)
                    Padding(
                      padding: const EdgeInsets.only(bottom: AppSpacing.md),
                      child: FadeSlideIn(
                        index: index,
                        child: _LedgerCard(entry: asMap(data.ledger[index])),
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

class _BalanceData {
  const _BalanceData({required this.available, required this.ledger});

  final double available;
  final List<dynamic> ledger;
}

class _BalanceHero extends StatelessWidget {
  const _BalanceHero({
    required this.available,
    required this.onUse,
    required this.busy,
  });

  final double available;
  final VoidCallback? onUse;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [colors.primary, colors.primary.withValues(alpha: 0.82)],
        ),
        borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
        boxShadow: [
          BoxShadow(
            color: colors.primary.withValues(alpha: 0.28),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Saldo Tersedia',
            style: TextStyle(
              color: colors.onPrimary.withValues(alpha: 0.85),
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            money(available),
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
              fontWeight: FontWeight.w900,
              color: colors.onPrimary,
              letterSpacing: -0.3,
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: available > 0 ? onUse : null,
              style: FilledButton.styleFrom(
                backgroundColor: colors.onPrimary,
                foregroundColor: colors.primary,
              ),
              icon: const Icon(Icons.payments_rounded),
              label: Text(
                busy ? 'Memproses...' : 'Gunakan Saldo untuk Bayar Tagihan',
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _LedgerCard extends StatelessWidget {
  const _LedgerCard({required this.entry});

  final Map<String, dynamic> entry;

  @override
  Widget build(BuildContext context) {
    final isCredit = compact(entry['direction']) == 'credit';
    final statusColors = Theme.of(context).extension<AppStatusColors>();
    final pair = isCredit
        ? statusColors?.success
        : statusColors?.danger;
    final container =
        pair?.container ?? Theme.of(context).colorScheme.surfaceContainerHighest;
    final onContainer =
        pair?.onContainer ?? Theme.of(context).colorScheme.onSurfaceVariant;

    return DutaCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(
            icon: isCredit
                ? Icons.call_received_rounded
                : Icons.call_made_rounded,
            background: container,
            foreground: onContainer,
            size: 40,
            iconSize: 20,
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _balanceTypeLabel(entry['type']),
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 2),
                Text(
                  dateTime(entry['created_at']),
                  style: TextStyle(
                    fontSize: 12,
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
                if (compact(entry['receipt_number']) != '-') ...[
                  const SizedBox(height: 2),
                  Text(
                    compact(entry['receipt_number']),
                    style: TextStyle(
                      fontSize: 12,
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Text(
            '${isCredit ? '+' : '-'}${money(entry['amount'])}',
            style: TextStyle(fontWeight: FontWeight.w900, color: onContainer),
          ),
        ],
      ),
    );
  }
}

class _UseBalanceSheet extends StatelessWidget {
  const _UseBalanceSheet({required this.preview});

  final Map<String, dynamic> preview;

  @override
  Widget build(BuildContext context) {
    final allocations = asList(preview['allocations']);
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: AppSpacing.lg,
          right: AppSpacing.lg,
          bottom: MediaQuery.viewInsetsOf(context).bottom + AppSpacing.lg,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Gunakan Saldo untuk Bayar Tagihan',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: AppSpacing.lg),
              InfoRows(
                items: {
                  'Total Tagihan Aktif': money(preview['total_outstanding']),
                  'Saldo Tersedia': money(preview['balance_available']),
                  'Saldo yang Digunakan': money(preview['balance_used']),
                  'Sisa Tagihan Setelah Saldo': money(
                    preview['remaining_outstanding'],
                  ),
                  'Saldo Setelah Transaksi': money(preview['new_balance']),
                },
              ),
              if (allocations.isEmpty) ...[
                const SizedBox(height: AppSpacing.lg),
                Text(
                  'Tidak ada tagihan aktif yang dapat dibayar saat ini.',
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
              ] else ...[
                const SizedBox(height: AppSpacing.lg),
                const SectionHeader(title: 'Tagihan yang Akan Dibayar'),
                const SizedBox(height: AppSpacing.sm),
                for (final row in allocations)
                  Padding(
                    padding: const EdgeInsets.symmetric(
                      vertical: AppSpacing.xs,
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Periode ${asMap(row)['year']}-${asMap(row)['month'].toString().padLeft(2, '0')}',
                        ),
                        Text(
                          money(asMap(row)['total_amount']),
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ],
                    ),
                  ),
              ],
              const SizedBox(height: AppSpacing.lg),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.pop(context, false),
                      child: const Text('Batal'),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: FilledButton(
                      onPressed: allocations.isEmpty
                          ? null
                          : () => Navigator.pop(context, true),
                      child: const Text('Konfirmasi & Bayar'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
