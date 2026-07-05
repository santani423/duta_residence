import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../constants/app_spacing.dart';
import '../screens/dashboard_screen.dart';
import '../screens/notifications_screen.dart';
import '../screens/profile_screen.dart';
import '../screens/property_screen.dart';
import '../screens/services_screen.dart';
import '../state/session_controller.dart';
import '../state/theme_controller.dart';
import '../widgets/app_logo.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({
    required this.apiClient,
    required this.sessionController,
    required this.themeController,
    super.key,
  });

  final ApiClient apiClient;
  final SessionController sessionController;
  final ThemeController themeController;

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final titles = const [
      'Beranda',
      'Properti',
      'Layanan',
      'Notifikasi',
      'Profil',
    ];
    final pages = [
      DashboardScreen(
        apiClient: widget.apiClient,
        userName: widget.sessionController.user?.name,
        onOpenServices: () => setState(() => _index = 2),
        onOpenNotifications: () => setState(() => _index = 3),
      ),
      PropertyScreen(apiClient: widget.apiClient),
      ServicesScreen(apiClient: widget.apiClient),
      NotificationsScreen(apiClient: widget.apiClient),
      ProfileScreen(
        apiClient: widget.apiClient,
        sessionController: widget.sessionController,
        themeController: widget.themeController,
      ),
    ];

    return Scaffold(
      appBar: AppBar(
        titleSpacing: AppSpacing.lg,
        title: Row(
          children: [
            const AppLogo(height: 34),
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
          IconButton(
            tooltip: 'Notifikasi',
            onPressed: () => setState(() => _index = 3),
            icon: const Icon(Icons.notifications_outlined),
          ),
          const SizedBox(width: AppSpacing.sm),
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
      bottomNavigationBar: DecoratedBox(
        decoration: BoxDecoration(
          color: colors.surface,
          border: Border(top: BorderSide(color: colors.outlineVariant)),
        ),
        child: SafeArea(
          top: false,
          child: NavigationBar(
            selectedIndex: _index,
            onDestinationSelected: (value) => setState(() => _index = value),
            destinations: const [
              NavigationDestination(
                icon: Icon(Icons.home_outlined),
                selectedIcon: Icon(Icons.home_rounded),
                label: 'Beranda',
              ),
              NavigationDestination(
                icon: Icon(Icons.domain_outlined),
                selectedIcon: Icon(Icons.domain_rounded),
                label: 'Properti',
              ),
              NavigationDestination(
                icon: Icon(Icons.grid_view_outlined),
                selectedIcon: Icon(Icons.grid_view_rounded),
                label: 'Layanan',
              ),
              NavigationDestination(
                icon: Icon(Icons.notifications_outlined),
                selectedIcon: Icon(Icons.notifications_rounded),
                label: 'Notifikasi',
              ),
              NavigationDestination(
                icon: Icon(Icons.person_outline_rounded),
                selectedIcon: Icon(Icons.person_rounded),
                label: 'Profil',
              ),
            ],
          ),
        ),
      ),
    );
  }
}
