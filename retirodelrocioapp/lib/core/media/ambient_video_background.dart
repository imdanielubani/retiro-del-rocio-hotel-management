import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:video_player/video_player.dart';

/// Full-bleed background that renders the shared ambient [controller] (cover
/// fit) when it's ready, or [fallback] (default: a dark gradient) otherwise.
class AmbientVideoBackground extends StatelessWidget {
  const AmbientVideoBackground({
    super.key,
    required this.controller,
    this.fallback,
  });

  final VideoPlayerController? controller;
  final Widget? fallback;

  @override
  Widget build(BuildContext context) {
    final c = controller;
    if (c != null && c.value.isInitialized) {
      return FittedBox(
        fit: BoxFit.cover,
        clipBehavior: Clip.hardEdge,
        child: SizedBox(
          width: c.value.size.width,
          height: c.value.size.height,
          child: VideoPlayer(c),
        ),
      );
    }
    return fallback ??
        const DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFF1B1712), AppColors.background],
            ),
          ),
        );
  }
}
