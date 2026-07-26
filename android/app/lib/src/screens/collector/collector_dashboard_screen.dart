import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/app_logo.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/state_views.dart';
import 'collector_reminder_screen.dart';
import 'collection_letters_screen.dart';
import 'collector_performance_screen.dart';

class CollectorDashboardScreen extends StatefulWidget {
  const CollectorDashboardScreen({
    required this.apiClient,
    required this.onOpenUnits,
    required this.onOpenRoute,
    required this.onOpenNotifications,
    this.userName,
    super.key,
  });

  final ApiClient apiClient;
  final String? userName;
  final VoidCallback onOpenUnits;
  final VoidCallback onOpenRoute;
  final VoidCallback onOpenNotifications;

  @override
  State<CollectorDashboardScreen> createState() =>
      _CollectorDashboardScreenState();
}

class _CollectorDashboardScreenState extends State<CollectorDashboardScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final now = DateTime.now();
    final periodStart = DateTime(now.year, now.month, 1);
    final result = await widget.apiClient.get(
      'collector-performance/me',
      query: {
        'period_type': 'monthly',
        'period_start':
            '${periodStart.year}-${periodStart.month.toString().padLeft(2, '0')}-01',
      },
    );
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
        Widget performanceCard;
        if (snapshot.hasError) {
          final message = snapshot.error is ApiException
              ? (snapshot.error as ApiException).message
              : snapshot.error.toString();
          performanceCard = ErrorView(message: message, onRetry: _refresh);
        } else {
          final data = snapshot.data ?? {};
          performanceCard = DutaCard(
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) =>
                    CollectorPerformanceScreen(apiClient: widget.apiClient),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionHeader(title: 'Performa Bulan Ini'),
                const SizedBox(height: AppSpacing.lg),
                Row(
                  children: [
                    Expanded(
                      child: _Stat(
                        label: 'Terkumpul',
                        value: money(data['collected_amount']),
                      ),
                    ),
                    Expanded(
                      child: _Stat(
                        label: 'Target',
                        value: money(data['target_amount']),
                      ),
                    ),
                    Expanded(
                      child: _Stat(
                        label: 'Pencapaian',
                        value: data['achievement_percent'] == null
                            ? '-'
                            : '${data['achievement_percent']}%',
                      ),
                    ),
                  ],
                ),
              ],
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              _Hero(userName: widget.userName ?? 'Kolektor'),
              const SizedBox(height: AppSpacing.lg),
              performanceCard,
              const SizedBox(height: AppSpacing.lg),
              DutaCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SectionHeader(title: 'Akses Cepat'),
                    const SizedBox(height: AppSpacing.lg),
                    Wrap(
                      spacing: AppSpacing.md,
                      runSpacing: AppSpacing.md,
                      children: [
                        _QuickAction(
                          icon: Icons.groups_outlined,
                          label: 'Unit Saya',
                          onTap: widget.onOpenUnits,
                        ),
                        _QuickAction(
                          icon: Icons.map_outlined,
                          label: 'Rute',
                          onTap: widget.onOpenRoute,
                        ),
                        _QuickAction(
                          icon: Icons.notifications_outlined,
                          label: 'Notifikasi',
                          onTap: widget.onOpenNotifications,
                        ),
                        _QuickAction(
                          icon: Icons.chat_outlined,
                          label: 'Pengingat WA',
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => CollectorReminderScreen(
                                apiClient: widget.apiClient,
                              ),
                            ),
                          ),
                        ),
                        _QuickAction(
                          icon: Icons.description_outlined,
                          label: 'Surat Penagihan',
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => CollectionLettersScreen(
                                apiClient: widget.apiClient,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.userName});

  final String userName;

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
      ),
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const AppLogo(height: 28),
          const SizedBox(height: AppSpacing.lg),
          Text(
            'Selamat bertugas, $userName',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w900,
              color: colors.onPrimary,
            ),
          ),
          const SizedBox(height: AppSpacing.xs),
          Text(
            'Kelola kunjungan dan penagihan unit yang menjadi tanggung jawab Anda.',
            style: TextStyle(color: colors.onPrimary.withValues(alpha: 0.9)),
          ),
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          value,
          style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        Text(
          label,
          style: Theme.of(context).textTheme.bodySmall,
        ),
      ],
    );
  }
}

class _QuickAction extends StatelessWidget {
  const _QuickAction({
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
