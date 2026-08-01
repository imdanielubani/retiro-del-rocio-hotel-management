import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/sos_channel.dart';
import 'package:retirodelrocioapp/features/reception/data/reception_repository.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_bill.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_booking_row.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_checkin.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_guest.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_overview.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_pickup.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_visitor.dart';
import 'package:retirodelrocioapp/features/reception/notifications/application/reception_notification_providers.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';

final receptionRepositoryProvider = Provider<ReceptionRepository>(
  (ref) => ReceptionRepository(),
);

/// The whole reception dashboard, keyed by the receptionist's staff token.
///
/// Polled every 15 seconds so arrivals, departures and counters stay honest even
/// if the realtime socket dropped — the belt-and-braces rule from the rest of the
/// tablet. [receptionRealtimeProvider] layers a live socket on top so an incoming
/// alert lands without a manual refresh.
final receptionOverviewProvider =
    FutureProvider.family<ReceptionOverview, String>((ref, token) async {
      final repo = ref.watch(receptionRepositoryProvider);

      final timer = Timer(const Duration(seconds: 2), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.overview(token);
    });

/// Subscribes the tablet to the hotel-wide `sos` channel and refreshes the
/// overview the instant any incident changes, so the Alerts panel is live. Pure
/// accelerator: if no broadcaster is configured, or the socket drops, the
/// 15-second poll still carries the screen.
final receptionRealtimeProvider = FutureProvider.family<void, String>((
  ref,
  token,
) async {
  final config = (await ref.watch(appConfigProvider.future)).realtime;
  if (config == null) {
    debugPrint('receptionRealtime: no broadcaster configured — polling only.');
    return;
  }

  final channel = SosChannel(config: config);
  channel.connect(
    onChanged: () {
      ref.invalidate(receptionOverviewProvider(token));
      ref.invalidate(receptionIncidentLogsProvider(token));
    },
  );

  ref.onDispose(channel.dispose);
});

/// The SOS Alert Logs — every incident, newest first (Incident Response). Kept
/// unfiltered and keyed only by the receptionist's token; the screen filters by
/// status client-side, which keeps realtime invalidation trivial. Re-polled
/// every 15 seconds as the backstop, and refreshed live by
/// [receptionRealtimeProvider] on any incident change.
final receptionIncidentLogsProvider =
    FutureProvider.family<List<SecurityIncident>, String>((ref, token) async {
      final repo = ref.watch(receptionRepositoryProvider);

      final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.incidents(token);
    });

/// Subscribes the tablet to the hotel-wide `reception` channel and refreshes the
/// dashboard, guests and bookings lists the instant any booking is created or
/// changes state — a new reservation from the website or admin lands live. The
/// same subscription also carries new front-desk notification signals — e.g. a
/// guest paying to extend their stay — by invalidating
/// [receptionNotificationsProvider]; [receptionNotificationChimeProvider] is
/// what actually rings the chime and toasts the arrival, reacting to that
/// refresh (or the plain 20-second poll, if the socket is down) either way.
/// Pure accelerator over the periodic polls: if no broadcaster is configured,
/// or the socket drops, the polls still carry every screen. Watched by every
/// reception screen so the subscription is live wherever the user is in the
/// module.
final receptionBookingsRealtimeProvider = FutureProvider.family<void, String>((
  ref,
  token,
) async {
  final config = (await ref.watch(appConfigProvider.future)).realtime;
  if (config == null) {
    debugPrint(
      'receptionBookingsRealtime: no broadcaster configured — polling only.',
    );
    return;
  }

  final channel = SosChannel(
    config: config,
    channel: 'reception',
    events: const {'booking.changed'},
  );
  channel.connect(
    onChanged: () {
      ref.invalidate(receptionOverviewProvider(token));
      ref.invalidate(receptionGuestsProvider(token));
      ref.invalidate(receptionBookingsProvider(token));
    },
    onNotification: () => ref.invalidate(receptionNotificationsProvider(token)),
  );

  ref.onDispose(channel.dispose);
});

/// The hotel's guests, keyed by the receptionist's token.
///
/// autoDispose so re-opening the Guests module always starts a fresh fetch — a
/// guest who just booked a room shows up rather than being hidden behind a
/// cached list. A 20-second poll keeps it live while the screen is open and,
/// crucially, lets it recover: the repository returns an empty list on a failed
/// load, so without a refetch an early failure would leave the module blank for
/// good. The screen filters client-side and can also pull-to-refresh.
final receptionGuestsProvider = FutureProvider.autoDispose
    .family<List<ReceptionGuestSummary>, String>((ref, token) {
      final repo = ref.watch(receptionRepositoryProvider);

      final timer = Timer(const Duration(seconds: 2), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.guests(token);
    });

/// Every room booking, keyed by the receptionist's token. Same freshness rules
/// as the guests list — a new reservation appears without a manual refresh.
final receptionBookingsProvider = FutureProvider.autoDispose
    .family<List<ReceptionBookingRow>, String>((ref, token) {
      final repo = ref.watch(receptionRepositoryProvider);

      final timer = Timer(const Duration(seconds: 2), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.bookings(token);
    });

/// Every guest vehicle pickup, keyed by the receptionist's token. Same freshness
/// rules as the other lists — a new pickup or a driver change appears without a
/// manual refresh.
final receptionPickupsProvider = FutureProvider.autoDispose
    .family<List<PickupBooking>, String>((ref, token) {
      final repo = ref.watch(receptionRepositoryProvider);

      final timer = Timer(const Duration(seconds: 2), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.pickups(token);
    });

/// Every checked-in guest's outstanding room-charge balance, keyed by the
/// receptionist's token. Same freshness rules as the other lists — a spa
/// session just charged to a room, or a guest pre-settling from their own
/// tablet, shows up without a manual refresh.
final receptionBillsProvider = FutureProvider.autoDispose
    .family<ReceptionBillsOverview, String>((ref, token) {
      final repo = ref.watch(receptionRepositoryProvider);

      final timer = Timer(const Duration(seconds: 2), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.bills(token);
    });

/// Every visitor pass, invited or arrived, keyed by the receptionist's token
/// — not scoped to today, so the desk can see who is coming later too.
final receptionVisitorsProvider = FutureProvider.autoDispose
    .family<ReceptionVisitorsOverview, String>((ref, token) {
      final repo = ref.watch(receptionRepositoryProvider);

      final timer = Timer(const Duration(seconds: 2), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.visitors(token);
    });

/// The assignable driver roster (available drivers only), for the assign-driver
/// dialog's dropdown.
final receptionDriversProvider = FutureProvider.autoDispose
    .family<List<PickupDriver>, String>((ref, token) {
      return ref.watch(receptionRepositoryProvider).drivers(token);
    });

/// Check-in and check-out. Both refresh the overview so the desk always renders
/// from the server — the one source of truth for occupancy.
class ReceptionActions {
  const ReceptionActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  /// The room options for the check-in flow's Room Assignment step.
  Future<RoomOptions> roomOptions(int bookingId) =>
      _ref.read(receptionRepositoryProvider).roomOptions(_token, bookingId);

  /// Complete a guest's check-in with their captured identity and chosen room,
  /// then refresh every reception list from the server.
  Future<CheckinConfirmation> checkIn(
    int bookingId, {
    String? documentType,
    String? documentNumber,
    String? documentPath,
    String? documentName,
    int? roomUnitId,
  }) async {
    final confirmation = await _ref
        .read(receptionRepositoryProvider)
        .checkIn(
          _token,
          bookingId,
          documentType: documentType,
          documentNumber: documentNumber,
          documentPath: documentPath,
          documentName: documentName,
          roomUnitId: roomUnitId,
        );
    _ref.invalidate(receptionOverviewProvider(_token));
    _ref.invalidate(receptionGuestsProvider(_token));
    _ref.invalidate(receptionBookingsProvider(_token));
    return confirmation;
  }

  Future<void> checkOut(int bookingId) async {
    await _ref.read(receptionRepositoryProvider).checkOut(_token, bookingId);
    // Checking out moves the room to "available" and drops the guest's
    // in-house flag, so every list that shows either must refresh — not just
    // the dashboard (a checkout can be triggered from the guest profile too).
    _ref.invalidate(receptionOverviewProvider(_token));
    _ref.invalidate(receptionGuestsProvider(_token));
    _ref.invalidate(receptionBookingsProvider(_token));
  }

  /// Acknowledge an SOS incident (help is on the way), then refresh the overview
  /// and the alert logs so every reception screen renders from the server.
  Future<SecurityIncident> respondIncident(int incidentId) async {
    final incident = await _ref
        .read(receptionRepositoryProvider)
        .respondIncident(_token, incidentId);
    _ref.invalidate(receptionOverviewProvider(_token));
    _ref.invalidate(receptionIncidentLogsProvider(_token));
    return incident;
  }

  /// Resolve an SOS incident, then refresh the overview and the alert logs.
  Future<SecurityIncident> resolveIncident(int incidentId) async {
    final incident = await _ref
        .read(receptionRepositoryProvider)
        .resolveIncident(_token, incidentId);
    _ref.invalidate(receptionOverviewProvider(_token));
    _ref.invalidate(receptionIncidentLogsProvider(_token));
    return incident;
  }

  /// Assign (or, with a null [driverId], clear) the driver for a vehicle pickup,
  /// then refresh the pickup list.
  Future<PickupBooking> assignDriver(int bookingId, int? driverId) async {
    final pickup = await _ref
        .read(receptionRepositoryProvider)
        .assignDriver(_token, bookingId, driverId);
    _ref.invalidate(receptionPickupsProvider(_token));
    return pickup;
  }

  /// Mark a guest as collected, then refresh the pickup list.
  Future<PickupBooking> completePickup(int bookingId) async {
    final pickup = await _ref
        .read(receptionRepositoryProvider)
        .completePickup(_token, bookingId);
    _ref.invalidate(receptionPickupsProvider(_token));
    return pickup;
  }
}

final receptionActionsProvider = Provider.family<ReceptionActions, String>(
  (ref, token) => ReceptionActions(ref, token),
);
