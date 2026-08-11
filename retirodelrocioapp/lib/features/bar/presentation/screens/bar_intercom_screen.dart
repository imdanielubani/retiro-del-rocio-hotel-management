import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/bar/notifications/application/bar_notification_providers.dart';
import 'package:retirodelrocioapp/features/bar/notifications/presentation/screens/bar_notification_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/screens/bar_chat_screen.dart';
import 'package:retirodelrocioapp/features/bar/presentation/widgets/bar_scaffold.dart';
import 'package:retirodelrocioapp/features/staff_intercom/presentation/widgets/staff_intercom_body.dart';

/// The Bar Tablet's Intercom screen — the shared staff directory
/// (voice-call Reception, Kitchen, Housekeeping or Security) inside the same
/// frosted shell every other Bar Tablet screen uses.
class BarIntercomScreen extends ConsumerWidget {
  const BarIntercomScreen({super.key, required this.session});

  final StaffSession session;

  void _openNotifications(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => BarNotificationScreen(session: session),
      ),
    );
  }

  void _openChat(BuildContext context) {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => BarChatScreen(session: session)));
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
      onChat: () => _openChat(context),
      title: 'Intercom',
      body: StaffIntercomBody(session: session),
    );
  }
}
