import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../constants/app_spacing.dart';
import '../../state/session_controller.dart';
import '../../state/theme_controller.dart';
import '../../widgets/app_logo.dart';
import '../../widgets/duta_card.dart';
import 'supervisor_approvals_screen.dart';
import 'supervisor_collectors_screen.dart';
import 'supervisor_dashboard_screen.dart';
import 'supervisor_map_screen.dart';
import 'supervisor_notifications_screen.dart';

/// Supervisor's own shell - deliberately mirrors [CollectorHomeShell]'s
/// bottom-nav/IndexedStack-style structure for UX consistency, but does NOT
/// replicate its location-ping `Timer.periodic` logic: a Supervisor only
/// ever *views* collector locations via `GET supervisor/map`, it never
/// sends its own device location, so no location permission is requested
/// anywhere in this shell or its child screens.
class SupervisorHomeShell extends StatefulWidget {
  const SupervisorHomeShell({
    required this.apiClient,
    required this.sessionController,
    required this.themeController,
    super.key,
  });

  final ApiClient apiClient;
  final SessionController sessionController;
  final ThemeController themeController;

  @override
  State<SupervisorHomeShell> createState() => _SupervisorHomeShellState();
}

class _NavItem {
  const _NavItem(this.icon, this.selectedIcon, this.label);

  final IconData icon;
  final IconData selectedIcon;
  final String label;
}

const _navItems = [
  _NavItem(Icons.home_outlined, Icons.home_rounded, 'Beranda'),
  _NavItem(Icons.groups_outlined, Icons.groups_rounded, 'Kolektor'),
  _NavItem(
    Icons.fact_check_outlined,
    Icons.fact_check_rounded,
    'Persetujuan',
  ),
  _NavItem(Icons.map_outlined, Icons.map_rounded, 'Peta'),
  _NavItem(
    Icons.notifications_outlined,
    Icons.notifications_rounded,
    'Notifikasi',
  ),
];

class _SupervisorHomeShellState extends State<SupervisorHomeShell> {
  int _index = 0;

  void _openProfileSheet() {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _SupervisorProfileSheet(
        sessionController: widget.sessionController,
        themeController: widget.themeController,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final titles = _navItems.map((item) => item.label).toList();
    final pages = [
      SupervisorDashboardScreen(
        apiClient: widget.apiClient,
        userName: widget.sessionController.user?.name,
        onOpenCollectors: () => setState(() => _index = 1),
        onOpenApprovals: () => setState(() => _index = 2),
        onOpenMap: () => setState(() => _index = 3),
        onOpenNotifications: () => setState(() => _index = 4),
      ),
      SupervisorCollectorsScreen(apiClient: widget.apiClient),
      SupervisorApprovalsScreen(apiClient: widget.apiClient),
      SupervisorMapScreen(apiClient: widget.apiClient),
      SupervisorNotificationsScreen(apiClient: widget.apiClient),
    ];

    return Scaffold(
      appBar: AppBar(
        titleSpacing: AppSpacing.lg,
        title: Row(
          children: [
            const AppLogo(height: 32),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 220),
                child: Text(
                  titles[_index],
                  key: ValueKey(titles[_index]),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ),
          ],
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: AppSpacing.sm),
            child: IconButton.filledTonal(
              tooltip: 'Profil',
              onPressed: _openProfileSheet,
              icon: const Icon(Icons.person_outline_rounded),
            ),
          ),
        ],
      ),
      body: AnimatedSwitcher(
        duration: const Duration(milliseconds: 260),
        switchInCurve: Curves.easeOutCubic,
        switchOutCurve: Curves.easeInCubic,
        transitionBuilder: (child, animation) {
          final offset = Tween<Offset>(
            begin: const Offset(0.04, 0),
            end: Offset.zero,
          ).animate(animation);
          return FadeTransition(
            opacity: animation,
            child: SlideTransition(position: offset, child: child),
          );
        },
        child: KeyedSubtree(key: ValueKey(_index), child: pages[_index]),
      ),
      bottomNavigationBar: _FloatingNavBar(
        index: _index,
        onSelect: (value) => setState(() => _index = value),
      ),
    );
  }
}

class _SupervisorProfileSheet extends StatelessWidget {
  const _SupervisorProfileSheet({
    required this.sessionController,
    required this.themeController,
  });

  final SessionController sessionController;
  final ThemeController themeController;

  @override
  Widget build(BuildContext context) {
    final user = sessionController.user;
    final profile = user?.supervisorProfile;
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const CircleAvatar(
                  radius: 28,
                  child: Icon(Icons.person_outline_rounded),
                ),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        user?.name ?? '-',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      Text(
                        profile?.supervisorCode ?? 'Supervisor',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.lg),
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
            const SizedBox(height: AppSpacing.lg),
            OutlinedButton.icon(
              onPressed: () async {
                Navigator.of(context).pop();
                await sessionController.logout();
              },
              icon: const Icon(Icons.logout_rounded),
              label: const Text('Keluar'),
            ),
          ],
        ),
      ),
    );
  }
}

class _FloatingNavBar extends StatelessWidget {
  const _FloatingNavBar({required this.index, required this.onSelect});

  final int index;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return SafeArea(
      top: false,
      minimum: const EdgeInsets.fromLTRB(
        AppSpacing.lg,
        0,
        AppSpacing.lg,
        AppSpacing.md,
      ),
      child: Container(
        height: 84,
        decoration: BoxDecoration(
          color: colors.surface,
          borderRadius: BorderRadius.circular(AppSpacing.pill),
          boxShadow: [
            BoxShadow(
              color: colors.shadow.withValues(alpha: 0.14),
              blurRadius: 28,
              offset: const Offset(0, 12),
            ),
          ],
        ),
        child: Row(
          children: [
            for (var i = 0; i < _navItems.length; i++)
              Expanded(
                child: _NavButton(
                  item: _navItems[i],
                  selected: i == index,
                  onTap: () => onSelect(i),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _NavButton extends StatelessWidget {
  const _NavButton({
    required this.item,
    required this.selected,
    required this.onTap,
  });

  final _NavItem item;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Material(
      type: MaterialType.transparency,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppSpacing.pill),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            mainAxisSize: MainAxisSize.min,
            children: [
              AnimatedContainer(
                duration: const Duration(milliseconds: 220),
                curve: Curves.easeOutCubic,
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: selected
                      ? colors.primaryContainer
                      : Colors.transparent,
                  shape: BoxShape.circle,
                ),
                alignment: Alignment.center,
                child: Icon(
                  selected ? item.selectedIcon : item.icon,
                  size: 22,
                  color: selected
                      ? colors.onPrimaryContainer
                      : colors.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                item.label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                  color: selected ? colors.primary : colors.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
