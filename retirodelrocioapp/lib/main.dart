import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/app/app.dart';
import 'package:retirodelrocioapp/app/app_router.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/core/session/device_session_service.dart';
import 'package:retirodelrocioapp/core/storage/device_session_store.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Log which backend the app is pointed at (debug/profile only).
  if (!kReleaseMode) {
    debugPrint(
      'Rocio Tablet · env=${ApiConfig.environment.name} · API=${ApiConfig.baseUrl}',
    );
  }

  // In-hotel tablet: lock to landscape only (never portrait, even when the
  // tablet is physically turned) and run edge-to-edge with the system bars
  // hidden (kiosk-style).
  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.landscapeLeft,
    DeviceOrientation.landscapeRight,
  ]);
  await SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);

  // If this tablet was already paired, boot straight to the welcome screen —
  // but first confirm the hotel still knows it. A tablet deleted from the admin
  // dashboard has had its token revoked, so it starts afresh at device setup.
  // (An offline tablet keeps its pairing; only an explicit rejection clears it.)
  var pairedDevice = await DeviceSessionStore().read();
  if (pairedDevice != null) {
    pairedDevice = await DeviceSessionService().verify(pairedDevice);
  }

  runApp(
    ProviderScope(
      overrides: [bootstrapDeviceProvider.overrideWithValue(pairedDevice)],
      child: const RocioTabletApp(),
    ),
  );
}
