import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/widgets/coming_soon_screen.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/housekeeping/lost_found/presentation/screens/housekeeping_lost_found_screen.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/screens/housekeeping_chat_screen.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/screens/housekeeping_inspection_screen.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/screens/housekeeping_requests_screen.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/screens/housekeeping_rooms_screen.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_nav_rail.dart';

/// Rail navigation for the housekeeping module, kept in one place so the
/// dashboard and every sub-screen route the same way — mirrors
/// `ReceptionNavigation`'s dashboard-is-base-route, sub-screens-replace-each-
/// other shape.
class HousekeepingNavigation {
  const HousekeepingNavigation._();

  static const _prefix = 'housekeeping/';

  static void open(
    BuildContext context,
    StaffSession session,
    HousekeepingNavItem item,
  ) {
    final page = _pageFor(item, session);
    if (page == null) {
      _comingSoon(context, item);
      return;
    }
    Navigator.of(context).push(_route(item, page));
  }

  static void select(
    BuildContext context,
    StaffSession session,
    HousekeepingNavItem item, {
    required HousekeepingNavItem current,
  }) {
    if (item == current) return;

    if (item == HousekeepingNavItem.dashboard) {
      Navigator.of(context).popUntil(
        (route) => !(route.settings.name?.startsWith(_prefix) ?? false),
      );
      return;
    }

    final page = _pageFor(item, session);
    if (page == null) {
      _comingSoon(context, item);
      return;
    }
    Navigator.of(context).pushReplacement(_route(item, page));
  }

  /// Push a detail screen (e.g. Notifications) that Back should return from.
  static Future<T?> push<T>(BuildContext context, String name, Widget page) {
    return Navigator.of(context).push<T>(
      MaterialPageRoute<T>(
        builder: (_) => page,
        settings: RouteSettings(name: _prefix + name),
      ),
    );
  }

  static void afterLogout(BuildContext context) {
    final navigator = Navigator.of(context);
    navigator.popUntil(
      (route) => !(route.settings.name?.startsWith(_prefix) ?? false),
    );
    navigator.pop(); // the staff dashboard, revealing the login screen
  }

  static Widget? _pageFor(HousekeepingNavItem item, StaffSession session) =>
      switch (item) {
        HousekeepingNavItem.rooms => HousekeepingRoomsScreen(session: session),
        HousekeepingNavItem.requests => HousekeepingRequestsScreen(
          session: session,
        ),
        HousekeepingNavItem.inspection => HousekeepingInspectionScreen(
          session: session,
        ),
        HousekeepingNavItem.lostFound => HousekeepingLostFoundScreen(
          session: session,
        ),
        HousekeepingNavItem.chat => HousekeepingChatScreen(session: session),
        _ => null,
      };

  static Route<void> _route(HousekeepingNavItem item, Widget page) =>
      MaterialPageRoute<void>(
        builder: (_) => page,
        settings: RouteSettings(name: _prefix + item.name),
      );

  static void _comingSoon(BuildContext context, HousekeepingNavItem item) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ComingSoonScreen(title: _label(item))),
    );
  }

  static String _label(HousekeepingNavItem item) => switch (item) {
    HousekeepingNavItem.dashboard => 'Dashboard',
    HousekeepingNavItem.rooms => 'Rooms',
    HousekeepingNavItem.requests => 'Requests',
    HousekeepingNavItem.inspection => 'Inspection',
    HousekeepingNavItem.lostFound => 'Lost & Found',
    HousekeepingNavItem.chat => 'Chat',
  };
}
