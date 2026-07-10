import 'package:flutter/material.dart';

import '../constants/app_spacing.dart';

class DutaCard extends StatelessWidget {
  const DutaCard({
    required this.child,
    this.padding = const EdgeInsets.all(AppSpacing.lg),
    this.onTap,
    this.color,
    this.borderRadius,
    super.key,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final Color? color;
  final BorderRadius? borderRadius;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final radius = borderRadius ?? BorderRadius.circular(AppSpacing.radiusLg);
    return Container(
      decoration: BoxDecoration(
        color: color ?? colors.surface,
        borderRadius: radius,
        boxShadow: [
          BoxShadow(
            color: colors.shadow.withValues(alpha: 0.06),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Material(
        type: MaterialType.transparency,
        borderRadius: radius,
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          borderRadius: radius,
          child: Padding(padding: padding, child: child),
        ),
      ),
    );
  }
}

class SectionHeader extends StatelessWidget {
  const SectionHeader({required this.title, this.action, super.key});

  final String title;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w800,
              letterSpacing: -0.1,
            ),
          ),
        ),
        ?action,
      ],
    );
  }
}

enum IconBadgeShape { circle, rounded }

class IconBadge extends StatelessWidget {
  const IconBadge({
    required this.icon,
    this.background,
    this.foreground,
    this.size = 44,
    this.iconSize,
    this.shape = IconBadgeShape.rounded,
    super.key,
  });

  final IconData icon;
  final Color? background;
  final Color? foreground;
  final double size;
  final double? iconSize;
  final IconBadgeShape shape;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final bg = background ?? colors.primaryContainer;
    final fg = foreground ?? colors.onPrimaryContainer;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: bg,
        shape: shape == IconBadgeShape.circle
            ? BoxShape.circle
            : BoxShape.rectangle,
        borderRadius: shape == IconBadgeShape.rounded
            ? BorderRadius.circular(size * 0.32)
            : null,
      ),
      child: Icon(icon, color: fg, size: iconSize ?? size * 0.5),
    );
  }
}
