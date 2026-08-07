import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/application/maintenance_notification_providers.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/presentation/screens/maintenance_notification_screen.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/maintenance_navigation.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_nav_rail.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_scaffold.dart';
import 'package:retirodelrocioapp/features/staff_intercom/presentation/widgets/staff_intercom_body.dart';

/// The maintenance tablet's Intercom screen — the shared staff directory
/// (voice-call Reception, Housekeeping or Security) inside the same frosted
/// shell every other maintenance screen uses.
class MaintenanceIntercomScreen extends ConsumerStatefulWidget {
  const MaintenanceIntercomScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<MaintenanceIntercomScreen> createState() =>
      _MaintenanceIntercomScreenState();
}

class _MaintenanceIntercomScreenState
    extends ConsumerState<MaintenanceIntercomScreen> {
  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) MaintenanceNavigation.afterLogout(context);
  }

  void _openNotifications() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MaintenanceNotificationScreen(
          session: widget.session,
          current: MaintenanceNavItem.intercom,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final unreadNotifications = ref.watch(
      maintenanceUnreadNotificationsProvider(_token),
    );

    return MaintenanceScaffold(
      session: widget.session,
      active: MaintenanceNavItem.intercom,
      onNav: (item) => MaintenanceNavigation.select(
        context,
        widget.session,
        item,
        current: MaintenanceNavItem.intercom,
      ),
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Intercom',
      body: StaffIntercomBody(session: widget.session),
    );
  }
}
