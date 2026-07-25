import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../constants/app_spacing.dart';
import '../utils/formatters.dart';
import '../widgets/duta_card.dart';
import '../widgets/fade_in.dart';
import '../widgets/info_row.dart';
import '../widgets/state_views.dart';
import '../widgets/status_badge.dart';

enum ServiceTab { bills, payments, complaints, documents }

class ServicesScreen extends StatefulWidget {
  const ServicesScreen({
    required this.apiClient,
    this.initialTab = ServiceTab.bills,
    super.key,
  });

  final ApiClient apiClient;
  final ServiceTab initialTab;

  @override
  State<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServiceTabItem {
  const _ServiceTabItem(this.value, this.icon, this.label);

  final ServiceTab value;
  final IconData icon;
  final String label;
}

const _serviceTabs = [
  _ServiceTabItem(ServiceTab.bills, Icons.receipt_long_outlined, 'Tagihan'),
  _ServiceTabItem(ServiceTab.payments, Icons.payments_outlined, 'Bayar'),
  _ServiceTabItem(
    ServiceTab.complaints,
    Icons.report_problem_outlined,
    'Komplain',
  ),
  _ServiceTabItem(ServiceTab.documents, Icons.folder_outlined, 'Dokumen'),
];

class _ServicesScreenState extends State<ServicesScreen> {
  late ServiceTab _tab = widget.initialTab;

  @override
  void didUpdateWidget(covariant ServicesScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.initialTab != oldWidget.initialTab) {
      setState(() => _tab = widget.initialTab);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            0,
            AppSpacing.lg,
            0,
            AppSpacing.sm,
          ),
          child: SizedBox(
            height: 40,
            child: ShaderMask(
              shaderCallback: (rect) => const LinearGradient(
                begin: Alignment.centerLeft,
                end: Alignment.centerRight,
                colors: [
                  Colors.transparent,
                  Colors.black,
                  Colors.black,
                  Colors.transparent,
                ],
                stops: [0.0, 0.03, 0.94, 1.0],
              ).createShader(rect),
              blendMode: BlendMode.dstIn,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
                itemCount: _serviceTabs.length,
                separatorBuilder: (_, _) =>
                    const SizedBox(width: AppSpacing.sm),
                itemBuilder: (context, index) {
                  final item = _serviceTabs[index];
                  return _ServiceTabChip(
                    item: item,
                    selected: item.value == _tab,
                    onTap: () => setState(() => _tab = item.value),
                  );
                },
              ),
            ),
          ),
        ),
        Expanded(
          child: switch (_tab) {
            ServiceTab.bills => _BillsSection(apiClient: widget.apiClient),
            ServiceTab.payments => _PaymentsSection(
              apiClient: widget.apiClient,
            ),
            ServiceTab.complaints => _TicketSection(
              apiClient: widget.apiClient,
              kind: _TicketKind.complaint,
            ),
            ServiceTab.documents => _DocumentsSection(
              apiClient: widget.apiClient,
            ),
          },
        ),
      ],
    );
  }
}

class _ServiceTabChip extends StatelessWidget {
  const _ServiceTabChip({
    required this.item,
    required this.selected,
    required this.onTap,
  });

  final _ServiceTabItem item;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Material(
      color: selected
          ? colors.primary
          : colors.surfaceContainerHighest.withValues(alpha: 0.5),
      borderRadius: BorderRadius.circular(AppSpacing.pill),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppSpacing.pill),
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.lg,
            vertical: AppSpacing.sm,
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                item.icon,
                size: 18,
                color: selected ? colors.onPrimary : colors.onSurfaceVariant,
              ),
              const SizedBox(width: 6),
              Text(
                item.label,
                style: TextStyle(
                  fontWeight: FontWeight.w700,
                  color: selected ? colors.onPrimary : colors.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BillsSection extends StatefulWidget {
  const _BillsSection({required this.apiClient});

  final ApiClient apiClient;

  @override
  State<_BillsSection> createState() => _BillsSectionState();
}

class _BillsSectionState extends State<_BillsSection> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final result = await widget.apiClient.get(
      'resident/bills',
      query: {'per_page': 30},
    );
    return {'items': asList(result.data), 'meta': asMap(result.meta)};
  }

  Future<void> _refresh() async {
    setState(() { _future = _load(); });
    await _future;
  }

  Future<void> _pay(Map<String, dynamic> invoice) async {
    try {
      final config = asMap(
        (await widget.apiClient.get('resident/payment-config')).data,
      );
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        showDragHandle: true,
        builder: (sheetContext) => _PaymentMethodSheet(
          config: config,
          invoice: invoice,
          onSelect: (provider) async {
            Navigator.pop(sheetContext);
            final result = await widget.apiClient.postJson(
              'resident/invoices/${invoice['id']}/payments',
              {'provider': provider},
            );
            final payment = asMap(result.data);
            final paymentUrl = payment['payment_url']?.toString();
            if (paymentUrl != null && paymentUrl.isNotEmpty) {
              await launchUrl(
                Uri.parse(paymentUrl),
                mode: LaunchMode.externalApplication,
              );
            }
            if (mounted) {
              ScaffoldMessenger.of(
                context,
              ).showSnackBar(SnackBar(content: Text(result.message)));
              await _refresh();
            }
          },
        ),
      );
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
    return FutureBuilder<Map<String, dynamic>>(
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
        final items = asList(snapshot.data?['items']);
        final meta = asMap(snapshot.data?['meta']);
        if (items.isEmpty) {
          return const EmptyView(message: 'Tidak ada tagihan.');
        }
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.separated(
            padding: const EdgeInsets.all(AppSpacing.lg),
            itemCount: items.length + 1,
            separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
            itemBuilder: (context, index) {
              if (index == 0) {
                return DutaCard(
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Total Tagihan',
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(
                                  color: Theme.of(
                                    context,
                                  ).colorScheme.onSurfaceVariant,
                                ),
                          ),
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            money(meta['total_outstanding']),
                            style: Theme.of(context).textTheme.titleLarge
                                ?.copyWith(fontWeight: FontWeight.w900),
                          ),
                        ],
                      ),
                      Text(
                        '${compact(meta['unpaid_count'])} invoice',
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                );
              }
              final invoice = asMap(items[index - 1]);
              final canPay = ['unpaid', 'overdue'].contains(invoice['status']);
              return FadeSlideIn(
                index: index,
                child: DutaCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              compact(invoice['invoice_number']),
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w900),
                            ),
                          ),
                          StatusBadge(invoice['status']),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.md),
                      InfoRows(
                        items: {
                          'Jenis': invoice['billing_type'],
                          'Periode': invoice['period'],
                          'Jatuh Tempo': dateOnly(invoice['due_date']),
                          'Subtotal': money(invoice['subtotal']),
                          'Denda': money(invoice['penalty']),
                          'Diskon': money(invoice['discount']),
                          'Total': money(invoice['total']),
                        },
                      ),
                      if (canPay) ...[
                        const SizedBox(height: AppSpacing.lg),
                        SizedBox(
                          width: double.infinity,
                          child: FilledButton.icon(
                            onPressed: () => _pay(invoice),
                            icon: const Icon(Icons.credit_card_rounded),
                            label: const Text('Bayar Tagihan'),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              );
            },
          ),
        );
      },
    );
  }
}

class _PaymentMethodSheet extends StatelessWidget {
  const _PaymentMethodSheet({
    required this.config,
    required this.invoice,
    required this.onSelect,
  });

  final Map<String, dynamic> config;
  final Map<String, dynamic> invoice;
  final Future<void> Function(String provider) onSelect;

  @override
  Widget build(BuildContext context) {
    final methods = asList(
      config['available_methods'] ?? config['enabled_gateways'],
    );
    final active = config['active_gateway']?.toString();
    final available = methods.isEmpty
        ? [?active]
        : methods.map((value) => value.toString()).toList();
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
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Pilih Metode Pembayaran',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(
                '${compact(invoice['invoice_number'])} - ${money(invoice['total'])}',
              ),
              const SizedBox(height: AppSpacing.lg),
              for (final provider in available) ...[
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.tonalIcon(
                    onPressed: () => onSelect(provider),
                    icon: const Icon(Icons.payment_rounded),
                    label: Text(_providerLabel(provider)),
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
              ],
              if (asMap(config['manual_payment']).isNotEmpty) ...[
                const SizedBox(height: AppSpacing.md),
                InfoRows(
                  items: {
                    'Bank': asMap(config['manual_payment'])['bank_name'],
                    'Nomor Rekening': asMap(
                      config['manual_payment'],
                    )['account_number'],
                    'Nama Rekening': asMap(
                      config['manual_payment'],
                    )['account_name'],
                  },
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  String _providerLabel(String provider) => switch (provider) {
    'manual' => 'Manual Transfer',
    'xendit' => 'Xendit',
    'midtrans' => 'Midtrans',
    _ => provider,
  };
}

class _PaymentsSection extends StatefulWidget {
  const _PaymentsSection({required this.apiClient});

  final ApiClient apiClient;

  @override
  State<_PaymentsSection> createState() => _PaymentsSectionState();
}

class _PaymentsSectionState extends State<_PaymentsSection> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final result = await widget.apiClient.get(
      'resident/payments',
      query: {'per_page': 30},
    );
    return asList(result.data);
  }

  Future<void> _refresh() async {
    setState(() { _future = _load(); });
    await _future;
  }

  Future<void> _showManualProof(Map<String, dynamic> payment) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) =>
          _ManualProofSheet(apiClient: widget.apiClient, payment: payment),
    );
    if (saved == true) await _refresh();
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<dynamic>>(
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
        final items = snapshot.data ?? [];
        if (items.isEmpty) {
          return const EmptyView(message: 'Belum ada riwayat pembayaran.');
        }
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.separated(
            padding: const EdgeInsets.all(AppSpacing.lg),
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
            itemBuilder: (context, index) {
              final payment = asMap(items[index]);
              final paymentUrl = payment['payment_url']?.toString();
              final canUpload =
                  payment['payment_gateway'] == 'manual' &&
                  ['pending', 'rejected'].contains(payment['status']);
              return FadeSlideIn(
                index: index,
                child: DutaCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              compact(payment['transaction_number']),
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w900),
                            ),
                          ),
                          StatusBadge(payment['status']),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.md),
                      InfoRows(
                        items: {
                          'Invoice': payment['invoice_number'],
                          'Gateway': payment['payment_gateway'],
                          'Metode': payment['payment_method'],
                          'Nominal': money(payment['total']),
                          'Transaksi': dateTime(payment['created_at']),
                          'Dibayar': dateTime(payment['paid_at']),
                        },
                      ),
                      if ((paymentUrl != null && paymentUrl.isNotEmpty) ||
                          canUpload) ...[
                        const SizedBox(height: AppSpacing.lg),
                        Wrap(
                          spacing: AppSpacing.sm,
                          runSpacing: AppSpacing.sm,
                          children: [
                            if (paymentUrl != null && paymentUrl.isNotEmpty)
                              FilledButton.tonalIcon(
                                onPressed: () => launchUrl(
                                  Uri.parse(paymentUrl),
                                  mode: LaunchMode.externalApplication,
                                ),
                                icon: const Icon(Icons.open_in_new_rounded),
                                label: const Text('Lanjut Bayar'),
                              ),
                            if (canUpload)
                              FilledButton.icon(
                                onPressed: () => _showManualProof(payment),
                                icon: const Icon(Icons.upload_file_rounded),
                                label: const Text('Upload Bukti'),
                              ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              );
            },
          ),
        );
      },
    );
  }
}

class _ManualProofSheet extends StatefulWidget {
  const _ManualProofSheet({required this.apiClient, required this.payment});

  final ApiClient apiClient;
  final Map<String, dynamic> payment;

  @override
  State<_ManualProofSheet> createState() => _ManualProofSheetState();
}

class _ManualProofSheetState extends State<_ManualProofSheet> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _bank = TextEditingController();
  final _account = TextEditingController();
  final _amount = TextEditingController();
  final _notes = TextEditingController();
  PlatformFile? _file;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _amount.text = compact(widget.payment['total']).replaceAll('.0', '');
  }

  @override
  void dispose() {
    _name.dispose();
    _bank.dispose();
    _account.dispose();
    _amount.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _pickFile() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
    );
    if (result != null) setState(() => _file = result.files.single);
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate() || _file == null || _saving) return;
    setState(() => _saving = true);
    try {
      final now = DateTime.now();
      await widget.apiClient.postMultipart(
        'resident/payments/${widget.payment['id']}/manual-proof',
        fileField: 'proof',
        file: _file,
        fields: {
          'sender_name': _name.text.trim(),
          'sender_bank': _bank.text.trim(),
          'sender_account_number': _account.text.trim(),
          'amount': _amount.text.trim(),
          'manual_transfer_date':
              '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}',
          if (_notes.text.trim().isNotEmpty) 'manual_notes': _notes.text.trim(),
        },
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: AppSpacing.lg,
        right: AppSpacing.lg,
        bottom: MediaQuery.viewInsetsOf(context).bottom + AppSpacing.lg,
      ),
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Upload Bukti Manual',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: AppSpacing.lg),
              TextFormField(
                controller: _name,
                decoration: const InputDecoration(labelText: 'Nama pengirim'),
                validator: _required,
              ),
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                controller: _bank,
                decoration: const InputDecoration(labelText: 'Bank pengirim'),
                validator: _required,
              ),
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                controller: _account,
                decoration: const InputDecoration(
                  labelText: 'Nomor rekening pengirim',
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                controller: _amount,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Nominal transfer',
                ),
                validator: _required,
              ),
              const SizedBox(height: AppSpacing.md),
              OutlinedButton.icon(
                onPressed: _pickFile,
                icon: const Icon(Icons.attach_file_rounded),
                label: Text(_file?.name ?? 'Pilih bukti JPG, PNG, atau PDF'),
              ),
              const SizedBox(height: AppSpacing.md),
              TextField(
                controller: _notes,
                minLines: 2,
                maxLines: 4,
                decoration: const InputDecoration(labelText: 'Catatan'),
              ),
              const SizedBox(height: AppSpacing.lg),
              FilledButton(
                onPressed: _saving ? null : _save,
                child: Text(_saving ? 'Mengirim...' : 'Kirim Bukti'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String? _required(String? value) =>
      value == null || value.trim().isEmpty ? 'Wajib diisi.' : null;
}

enum _TicketKind { complaint, maintenance }

class _TicketSection extends StatefulWidget {
  const _TicketSection({required this.apiClient, required this.kind});

  final ApiClient apiClient;
  final _TicketKind kind;

  @override
  State<_TicketSection> createState() => _TicketSectionState();
}

class _TicketSectionState extends State<_TicketSection> {
  late Future<List<dynamic>> _future;

  bool get _isComplaint => widget.kind == _TicketKind.complaint;
  String get _path =>
      _isComplaint ? 'resident/complaints' : 'resident/maintenance-requests';

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final result = await widget.apiClient.get(_path, query: {'per_page': 30});
    return asList(result.data);
  }

  Future<void> _refresh() async {
    setState(() { _future = _load(); });
    await _future;
  }

  Future<void> _create() async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) =>
          _TicketForm(apiClient: widget.apiClient, kind: widget.kind),
    );
    if (saved == true) await _refresh();
  }

  Future<void> _detail(Map<String, dynamic> item) async {
    try {
      final result = await widget.apiClient.get('$_path/${item['id']}');
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        showDragHandle: true,
        builder: (context) => _TicketDetail(
          apiClient: widget.apiClient,
          path: _path,
          item: asMap(result.data),
          isComplaint: _isComplaint,
        ),
      );
      await _refresh();
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
    return Stack(
      children: [
        FutureBuilder<List<dynamic>>(
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
            final items = snapshot.data ?? [];
            if (items.isEmpty) {
              return EmptyView(
                message: _isComplaint
                    ? 'Belum ada komplain.'
                    : 'Belum ada permintaan maintenance.',
              );
            }
            return RefreshIndicator(
              onRefresh: _refresh,
              child: ListView.separated(
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.lg,
                  AppSpacing.lg,
                  AppSpacing.lg,
                  92,
                ),
                itemCount: items.length,
                separatorBuilder: (_, _) =>
                    const SizedBox(height: AppSpacing.md),
                itemBuilder: (context, index) {
                  final item = asMap(items[index]);
                  return FadeSlideIn(
                    index: index,
                    child: DutaCard(
                      onTap: () => _detail(item),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  compact(
                                    _isComplaint
                                        ? item['title']
                                        : item['category'],
                                  ),
                                  style: Theme.of(context).textTheme.titleMedium
                                      ?.copyWith(fontWeight: FontWeight.w900),
                                ),
                              ),
                              StatusBadge(item['status']),
                            ],
                          ),
                          const SizedBox(height: AppSpacing.md),
                          Text(
                            compact(item['description']),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: Theme.of(
                                context,
                              ).colorScheme.onSurfaceVariant,
                            ),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          Text(
                            dateTime(item['created_at']),
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            );
          },
        ),
        Positioned(
          right: AppSpacing.lg,
          bottom: AppSpacing.lg,
          child: FloatingActionButton.extended(
            onPressed: _create,
            icon: const Icon(Icons.add_rounded),
            label: Text(_isComplaint ? 'Komplain' : 'Maintenance'),
          ),
        ),
      ],
    );
  }
}

class _TicketForm extends StatefulWidget {
  const _TicketForm({required this.apiClient, required this.kind});

  final ApiClient apiClient;
  final _TicketKind kind;

  @override
  State<_TicketForm> createState() => _TicketFormState();
}

class _TicketFormState extends State<_TicketForm> {
  final _formKey = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _category = TextEditingController();
  final _description = TextEditingController();
  String _priority = 'normal';
  PlatformFile? _file;
  bool _saving = false;

  bool get _isComplaint => widget.kind == _TicketKind.complaint;

  @override
  void dispose() {
    _title.dispose();
    _category.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<void> _pickFile() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
    );
    if (result != null) setState(() => _file = result.files.single);
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate() || _saving) return;
    setState(() => _saving = true);
    try {
      await widget.apiClient.postMultipart(
        _isComplaint ? 'resident/complaints' : 'resident/maintenance-requests',
        file: _file,
        fields: {
          if (_isComplaint) 'title': _title.text.trim(),
          'category': _category.text.trim(),
          _isComplaint ? 'priority' : 'urgency': _priority,
          'description': _description.text.trim(),
        },
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final priorities = _isComplaint
        ? ['low', 'normal', 'high', 'urgent']
        : ['low', 'normal', 'high', 'emergency'];
    return Padding(
      padding: EdgeInsets.only(
        left: AppSpacing.lg,
        right: AppSpacing.lg,
        bottom: MediaQuery.viewInsetsOf(context).bottom + AppSpacing.lg,
      ),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                _isComplaint ? 'Buat Komplain' : 'Buat Maintenance',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: AppSpacing.lg),
              if (_isComplaint) ...[
                TextFormField(
                  controller: _title,
                  decoration: const InputDecoration(labelText: 'Judul'),
                  validator: _required,
                ),
                const SizedBox(height: AppSpacing.md),
              ],
              TextFormField(
                controller: _category,
                decoration: const InputDecoration(labelText: 'Kategori'),
                validator: _required,
              ),
              const SizedBox(height: AppSpacing.md),
              DropdownButtonFormField<String>(
                initialValue: _priority,
                decoration: InputDecoration(
                  labelText: _isComplaint ? 'Prioritas' : 'Urgensi',
                ),
                items: priorities
                    .map(
                      (value) => DropdownMenuItem(
                        value: value,
                        child: Text(titleCaseStatus(value)),
                      ),
                    )
                    .toList(),
                onChanged: (value) =>
                    setState(() => _priority = value ?? 'normal'),
              ),
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                controller: _description,
                minLines: 4,
                maxLines: 6,
                decoration: const InputDecoration(labelText: 'Deskripsi'),
                validator: _required,
              ),
              const SizedBox(height: AppSpacing.md),
              OutlinedButton.icon(
                onPressed: _pickFile,
                icon: const Icon(Icons.attach_file_rounded),
                label: Text(_file?.name ?? 'Tambah lampiran'),
              ),
              const SizedBox(height: AppSpacing.lg),
              FilledButton(
                onPressed: _saving ? null : _save,
                child: Text(_saving ? 'Menyimpan...' : 'Simpan'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String? _required(String? value) =>
      value == null || value.trim().isEmpty ? 'Wajib diisi.' : null;
}

class _TicketDetail extends StatefulWidget {
  const _TicketDetail({
    required this.apiClient,
    required this.path,
    required this.item,
    required this.isComplaint,
  });

  final ApiClient apiClient;
  final String path;
  final Map<String, dynamic> item;
  final bool isComplaint;

  @override
  State<_TicketDetail> createState() => _TicketDetailState();
}

class _TicketDetailState extends State<_TicketDetail> {
  late Map<String, dynamic> _item;
  final _commentController = TextEditingController();
  final _ratingNotesController = TextEditingController();
  int _ratingValue = 0;
  bool _submittingComment = false;
  bool _closing = false;
  bool _submittingRating = false;

  @override
  void initState() {
    super.initState();
    _item = widget.item;
    _ratingValue = (_item['rating'] as num?)?.toInt() ?? 0;
  }

  @override
  void dispose() {
    _commentController.dispose();
    _ratingNotesController.dispose();
    super.dispose();
  }

  Future<void> _reloadItem() async {
    final result = await widget.apiClient.get(
      '${widget.path}/${_item['id']}',
    );
    if (mounted) setState(() => _item = asMap(result.data));
  }

  void _showError(Object error) {
    if (!mounted) return;
    final message = error is ApiException
        ? error.message
        : 'Terjadi kesalahan. Coba lagi.';
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _submitComment() async {
    final body = _commentController.text.trim();
    if (body.isEmpty || _submittingComment) return;
    setState(() => _submittingComment = true);
    try {
      await widget.apiClient.postMultipart(
        '${widget.path}/${_item['id']}/comments',
        fields: {'body': body},
      );
      _commentController.clear();
      await _reloadItem();
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) setState(() => _submittingComment = false);
    }
  }

  Future<void> _closeComplaint() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Tutup komplain ini?'),
        content: const Text(
          'Komplain yang sudah ditutup tidak dapat dibuka kembali.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Tutup'),
          ),
        ],
      ),
    );
    if (confirmed != true || _closing) return;
    setState(() => _closing = true);
    try {
      await widget.apiClient.postJson(
        '${widget.path}/${_item['id']}/close',
        const {},
      );
      await _reloadItem();
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) setState(() => _closing = false);
    }
  }

  Future<void> _submitRating() async {
    if (_ratingValue < 1 || _submittingRating) return;
    setState(() => _submittingRating = true);
    try {
      await widget.apiClient.postJson(
        '${widget.path}/${_item['id']}/rating',
        {
          'rating': _ratingValue,
          if (_ratingNotesController.text.trim().isNotEmpty)
            'rating_notes': _ratingNotesController.text.trim(),
        },
      );
      await _reloadItem();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Rating berhasil dikirim.')),
        );
      }
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) setState(() => _submittingRating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final status = compact(_item['status']).toLowerCase();
    final canClose = widget.isComplaint && status != 'closed';
    final canRate =
        !widget.isComplaint && status == 'completed' && _item['rating'] == null;

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: AppSpacing.lg,
          right: AppSpacing.lg,
          top: AppSpacing.lg,
          bottom: MediaQuery.viewInsetsOf(context).bottom + AppSpacing.lg,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      compact(
                        widget.isComplaint
                            ? _item['title']
                            : _item['category'],
                      ),
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  StatusBadge(_item['status']),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              InfoRows(
                items: {
                  'Kategori': _item['category'],
                  'Prioritas': _item['priority'] ?? _item['urgency'],
                  'Dibuat': dateTime(_item['created_at']),
                  'Jadwal': dateTime(
                    _item['scheduled_at'] ?? _item['preferred_schedule'],
                  ),
                  'Catatan Petugas': _item['technician_notes'],
                  'Rating': _item['rating'] == null
                      ? null
                      : '${_item['rating']} / 5',
                },
              ),
              const SizedBox(height: AppSpacing.lg),
              Text(compact(_item['description'])),
              if (widget.isComplaint) ...[
                const SizedBox(height: AppSpacing.lg),
                const SectionHeader(title: 'Riwayat Respons'),
                const SizedBox(height: AppSpacing.md),
                if (asList(_item['comments']).isEmpty)
                  Text(
                    'Belum ada respons.',
                    style: TextStyle(color: colors.onSurfaceVariant),
                  )
                else
                  for (final comment in asList(_item['comments']))
                    Padding(
                      padding: const EdgeInsets.symmetric(
                        vertical: AppSpacing.sm,
                      ),
                      child: Text(
                        '${compact(asMap(asMap(comment)['user'])['name'])}: ${compact(asMap(comment)['body'])}',
                      ),
                    ),
                if (status != 'closed') ...[
                  const SizedBox(height: AppSpacing.md),
                  TextField(
                    controller: _commentController,
                    minLines: 2,
                    maxLines: 4,
                    decoration: const InputDecoration(
                      labelText: 'Tulis balasan...',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Align(
                    alignment: Alignment.centerRight,
                    child: FilledButton.icon(
                      onPressed: _submittingComment ? null : _submitComment,
                      icon: const Icon(Icons.send_rounded, size: 18),
                      label: Text(
                        _submittingComment ? 'Mengirim...' : 'Kirim Balasan',
                      ),
                    ),
                  ),
                ],
                if (canClose) ...[
                  const SizedBox(height: AppSpacing.md),
                  OutlinedButton.icon(
                    onPressed: _closing ? null : _closeComplaint,
                    icon: Icon(Icons.check_circle_outline, color: colors.error),
                    label: Text(
                      _closing ? 'Menutup...' : 'Tutup Komplain',
                      style: TextStyle(color: colors.error),
                    ),
                    style: OutlinedButton.styleFrom(
                      side: BorderSide(
                        color: colors.error.withValues(alpha: 0.5),
                      ),
                    ),
                  ),
                ],
              ],
              if (canRate) ...[
                const SizedBox(height: AppSpacing.lg),
                const SectionHeader(title: 'Beri Rating'),
                const SizedBox(height: AppSpacing.md),
                Row(
                  children: [
                    for (var i = 1; i <= 5; i++)
                      IconButton(
                        onPressed: () => setState(() => _ratingValue = i),
                        icon: Icon(
                          i <= _ratingValue
                              ? Icons.star_rounded
                              : Icons.star_outline_rounded,
                          color: i <= _ratingValue
                              ? Colors.amber
                              : colors.onSurfaceVariant,
                          size: 32,
                        ),
                      ),
                  ],
                ),
                TextField(
                  controller: _ratingNotesController,
                  minLines: 2,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    labelText: 'Catatan (opsional)',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                Align(
                  alignment: Alignment.centerRight,
                  child: FilledButton(
                    onPressed: (_ratingValue < 1 || _submittingRating)
                        ? null
                        : _submitRating,
                    child: Text(
                      _submittingRating ? 'Mengirim...' : 'Kirim Rating',
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _DocumentsSection extends StatefulWidget {
  const _DocumentsSection({required this.apiClient});

  final ApiClient apiClient;

  @override
  State<_DocumentsSection> createState() => _DocumentsSectionState();
}

class _DocumentsSectionState extends State<_DocumentsSection> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final result = await widget.apiClient.get('resident/documents');
    return asList(result.data);
  }

  Future<void> _refresh() async {
    setState(() { _future = _load(); });
    await _future;
  }

  Future<void> _download(Map<String, dynamic> document) async {
    try {
      final bytes = await widget.apiClient.downloadDocument(document);
      final directory = await getTemporaryDirectory();
      final filename =
          '${compact(document['reference'] ?? document['name']).replaceAll(RegExp(r'[^A-Za-z0-9_-]'), '_')}.pdf';
      final file = File('${directory.path}/$filename');
      await file.writeAsBytes(bytes, flush: true);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Dokumen tersimpan: ${file.path}')),
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
    return FutureBuilder<List<dynamic>>(
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
        final items = snapshot.data ?? [];
        if (items.isEmpty) {
          return const EmptyView(message: 'Belum ada dokumen.');
        }
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.separated(
            padding: const EdgeInsets.all(AppSpacing.lg),
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
            itemBuilder: (context, index) {
              final document = asMap(items[index]);
              return FadeSlideIn(
                index: index,
                child: DutaCard(
                  child: Row(
                    children: [
                      const IconBadge(icon: Icons.picture_as_pdf_rounded),
                      const SizedBox(width: AppSpacing.md),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              compact(document['name']),
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            Text(
                              '${compact(document['type'])} - ${dateOnly(document['created_at'])}',
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        tooltip: 'Download',
                        onPressed: () => _download(document),
                        icon: const Icon(Icons.download_rounded),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        );
      },
    );
  }
}
