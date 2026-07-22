import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/device_setup/presentation/screens/device_setup_screen.dart';
import 'package:retirodelrocioapp/features/onboarding/presentation/screens/onboarding_screen.dart';
import 'package:retirodelrocioapp/features/splash/presentation/screens/splash_screen.dart';
import 'package:retirodelrocioapp/features/welcome/presentation/screens/welcome_screen.dart';

/// The already-paired device loaded from disk at startup (null if unpaired).
/// Overridden in `main()` after reading [DeviceSessionStore].
final bootstrapDeviceProvider = Provider<ProvisionedDevice?>((ref) => null);

/// Central navigation graph.
///
/// First-time flow: splash → onboarding → device setup → welcome.
/// Once a tablet is paired, it is persisted, so on every subsequent launch the
/// app **boots straight to the welcome screen** and stays there.
final routerProvider = Provider<GoRouter>((ref) {
  final paired = ref.read(bootstrapDeviceProvider);

  return GoRouter(
    initialLocation: paired != null ? Routes.welcome : Routes.splash,
    routes: [
      GoRoute(
        path: Routes.splash,
        builder: (context, state) => const SplashScreen(),
      ),
      GoRoute(
        path: Routes.onboarding,
        pageBuilder: (context, state) => CustomTransitionPage(
          key: state.pageKey,
          transitionDuration: const Duration(milliseconds: 600),
          child: const OnboardingScreen(),
          transitionsBuilder: (context, animation, _, child) =>
              FadeTransition(opacity: animation, child: child),
        ),
      ),
      GoRoute(
        path: Routes.setup,
        builder: (context, state) => const DeviceSetupScreen(),
      ),
      GoRoute(
        path: Routes.welcome,
        builder: (context, state) {
          // Prefer the device passed on pairing; fall back to the persisted one.
          final device = state.extra is ProvisionedDevice
              ? state.extra as ProvisionedDevice
              : paired;
          if (device == null) return const DeviceSetupScreen();
          return WelcomeScreen(device: device);
        },
      ),
    ],
  );
});

/// Route paths, referenced instead of raw strings.
abstract final class Routes {
  static const splash = '/';
  static const onboarding = '/onboarding';
  static const setup = '/setup';
  static const welcome = '/welcome';
}
