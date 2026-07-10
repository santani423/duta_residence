import 'package:flutter/material.dart';

import '../constants/app_spacing.dart';
import 'duta_card.dart';
import 'fade_in.dart';

class LoadingView extends StatelessWidget {
  const LoadingView({this.message = 'Memuat data...', super.key});

  final String message;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: FadeSlideIn(
          child: DutaCard(
            padding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.xxl,
              vertical: AppSpacing.xl,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                SizedBox(
                  width: 28,
                  height: 28,
                  child: CircularProgressIndicator(
                    strokeWidth: 2.6,
                    color: colors.primary,
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                Text(
                  message,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: colors.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class EmptyView extends StatelessWidget {
  const EmptyView({this.message = 'Belum ada data.', super.key});

  final String message;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: FadeSlideIn(
          child: DutaCard(
            padding: const EdgeInsets.all(AppSpacing.xxl),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                IconBadge(
                  icon: Icons.inbox_rounded,
                  size: 64,
                  background: colors.surfaceContainerHighest,
                  foreground: colors.onSurfaceVariant,
                ),
                const SizedBox(height: AppSpacing.lg),
                Text(
                  message,
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: colors.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class ErrorView extends StatelessWidget {
  const ErrorView({required this.message, required this.onRetry, super.key});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: FadeSlideIn(
          child: DutaCard(
            padding: const EdgeInsets.all(AppSpacing.xxl),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                IconBadge(
                  icon: Icons.wifi_off_rounded,
                  size: 64,
                  background: colors.errorContainer,
                  foreground: colors.onErrorContainer,
                ),
                const SizedBox(height: AppSpacing.lg),
                Text(
                  message,
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: colors.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: AppSpacing.xl),
                FilledButton.icon(
                  onPressed: onRetry,
                  icon: const Icon(Icons.refresh_rounded),
                  label: const Text('Coba Lagi'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class AsyncStateBuilder extends StatelessWidget {
  const AsyncStateBuilder({
    required this.future,
    required this.builder,
    this.loadingMessage,
    super.key,
  });

  final Future<Object?> future;
  final Widget Function(BuildContext context, Object? value) builder;
  final String? loadingMessage;

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Object?>(
      future: future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return LoadingView(message: loadingMessage ?? 'Memuat data...');
        }
        if (snapshot.hasError) {
          return ErrorView(message: snapshot.error.toString(), onRetry: () {});
        }
        return builder(context, snapshot.data);
      },
    );
  }
}
