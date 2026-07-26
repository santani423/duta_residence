import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../constants/app_config.dart';
import '../../constants/app_spacing.dart';
import '../../state/session_controller.dart';
import '../../state/theme_controller.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/info_row.dart';
import 'collector_performance_screen.dart';
import 'collection_letters_screen.dart';

const _employmentStatusLabels = {
  'tetap': 'Tetap',
  'kontrak': 'Kontrak',
  'harian': 'Harian',
};

const _accountStatusLabels = {
  'active': 'Aktif',
  'inactive': 'Nonaktif',
  'leave': 'Cuti',
  'suspended': 'Suspend',
};

/// Mirrors the web frontend's storageUrl() convention: the API base URL minus
/// its `/api/v1` suffix, plus `/storage/` and the given path.
String? _storageUrl(String? path) {
  if (path == null || path.isEmpty) return null;
  final base = AppConfig.apiBaseUrl.replaceFirst(RegExp(r'/api/v1/?$'), '');
  return '$base/storage/$path';
}

class CollectorProfileScreen extends StatelessWidget {
  const CollectorProfileScreen({
    required this.sessionController,
    required this.themeController,
    required this.apiClient,
    super.key,
  });

  final SessionController sessionController;
  final ThemeController themeController;
  final ApiClient apiClient;

  @override
  Widget build(BuildContext context) {
    final user = sessionController.user;
    final profile = user?.collectorProfile;
    final photoUrl = _storageUrl(profile?.photoPath);

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        DutaCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  if (photoUrl != null)
                    CircleAvatar(radius: 28, backgroundImage: NetworkImage(photoUrl))
                  else
                    const CircleAvatar(radius: 28, child: Icon(Icons.person_outline_rounded)),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(user?.name ?? '-', style: Theme.of(context).textTheme.titleMedium),
                        if (profile?.collectorCode != null)
                          Text(
                            profile!.collectorCode!,
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              InfoRows(
                items: {
                  'Nama': user?.name,
                  'Username': user?.username,
                  'Telepon': user?.phone,
                  'WhatsApp': profile?.whatsappNumber,
                  'Status Kepegawaian': profile?.employmentStatus != null
                      ? _employmentStatusLabels[profile!.employmentStatus] ?? profile.employmentStatus
                      : null,
                  'Status Akun': profile?.accountStatus != null
                      ? _accountStatusLabels[profile!.accountStatus] ?? profile.accountStatus
                      : null,
                  'Peran': 'Kolektor',
                },
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        DutaCard(
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) =>
                  CollectorPerformanceScreen(apiClient: apiClient),
            ),
          ),
          child: const Row(
            children: [
              IconBadge(icon: Icons.emoji_events_outlined),
              SizedBox(width: AppSpacing.md),
              Expanded(child: Text('Target & Performa Saya')),
              Icon(Icons.chevron_right_rounded),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        DutaCard(
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => CollectionLettersScreen(apiClient: apiClient),
            ),
          ),
          child: const Row(
            children: [
              IconBadge(icon: Icons.description_outlined),
              SizedBox(width: AppSpacing.md),
              Expanded(child: Text('Surat Penagihan')),
              Icon(Icons.chevron_right_rounded),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        DutaCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SectionHeader(title: 'Tampilan'),
              const SizedBox(height: AppSpacing.md),
              AnimatedBuilder(
                animation: themeController,
                builder: (context, _) => SegmentedButton<ThemeMode>(
                  segments: const [
                    ButtonSegment(
                      value: ThemeMode.light,
                      icon: Icon(Icons.light_mode_outlined),
                    ),
                    ButtonSegment(
                      value: ThemeMode.dark,
                      icon: Icon(Icons.dark_mode_outlined),
                    ),
                    ButtonSegment(
                      value: ThemeMode.system,
                      icon: Icon(Icons.settings_suggest_outlined),
                    ),
                  ],
                  selected: {themeController.mode},
                  onSelectionChanged: (value) =>
                      themeController.setMode(value.first),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.xl),
        OutlinedButton.icon(
          onPressed: () async {
            await sessionController.logout();
          },
          icon: const Icon(Icons.logout_rounded),
          label: const Text('Keluar'),
        ),
      ],
    );
  }
}
