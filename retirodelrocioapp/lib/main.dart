import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/app/app.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Log which backend the app is pointed at (debug/profile only).
  if (!kReleaseMode) {
    debugPrint(
      '🏨 Rocio Tablet · env=${ApiConfig.environment.name} · API=${ApiConfig.baseUrl}',
    );
  }

  // In-hotel tablet: lock to landscape and run edge-to-edge with the system
  // status bar and navigation bar hidden (kiosk-style). immersiveSticky lets
  // them briefly reappear on a swipe, then auto-hides again.
  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.landscapeLeft,
    DeviceOrientation.landscapeRight,
  ]);
  await SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);

  runApp(const ProviderScope(child: RocioTabletApp()));
}
