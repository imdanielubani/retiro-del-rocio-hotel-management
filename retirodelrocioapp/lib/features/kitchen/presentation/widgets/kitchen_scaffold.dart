import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/core/widgets/staff_top_bar.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/session_guard.dart';
import 'package:retirodelrocioapp/features/kitchen/notifications/application/kitchen_notification_providers.dart';
import 'package:retirodelrocioapp/features/kitchen/notifications/presentation/screens/kitchen_notification_screen.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_nav_rail.dart';
import 'package:retirodelrocioapp/features/staff_chat/application/staff_chat_providers.dart';
import 'package:retirodelrocioapp/features/staff_intercom/presentation/widgets/staff_intercom_call_gate.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

/// The frosted Kitchen Tablet shell shared by every screen — the module
/// rail, the header bar, and a titled page heading with an optional back
/// button and a trailing slot — pixel-matches `MaintenanceScaffold`/
/// `ReceptionScaffold` so every staff tablet reads the same way, per the
/// left-nav-rail layout the Kitchen Tablet uses (unlike the Bar Tablet's
/// bottom-tab-bar shell).
class KitchenScaffold extends ConsumerWidget {
  const KitchenScaffold({
    super.key,
    required this.session,
    required this.active,
    required this.onNav,
    required this.onLogout,
    required this.title,
    required this.body,
    this.subtitle = 'Kitchen',
    this.onBack,
    this.trailing,
    this.hasAlert = false,
  });

  final StaffSession session;
  final KitchenNavItem active;
  final ValueChanged<KitchenNavItem> onNav;
  final VoidCallback onLogout;
  final String title;
  final String subtitle;
  final Widget body;

  /// When set, a round back button appears left of the heading.
  final VoidCallback? onBack;

  /// An optional widget pinned to the right of the heading (e.g. an action button).
  final Widget? trailing;

  /// A live new-ticket alert lights the top bar's bell dot red — outranks a
  /// plain unread notification.
  final bool hasAlert;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final weather = ref.watch(weatherProvider).value;
    // Keeps the new-order chime, staff-chat chime and incoming-call ringer
    // alive on every Kitchen screen that uses this shell.
    ref.watch(kitchenNotificationChimeProvider(session.token));
    ref.watch(staffChatChimeProvider(session.token));
    ref.watch(staffChatRealtimeProvider((session.token, session.userId)));
    watchStaffIntercomCall(context, ref, session);

    final unreadNotifications = ref.watch(
      kitchenUnreadNotificationsProvider(session.token),
    );

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
                    KitchenNavRail(
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
                            hasAlert: hasAlert,
                            hasUnreadNotifications: unreadNotifications > 0,
                            onNotifications: () => Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) =>
                                    KitchenNotificationScreen(session: session),
                              ),
                            ),
                            initialsFallback: 'KT',
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
