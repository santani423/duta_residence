import 'dart:async';

import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/info_row.dart';
import '../../widgets/state_views.dart';
import '../../widgets/status_badge.dart';

class PaymentCollectionScreen extends StatefulWidget {
  const PaymentCollectionScreen({
    required this.apiClient,
    required this.unit,
    super.key,
  });

  final ApiClient apiClient;
  final Map<String, dynamic> unit;

  @override
  State<PaymentCollectionScreen> createState() =>
      _PaymentCollectionScreenState();
}

class _PaymentCollectionScreenState extends State<PaymentCollectionScreen> {
  late Future<Map<String, dynamic>> _future;
  final _amountController = TextEditingController();
  Timer? _debounce;
  bool _useBalance = true;
  Map<String, dynamic>? _preview;
  String _method = 'C';
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
    _amountController.addListener(_onAmountChanged);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _amountController.dispose();
    super.dispose();
  }

  Future<Map<String, dynamic>> _load() async {
    final result = await widget.apiClient.get(
      'payments/search',
      query: {'unit_id': widget.unit['id']},
    );
    final unit = asMap(result.data);
    // Preview once on load (amount empty = 0) so a unit balance alone is
    // already reflected before the collector types anything.
    unawaited(_updatePreview());
    return unit;
  }

  void _onAmountChanged() {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), _updatePreview);
  }

  double _enteredAmount() =>
      double.tryParse(_amountController.text.trim()) ?? 0;

  Future<void> _updatePreview() async {
    try {
      final result = await widget.apiClient.postJson('payments/preview', {
        'unit_id': widget.unit['id'],
        'amount': _enteredAmount(),
        'use_balance': _useBalance,
      });
      if (mounted) setState(() => _preview = asMap(result.data));
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _process() async {
    setState(() => _busy = true);
    try {
      final result = await widget.apiClient.postJson('payments/process', {
        'unit_id': widget.unit['id'],
        'amount': _enteredAmount(),
        'use_balance': _useBalance,
        'payment_method_id': _method,
      });
      final receipt = asMap(result.data);
      if (mounted) {
        final balanceUsed = num.tryParse(
              receipt['balance_used']?.toString() ?? '',
            ) ??
            0;
        final overpayment = num.tryParse(
              receipt['deposit_amount']?.toString() ?? '',
            ) ??
            0;
        showDialog(
          context: context,
          builder: (_) => AlertDialog(
            title: const Text('Pembayaran Berhasil'),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Nomor kwitansi: ${compact(receipt['number'])}'),
                const SizedBox(height: AppSpacing.sm),
                Text('Total dialokasikan: ${money(receipt['grand_total'])}'),
                if (balanceUsed > 0)
                  Text('Saldo digunakan: ${money(balanceUsed)}'),
                if (overpayment > 0)
                  Text(
                    'Kelebihan pembayaran (masuk saldo): ${money(overpayment)}',
                  ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () {
                  Navigator.of(context).pop();
                  Navigator.of(context).pop();
                },
                child: const Text('Tutup'),
              ),
            ],
          ),
        );
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Proses Pembayaran')),
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
            return ErrorView(
              message: message,
              onRetry: () => setState(() => _future = _load()),
            );
          }
          final unit = snapshot.data ?? const <String, dynamic>{};
          final billings = asList(unit['billings']).map(asMap).toList();
          final currentBalance = unit['deposit_balance'];
          final totalOutstanding = unit['total_outstanding'];
          final upcomingBills = unit['total_upcoming'];

          return ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              DutaCard(
                child: InfoRows(
                  items: {
                    'Saldo Customer': money(currentBalance),
                    'Total Tunggakan': money(totalOutstanding),
                    'Tagihan Mendatang': money(upcomingBills),
                  },
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              if (billings.isEmpty)
                const EmptyView(
                  message: 'Tidak ada tagihan yang bisa dibayar untuk unit ini.',
                )
              else
                ...billings.map((billing) {
                  final penalty = asMap(billing['penalty_detail']);
                  return Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.md),
                    child: DutaCard(
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${compact(billing['year'])}-${compact(billing['month'])}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const SizedBox(height: AppSpacing.xs),
                                Text(
                                  'Sisa: ${money(penalty['total_outstanding'] ?? billing['amount'])}',
                                ),
                              ],
                            ),
                          ),
                          StatusBadge(penalty['status']),
                        ],
                      ),
                    ),
                  );
                }),
              const SizedBox(height: AppSpacing.md),
              TextField(
                controller: _amountController,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: false,
                ),
                decoration: const InputDecoration(
                  labelText: 'Nominal Pembayaran (Rp)',
                  border: OutlineInputBorder(),
                ),
              ),
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                value: _useBalance,
                onChanged: (value) {
                  setState(() => _useBalance = value ?? true);
                  _updatePreview();
                },
                title: Text('Gunakan saldo customer (${money(currentBalance)})'),
                controlAffinity: ListTileControlAffinity.leading,
              ),
              if (_preview != null) ...[
                const SizedBox(height: AppSpacing.sm),
                DutaCard(
                  child: InfoRows(
                    items: {
                      'Nominal Pembayaran': money(_preview!['payment_amount']),
                      'Teralokasi': money(_preview!['amount_allocated']),
                      'Saldo Digunakan': money(_preview!['balance_used']),
                      'Kelebihan (masuk saldo)': money(_preview!['overpayment']),
                      'Sisa Tunggakan': money(_preview!['remaining_outstanding']),
                      'Saldo Baru': money(_preview!['new_balance']),
                    },
                  ),
                ),
              ],
              const SizedBox(height: AppSpacing.lg),
              SegmentedButton<String>(
                segments: const [
                  ButtonSegment(value: 'C', label: Text('Tunai')),
                  ButtonSegment(value: 'D', label: Text('Debit/Transfer')),
                ],
                selected: {_method},
                onSelectionChanged: (value) =>
                    setState(() => _method = value.first),
              ),
              const SizedBox(height: AppSpacing.md),
              FilledButton.icon(
                onPressed:
                    (_busy || !((_preview?['amount_allocated'] ?? 0) > 0))
                        ? null
                        : _process,
                icon: const Icon(Icons.payments_outlined),
                label: const Text('Proses Pembayaran'),
              ),
            ],
          );
        },
      ),
    );
  }
}
