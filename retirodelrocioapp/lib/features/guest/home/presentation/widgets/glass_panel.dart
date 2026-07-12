import 'dart:ui';

import 'package:flutter/material.dart';

/// The frosted surface used across the guest home — 7% white over a 15px blur
/// with a hairline 12% border (Figma 84:3429).
class GlassPanel extends StatelessWidget {
  const GlassPanel({
    super.key,
    required this.child,
    this.borderRadius = 20,
    this.padding = EdgeInsets.zero,
    this.opacity = 0.07,
    this.borderOpacity = 0.12,
    this.blur = 15,
    this.width,
    this.height,
  });

  final Widget child;
  final double borderRadius;
  final EdgeInsetsGeometry padding;
  final double opacity;
  final double borderOpacity;
  final double blur;
  final double? width;
  final double? height;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(borderRadius);

    return ClipRRect(
      borderRadius: radius,
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: blur, sigmaY: blur),
        child: Container(
          width: width,
          height: height,
          padding: padding,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: opacity),
            borderRadius: radius,
            border: Border.all(
              color: Colors.white.withValues(alpha: borderOpacity),
              width: 0.8,
            ),
          ),
          child: child,
        ),
      ),
    );
  }
}
