import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/core/widgets/staff_top_bar.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/session_guard.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_nav_rail.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

/// The frosted maintenance shell shared by the sub-screens (Work Orders):
/// the module rail, the header bar, and a titled page heading with an
/// optional back button and a trailing slot — pixel-matches
/// `HousekeepingScaffold`/`ReceptionScaffold` so every staff tablet's
/// secondary screens read the same way. The dashboard keeps its own inline
/// layout, same as reception's and housekeeping's.
class MaintenanceScaffold extends ConsumerWidget {
  const MaintenanceScaffold({
    super.key,
    required this.session,
    required this.active,
    required this.onNav,
    required this.onLogout,
    required this.title,
    required this.body,
    this.subtitle = 'Maintenance',
    this.onBack,
    this.trailing,
    this.hasUnreadNotifications = false,
    this.onNotifications,
  });

  final StaffSession session;
  final MaintenanceNavItem active;
  final ValueChanged<MaintenanceNavItem> onNav;
  final VoidCallback onLogout;
  final String title;
  final String subtitle;
  final Widget body;

  /// When set, a round back button appears left of the heading.
  final VoidCallback? onBack;

  /// An optional widget pinned to the right of the heading (e.g. an action button).
  final Widget? trailing;

  /// Lights the top bar's bell badge gold when maintenance has an unread notification.
  final bool hasUnreadNotifications;

  /// Opens the maintenance Notifications screen. Left null on a screen that
  /// doesn't wire it up yet, in which case the bell is inert.
  final VoidCallback? onNotifications;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final weather = ref.watch(weatherProvider).value;

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
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    MaintenanceNavRail(active: active, onSelect: onNav, onLogout: onLogout),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          StaffTopBar(
                            name: session.name,
                            role: subtitle,
                            brandLabel: subtitle.toUpperCase(),
                            weather: weather,
                            hasUnreadNotifications: hasUnreadNotifications,
                            onNotifications: onNotifications,
                            initialsFallback: 'MT',
                          ),
                          const SizedBox(height: 20),
                          _header(),
                          const SizedBox(height: 20),
                          Expanded(child: body),
                        ],
                      ),
                    ),
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
              Text(subtitle, style: AppTypography.style(color: AppColors.gold, fontSize: 12)),
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
        side: BorderSide(color: Colors.white.withValues(alpha: 0.1), width: 0.8),
      ),
      child: InkWell(
        onTap: onBack,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 40,
          height: 40,
          child: Icon(Icons.arrow_back_rounded, size: 18, color: Colors.white.withValues(alpha: 0.8)),
        ),
      ),
    );
  }
}
