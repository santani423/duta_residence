import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../constants/app_spacing.dart';
import '../../utils/formatters.dart';
import '../../widgets/app_logo.dart';
import '../../widgets/duta_card.dart';
import '../../widgets/state_views.dart';
import 'supervisor_payment_promises_screen.dart';
import 'supervisor_reports_screen.dart';
import 'supervisor_targets_screen.dart';
import 'supervisor_tunggakan_screen.dart';

class SupervisorDashboardScreen extends StatefulWidget {
  const SupervisorDashboardScreen({
    required this.apiClient,
    required this.onOpenCollectors,
    required this.onOpenApprovals,
    required this.onOpenMap,
    required this.onOpenNotifications,
    this.userName,
    super.key,
  });

  final ApiClient apiClient;
  final String? userName;
  final VoidCallback onOpenCollectors;
  final VoidCallback onOpenApprovals;
  final VoidCallback onOpenMap;
  final VoidCallback onOpenNotifications;

  @override
  State<SupervisorDashboardScreen> createState() =>
      _SupervisorDashboardScreenState();
}

class _SupervisorDashboardScreenState
    extends State<SupervisorDashboardScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final result = await widget.apiClient.get('supervisor/dashboard');
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
          return ErrorView(message: message, onRetry: _refresh);
        }
        final data = snapshot.data ?? {};
        final collectors = asMap(data['collectors']);
        final billing = asMap(data['billing']);
        final payments = asMap(data['payments']);
        final promises = asMap(data['promises']);

        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              _Hero(userName: widget.userName ?? 'Supervisor'),
              const SizedBox(height: AppSpacing.lg),
              DutaCard(
                onTap: widget.onOpenCollectors,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SectionHeader(title: 'Kolektor'),
                    const SizedBox(height: AppSpacing.lg),
                    Row(
                      children: [
                        Expanded(
                          child: _Stat(
                            label: 'Aktif',
                            value: compact(collectors['active']),
                          ),
                        ),
                        Expanded(
                          child: _Stat(
                            label: 'Bertugas Hari Ini',
                            value: compact(collectors['on_duty_today']),
                          ),
                        ),
                        Expanded(
                          child: _Stat(
                            label: 'Tanpa Lokasi',
                            value: compact(
                              collectors['not_sending_location'],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              DutaCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SectionHeader(title: 'Penagihan'),
                    const SizedBox(height: AppSpacing.lg),
                    Row(
                      children: [
                        Expanded(
                          child: _Stat(
                            label: 'Unit Menunggak',
                            value: compact(billing['unpaid_units']),
                          ),
                        ),
                        Expanded(
                          child: _Stat(
                            label: 'Total Piutang',
                            value: money(billing['total_receivables']),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              Row(
                children: [
                  Expanded(
                    child: _MiniCard(
                      icon: Icons.payments_outlined,
                      label: 'Bayar Hari Ini',
                      value: compact(payments['successful_today']),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: _MiniCard(
                      icon: Icons.handshake_outlined,
                      label: 'Janji Aktif',
                      value: compact(promises['active_ptp']),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: _MiniCard(
                      icon: Icons.report_gmailerrorred_outlined,
                      label: 'Janji Gagal',
                      value: compact(promises['broken_promise']),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              Row(
                children: [
                  Expanded(
                    child: _MiniCard(
                      icon: Icons.warning_amber_rounded,
                      label: 'Darurat Aktif',
                      value: compact(data['active_emergencies']),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: _MiniCard(
                      icon: Icons.fact_check_outlined,
                      label: 'Persetujuan Tertunda',
                      value: compact(data['pending_approvals']),
                      onTap: widget.onOpenApprovals,
                    ),
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: _MiniCard(
                      icon: Icons.notifications_active_outlined,
                      label: 'Notifikasi Belum Ditangani',
                      value: compact(data['unhandled_notifications']),
                      onTap: widget.onOpenNotifications,
                    ),
                  ),
                ],
              ),
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
                          label: 'Kolektor',
                          onTap: widget.onOpenCollectors,
                        ),
                        _QuickAction(
                          icon: Icons.map_outlined,
                          label: 'Peta',
                          onTap: widget.onOpenMap,
                        ),
                        _QuickAction(
                          icon: Icons.emoji_events_outlined,
                          label: 'Target',
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => SupervisorTargetsScreen(
                                apiClient: widget.apiClient,
                              ),
                            ),
                          ),
                        ),
                        _QuickAction(
                          icon: Icons.handshake_outlined,
                          label: 'Janji Bayar',
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => SupervisorPaymentPromisesScreen(
                                apiClient: widget.apiClient,
                              ),
                            ),
                          ),
                        ),
                        _QuickAction(
                          icon: Icons.pie_chart_outline_rounded,
                          label: 'Tunggakan',
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => SupervisorTunggakanScreen(
                                apiClient: widget.apiClient,
                              ),
                            ),
                          ),
                        ),
                        _QuickAction(
                          icon: Icons.summarize_outlined,
                          label: 'Laporan',
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => SupervisorReportsScreen(
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
            'Pantau kolektor, persetujuan, dan tunggakan di wilayah Anda.',
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
        Text(label, style: Theme.of(context).textTheme.bodySmall),
      ],
    );
  }
}

class _MiniCard extends StatelessWidget {
  const _MiniCard({
    required this.icon,
    required this.label,
    required this.value,
    this.onTap,
  });

  final IconData icon;
  final String label;
  final String value;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return DutaCard(
      onTap: onTap,
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(icon: icon, size: 36),
          const SizedBox(height: AppSpacing.sm),
          Text(
            value,
            style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
          ),
          Text(
            label,
            style: Theme.of(context).textTheme.bodySmall,
            maxLines: 2,
          ),
        ],
      ),
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
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 11,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
