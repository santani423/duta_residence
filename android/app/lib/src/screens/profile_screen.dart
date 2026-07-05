import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../constants/app_spacing.dart';
import '../state/session_controller.dart';
import '../state/theme_controller.dart';
import '../utils/formatters.dart';
import '../widgets/app_logo.dart';
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
    final result = await widget.apiClient.get('customer/account');
    return asMap(result.data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
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
        (await widget.apiClient.get('customer/settings')).data,
      );
      await widget.apiClient.putJson('customer/settings', {
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
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              DutaCard(
                child: Column(
                  children: [
                    const AppLogo(height: 54, alignment: Alignment.center),
                    const SizedBox(height: AppSpacing.xl),
                    CircleAvatar(
                      radius: 34,
                      backgroundColor: Theme.of(
                        context,
                      ).colorScheme.primaryContainer,
                      child: Text(
                        compact(account['name']).substring(0, 1).toUpperCase(),
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    Text(
                      compact(account['name']),
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      compact(account['email']),
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
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
                        'Nomor Customer': account['customer_number'],
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
              DutaCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SectionHeader(title: 'Tentang'),
                    const SizedBox(height: AppSpacing.md),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.info_outline_rounded),
                      title: const Text('Duta Residence'),
                      subtitle: const Text('Aplikasi layanan penghuni'),
                    ),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.privacy_tip_outlined),
                      title: const Text('Kebijakan Privasi'),
                      subtitle: const Text(
                        'Mengikuti kebijakan pengelola estate.',
                      ),
                    ),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.article_outlined),
                      title: const Text('Syarat dan Ketentuan'),
                      subtitle: const Text(
                        'Mengikuti aturan layanan perumahan.',
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              OutlinedButton.icon(
                onPressed: _logout,
                icon: const Icon(Icons.logout_rounded),
                label: const Text('Keluar'),
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
