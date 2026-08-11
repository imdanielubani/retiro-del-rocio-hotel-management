import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/application/housekeeping_notification_providers.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/presentation/screens/housekeeping_notification_screen.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/housekeeping_navigation.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_nav_rail.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_scaffold.dart';
import 'package:retirodelrocioapp/features/staff_chat/presentation/widgets/staff_chat_body.dart';

/// The housekeeping tablet's Chat screen — the shared Staff Chat body (talk
/// to Reception, Maintenance, Security, or the Admin dashboard) inside the
/// same frosted shell every other housekeeping screen uses.
class HousekeepingChatScreen extends ConsumerStatefulWidget {
  const HousekeepingChatScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<HousekeepingChatScreen> createState() =>
      _HousekeepingChatScreenState();
}

class _HousekeepingChatScreenState
    extends ConsumerState<HousekeepingChatScreen> {
  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) HousekeepingNavigation.afterLogout(context);
  }

  void _onNav(HousekeepingNavItem item) {
    HousekeepingNavigation.select(
      context,
      widget.session,
      item,
      current: HousekeepingNavItem.chat,
    );
  }

  void _openNotifications() {
    HousekeepingNavigation.push(
      context,
      'notifications',
      HousekeepingNotificationScreen(
        session: widget.session,
        current: HousekeepingNavItem.chat,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final unreadNotifications = ref.watch(
      housekeepingUnreadNotificationsProvider(_token),
    );

    return HousekeepingScaffold(
      session: widget.session,
      active: HousekeepingNavItem.chat,
      onNav: _onNav,
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Chat',
      body: StaffChatBody(session: widget.session),
    );
  }
}
