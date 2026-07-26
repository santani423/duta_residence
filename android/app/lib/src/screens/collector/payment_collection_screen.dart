import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/state_views.dart';

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
  late Future<List<dynamic>> _future;
  final Set<String> _selected = {};
  Map<String, dynamic>? _preview;
  String _method = 'C';
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final result = await widget.apiClient.get(
      'payments/search',
      query: {'unit_id': widget.unit['id']},
    );
    final unit = asMap(result.data);
    return asList(unit['billings']);
  }

  Future<void> _updatePreview() async {
    if (_selected.isEmpty) {
      setState(() => _preview = null);
      return;
    }
    try {
      final result = await widget.apiClient.postJson('payments/preview', {
        'unit_id': widget.unit['id'],
        'billing_ids': _selected.map(int.parse).toList(),
      });
      setState(() => _preview = asMap(result.data));
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _process() async {
    if (_selected.isEmpty) return;
    setState(() => _busy = true);
    try {
      final result = await widget.apiClient.postJson('payments/process', {
        'unit_id': widget.unit['id'],
        'billing_ids': _selected.map(int.parse).toList(),
        'payment_method_id': _method,
      });
      final receipt = asMap(result.data);
      if (mounted) {
        showDialog(
          context: context,
          builder: (_) => AlertDialog(
            title: const Text('Pembayaran Berhasil'),
            content: Text(
              'Nomor kwitansi: ${compact(receipt['receipt_number'] ?? receipt['id'])}',
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
            return ErrorView(
              message: message,
              onRetry: () => setState(() => _future = _load()),
            );
          }
          final billings = (snapshot.data ?? const []).map(asMap).toList();
          if (billings.isEmpty) {
            return const EmptyView(
              message: 'Tidak ada tagihan yang bisa dibayar untuk unit ini.',
            );
          }
          return Column(
            children: [
              Expanded(
                child: ListView.separated(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  itemCount: billings.length,
                  separatorBuilder: (_, _) =>
                      const SizedBox(height: AppSpacing.md),
                  itemBuilder: (context, index) {
                    final billing = billings[index];
                    final id = billing['id'].toString();
                    final penalty = asMap(billing['penalty_detail']);
                    return DutaCard(
                      onTap: () {
                        setState(() {
                          if (_selected.contains(id)) {
                            _selected.remove(id);
                          } else {
                            _selected.add(id);
                          }
                        });
                        _updatePreview();
                      },
                      child: Row(
                        children: [
                          Checkbox(
                            value: _selected.contains(id),
                            onChanged: (_) {
                              setState(() {
                                if (_selected.contains(id)) {
                                  _selected.remove(id);
                                } else {
                                  _selected.add(id);
                                }
                              });
                              _updatePreview();
                            },
                          ),
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
                                Text(money(penalty['total_amount'] ?? billing['amount'])),
                              ],
                            ),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),
              if (_preview != null)
                Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: AppSpacing.lg,
                  ),
                  child: DutaCard(
                    child: Row(
                      children: [
                        const Expanded(child: Text('Total Dibayar')),
                        Text(
                          money(_preview!['total']),
                          style: const TextStyle(fontWeight: FontWeight.w900),
                        ),
                      ],
                    ),
                  ),
                ),
              Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: Column(
                  children: [
                    SegmentedButton<String>(
                      segments: const [
                        ButtonSegment(value: 'C', label: Text('Tunai')),
                        ButtonSegment(
                          value: 'D',
                          label: Text('Debit/Transfer'),
                        ),
                      ],
                      selected: {_method},
                      onSelectionChanged: (value) =>
                          setState(() => _method = value.first),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    FilledButton.icon(
                      onPressed: (_selected.isEmpty || _busy) ? null : _process,
                      icon: const Icon(Icons.payments_outlined),
                      label: const Text('Proses Pembayaran'),
                    ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
