import 'package:flutter/material.dart';

/// The single typeface used across the whole app: **Poppins**.
///
/// The `.ttf` weights are bundled under `assets/fonts/` and declared in
/// `pubspec.yaml`, so the font renders fully offline (no runtime download).
abstract final class AppTypography {
  const AppTypography._();

  static const String fontFamily = 'Poppins';

  /// Applies Poppins to every text style of [base]. Used for the app-wide
  /// [ThemeData.textTheme] so all default text renders in Poppins.
  static TextTheme textTheme(TextTheme base) =>
      base.apply(fontFamily: fontFamily);

  /// A Poppins [TextStyle] with the given attributes — for one-off design text.
  static TextStyle style({
    Color? color,
    double? fontSize,
    FontWeight? fontWeight,
    double? letterSpacing,
    double? height,
  }) => TextStyle(
    fontFamily: fontFamily,
    color: color,
    fontSize: fontSize,
    fontWeight: fontWeight,
    letterSpacing: letterSpacing,
    height: height,
  );
}
