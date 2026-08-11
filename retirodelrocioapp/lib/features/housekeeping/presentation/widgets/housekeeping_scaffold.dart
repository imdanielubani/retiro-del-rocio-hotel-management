import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/core/widgets/staff_top_bar.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/session_guard.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/application/housekeeping_notification_providers.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_nav_rail.dart';
import 'package:retirodelrocioapp/features/staff_chat/application/staff_chat_providers.dart';
import 'package:retirodelrocioapp/features/staff_intercom/presentation/widgets/staff_intercom_call_gate.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

/// The frosted housekeeping shell shared by the sub-screens (Rooms, Requests,
/// Notifications): the module rail, the header bar, and a titled page
/// heading with an optional back button and a trailing slot — pixel-matches
/// `ReceptionScaffold` so every staff tablet's secondary screens read the
/// same way. The dashboard keeps its own inline layout, same as reception's.
class HousekeepingScaffold extends ConsumerWidget {
  const HousekeepingScaffold({
    super.key,
    required this.session,
    required this.active,
    required this.onNav,
    required this.onLogout,
    required this.title,
    required this.body,
    this.subtitle = 'Housekeeping',
    this.onBack,
    this.trailing,
    this.hasUnreadNotifications = false,
    this.onNotifications,
  });

  final StaffSession session;
  final HousekeepingNavItem active;
  final ValueChanged<HousekeepingNavItem> onNav;
  final VoidCallback onLogout;
  final String title;
  final String subtitle;
  final Widget body;

  /// When set, a round back button appears left of the heading.
  final VoidCallback? onBack;

  /// An optional widget pinned to the right of the heading (e.g. a search box).
  final Widget? trailing;

  /// Lights the top bar's bell badge gold when housekeeping has an unread
  /// notification.
  final bool hasUnreadNotifications;

  /// Opens the housekeeping Notifications screen. Left null on a screen that
  /// doesn't wire it up yet, in which case the bell is inert.
  final VoidCallback? onNotifications;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final weather = ref.watch(weatherProvider).value;
    // Keeps the notification chime alive on every housekeeping screen that
    // uses this shell — not just the dashboard.
    ref.watch(housekeepingNotificationChimeProvider(session.token));
    ref.watch(staffChatChimeProvider(session.token));
    ref.watch(staffChatRealtimeProvider((session.token, session.role)));
    watchStaffIntercomCall(context, ref, session);

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
                    HousekeepingNavRail(
                      active: active,
                      onSelect: onNav,
                      onLogout: onLogout,
                    ),
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
                            initialsFallback: 'HK',
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

  /// Same shape as the guest tablet's own notification back button — a 40px
  /// frosted circle, not the 44px one `ReceptionScaffold` uses elsewhere —
  /// so every notification screen in the app reads identically.
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
          width: 40,
          height: 40,
          child: Icon(
            Icons.arrow_back_rounded,
            size: 18,
            color: Colors.white.withValues(alpha: 0.8),
          ),
        ),
      ),
    );
  }
}

/// The frosted search box used on the Rooms header — pixel-matches
/// `ReceptionSearchField`.
class HousekeepingSearchField extends StatelessWidget {
  const HousekeepingSearchField({
    super.key,
    required this.hint,
    required this.onChanged,
    this.width = 280,
  });

  final String hint;
  final ValueChanged<String> onChanged;
  final double width;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: width,
      height: 44,
      child: TextField(
        onChanged: onChanged,
        cursorColor: AppColors.gold,
        style: AppTypography.style(color: Colors.white, fontSize: 14),
        decoration: InputDecoration(
          isDense: true,
          filled: true,
          fillColor: Colors.white.withValues(alpha: 0.06),
          hintText: hint,
          hintStyle: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.35),
            fontSize: 14,
          ),
          prefixIcon: Icon(
            Icons.search_rounded,
            size: 18,
            color: Colors.white.withValues(alpha: 0.4),
          ),
          contentPadding: const EdgeInsets.symmetric(vertical: 12),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(
              color: Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(
              color: Colors.white.withValues(alpha: 0.1),
              width: 0.8,
            ),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(
              color: AppColors.gold.withValues(alpha: 0.5),
              width: 1,
            ),
          ),
        ),
      ),
    );
  }
}
