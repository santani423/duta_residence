import 'package:flutter/material.dart';

import '../constants/app_spacing.dart';
import '../utils/formatters.dart';
import '../widgets/duta_card.dart';
import 'app_notification.dart';

/// One tappable notification card. The whole card is the tap target; unread state is
/// shown by a dot, a tinted card and bold title, and is also spoken by screen readers.
class NotificationTile extends StatelessWidget {
  const NotificationTile({
    required this.notification,
    required this.onTap,
    this.trailing,
    this.footer,
    this.subtitle,
    super.key,
  });

  final AppNotification notification;
  final VoidCallback onTap;

  /// Extra chip shown next to the title (e.g. supervisor priority).
  final Widget? trailing;

  /// Extra content under the card text, e.g. supervisor action buttons.
  final Widget? footer;

  /// Overrides the default "category · date" line.
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final textTheme = Theme.of(context).textTheme;
    final unread = !notification.isRead;

    return Semantics(
      button: true,
      label:
          '${unread ? 'Belum dibaca. ' : ''}${notification.title}. Ketuk untuk membuka detail.',
      excludeSemantics: true,
      onTap: onTap,
      child: DutaCard(
        onTap: onTap,
        color: unread ? colors.primaryContainer.withValues(alpha: 0.22) : null,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              margin: const EdgeInsets.only(top: 6, right: AppSpacing.sm),
              width: 10,
              height: 10,
              decoration: BoxDecoration(
                color: unread ? colors.primary : Colors.transparent,
                shape: BoxShape.circle,
              ),
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Text(
                          notification.title,
                          style: textTheme.titleSmall?.copyWith(
                            fontWeight: unread
                                ? FontWeight.w900
                                : FontWeight.w600,
                          ),
                        ),
                      ),
                      if (trailing != null) ...[
                        const SizedBox(width: AppSpacing.sm),
                        trailing!,
                      ],
                    ],
                  ),
                  const SizedBox(height: AppSpacing.xs),
                  Text(
                    notification.message,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: textTheme.bodySmall?.copyWith(
                      color: colors.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.xs),
                  Text(
                    subtitle ??
                        '${notification.categoryLabel} · ${dateTime(notification.createdAt)}',
                    style: textTheme.bodySmall?.copyWith(
                      color: colors.onSurfaceVariant,
                    ),
                  ),
                  if (footer != null) ...[
                    const SizedBox(height: AppSpacing.md),
                    footer!,
                  ],
                ],
              ),
            ),
            Icon(
              Icons.chevron_right_rounded,
              color: colors.onSurfaceVariant,
              semanticLabel: '',
            ),
          ],
        ),
      ),
    );
  }
}
