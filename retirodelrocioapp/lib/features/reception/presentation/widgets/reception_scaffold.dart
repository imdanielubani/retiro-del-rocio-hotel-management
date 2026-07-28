import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/session_guard.dart';
import 'package:retirodelrocioapp/features/reception/notifications/application/reception_notification_providers.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_nav_rail.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_top_bar.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

/// The frosted reception shell shared by the sub-screens (Guests, Bookings,
/// Guest Profile): the module rail, the header bar, and a titled page heading
/// with an optional back button and a trailing slot. The dashboard keeps its own
/// inline layout; this exists so the secondary screens stay pixel-consistent
/// without duplicating the chrome.
class ReceptionScaffold extends ConsumerWidget {
  const ReceptionScaffold({
    super.key,
    required this.session,
    required this.active,
    required this.onNav,
    required this.onLogout,
    required this.title,
    required this.body,
    this.subtitle = 'Reception',
    this.onBack,
    this.trailing,
    this.hasUnreadNotifications = false,
    this.onNotifications,
  });

  final StaffSession session;
  final ReceptionNavItem active;
  final ValueChanged<ReceptionNavItem> onNav;
  final VoidCallback onLogout;
  final String title;
  final String subtitle;
  final Widget body;

  /// When set, a round back button appears left of the heading.
  final VoidCallback? onBack;

  /// An optional widget pinned to the right of the heading (e.g. a search box).
  final Widget? trailing;

  /// Lights the top bar's bell badge gold when the front desk has an unread
  /// notification.
  final bool hasUnreadNotifications;

  /// Opens the reception Notifications screen. Left null on a screen that
  /// doesn't wire it up yet, in which case the bell is inert.
  final VoidCallback? onNotifications;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final weather = ref.watch(weatherProvider).value;
    // Keeps the notification chime alive on every reception screen that uses
    // this shell — not just the dashboard.
    ref.watch(receptionNotificationChimeProvider(session.token));

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
                    ReceptionNavRail(
                      active: active,
                      onSelect: onNav,
                      onLogout: onLogout,
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          ReceptionTopBar(
                            name: session.name,
                            role: subtitle,
                            weather: weather,
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
        if (onBack != null) ...[
          _backButton(),
          const SizedBox(width: 15),
        ],
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
        side: BorderSide(color: Colors.white.withValues(alpha: 0.1), width: 0.8),
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

/// The frosted search box used on the Guests and Bookings headers.
class ReceptionSearchField extends StatelessWidget {
  const ReceptionSearchField({
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
            borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.1), width: 0.8),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.1), width: 0.8),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(color: AppColors.gold.withValues(alpha: 0.5), width: 1),
          ),
        ),
      ),
    );
  }
}
