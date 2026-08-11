import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/bar/notifications/application/bar_notification_providers.dart';
import 'package:retirodelrocioapp/features/bar/notifications/presentation/screens/bar_notification_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/screens/bar_intercom_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_scaffold.dart';
import 'package:retirodelrocioapp/features/staff_chat/presentation/widgets/staff_chat_body.dart';

/// The Bar Tablet's Chat screen — the shared Staff Chat body (talk to
/// Reception, Kitchen, Housekeeping, Security or the Admin dashboard) inside
/// the same frosted shell every other Bar Tablet screen uses.
class BarChatScreen extends ConsumerWidget {
  const BarChatScreen({super.key, required this.session});

  final StaffSession session;

  void _openNotifications(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => BarNotificationScreen(session: session),
      ),
    );
  }

  void _openIntercom(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => BarIntercomScreen(session: session)),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final unreadNotifications = ref.watch(
      barUnreadNotificationsProvider(session.token),
    );

    return BarScaffold(
      session: session,
      onBack: () => Navigator.of(context).pop(),
      hasUnreadNotifications: unreadNotifications > 0,
      onNotifications: () => _openNotifications(context),
      onIntercom: () => _openIntercom(context),
      title: 'Chat',
      body: StaffChatBody(session: session),
    );
  }
}
