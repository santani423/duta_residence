import 'dart:async';

import 'package:flutter/material.dart';

import '../../api/api_client.dart';
import '../../constants/app_spacing.dart';
import '../../services/location_service.dart';
import '../../state/session_controller.dart';
import '../../state/theme_controller.dart';
import '../../widgets/app_logo.dart';
import 'collector_dashboard_screen.dart';
import 'collector_emergency_screen.dart';
import 'collector_notifications_screen.dart';
import 'collector_profile_screen.dart';
import 'collector_units_screen.dart';
import 'route_map_screen.dart';

class CollectorHomeShell extends StatefulWidget {
  const CollectorHomeShell({
    required this.apiClient,
    required this.sessionController,
    required this.themeController,
    super.key,
  });

  final ApiClient apiClient;
  final SessionController sessionController;
  final ThemeController themeController;

  @override
  State<CollectorHomeShell> createState() => _CollectorHomeShellState();
}

class _NavItem {
  const _NavItem(this.icon, this.selectedIcon, this.label);

  final IconData icon;
  final IconData selectedIcon;
  final String label;
}

const _navItems = [
  _NavItem(Icons.home_outlined, Icons.home_rounded, 'Beranda'),
  _NavItem(Icons.groups_outlined, Icons.groups_rounded, 'Unit Saya'),
  _NavItem(Icons.map_outlined, Icons.map_rounded, 'Rute'),
  _NavItem(
    Icons.notifications_outlined,
    Icons.notifications_rounded,
    'Notifikasi',
  ),
  _NavItem(Icons.person_outline_rounded, Icons.person_rounded, 'Profil'),
];

class _CollectorHomeShellState extends State<CollectorHomeShell> {
  int _index = 0;
  Timer? _locationTimer;
  final _locationService = const LocationService();

  @override
  void initState() {
    super.initState();
    // Foreground-only live location: pings while this shell is mounted (the
    // collector app is open), not a true background service - documented
    // limitation, see CollectorAssignmentService plan notes.
    _reportLocation();
    _locationTimer = Timer.periodic(
      const Duration(minutes: 5),
      (_) => _reportLocation(),
    );
  }

  @override
  void dispose() {
    _locationTimer?.cancel();
    super.dispose();
  }

  Future<void> _reportLocation() async {
    try {
      final fix = await _locationService.captureCurrentPosition();
      await widget.apiClient.postJson('collector-locations', {
        'latitude': fix.latitude,
        'longitude': fix.longitude,
        'accuracy_meters': fix.accuracyMeters,
      });
    } catch (_) {
      // Silent - location reporting must never interrupt normal app use.
    }
  }

  void _triggerEmergency() {
    Navigator.of(context).push(
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) => CollectorEmergencyScreen(apiClient: widget.apiClient),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final titles = _navItems.map((item) => item.label).toList();
    final pages = [
      CollectorDashboardScreen(
        apiClient: widget.apiClient,
        userName: widget.sessionController.user?.name,
        onOpenUnits: () => setState(() => _index = 1),
        onOpenRoute: () => setState(() => _index = 2),
        onOpenNotifications: () => setState(() => _index = 3),
      ),
      CollectorUnitsScreen(apiClient: widget.apiClient),
      RouteMapScreen(apiClient: widget.apiClient),
      CollectorNotificationsScreen(apiClient: widget.apiClient),
      CollectorProfileScreen(
        sessionController: widget.sessionController,
        themeController: widget.themeController,
        apiClient: widget.apiClient,
      ),
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
              tooltip: 'Darurat',
              onPressed: _triggerEmergency,
              icon: const Icon(Icons.warning_amber_rounded),
              style: IconButton.styleFrom(
                backgroundColor: colors.errorContainer,
                foregroundColor: colors.onErrorContainer,
              ),
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
