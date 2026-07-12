import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/app/app_router.dart';
import 'package:retirodelrocioapp/core/media/ambient_video_scope.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';

/// Root of the Rocio tablet application.
class RocioTabletApp extends ConsumerWidget {
  const RocioTabletApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);
    return MaterialApp.router(
      title: 'Retiro Del Rocio',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        brightness: Brightness.dark,
        scaffoldBackgroundColor: AppColors.background,
        textTheme: AppTypography.textTheme(ThemeData.dark().textTheme),
        colorScheme: ColorScheme.fromSeed(
          seedColor: AppColors.gold,
          brightness: Brightness.dark,
        ),
      ),
      routerConfig: router,
      // In-room kiosk: the screens are laid out to a fixed 1280 × 800 design, so
      // the tablet's system font-size setting must not resize our text — it
      // would push content past the fixed-height cards and bars.
      builder: (context, child) => MediaQuery.withNoTextScaling(
        child: AmbientVideoScope(child: child ?? const SizedBox.shrink()),
      ),
    );
  }
}
