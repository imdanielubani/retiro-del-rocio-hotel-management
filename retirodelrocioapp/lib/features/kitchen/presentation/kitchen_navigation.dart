import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/screens/kitchen_chat_screen.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/screens/kitchen_history_screen.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/screens/kitchen_intercom_screen.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/screens/kitchen_menu_screen.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/screens/kitchen_queue_screen.dart';
import 'package:retirodelrocioapp/features/kitchen/presentation/widgets/kitchen_nav_rail.dart';

/// Rail navigation for the kitchen module, kept in one place so the
/// dashboard (Live Board) and every sub-screen route the same way — mirrors
/// `MaintenanceNavigation`'s shape.
class KitchenNavigation {
  const KitchenNavigation._();

  static const _prefix = 'kitchen/';

  static void open(
    BuildContext context,
    StaffSession session,
    KitchenNavItem item,
  ) {
    final page = _pageFor(item, session);
    if (page == null) return;
    Navigator.of(context).push(_route(item, page));
  }

  static void select(
    BuildContext context,
    StaffSession session,
    KitchenNavItem item, {
    required KitchenNavItem current,
  }) {
    if (item == current) return;

    // Always return to the Live Board root first — it's never a named
    // `kitchen/` route itself (it occupies the route slot the login screen
    // replaced), so popping past it here would otherwise be irreversible.
    Navigator.of(
      context,
    ).popUntil((route) => !(route.settings.name?.startsWith(_prefix) ?? false));

    if (item == KitchenNavItem.liveBoard) return;

    final page = _pageFor(item, session);
    if (page == null) return;
    Navigator.of(context).push(_route(item, page));
  }

  static void afterLogout(BuildContext context) {
    final navigator = Navigator.of(context);
    navigator.popUntil(
      (route) => !(route.settings.name?.startsWith(_prefix) ?? false),
    );
    navigator.pop(); // the staff dashboard, revealing the login screen
  }

  static Widget? _pageFor(KitchenNavItem item, StaffSession session) =>
      switch (item) {
        KitchenNavItem.queue => KitchenQueueScreen(session: session),
        KitchenNavItem.menu => KitchenMenuScreen(session: session),
        KitchenNavItem.history => KitchenHistoryScreen(session: session),
        KitchenNavItem.chat => KitchenChatScreen(session: session),
        KitchenNavItem.intercom => KitchenIntercomScreen(session: session),
        _ => null,
      };

  static Route<void> _route(KitchenNavItem item, Widget page) =>
      MaterialPageRoute<void>(
        builder: (_) => page,
        settings: RouteSettings(name: _prefix + item.name),
      );
}
