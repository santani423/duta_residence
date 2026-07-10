import 'package:flutter/material.dart';

import '../theme/app_status_colors.dart';
import '../utils/formatters.dart';

class StatusBadge extends StatelessWidget {
  const StatusBadge(this.status, {super.key});

  final Object? status;

  @override
  Widget build(BuildContext context) {
    final value = compact(status).toLowerCase();
    final theme = Theme.of(context);
    final statusColors = theme.extension<AppStatusColors>();
    final fallback = StatusColorPair(
      container: theme.colorScheme.surfaceContainerHighest,
      onContainer: theme.colorScheme.onSurfaceVariant,
    );
    final pair = switch (value) {
      'paid' ||
      'read' ||
      'resolved' ||
      'completed' ||
      'closed' => statusColors?.success ?? fallback,
      'overdue' ||
      'urgent' ||
      'emergency' ||
      'rejected' ||
      'failed' => statusColors?.danger ?? fallback,
      'pending' ||
      'waiting_verification' ||
      'submitted' ||
      'in_review' => statusColors?.warning ?? fallback,
      'unpaid' ||
      'in_progress' ||
      'scheduled' => statusColors?.info ?? fallback,
      _ => statusColors?.neutral ?? fallback,
    };

    return DecoratedBox(
      decoration: BoxDecoration(
        color: pair.container,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 6,
              height: 6,
              decoration: BoxDecoration(
                color: pair.onContainer,
                shape: BoxShape.circle,
              ),
            ),
            const SizedBox(width: 6),
            Text(
              titleCaseStatus(status),
              style: TextStyle(
                color: pair.onContainer,
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
