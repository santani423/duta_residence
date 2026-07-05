import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../api/api_exception.dart';
import '../constants/app_config.dart';
import '../constants/app_spacing.dart';
import '../utils/formatters.dart';
import '../widgets/app_logo.dart';
import '../widgets/duta_card.dart';
import '../widgets/info_row.dart';
import '../widgets/state_views.dart';
import '../widgets/status_badge.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({
    required this.apiClient,
    required this.onOpenServices,
    required this.onOpenNotifications,
    this.userName,
    super.key,
  });

  final ApiClient apiClient;
  final String? userName;
  final VoidCallback onOpenServices;
  final VoidCallback onOpenNotifications;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final result = await widget.apiClient.get('customer/dashboard');
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
        final data = snapshot.data ?? {};
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              _HeroSummary(
                userName: compact(
                  asMap(data['customer'])['name'] ?? widget.userName,
                ),
                property: asMap(data['property']),
                onOpenNotifications: widget.onOpenNotifications,
              ),
              const SizedBox(height: AppSpacing.lg),
              _StatGrid(data: data),
              const SizedBox(height: AppSpacing.lg),
              _QuickActions(
                onOpenServices: widget.onOpenServices,
                onOpenNotifications: widget.onOpenNotifications,
              ),
              const SizedBox(height: AppSpacing.lg),
              _InfoCards(data: data),
              const SizedBox(height: AppSpacing.lg),
              _LatestSections(data: data),
            ],
          ),
        );
      },
    );
  }
}

class _HeroSummary extends StatelessWidget {
  const _HeroSummary({
    required this.userName,
    required this.property,
    required this.onOpenNotifications,
  });

  final String userName;
  final Map<String, dynamic> property;
  final VoidCallback onOpenNotifications;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return DutaCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(child: AppLogo(height: 52)),
              IconButton.filledTonal(
                tooltip: 'Notifikasi',
                onPressed: onOpenNotifications,
                icon: const Icon(Icons.notifications_outlined),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.xl),
          Text(
            'Selamat datang, $userName',
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            '${compact(property['unit_label'])} - ${compact(property['occupancy'])}',
            style: TextStyle(color: colors.onSurfaceVariant),
          ),
          const SizedBox(height: AppSpacing.lg),
          Material(
            color: colors.primaryContainer.withValues(alpha: 0.7),
            borderRadius: BorderRadius.circular(AppSpacing.radius),
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Row(
                children: [
                  Icon(
                    Icons.campaign_outlined,
                    color: colors.onPrimaryContainer,
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Text(
                      'Informasi tagihan, layanan, dan aktivitas hunian Anda tersaji ringkas di sini.',
                      style: TextStyle(
                        color: colors.onPrimaryContainer,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StatGrid extends StatelessWidget {
  const _StatGrid({required this.data});

  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final billing = asMap(data['billing_summary']);
    final payment = asMap(data['payment_summary']);
    final services = asMap(data['service_summary']);
    final stats = [
      _StatItem(
        'Tagihan Aktif',
        money(billing['active_total']),
        Icons.receipt_long_outlined,
      ),
      _StatItem(
        'Belum Dibayar',
        compact(billing['unpaid_count']),
        Icons.schedule_outlined,
      ),
      _StatItem(
        'Jatuh Tempo',
        compact(billing['overdue_count']),
        Icons.warning_amber_rounded,
      ),
      _StatItem(
        'Pembayaran',
        money(payment['successful_total']),
        Icons.payments_outlined,
      ),
      _StatItem(
        'Diproses',
        compact(payment['processing_count']),
        Icons.sync_rounded,
      ),
      _StatItem(
        'Verifikasi',
        compact(payment['manual_waiting_count']),
        Icons.fact_check_outlined,
      ),
      _StatItem(
        'Komplain',
        compact(services['active_complaints']),
        Icons.report_problem_outlined,
      ),
      _StatItem(
        'Maintenance',
        compact(services['active_maintenance_requests']),
        Icons.handyman_outlined,
      ),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth > 680 ? 4 : 2;
        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            mainAxisSpacing: AppSpacing.md,
            crossAxisSpacing: AppSpacing.md,
            childAspectRatio: columns == 4 ? 1.55 : 1.22,
          ),
          itemCount: stats.length,
          itemBuilder: (context, index) => _StatCard(item: stats[index]),
        );
      },
    );
  }
}

class _StatItem {
  const _StatItem(this.label, this.value, this.icon);

  final String label;
  final String value;
  final IconData icon;
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.item});

  final _StatItem item;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return DutaCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(item.icon, color: colors.primary),
          const Spacer(),
          Text(
            item.value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: AppSpacing.xs),
          Text(
            item.label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(color: colors.onSurfaceVariant),
          ),
        ],
      ),
    );
  }
}

class _QuickActions extends StatelessWidget {
  const _QuickActions({
    required this.onOpenServices,
    required this.onOpenNotifications,
  });

  final VoidCallback onOpenServices;
  final VoidCallback onOpenNotifications;

  @override
  Widget build(BuildContext context) {
    final actions = [
      _ActionItem('Bayar IPL', Icons.credit_card_rounded, onOpenServices),
      _ActionItem('Buat Komplain', Icons.add_comment_outlined, onOpenServices),
      _ActionItem('Maintenance', Icons.handyman_outlined, onOpenServices),
      _ActionItem(
        'Notifikasi',
        Icons.notifications_outlined,
        onOpenNotifications,
      ),
    ];
    return DutaCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SectionHeader(title: 'Akses Cepat'),
          const SizedBox(height: AppSpacing.lg),
          Row(
            children: [
              for (final action in actions)
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 2),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
                      onTap: action.onTap,
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          vertical: AppSpacing.sm,
                        ),
                        child: Column(
                          children: [
                            Icon(action.icon),
                            const SizedBox(height: AppSpacing.sm),
                            Text(
                              action.label,
                              textAlign: TextAlign.center,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ActionItem {
  const _ActionItem(this.label, this.icon, this.onTap);

  final String label;
  final IconData icon;
  final VoidCallback onTap;
}

class _InfoCards extends StatelessWidget {
  const _InfoCards({required this.data});

  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final children = [
          DutaCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionHeader(title: 'Informasi Customer'),
                const SizedBox(height: AppSpacing.lg),
                InfoRows(
                  items: {
                    'Nama': asMap(data['customer'])['name'],
                    'Nomor Customer': asMap(
                      data['customer'],
                    )['customer_number'],
                    'Email': asMap(data['customer'])['email'],
                    'Telepon': asMap(data['customer'])['phone'],
                    'Status': asMap(data['customer'])['status'],
                  },
                ),
              ],
            ),
          ),
          DutaCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionHeader(title: 'Estate dan Unit'),
                const SizedBox(height: AppSpacing.lg),
                InfoRows(
                  items: {
                    'Estate': AppConfig.estateName,
                    'Cluster': asMap(data['property'])['cluster'],
                    'Unit': asMap(data['property'])['unit_label'],
                    'Tipe Properti': asMap(data['property'])['property_type'],
                    'Hunian': asMap(data['property'])['occupancy'],
                  },
                ),
              ],
            ),
          ),
        ];
        if (constraints.maxWidth > 760) {
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: children[0]),
              const SizedBox(width: AppSpacing.md),
              Expanded(child: children[1]),
            ],
          );
        }
        return Column(
          children: [
            children[0],
            const SizedBox(height: AppSpacing.md),
            children[1],
          ],
        );
      },
    );
  }
}

class _LatestSections extends StatelessWidget {
  const _LatestSections({required this.data});

  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final billing = asMap(data['billing_summary']);
    final payment = asMap(data['payment_summary']);
    final services = asMap(data['service_summary']);
    return Column(
      children: [
        _LatestCard(
          title: 'Tagihan Terbaru',
          items: asList(billing['latest']),
          itemBuilder: (item) => _InvoiceLine(item: asMap(item)),
        ),
        const SizedBox(height: AppSpacing.md),
        _LatestCard(
          title: 'Pembayaran Terbaru',
          items: asList(payment['latest']),
          itemBuilder: (item) => _PaymentLine(item: asMap(item)),
        ),
        const SizedBox(height: AppSpacing.md),
        _LatestCard(
          title: 'Penggunaan Layanan',
          items: asList(services['usage']),
          itemBuilder: (item) => _SimpleLine(
            title: compact(asMap(item)['label']),
            trailing: compact(asMap(item)['value']),
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        _LatestCard(
          title: 'Dokumen Terbaru',
          items: asList(data['latest_documents']),
          itemBuilder: (item) => _SimpleLine(
            title: compact(asMap(item)['name']),
            subtitle: dateOnly(asMap(item)['created_at']),
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        _LatestCard(
          title: 'Notifikasi Terbaru',
          items: asList(data['latest_notifications']),
          itemBuilder: (item) => _SimpleLine(
            title: compact(
              asMap(item)['title'] ??
                  asMap(item)['subject'] ??
                  asMap(item)['type'],
            ),
            subtitle: dateTime(asMap(item)['created_at']),
          ),
        ),
      ],
    );
  }
}

class _LatestCard extends StatelessWidget {
  const _LatestCard({
    required this.title,
    required this.items,
    required this.itemBuilder,
  });

  final String title;
  final List<dynamic> items;
  final Widget Function(dynamic item) itemBuilder;

  @override
  Widget build(BuildContext context) {
    return DutaCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SectionHeader(title: title),
          const SizedBox(height: AppSpacing.md),
          if (items.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: AppSpacing.lg),
              child: Center(child: Text('Belum ada data.')),
            )
          else
            for (final item in items.take(5)) itemBuilder(item),
        ],
      ),
    );
  }
}

class _InvoiceLine extends StatelessWidget {
  const _InvoiceLine({required this.item});

  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    return _SimpleLine(
      title: compact(item['invoice_number']),
      subtitle: '${compact(item['period'])} - ${money(item['total'])}',
      badge: StatusBadge(item['status']),
    );
  }
}

class _PaymentLine extends StatelessWidget {
  const _PaymentLine({required this.item});

  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    return _SimpleLine(
      title: compact(item['transaction_number']),
      subtitle: money(item['total']),
      badge: StatusBadge(item['status']),
    );
  }
}

class _SimpleLine extends StatelessWidget {
  const _SimpleLine({
    required this.title,
    this.subtitle,
    this.trailing,
    this.badge,
  });

  final String title;
  final String? subtitle;
  final String? trailing;
  final Widget? badge;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                if (subtitle != null)
                  Text(
                    subtitle!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                  ),
              ],
            ),
          ),
          if (trailing != null)
            Text(
              trailing!,
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ?badge,
        ],
      ),
    );
  }
}
