import 'package:flutter/material.dart';

@immutable
class StatusColorPair {
  const StatusColorPair({required this.container, required this.onContainer});

  final Color container;
  final Color onContainer;

  static StatusColorPair lerp(StatusColorPair a, StatusColorPair b, double t) {
    return StatusColorPair(
      container: Color.lerp(a.container, b.container, t)!,
      onContainer: Color.lerp(a.onContainer, b.onContainer, t)!,
    );
  }
}

@immutable
class AppStatusColors extends ThemeExtension<AppStatusColors> {
  const AppStatusColors({
    required this.success,
    required this.danger,
    required this.warning,
    required this.info,
    required this.neutral,
  });

  final StatusColorPair success;
  final StatusColorPair danger;
  final StatusColorPair warning;
  final StatusColorPair info;
  final StatusColorPair neutral;

  @override
  AppStatusColors copyWith({
    StatusColorPair? success,
    StatusColorPair? danger,
    StatusColorPair? warning,
    StatusColorPair? info,
    StatusColorPair? neutral,
  }) {
    return AppStatusColors(
      success: success ?? this.success,
      danger: danger ?? this.danger,
      warning: warning ?? this.warning,
      info: info ?? this.info,
      neutral: neutral ?? this.neutral,
    );
  }

  @override
  AppStatusColors lerp(ThemeExtension<AppStatusColors>? other, double t) {
    if (other is! AppStatusColors) return this;
    return AppStatusColors(
      success: StatusColorPair.lerp(success, other.success, t),
      danger: StatusColorPair.lerp(danger, other.danger, t),
      warning: StatusColorPair.lerp(warning, other.warning, t),
      info: StatusColorPair.lerp(info, other.info, t),
      neutral: StatusColorPair.lerp(neutral, other.neutral, t),
    );
  }
}
