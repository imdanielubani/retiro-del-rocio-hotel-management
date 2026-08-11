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
import 'package:retirodelrocioapp/features/staff_chat/presentation/widgets/staff_chat_body.dart';

/// The maintenance tablet's Chat screen — the shared Staff Chat body (talk to
/// Reception, Housekeeping, Security, or the Admin dashboard) inside the
/// same frosted shell every other maintenance screen uses.
class MaintenanceChatScreen extends ConsumerStatefulWidget {
  const MaintenanceChatScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<MaintenanceChatScreen> createState() =>
      _MaintenanceChatScreenState();
}

class _MaintenanceChatScreenState extends ConsumerState<MaintenanceChatScreen> {
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
          current: MaintenanceNavItem.chat,
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
      active: MaintenanceNavItem.chat,
      onNav: (item) => MaintenanceNavigation.select(
        context,
        widget.session,
        item,
        current: MaintenanceNavItem.chat,
      ),
      onLogout: _logout,
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: _openNotifications,
      title: 'Chat',
      body: StaffChatBody(session: widget.session),
    );
  }
}
