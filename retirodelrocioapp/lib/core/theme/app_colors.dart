import 'package:flutter/material.dart';

/// Brand palette for the Rocio tablet app, taken from the Figma design system.
abstract final class AppColors {
  const AppColors._();

  /// Primary brand gold (#FFB11B) — logo, headings, accents.
  static const Color gold = Color(0xFFFFB11B);

  /// Muted gold (#C9A227) — secondary accents like the "Skip" control.
  static const Color goldAccent = Color(0xFFC9A227);

  /// Near-black used for text/icons placed on a solid gold surface.
  static const Color onGold = Color(0xFF0B0606);

  /// Success green (#34D399) — paired confirmation, availability dot.
  static const Color success = Color(0xFF34D399);

  /// Warm glow behind the logo — rgba(201,162,39,0.6).
  static const Color goldGlow = Color(0x99C9A227);

  /// App background — deep near-black.
  static const Color background = Color(0xFF0D0D0D);

  /// 85% black scrim laid over background imagery.
  static const Color scrim = Color(0xD9000000);

  static const Color textFaint = Color(0x4DFFFFFF); // white @ 30%
  static const Color textMuted = Color(0x73FFFFFF); // white @ 45%
}
