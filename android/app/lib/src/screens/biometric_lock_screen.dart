import 'package:flutter/material.dart';

import '../constants/app_spacing.dart';
import '../state/session_controller.dart';
import '../widgets/app_logo.dart';
import '../widgets/duta_card.dart';

/// Shown instead of the resident portal right after a cold start when the
/// user has "Login Biometrik" turned on - the saved session token is still
/// valid, but we gate access to it behind a local biometric check.
class BiometricLockScreen extends StatefulWidget {
  const BiometricLockScreen({required this.sessionController, super.key});

  final SessionController sessionController;

  @override
  State<BiometricLockScreen> createState() => _BiometricLockScreenState();
}

class _BiometricLockScreenState extends State<BiometricLockScreen> {
  bool _checking = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _attempt());
  }

  Future<void> _attempt() async {
    if (_checking) return;
    setState(() {
      _checking = true;
      _error = null;
    });
    final ok = await widget.sessionController.unlockWithBiometrics();
    if (!mounted) return;
    setState(() {
      _checking = false;
      _error = ok ? null : 'Verifikasi biometrik gagal atau dibatalkan.';
    });
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(AppSpacing.xl),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: DutaCard(
                padding: const EdgeInsets.all(AppSpacing.xl),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const AppLogo(height: 48),
                    const SizedBox(height: AppSpacing.xl),
                    IconBadge(
                      icon: Icons.fingerprint_rounded,
                      size: 72,
                      iconSize: 40,
                      shape: IconBadgeShape.circle,
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    Text(
                      'Verifikasi biometrik diperlukan',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Text(
                      'Gunakan sidik jari atau wajah Anda untuk membuka aplikasi.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: colors.onSurfaceVariant,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: AppSpacing.lg),
                      Text(
                        _error!,
                        textAlign: TextAlign.center,
                        style: TextStyle(color: colors.error),
                      ),
                    ],
                    const SizedBox(height: AppSpacing.xl),
                    FilledButton.icon(
                      onPressed: _checking ? null : _attempt,
                      icon: _checking
                          ? const SizedBox.square(
                              dimension: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Icon(Icons.fingerprint_rounded),
                      label: Text(_checking ? 'Memverifikasi...' : 'Coba Lagi'),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    TextButton(
                      onPressed: _checking
                          ? null
                          : () => widget.sessionController.logout(),
                      child: const Text('Keluar dan login manual'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
