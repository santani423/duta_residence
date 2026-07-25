import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../constants/app_spacing.dart';
import '../state/session_controller.dart';
import '../state/theme_controller.dart';
import '../utils/formatters.dart';
import '../widgets/duta_card.dart';
import '../widgets/info_row.dart';
import '../widgets/state_views.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({
    required this.apiClient,
    required this.sessionController,
    required this.themeController,
    super.key,
  });

  final ApiClient apiClient;
  final SessionController sessionController;
  final ThemeController themeController;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final result = await widget.apiClient.get('resident/account');
    return asMap(result.data);
  }

  Future<void> _refresh() async {
    setState(() { _future = _load(); });
    await _future;
  }

  Future<void> _editProfile(Map<String, dynamic> account) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) =>
          _EditProfileForm(apiClient: widget.apiClient, initial: account),
    );
    if (saved == true) await _refresh();
  }

  Future<void> _changePassword() async {
    await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => _ChangePasswordForm(apiClient: widget.apiClient),
    );
  }

  Future<void> _logout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Keluar dari aplikasi?'),
        content: const Text(
          'Anda perlu login kembali untuk mengakses layanan penghuni.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Keluar'),
          ),
        ],
      ),
    );
    if (confirmed == true) await widget.sessionController.logout();
  }

  Future<void> _saveSettings(ThemeMode mode) async {
    try {
      await widget.themeController.setMode(mode);
      final settings = asMap(
        (await widget.apiClient.get('resident/settings')).data,
      );
      await widget.apiClient.putJson('resident/settings', {
        'theme_preference': _themeValue(mode),
        'language_preference': settings['language_preference'] ?? 'id',
        'notification_preferences':
            asMap(settings['notification_preferences']).isEmpty
            ? {
                'billing': true,
                'payments': true,
                'complaints': true,
                'maintenance': true,
                'documents': true,
                'announcements': true,
              }
            : asMap(settings['notification_preferences']),
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Pengaturan tema disimpan.')),
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
        final data = snapshot.data ?? {};
        final account = asMap(data['account']);
        final property = asMap(data['property']);
        final security = asMap(data['security']);
        final colors = Theme.of(context).colorScheme;
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              DutaCard(
                child: Column(
                  children: [
                    Container(
                      width: 76,
                      height: 76,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        gradient: LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [
                            colors.primary,
                            colors.primary.withValues(alpha: 0.7),
                          ],
                        ),
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        compact(account['name']).substring(0, 1).toUpperCase(),
                        style: Theme.of(context).textTheme.headlineMedium
                            ?.copyWith(
                              fontWeight: FontWeight.w900,
                              color: colors.onPrimary,
                            ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    Text(
                      compact(account['name']),
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      compact(account['email']),
                      style: TextStyle(
                        color: colors.onSurfaceVariant,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    OutlinedButton.icon(
                      onPressed: () => _editProfile(account),
                      icon: const Icon(Icons.edit_outlined, size: 18),
                      label: const Text('Edit Profil'),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              DutaCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SectionHeader(title: 'Informasi Pengguna'),
                    const SizedBox(height: AppSpacing.lg),
                    InfoRows(
                      items: {
                        'Nomor Penghuni': account['resident_number'],
                        'Telepon': account['phone'],
                        'Alamat': account['address'],
                        'Unit': property['unit_label'],
                        'Bergabung': dateOnly(account['joined_at']),
                        'Login Terakhir': dateTime(security['last_login_at']),
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              DutaCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SectionHeader(title: 'Tema Aplikasi'),
                    const SizedBox(height: AppSpacing.md),
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: SegmentedButton<ThemeMode>(
                        showSelectedIcon: false,
                        selected: {widget.themeController.mode},
                        onSelectionChanged: (value) =>
                            _saveSettings(value.first),
                        segments: const [
                          ButtonSegment(
                            value: ThemeMode.light,
                            label: Text('Terang'),
                          ),
                          ButtonSegment(
                            value: ThemeMode.dark,
                            label: Text('Gelap'),
                          ),
                          ButtonSegment(
                            value: ThemeMode.system,
                            label: Text('Ikuti Sistem'),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              _NotificationPreferencesCard(apiClient: widget.apiClient),
              const SizedBox(height: AppSpacing.lg),
              DutaCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SectionHeader(title: 'Keamanan Akun'),
                    const SizedBox(height: AppSpacing.md),
                    _BiometricSettingTile(
                      sessionController: widget.sessionController,
                    ),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const IconBadge(
                        icon: Icons.lock_outline_rounded,
                        size: 40,
                        iconSize: 20,
                      ),
                      title: const Text(
                        'Ganti Password',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      trailing: const Icon(Icons.chevron_right_rounded),
                      onTap: _changePassword,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              DutaCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SectionHeader(title: 'Tentang'),
                    const SizedBox(height: AppSpacing.md),
                    _AboutTile(
                      icon: Icons.info_outline_rounded,
                      title: 'Duta Residence',
                      subtitle: 'Aplikasi layanan penghuni',
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    _AboutTile(
                      icon: Icons.privacy_tip_outlined,
                      title: 'Kebijakan Privasi',
                      subtitle: 'Mengikuti kebijakan pengelola estate.',
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    _AboutTile(
                      icon: Icons.article_outlined,
                      title: 'Syarat dan Ketentuan',
                      subtitle: 'Mengikuti aturan layanan perumahan.',
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              OutlinedButton.icon(
                onPressed: _logout,
                icon: Icon(Icons.logout_rounded, color: colors.error),
                label: Text('Keluar', style: TextStyle(color: colors.error)),
                style: OutlinedButton.styleFrom(
                  side: BorderSide(color: colors.error.withValues(alpha: 0.5)),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  String _themeValue(ThemeMode mode) => switch (mode) {
    ThemeMode.light => 'light',
    ThemeMode.dark => 'dark',
    ThemeMode.system => 'system',
  };
}

class _AboutTile extends StatelessWidget {
  const _AboutTile({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Row(
      children: [
        IconBadge(icon: icon, size: 40, iconSize: 20),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
              Text(
                subtitle,
                style: TextStyle(color: colors.onSurfaceVariant, fontSize: 13),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _NotificationPreferencesCard extends StatefulWidget {
  const _NotificationPreferencesCard({required this.apiClient});

  final ApiClient apiClient;

  @override
  State<_NotificationPreferencesCard> createState() =>
      _NotificationPreferencesCardState();
}

class _NotificationPreferencesCardState
    extends State<_NotificationPreferencesCard> {
  static const _labels = {
    'billing': 'Tagihan',
    'payments': 'Pembayaran',
    'complaints': 'Komplain',
    'maintenance': 'Maintenance',
    'documents': 'Dokumen',
    'announcements': 'Pengumuman',
  };

  late Future<Map<String, dynamic>> _future;
  Map<String, dynamic> _preferences = {};
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final result = await widget.apiClient.get('resident/settings');
    final data = asMap(result.data);
    final preferences = asMap(data['notification_preferences']);
    _preferences = preferences.isEmpty
        ? {for (final key in _labels.keys) key: true}
        : preferences;
    return data;
  }

  Future<void> _toggle(String key, bool value) async {
    final current = await _future;
    setState(() {
      _preferences = {..._preferences, key: value};
      _saving = true;
    });
    try {
      await widget.apiClient.putJson('resident/settings', {
        'theme_preference': current['theme_preference'] ?? 'system',
        'language_preference': current['language_preference'] ?? 'id',
        'notification_preferences': _preferences,
      });
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
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const DutaCard(
            child: Padding(
              padding: EdgeInsets.all(AppSpacing.lg),
              child: LinearProgressIndicator(),
            ),
          );
        }
        if (snapshot.hasError) return const SizedBox.shrink();
        return DutaCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SectionHeader(title: 'Preferensi Notifikasi'),
              const SizedBox(height: AppSpacing.sm),
              for (final entry in _labels.entries)
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(entry.value),
                  value: _preferences[entry.key] == true,
                  onChanged: _saving
                      ? null
                      : (value) => _toggle(entry.key, value),
                ),
            ],
          ),
        );
      },
    );
  }
}

class _EditProfileForm extends StatefulWidget {
  const _EditProfileForm({required this.apiClient, required this.initial});

  final ApiClient apiClient;
  final Map<String, dynamic> initial;

  @override
  State<_EditProfileForm> createState() => _EditProfileFormState();
}

class _EditProfileFormState extends State<_EditProfileForm> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _phone;
  late final TextEditingController _email;
  late final TextEditingController _address;
  PlatformFile? _photo;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: widget.initial['name']?.toString());
    _phone = TextEditingController(text: widget.initial['phone']?.toString());
    _email = TextEditingController(text: widget.initial['email']?.toString());
    _address = TextEditingController(
      text: widget.initial['address']?.toString(),
    );
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _email.dispose();
    _address.dispose();
    super.dispose();
  }

  Future<void> _pickPhoto() async {
    final result = await FilePicker.platform.pickFiles(type: FileType.image);
    if (result != null) setState(() => _photo = result.files.single);
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate() || _saving) return;
    setState(() => _saving = true);
    try {
      await widget.apiClient.postMultipart(
        'resident/profile',
        fields: {
          'name': _name.text.trim(),
          if (_phone.text.trim().isNotEmpty) 'phone': _phone.text.trim(),
          if (_email.text.trim().isNotEmpty) 'email': _email.text.trim(),
          if (_address.text.trim().isNotEmpty)
            'id_card_address': _address.text.trim(),
        },
        file: _photo,
        fileField: 'photo',
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
                'Edit Profil',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: AppSpacing.lg),
              TextFormField(
                controller: _name,
                decoration: const InputDecoration(labelText: 'Nama'),
                validator: (value) => value == null || value.trim().isEmpty
                    ? 'Wajib diisi.'
                    : null,
              ),
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                controller: _phone,
                decoration: const InputDecoration(labelText: 'Telepon'),
              ),
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                controller: _email,
                decoration: const InputDecoration(labelText: 'Email'),
              ),
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                controller: _address,
                minLines: 2,
                maxLines: 3,
                decoration: const InputDecoration(labelText: 'Alamat KTP'),
              ),
              const SizedBox(height: AppSpacing.md),
              OutlinedButton.icon(
                onPressed: _pickPhoto,
                icon: const Icon(Icons.image_outlined),
                label: Text(_photo?.name ?? 'Ganti Foto Profil (opsional)'),
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

class _ChangePasswordForm extends StatefulWidget {
  const _ChangePasswordForm({required this.apiClient});

  final ApiClient apiClient;

  @override
  State<_ChangePasswordForm> createState() => _ChangePasswordFormState();
}

class _ChangePasswordFormState extends State<_ChangePasswordForm> {
  final _formKey = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _next = TextEditingController();
  final _confirm = TextEditingController();
  bool _obscure = true;
  bool _saving = false;

  @override
  void dispose() {
    _current.dispose();
    _next.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate() || _saving) return;
    setState(() => _saving = true);
    try {
      await widget.apiClient.postJson('auth/change-password', {
        'current_password': _current.text,
        'new_password': _next.text,
        'new_password_confirmation': _confirm.text,
      });
      if (mounted) {
        Navigator.pop(context, true);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Password berhasil diubah.')),
        );
      }
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
                'Ganti Password',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: AppSpacing.lg),
              TextFormField(
                controller: _current,
                obscureText: _obscure,
                decoration: const InputDecoration(labelText: 'Password Saat Ini'),
                validator: (value) =>
                    value == null || value.isEmpty ? 'Wajib diisi.' : null,
              ),
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                controller: _next,
                obscureText: _obscure,
                decoration: const InputDecoration(labelText: 'Password Baru'),
                validator: (value) => value == null || value.length < 8
                    ? 'Minimal 8 karakter.'
                    : null,
              ),
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                controller: _confirm,
                obscureText: _obscure,
                decoration: const InputDecoration(
                  labelText: 'Konfirmasi Password Baru',
                ),
                validator: (value) =>
                    value != _next.text ? 'Konfirmasi tidak cocok.' : null,
              ),
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                value: !_obscure,
                controlAffinity: ListTileControlAffinity.leading,
                title: const Text('Tampilkan password'),
                onChanged: (value) =>
                    setState(() => _obscure = !(value ?? false)),
              ),
              const SizedBox(height: AppSpacing.md),
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

class _BiometricSettingTile extends StatefulWidget {
  const _BiometricSettingTile({required this.sessionController});

  final SessionController sessionController;

  @override
  State<_BiometricSettingTile> createState() => _BiometricSettingTileState();
}

class _BiometricSettingTileState extends State<_BiometricSettingTile> {
  late final Future<bool> _supported;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _supported = widget.sessionController.canUseBiometrics();
  }

  Future<void> _toggle(bool value) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      if (value) {
        final confirmed = await widget.sessionController
            .unlockWithBiometrics();
        if (!confirmed) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text(
                  'Verifikasi biometrik gagal. Login biometrik tidak diaktifkan.',
                ),
              ),
            );
          }
          return;
        }
        await widget.sessionController.setBiometricEnabled(true);
      } else {
        await widget.sessionController.setBiometricEnabled(false);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<bool>(
      future: _supported,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done ||
            snapshot.data != true) {
          return const SizedBox.shrink();
        }
        return AnimatedBuilder(
          animation: widget.sessionController,
          builder: (context, _) => SwitchListTile(
            contentPadding: EdgeInsets.zero,
            secondary: const IconBadge(
              icon: Icons.fingerprint_rounded,
              size: 40,
              iconSize: 20,
            ),
            title: const Text(
              'Login Biometrik',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
            subtitle: const Text(
              'Gunakan sidik jari atau wajah untuk masuk lebih cepat.',
            ),
            value: widget.sessionController.biometricEnabled,
            onChanged: _busy ? null : _toggle,
          ),
        );
      },
    );
  }
}
