import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/session_guard.dart';
import 'package:retirodelrocioapp/features/bar/notifications/application/bar_notification_providers.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_top_bar.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

/// The frosted Bar Tablet shell shared by every screen pushed on top of
/// [BarShell] (Order Detail, Tab Detail, Point of Sale): the same
/// [BarTopBar] header and a titled page heading with an optional back
/// button and trailing slot — mirrors [ReceptionScaffold] so the Bar Tablet
/// carries its header on every screen, not just the bottom-tab shell.
class BarScaffold extends ConsumerWidget {
  const BarScaffold({
    super.key,
    required this.session,
    required this.title,
    required this.body,
    this.subtitle = 'Bar & Lounge',
    this.onBack,
    this.trailing,
    this.hasAlert = false,
    this.hasUnreadNotifications = false,
    this.onNotifications,
  });

  final StaffSession session;
  final String title;
  final Widget body;
  final String subtitle;

  /// When set, a round back button appears left of the heading.
  final VoidCallback? onBack;

  /// An optional widget pinned to the right of the heading (e.g. the VIP toggle).
  final Widget? trailing;
  final bool hasAlert;
  final bool hasUnreadNotifications;
  final VoidCallback? onNotifications;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final weather = ref.watch(weatherProvider).value;
    // Keeps the new-order chime alive on every Bar screen that uses this
    // shell, not just the bottom-tab dashboard.
    ref.watch(barNotificationChimeProvider(session.token));

    return SessionGuard(
      child: Scaffold(
        backgroundColor: AppColors.background,
        body: Stack(
          fit: StackFit.expand,
          children: [
            Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
            const ColoredBox(color: Color(0xF2000000)),
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    BarTopBar(
                      name: session.name,
                      role: subtitle,
                      weather: weather,
                      hasAlert: hasAlert,
                      hasUnreadNotifications: hasUnreadNotifications,
                      onNotifications: onNotifications,
                    ),
                    const SizedBox(height: 20),
                    _header(),
                    const SizedBox(height: 20),
                    Expanded(child: body),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _header() {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        if (onBack != null) ...[_backButton(), const SizedBox(width: 15)],
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                subtitle,
                style: AppTypography.style(color: AppColors.gold, fontSize: 12),
              ),
              const SizedBox(height: 3),
              Text(
                title,
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 36,
                  fontWeight: FontWeight.w700,
                  height: 1.1,
                ),
              ),
            ],
          ),
        ),
        ?trailing,
      ],
    );
  }

  Widget _backButton() {
    return Material(
      color: Colors.white.withValues(alpha: 0.06),
      shape: CircleBorder(
        side: BorderSide(
          color: Colors.white.withValues(alpha: 0.1),
          width: 0.8,
        ),
      ),
      child: InkWell(
        onTap: onBack,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 44,
          height: 44,
          child: Icon(
            Icons.arrow_back_rounded,
            size: 20,
            color: Colors.white.withValues(alpha: 0.8),
          ),
        ),
      ),
    );
  }
}
