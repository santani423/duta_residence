import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../constants/app_config.dart';
import '../constants/app_spacing.dart';
import '../utils/formatters.dart';
import '../widgets/duta_card.dart';
import '../widgets/info_row.dart';
import '../widgets/state_views.dart';
import '../widgets/status_badge.dart';

class PropertyScreen extends StatefulWidget {
  const PropertyScreen({required this.apiClient, super.key});

  final ApiClient apiClient;

  @override
  State<PropertyScreen> createState() => _PropertyScreenState();
}

class _PropertyScreenState extends State<PropertyScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final result = await widget.apiClient.get('resident/property');
    return asMap(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
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
        final property = snapshot.data ?? {};
        final colors = Theme.of(context).colorScheme;
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
                        IconBadge(
                          icon: Icons.domain_rounded,
                          size: 56,
                          shape: IconBadgeShape.circle,
                        ),
                        const SizedBox(width: AppSpacing.lg),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                compact(property['unit_label']),
                                style: Theme.of(context).textTheme.headlineSmall
                                    ?.copyWith(
                                      fontWeight: FontWeight.w900,
                                      letterSpacing: -0.3,
                                    ),
                              ),
                              const SizedBox(height: AppSpacing.xs),
                              Text(
                                AppConfig.estateName,
                                style: TextStyle(
                                  color: colors.onSurfaceVariant,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    StatusBadge(property['status']),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              DutaCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SectionHeader(title: 'Detail Properti'),
                    const SizedBox(height: AppSpacing.lg),
                    InfoRows(
                      items: {
                        'Cluster': property['cluster'],
                        'Blok': property['block'],
                        'Nomor Unit': property['lot_number'],
                        'Tipe Properti': property['property_type'],
                        'Status Hunian': property['occupancy'],
                        'Luas Bangunan': property['building_area'],
                        'Luas Tanah': property['land_area'],
                        'Serah Terima': dateOnly(property['handover_date']),
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              _TenantSection(apiClient: widget.apiClient),
            ],
          ),
        );
      },
    );
  }
}

class _TenantSection extends StatefulWidget {
  const _TenantSection({required this.apiClient});

  final ApiClient apiClient;

  @override
  State<_TenantSection> createState() => _TenantSectionState();
}

class _TenantSectionState extends State<_TenantSection> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final result = await widget.apiClient.get('resident/tenant');
    return asMap(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
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

  Future<void> _addTenant() async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => _AddTenantForm(apiClient: widget.apiClient),
    );
    if (saved == true) await _refresh();
  }

  Future<void> _removeTenant() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Hapus penyewa dari unit ini?'),
        content: const Text(
          'Akun penyewa tidak akan dihapus, hanya dilepas dari unit ini.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Hapus'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await widget.apiClient.delete('resident/tenant');
      await _refresh();
    } catch (error) {
      _showError(error);
    }
  }

  Future<void> _setPayer(String value) async {
    try {
      await widget.apiClient.putJson('resident/tenant/billing-payer', {
        'billing_payer': value,
      });
      await _refresh();
    } catch (error) {
      _showError(error);
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const DutaCard(
            child: Padding(
              padding: EdgeInsets.all(AppSpacing.lg),
              child: Center(
                child: SizedBox(
                  width: 24,
                  height: 24,
                  child: CircularProgressIndicator(strokeWidth: 2.6),
                ),
              ),
            ),
          );
        }
        if (snapshot.hasError) {
          final message = snapshot.error is ApiException
              ? (snapshot.error as ApiException).message
              : snapshot.error.toString();
          return DutaCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(message),
                const SizedBox(height: AppSpacing.sm),
                TextButton(
                  onPressed: _refresh,
                  child: const Text('Coba lagi'),
                ),
              ],
            ),
          );
        }
        final data = snapshot.data ?? {};
        final isOwner = data['is_owner'] == true;
        final hasTenant = data['has_tenant'] == true;
        final tenant = asMap(data['tenant']);
        final payer = compact(data['billing_payer']).toLowerCase();

        return DutaCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SectionHeader(title: 'Penyewa & Penanggung Jawab Tagihan'),
              const SizedBox(height: AppSpacing.lg),
              InfoRows(
                items: {
                  'Status Anda': isOwner ? 'Pemilik' : 'Penyewa',
                  'Penanggung Jawab Tagihan': payer == 'penyewa'
                      ? 'Penyewa'
                      : 'Pemilik',
                  'Penyewa Terdaftar': hasTenant
                      ? compact(tenant['name'])
                      : 'Belum ada',
                },
              ),
              if (!isOwner) ...[
                const SizedBox(height: AppSpacing.md),
                Text(
                  'Hanya pemilik unit yang dapat mengubah pengaturan penyewa dan penanggung jawab tagihan.',
                  style: TextStyle(
                    color: colors.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ] else ...[
                const SizedBox(height: AppSpacing.lg),
                Text(
                  'Siapa yang membayar tagihan?',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: AppSpacing.sm),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'pemilik', label: Text('Pemilik')),
                    ButtonSegment(value: 'penyewa', label: Text('Penyewa')),
                  ],
                  selected: {payer == 'penyewa' ? 'penyewa' : 'pemilik'},
                  onSelectionChanged: hasTenant
                      ? (value) => _setPayer(value.first)
                      : null,
                ),
                const SizedBox(height: AppSpacing.lg),
                if (hasTenant)
                  OutlinedButton.icon(
                    onPressed: _removeTenant,
                    icon: Icon(
                      Icons.person_remove_outlined,
                      color: colors.error,
                    ),
                    label: Text(
                      'Hapus Penyewa',
                      style: TextStyle(color: colors.error),
                    ),
                    style: OutlinedButton.styleFrom(
                      side: BorderSide(
                        color: colors.error.withValues(alpha: 0.5),
                      ),
                    ),
                  )
                else
                  FilledButton.icon(
                    onPressed: _addTenant,
                    icon: const Icon(Icons.person_add_alt_1_rounded),
                    label: const Text('Tambah Penyewa'),
                  ),
              ],
            ],
          ),
        );
      },
    );
  }
}

class _AddTenantForm extends StatefulWidget {
  const _AddTenantForm({required this.apiClient});

  final ApiClient apiClient;

  @override
  State<_AddTenantForm> createState() => _AddTenantFormState();
}

class _AddTenantFormState extends State<_AddTenantForm> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _username = TextEditingController();
  bool _saving = false;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    _username.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate() || _saving) return;
    setState(() => _saving = true);
    try {
      final result = await widget.apiClient.postJson('resident/tenant', {
        'name': _name.text.trim(),
        if (_email.text.trim().isNotEmpty) 'email': _email.text.trim(),
        if (_phone.text.trim().isNotEmpty) 'phone': _phone.text.trim(),
        if (_username.text.trim().isNotEmpty)
          'username': _username.text.trim(),
      });
      if (!mounted) return;
      final account = asMap(asMap(result.data)['login_account']);
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Akun login penyewa dibuat'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Sampaikan detail berikut ke penyewa (password ini hanya ditampilkan sekali):',
              ),
              const SizedBox(height: AppSpacing.md),
              Text(
                'Username: ${compact(account['username'])}',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              Text(
                'Password sementara: ${compact(account['temporary_password'])}',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ],
          ),
          actions: [
            FilledButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('OK'),
            ),
          ],
        ),
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
        top: AppSpacing.lg,
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
                'Tambah Penyewa',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: AppSpacing.lg),
              TextFormField(
                controller: _name,
                decoration: const InputDecoration(labelText: 'Nama Penyewa'),
                validator: (value) => value == null || value.trim().isEmpty
                    ? 'Wajib diisi.'
                    : null,
              ),
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                controller: _email,
                decoration: const InputDecoration(
                  labelText: 'Email (opsional)',
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                controller: _phone,
                decoration: const InputDecoration(
                  labelText: 'Telepon (opsional)',
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                controller: _username,
                decoration: const InputDecoration(
                  labelText: 'Username (opsional)',
                ),
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
}
