import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../constants/app_spacing.dart';

/// A label/value row like [InfoRows], but for values the user needs to copy
/// elsewhere (e.g. a payment Virtual Account number) - shows a copy button
/// next to the value and a confirmation snackbar, and degrades to a plain
/// "Belum tersedia" state when [value] is null/empty instead of a dead
/// button, since not every unit has one yet.
class CopyableValueRow extends StatelessWidget {
  const CopyableValueRow({
    required this.label,
    required this.value,
    this.emptyLabel = 'Belum tersedia',
    this.copiedMessage = 'Berhasil disalin.',
    super.key,
  });

  final String label;
  final String? value;
  final String emptyLabel;
  final String copiedMessage;

  bool get _hasValue => value != null && value!.trim().isNotEmpty;

  Future<void> _copy(BuildContext context) async {
    await Clipboard.setData(ClipboardData(text: value!.trim()));
    if (!context.mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(copiedMessage)));
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final textTheme = Theme.of(context).textTheme;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Expanded(
          flex: 4,
          child: Text(
            label,
            style: textTheme.bodyMedium?.copyWith(
              color: colors.onSurfaceVariant,
            ),
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          flex: 6,
          child: _hasValue
              ? Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    Flexible(
                      child: SelectableText(
                        value!,
                        textAlign: TextAlign.right,
                        style: textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: colors.onSurface,
                          letterSpacing: -0.1,
                        ),
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.copy_rounded, size: 18),
                      tooltip: 'Salin $label',
                      visualDensity: VisualDensity.compact,
                      onPressed: () => _copy(context),
                    ),
                  ],
                )
              : Text(
                  emptyLabel,
                  textAlign: TextAlign.right,
                  style: textTheme.bodyMedium?.copyWith(
                    color: colors.onSurfaceVariant,
                    fontStyle: FontStyle.italic,
                  ),
                ),
        ),
      ],
    );
  }
}
