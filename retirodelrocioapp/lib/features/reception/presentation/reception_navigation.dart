import 'package:flutter/material.dart';
import 'package:retirodelrocioapp/core/widgets/coming_soon_screen.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/reception/presentation/screens/reception_bills_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/screens/reception_bookings_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/screens/reception_guests_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/screens/reception_incident_response_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/screens/reception_vehicle_pickup_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/screens/reception_visitors_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_nav_rail.dart';

/// Rail navigation for the reception module, kept in one place so the dashboard
/// and every sub-screen route the same way.
///
/// The dashboard is the base route (returned by the staff dashboard). It *pushes*
/// a sub-screen so Back returns to it. Sub-screens *replace* each other so the
/// stack never grows past `dashboard → one sub-screen`, and jumping to Dashboard
/// simply pops every tagged reception route off.
class ReceptionNavigation {
  const ReceptionNavigation._();

  static const _prefix = 'reception/';

  /// From the dashboard: open a sub-screen (or a placeholder) on top.
  static void open(
    BuildContext context,
    StaffSession session,
    ReceptionNavItem item,
  ) {
    final page = _pageFor(item, session);
    if (page == null) {
      _comingSoon(context, item);
      return;
    }
    Navigator.of(context).push(_route(item, page));
  }

  /// From a sub-screen: swap to a sibling, drop back to the dashboard, or open a
  /// placeholder. [current] is the calling screen's own item, so re-tapping it
  /// does nothing.
  static void select(
    BuildContext context,
    StaffSession session,
    ReceptionNavItem item, {
    required ReceptionNavItem current,
  }) {
    if (item == current) return;

    if (item == ReceptionNavItem.dashboard) {
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

  /// Leave the reception module after signing out, landing on the staff login.
  ///
  /// The staff dashboard reveals login by popping its own route, so from a
  /// sub-screen we must pop every reception route *and* the dashboard beneath
  /// them. The NavigatorState is captured first because [context] is unmounted
  /// once its route is popped.
  static void afterLogout(BuildContext context) {
    final navigator = Navigator.of(context);
    navigator.popUntil(
      (route) => !(route.settings.name?.startsWith(_prefix) ?? false),
    );
    navigator.pop(); // the staff dashboard, revealing the login screen
  }

  /// Push a detail screen (e.g. a guest profile) that Back should return from.
  static Future<T?> push<T>(BuildContext context, String name, Widget page) {
    return Navigator.of(context).push<T>(
      MaterialPageRoute<T>(
        builder: (_) => page,
        settings: RouteSettings(name: _prefix + name),
      ),
    );
  }

  static Widget? _pageFor(ReceptionNavItem item, StaffSession session) =>
      switch (item) {
        ReceptionNavItem.guests => ReceptionGuestsScreen(session: session),
        ReceptionNavItem.bookings => ReceptionBookingsScreen(session: session),
        ReceptionNavItem.bills => ReceptionBillsScreen(session: session),
        ReceptionNavItem.visitorPass => ReceptionVisitorsScreen(session: session),
        ReceptionNavItem.vehiclePickup => ReceptionVehiclePickupScreen(
          session: session,
        ),
        ReceptionNavItem.incidentResponse => ReceptionIncidentResponseScreen(
          session: session,
        ),
        _ => null,
      };

  static Route<void> _route(ReceptionNavItem item, Widget page) =>
      MaterialPageRoute<void>(
        builder: (_) => page,
        settings: RouteSettings(name: _prefix + item.name),
      );

  static void _comingSoon(BuildContext context, ReceptionNavItem item) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ComingSoonScreen(title: _label(item))),
    );
  }

  static String _label(ReceptionNavItem item) => switch (item) {
    ReceptionNavItem.bills => 'Bills',
    ReceptionNavItem.visitorPass => 'Visitor Pass',
    ReceptionNavItem.roomStatus => 'Room Status',
    ReceptionNavItem.vehiclePickup => 'Vehicle Pickup',
    ReceptionNavItem.incidentResponse => 'Incident Response',
    ReceptionNavItem.chat => 'Chat',
    _ => 'Coming soon',
  };
}
