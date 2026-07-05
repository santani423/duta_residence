import 'package:flutter/material.dart';

import '../constants/app_spacing.dart';
import '../utils/formatters.dart';

class InfoRows extends StatelessWidget {
  const InfoRows({required this.items, super.key});

  final Map<String, Object?> items;

  @override
  Widget build(BuildContext context) {
    final entries = items.entries.toList();
    return Column(
      children: [
        for (var index = 0; index < entries.length; index++) ...[
          _InfoRow(
            label: entries[index].key,
            value: compact(entries[index].value),
          ),
          if (index != entries.length - 1)
            const SizedBox(height: AppSpacing.md),
        ],
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          flex: 4,
          child: Text(label, style: TextStyle(color: colors.onSurfaceVariant)),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          flex: 5,
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
      ],
    );
  }
}
