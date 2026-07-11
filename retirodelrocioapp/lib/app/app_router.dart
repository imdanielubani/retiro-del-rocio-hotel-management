import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/device_setup/presentation/screens/device_setup_screen.dart';
import 'package:retirodelrocioapp/features/onboarding/presentation/screens/onboarding_screen.dart';
import 'package:retirodelrocioapp/features/splash/presentation/screens/splash_screen.dart';
import 'package:retirodelrocioapp/features/welcome/presentation/screens/welcome_screen.dart';

/// Central navigation graph for the tablet app.
///
/// Linear pairing flow: splash → onboarding → device setup → welcome.
final appRouter = GoRouter(
  initialLocation: Routes.splash,
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
        final device = state.extra;
        // Guard against a direct/deep navigation without a paired device.
        if (device is! ProvisionedDevice) return const DeviceSetupScreen();
        return WelcomeScreen(device: device);
      },
    ),
  ],
);

/// Route paths, referenced instead of raw strings.
abstract final class Routes {
  static const splash = '/';
  static const onboarding = '/onboarding';
  static const setup = '/setup';
  static const welcome = '/welcome';
}
