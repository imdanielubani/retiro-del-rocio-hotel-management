import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/logout_confirm_dialog.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/session_guard.dart';
import 'package:retirodelrocioapp/features/reception/application/reception_providers.dart';
import 'package:retirodelrocioapp/features/reception/data/reception_repository.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_overview.dart';
import 'package:retirodelrocioapp/features/reception/notifications/application/reception_notification_providers.dart';
import 'package:retirodelrocioapp/features/reception/notifications/presentation/screens/reception_notification_screen.dart';
import 'package:retirodelrocioapp/features/reception/presentation/reception_navigation.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_checkin_dialog.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_nav_rail.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_top_bar.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_widgets.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';
import 'package:retirodelrocioapp/features/security/presentation/dialogs/sos_alert_overlay.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';

/// The receptionist's home dashboard (Figma 299:322 / 345:14699).
///
/// One frosted screen: the module rail, a hotel-wide header, the four headline
/// counters, today's arrivals and departures with their check-in / check-out
/// actions, an alerts panel fed by live SOS incidents, and the room-status tally.
/// It re-polls every 15 seconds and follows the `sos` realtime channel, so the
/// desk stays current without anyone refreshing.
class ReceptionDashboardScreen extends ConsumerStatefulWidget {
  const ReceptionDashboardScreen({super.key, required this.session});

  final StaffSession session;

  @override
  ConsumerState<ReceptionDashboardScreen> createState() =>
      _ReceptionDashboardScreenState();
}

class _ReceptionDashboardScreenState
    extends ConsumerState<ReceptionDashboardScreen> {
  /// The booking whose action button is mid-request, so only that one spins.
  int? _busyBookingId;

  /// Incident ids already surfaced as the priority overlay, so a still-active
  /// alert is not thrown up again on every poll — only genuinely new ones
  /// interrupt.
  final Set<int> _announced = {};

  /// True while the priority overlay is on screen, so alerts never stack.
  bool _presenting = false;

  /// Booking ids already toasted as newly overdue, so the same guest doesn't
  /// re-announce on every 15s poll — only the moment they first cross over.
  final Set<int> _announcedOverdue = {};

  String get _token => widget.session.token;

  Future<void> _logout() async {
    final confirmed = await showLogoutConfirmDialog(context);
    if (!confirmed) return;
    await ref.read(authControllerProvider.notifier).logout();
    if (mounted) Navigator.of(context).pop();
  }

  void _openNotifications() {
    ReceptionNavigation.push(
      context,
      'notifications',
      ReceptionNotificationScreen(
        session: widget.session,
        current: ReceptionNavItem.dashboard,
      ),
    );
  }

  void _onNav(ReceptionNavItem item) {
    if (item == ReceptionNavItem.dashboard) return; // already here
    // The dashboard is the base route, so it *pushes* a sub-screen (Back returns
    // here); the sub-screens then replace each other. Guests/Bookings are live;
    // the rest land on the shared placeholder.
    ReceptionNavigation.open(context, widget.session, item);
  }

  /// Check-in opens the 3-step modal (Verify Identity → Room Assignment →
  /// Complete). The modal refreshes the lists itself on success; a completed
  /// check-in shows a toast here.
  Future<void> _checkIn(ReceptionBooking booking) async {
    final done = await showReceptionCheckInDialog(
      context,
      session: widget.session,
      booking: booking,
    );
    if (done == true && mounted) _toast('${booking.guestName} checked in.');
  }

  Future<void> _checkOut(ReceptionBooking booking) => _run(
    booking.id,
    () => ref.read(receptionActionsProvider(_token)).checkOut(booking.id),
    '${booking.guestName} checked out.',
  );

  /// Runs a desk action with a per-card spinner, a success toast and a failure
  /// toast — the list then re-renders from the refreshed overview.
  Future<void> _run(
    int bookingId,
    Future<void> Function() action,
    String ok,
  ) async {
    setState(() => _busyBookingId = bookingId);
    try {
      await action();
      if (mounted) _toast(ok);
    } on ReceptionException catch (e) {
      if (mounted) _toast(e.message, error: true);
    } catch (_) {
      if (mounted)
        _toast('Something went wrong. Please try again.', error: true);
    } finally {
      if (mounted) setState(() => _busyBookingId = null);
    }
  }

  void _toast(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: error
            ? const Color(0xFF7F1D1D)
            : const Color(0xFF14532D),
        content: Text(
          message,
          style: AppTypography.style(color: Colors.white, fontSize: 14),
        ),
      ),
    );
  }

  /// The SOS trigger: throw up the full-screen priority overlay the instant a
  /// new unacknowledged emergency arrives. Driven off the same
  /// [receptionOverviewProvider] the desk already watches — which the `sos`
  /// realtime channel refreshes — so it fires within a socket round-trip, and
  /// the poll is the backstop. Lives on the dashboard (the base reception route,
  /// always mounted) and the overlay uses the root navigator, so it interrupts
  /// wherever the receptionist is — including Incident Response.
  void _maybeAnnounce(ReceptionOverview? overview) {
    if (overview == null || _presenting || !mounted) return;

    SecurityIncident? incoming;
    for (final i in overview.incidents) {
      if (i.isActive && !_announced.contains(i.id)) {
        incoming = i;
        break;
      }
    }
    if (incoming == null) return;

    final incident = incoming;
    _announced.add(incident.id);
    _presenting = true;

    final receptionistName = overview.receptionistName != 'Reception'
        ? overview.receptionistName
        : widget.session.name;

    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) {
        _presenting = false;
        return;
      }
      await showSosAlertOverlay(
        context,
        incident: incident,
        officerName: receptionistName,
        activeIncidents: receptionOverviewProvider(
          _token,
        ).select((async) => async.whenData((o) => o.incidents)),
        onAcknowledge: () => ref
            .read(receptionActionsProvider(_token))
            .respondIncident(incident.id),
        onCallRoom: () => _toast('Room calling is coming soon.'),
      );
      _presenting = false;
      // A second emergency may have arrived while this one was on screen.
      if (mounted) {
        _maybeAnnounce(ref.read(receptionOverviewProvider(_token)).value);
      }
    });
  }

  /// The overdue-departure push: a one-time toast the moment a guest first
  /// crosses into "overdue", so it's not left as a passive badge the desk has
  /// to notice on their own. Deliberately a toast, not the blocking SOS
  /// overlay — this is an operational reminder, not a life-safety emergency,
  /// and hijacking that overlay would dilute its urgency. Batches multiple
  /// newly-overdue guests into one message so a burst (e.g. midnight rollover)
  /// doesn't spam the desk.
  void _maybeToastOverdue(ReceptionOverview? overview) {
    if (overview == null || !mounted) return;

    final newlyOverdue = overview.departures
        .where((b) => b.isOverdue && !_announcedOverdue.contains(b.id))
        .toList();
    if (newlyOverdue.isEmpty) return;

    for (final b in newlyOverdue) {
      _announcedOverdue.add(b.id);
    }

    final message = newlyOverdue.length == 1
        ? '${newlyOverdue.first.guestName} is now overdue for checkout.'
        : '${newlyOverdue.length} guests are now overdue for checkout.';
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _toast(message, error: true);
    });
  }

  @override
  Widget build(BuildContext context) {
    // Keep the live sockets alive for as long as the dashboard is on screen —
    // one for alerts, one for booking changes (new reservations, check-ins, etc.).
    ref.watch(receptionRealtimeProvider(_token));
    ref.watch(receptionBookingsRealtimeProvider(_token));
    // Rings the chime and toasts a new front-desk notification, from either
    // the socket above or the plain poll if it's down.
    ref.watch(receptionNotificationChimeProvider(_token));

    // Surface any new unacknowledged emergency as the priority overlay. Fires on
    // first load and on every refresh (socket or poll); the guards inside make it
    // announce each alert exactly once.
    ref.listen(receptionOverviewProvider(_token), (_, next) {
      _maybeAnnounce(next.value);
      _maybeToastOverdue(next.value);
    });

    final overviewAsync = ref.watch(receptionOverviewProvider(_token));
    final weather = ref.watch(weatherProvider).value;
    final overview = overviewAsync.value;
    final unreadNotifications = ref.watch(
      receptionUnreadNotificationsProvider(_token),
    );

    final name = overview != null && overview.receptionistName != 'Reception'
        ? overview.receptionistName
        : widget.session.name;

    return SessionGuard(
      child: Scaffold(
        backgroundColor: AppColors.background,
        body: Stack(
          fit: StackFit.expand,
          children: [
            Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
            const ColoredBox(color: Color(0xF2000000)),
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    ReceptionNavRail(
                      active: ReceptionNavItem.dashboard,
                      onSelect: _onNav,
                      onLogout: _logout,
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          ReceptionTopBar(
                            name: name,
                            role: overview?.receptionistRole ?? 'Reception',
                            weather: weather,
                            hasAlert: (overview?.alerts.isNotEmpty ?? false),
                            hasUnreadNotifications: unreadNotifications > 0,
                            onNotifications: _openNotifications,
                          ),
                          const SizedBox(height: 20),
                          _header(
                            stale: overviewAsync.hasError && overview != null,
                          ),
                          const SizedBox(height: 20),
                          Expanded(
                            child: overviewAsync.when(
                              data: _content,
                              loading: () => overview != null
                                  ? _content(overview)
                                  : const Center(
                                      child: CircularProgressIndicator(
                                        color: AppColors.gold,
                                      ),
                                    ),
                              error: (_, _) => overview != null
                                  ? _content(overview)
                                  : _errorState(),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _header({bool stale = false}) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Reception',
                style: AppTypography.style(color: AppColors.gold, fontSize: 12),
              ),
              const SizedBox(height: 3),
              Text(
                'Dashboard',
                style: AppTypography.style(
                  color: Colors.white,
                  fontSize: 36,
                  fontWeight: FontWeight.w700,
                  height: 1.1,
                ),
              ),
            ],
          ),
        ),
        if (stale) _staleChip(),
      ],
    );
  }

  Widget _staleChip() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: const Color(0xFFFF0000).withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: const Color(0xFFFF0000).withValues(alpha: 0.3),
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.cloud_off_rounded,
            size: 14,
            color: const Color(0xFFFF0000).withValues(alpha: 0.9),
          ),
          const SizedBox(width: 8),
          Text(
            'Not updating — check the connection',
            style: AppTypography.style(
              color: const Color(0xFFFF0000).withValues(alpha: 0.9),
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _content(ReceptionOverview data) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _kpiRow(data),
        const SizedBox(height: 20),
        Expanded(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(child: _arrivalsPanel(data)),
              const SizedBox(width: 20),
              Expanded(child: _departuresPanel(data)),
              const SizedBox(width: 20),
              SizedBox(width: 328, child: _rightColumn(data)),
            ],
          ),
        ),
      ],
    );
  }

  Widget _kpiRow(ReceptionOverview data) {
    return Row(
      children: [
        Expanded(
          child: ReceptionStatCard(
            label: 'ARRIVALS TODAY',
            value: data.arrivalsToday,
            accent: AppColors.gold,
          ),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: ReceptionStatCard(
            label: 'CHECK-INS TODAY',
            value: data.checkInsToday,
            accent: kReceptionBlue,
          ),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: ReceptionStatCard(
            label: 'CHECK-OUTS TODAY',
            value: data.checkOutsToday,
            accent: AppColors.gold,
          ),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: ReceptionStatCard(
            label: 'VISITORS PASS CHECK IN',
            value: data.visitorPassCheckIns,
            accent: kReceptionGreen,
          ),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: ReceptionStatCard(
            label: 'OVERDUE DEPARTURES',
            value: data.overdueDepartures,
            accent: kReceptionRed,
          ),
        ),
      ],
    );
  }

  Widget _arrivalsPanel(ReceptionOverview data) {
    return ReceptionSectionPanel(
      title: "TODAY'S ARRIVALS",
      child: data.arrivals.isEmpty
          ? const ReceptionArrivalsEmpty()
          : ListView.separated(
              padding: EdgeInsets.zero,
              itemCount: data.arrivals.length,
              separatorBuilder: (_, _) => const SizedBox(height: 14),
              itemBuilder: (_, i) {
                final b = data.arrivals[i];
                return ReceptionBookingCard(
                  booking: b,
                  busy: _busyBookingId == b.id,
                  onCheckIn: () => _checkIn(b),
                  onCheckOut: () => _checkOut(b),
                );
              },
            ),
    );
  }

  Widget _departuresPanel(ReceptionOverview data) {
    return ReceptionSectionPanel(
      title: "TODAY'S DEPARTURES",
      child: data.departures.isEmpty
          ? const ReceptionSectionEmpty(
              icon: Icons.luggage_outlined,
              message: 'No departures today',
            )
          : ListView.separated(
              padding: EdgeInsets.zero,
              itemCount: data.departures.length,
              separatorBuilder: (_, _) => const SizedBox(height: 14),
              itemBuilder: (_, i) {
                final b = data.departures[i];
                return ReceptionBookingCard(
                  booking: b,
                  busy: _busyBookingId == b.id,
                  onCheckIn: () => _checkIn(b),
                  onCheckOut: () => _checkOut(b),
                );
              },
            ),
    );
  }

  Widget _rightColumn(ReceptionOverview data) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          child: ReceptionSectionPanel(
            title: 'ALERTS',
            child: data.alerts.isEmpty
                ? const ReceptionSectionEmpty(
                    icon: Icons.check_circle_outline_rounded,
                    message: 'No active alerts',
                  )
                : ListView.separated(
                    padding: EdgeInsets.zero,
                    itemCount: data.alerts.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 16),
                    itemBuilder: (_, i) =>
                        ReceptionAlertRow(alert: data.alerts[i]),
                  ),
          ),
        ),
        const SizedBox(height: 16),
        ReceptionSectionPanel(
          title: 'ROOM STATUS',
          expand: false,
          child: ReceptionRoomStatusTiles(status: data.roomStatus),
        ),
      ],
    );
  }

  Widget _errorState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.cloud_off_rounded,
            size: 32,
            color: Colors.white.withValues(alpha: 0.4),
          ),
          const SizedBox(height: 12),
          Text(
            'Could not load the dashboard.',
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.6),
              fontSize: 14,
            ),
          ),
          const SizedBox(height: 16),
          TextButton(
            onPressed: () => ref.invalidate(receptionOverviewProvider(_token)),
            child: Text(
              'Retry',
              style: AppTypography.style(
                color: AppColors.gold,
                fontSize: 14,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
