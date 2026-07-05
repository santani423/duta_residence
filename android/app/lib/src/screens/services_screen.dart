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
import '../widgets/info_row.dart';
import '../widgets/state_views.dart';
import '../widgets/status_badge.dart';

enum ServiceTab { bills, payments, complaints, maintenance, documents }

class ServicesScreen extends StatefulWidget {
  const ServicesScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServicesScreenState extends State<ServicesScreen> {
  ServiceTab _tab = ServiceTab.bills;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.lg,
            AppSpacing.lg,
            AppSpacing.lg,
            AppSpacing.sm,
          ),
          child: SegmentedButton<ServiceTab>(
            selected: {_tab},
            onSelectionChanged: (value) => setState(() => _tab = value.first),
            segments: const [
              ButtonSegment(
                value: ServiceTab.bills,
                icon: Icon(Icons.receipt_long_outlined),
                label: Text('Tagihan'),
              ),
              ButtonSegment(
                value: ServiceTab.payments,
                icon: Icon(Icons.payments_outlined),
                label: Text('Bayar'),
              ),
              ButtonSegment(
                value: ServiceTab.complaints,
                icon: Icon(Icons.report_problem_outlined),
                label: Text('Komplain'),
              ),
              ButtonSegment(
                value: ServiceTab.maintenance,
                icon: Icon(Icons.handyman_outlined),
                label: Text('Maintenance'),
              ),
              ButtonSegment(
                value: ServiceTab.documents,
                icon: Icon(Icons.folder_outlined),
                label: Text('Dokumen'),
              ),
            ],
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
            ServiceTab.maintenance => _TicketSection(
              apiClient: widget.apiClient,
              kind: _TicketKind.maintenance,
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

class _BillsSection extends StatefulWidget {
  const _BillsSection({required this.apiClient});

  final ApiClient apiClient;

  @override
  State<_BillsSection> createState() => _BillsSectionState();
}

class _BillsSectionState extends State<_BillsSection> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final result = await widget.apiClient.get(
      'customer/bills',
      query: {'per_page': 30},
    );
    return asList(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _pay(Map<String, dynamic> invoice) async {
    try {
      final config = asMap(
        (await widget.apiClient.get('customer/payment-config')).data,
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
              'customer/invoices/${invoice['id']}/payments',
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
          return const EmptyView(message: 'Tidak ada tagihan.');
        }
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.separated(
            padding: const EdgeInsets.all(AppSpacing.lg),
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
            itemBuilder: (context, index) {
              final invoice = asMap(items[index]);
              final canPay = ['unpaid', 'overdue'].contains(invoice['status']);
              return DutaCard(
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
        padding: const EdgeInsets.all(AppSpacing.lg),
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
      'customer/payments',
      query: {'per_page': 30},
    );
    return asList(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
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
              return DutaCard(
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
        'customer/payments/${widget.payment['id']}/manual-proof',
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
      _isComplaint ? 'customer/complaints' : 'customer/maintenance-requests';

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
    setState(() => _future = _load());
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
        showDragHandle: true,
        builder: (context) =>
            _TicketDetail(item: asMap(result.data), isComplaint: _isComplaint),
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
                  return DutaCard(
                    child: InkWell(
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
        _isComplaint ? 'customer/complaints' : 'customer/maintenance-requests',
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

class _TicketDetail extends StatelessWidget {
  const _TicketDetail({required this.item, required this.isComplaint});

  final Map<String, dynamic> item;
  final bool isComplaint;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      compact(isComplaint ? item['title'] : item['category']),
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  StatusBadge(item['status']),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              InfoRows(
                items: {
                  'Kategori': item['category'],
                  'Prioritas': item['priority'] ?? item['urgency'],
                  'Dibuat': dateTime(item['created_at']),
                  'Jadwal': dateTime(
                    item['scheduled_at'] ?? item['preferred_schedule'],
                  ),
                  'Catatan Petugas': item['technician_notes'],
                  'Rating': item['rating'],
                },
              ),
              const SizedBox(height: AppSpacing.lg),
              Text(compact(item['description'])),
              if (isComplaint && asList(item['comments']).isNotEmpty) ...[
                const SizedBox(height: AppSpacing.lg),
                const SectionHeader(title: 'Riwayat Respons'),
                const SizedBox(height: AppSpacing.md),
                for (final comment in asList(item['comments']))
                  Padding(
                    padding: const EdgeInsets.symmetric(
                      vertical: AppSpacing.sm,
                    ),
                    child: Text(
                      '${compact(asMap(asMap(comment)['user'])['name'])}: ${compact(asMap(comment)['body'])}',
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
    final result = await widget.apiClient.get('customer/documents');
    return asList(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
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
              return DutaCard(
                child: Row(
                  children: [
                    const Icon(Icons.picture_as_pdf_outlined),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            compact(document['name']),
                            style: const TextStyle(fontWeight: FontWeight.w900),
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
              );
            },
          ),
        );
      },
    );
  }
}
