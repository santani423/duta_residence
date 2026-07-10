import 'package:flutter/material.dart';

@immutable
class TilePair {
  const TilePair({required this.container, required this.onContainer});

  final Color container;
  final Color onContainer;

  static TilePair lerp(TilePair a, TilePair b, double t) {
    return TilePair(
      container: Color.lerp(a.container, b.container, t)!,
      onContainer: Color.lerp(a.onContainer, b.onContainer, t)!,
    );
  }
}

@immutable
class AppPalette extends ThemeExtension<AppPalette> {
  const AppPalette({required this.tiles});

  final List<TilePair> tiles;

  TilePair tile(int index) => tiles[index % tiles.length];

  static const light = AppPalette(
    tiles: [
      TilePair(container: Color(0xFFD1FAE5), onContainer: Color(0xFF047857)),
      TilePair(container: Color(0xFFFFE4D6), onContainer: Color(0xFFC2410C)),
      TilePair(container: Color(0xFFDDEAFE), onContainer: Color(0xFF1D4ED8)),
      TilePair(container: Color(0xFFF3E1FF), onContainer: Color(0xFF7E22CE)),
      TilePair(container: Color(0xFFFFE1EE), onContainer: Color(0xFFBE185D)),
      TilePair(container: Color(0xFFFEF3C2), onContainer: Color(0xFFA16207)),
    ],
  );

  static const dark = AppPalette(
    tiles: [
      TilePair(container: Color(0xFF0B4A3B), onContainer: Color(0xFF86EFC5)),
      TilePair(container: Color(0xFF5A2E15), onContainer: Color(0xFFFDBA8C)),
      TilePair(container: Color(0xFF16305C), onContainer: Color(0xFF9DC0FF)),
      TilePair(container: Color(0xFF3E1E5C), onContainer: Color(0xFFDFB8FF)),
      TilePair(container: Color(0xFF5C1B38), onContainer: Color(0xFFFFAAD0)),
      TilePair(container: Color(0xFF553E0A), onContainer: Color(0xFFFCE092)),
    ],
  );

  @override
  AppPalette copyWith({List<TilePair>? tiles}) =>
      AppPalette(tiles: tiles ?? this.tiles);

  @override
  AppPalette lerp(ThemeExtension<AppPalette>? other, double t) {
    if (other is! AppPalette) return this;
    final length = tiles.length < other.tiles.length
        ? tiles.length
        : other.tiles.length;
    return AppPalette(
      tiles: [
        for (var i = 0; i < length; i++)
          TilePair.lerp(tiles[i], other.tiles[i], t),
      ],
    );
  }
}
