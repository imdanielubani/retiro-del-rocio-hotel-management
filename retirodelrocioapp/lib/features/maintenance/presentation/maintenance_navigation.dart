import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/widgets/coming_soon_screen.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/screens/work_orders_screen.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_nav_rail.dart';

/// Rail navigation for the maintenance module, kept in one place so the
/// dashboard and every sub-screen route the same way — mirrors
/// `ReceptionNavigation`/`HousekeepingNavigation`'s shape.
class MaintenanceNavigation {
  const MaintenanceNavigation._();

  static const _prefix = 'maintenance/';

  static void open(BuildContext context, StaffSession session, MaintenanceNavItem item) {
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
    MaintenanceNavItem item, {
    required MaintenanceNavItem current,
  }) {
    if (item == current) return;

    if (item == MaintenanceNavItem.dashboard) {
      Navigator.of(context).popUntil((route) => !(route.settings.name?.startsWith(_prefix) ?? false));
      return;
    }

    final page = _pageFor(item, session);
    if (page == null) {
      _comingSoon(context, item);
      return;
    }
    Navigator.of(context).pushReplacement(_route(item, page));
  }

  static void afterLogout(BuildContext context) {
    final navigator = Navigator.of(context);
    navigator.popUntil((route) => !(route.settings.name?.startsWith(_prefix) ?? false));
    navigator.pop(); // the staff dashboard, revealing the login screen
  }

  static Widget? _pageFor(MaintenanceNavItem item, StaffSession session) => switch (item) {
    MaintenanceNavItem.workOrders => WorkOrdersScreen(session: session),
    _ => null,
  };

  static Route<void> _route(MaintenanceNavItem item, Widget page) => MaterialPageRoute<void>(
    builder: (_) => page,
    settings: RouteSettings(name: _prefix + item.name),
  );

  static void _comingSoon(BuildContext context, MaintenanceNavItem item) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => ComingSoonScreen(title: _label(item))));
  }

  static String _label(MaintenanceNavItem item) => switch (item) {
    MaintenanceNavItem.dashboard => 'Dashboard',
    MaintenanceNavItem.workOrders => 'Work Orders',
    MaintenanceNavItem.chat => 'Chat',
  };
}
