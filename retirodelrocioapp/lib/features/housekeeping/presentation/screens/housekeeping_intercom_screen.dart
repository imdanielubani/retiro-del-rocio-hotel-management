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
import 'package:retirodelrocioapp/features/staff_intercom/presentation/widgets/staff_intercom_body.dart';

/// The housekeeping tablet's Intercom screen — the shared staff directory
/// (voice-call Reception, Maintenance or Security) inside the same frosted
/// shell every other housekeeping screen uses.
class HousekeepingIntercomScreen extends ConsumerStatefulWidget {
  const HousekeepingIntercomScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<HousekeepingIntercomScreen> createState() =>
      _HousekeepingIntercomScreenState();
}

class _HousekeepingIntercomScreenState
    extends ConsumerState<HousekeepingIntercomScreen> {
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
      current: HousekeepingNavItem.intercom,
    );
  }

  void _openNotifications() {
    HousekeepingNavigation.push(
      context,
      'notifications',
      HousekeepingNotificationScreen(
        session: widget.session,
        current: HousekeepingNavItem.intercom,
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
      active: HousekeepingNavItem.intercom,
      onNav: _onNav,
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Intercom',
      body: StaffIntercomBody(session: widget.session),
    );
  }
}
