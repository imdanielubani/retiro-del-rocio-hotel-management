import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/sos_channel.dart';
import 'package:retirodelrocioapp/features/security/data/security_repository.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';
import 'package:retirodelrocioapp/features/security/domain/security_overview.dart';
import 'package:retirodelrocioapp/features/security/domain/visitor_pass_record.dart';
import 'package:retirodelrocioapp/features/security/notifications/application/security_notification_providers.dart';

final securityRepositoryProvider =
    Provider<SecurityRepository>((ref) => SecurityRepository());

/// The whole security dashboard, keyed by the officer's staff token.
///
/// Polled every 12 seconds so the dashboard stays honest even if the realtime
/// socket dropped — the belt-and-braces rule from room status. In an emergency,
/// stale state is not acceptable. [securityRealtimeProvider] layers a live
/// socket on top to make the common case instant.
final securityOverviewProvider =
    FutureProvider.family<SecurityOverview, String>((ref, token) async {
  final repo = ref.watch(securityRepositoryProvider);

  final timer = Timer(const Duration(seconds: 12), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.overview(token);
});

/// The SOS Alert Logs — every incident, newest first (Incident Response). Kept
/// unfiltered and keyed only by the officer's token; the screen filters by
/// status client-side, which keeps realtime invalidation trivial. Re-polled
/// every 15 seconds as the backstop.
final incidentLogsProvider =
    FutureProvider.family<List<SecurityIncident>, String>((ref, token) async {
  final repo = ref.watch(securityRepositoryProvider);

  final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.incidents(token);
});

/// Today's visitor passes hotel-wide, newest first — the Visitor Verification
/// list. Re-polled every 20 seconds so a pass a guest just issued (or one another
/// station granted) appears without a manual refresh.
final securityVisitorsProvider =
    FutureProvider.family<List<VisitorPassRecord>, String>((ref, token) async {
  final repo = ref.watch(securityRepositoryProvider);

  final timer = Timer(const Duration(seconds: 20), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.visitors(token);
});

/// Subscribes the tablet to the hotel-wide `sos` channel and refreshes both the
/// overview and the alert logs the instant any incident changes. Pure
/// accelerator: if no broadcaster is configured, or the socket drops, the
/// periodic polls still carry the screens.
final securityRealtimeProvider =
    FutureProvider.family<void, String>((ref, token) async {
  final config = (await ref.watch(appConfigProvider.future)).realtime;
  if (config == null) {
    debugPrint('securityRealtime: no broadcaster configured — polling only.');
    return;
  }

  final channel = SosChannel(config: config);
  channel.connect(onChanged: () {
    ref.invalidate(securityOverviewProvider(token));
    ref.invalidate(incidentLogsProvider(token));
  });

  ref.onDispose(channel.dispose);
});

/// Subscribes the tablet to the hotel-wide `security` channel and refreshes
/// the notification feed the instant a new one lands (today, only "a guest
/// invited a visitor"). A separate subscription from [securityRealtimeProvider]
/// — that one watches `sos` for incidents, this one watches `security` for
/// notifications, exactly like the reception tablet's `reception` channel
/// multiplexes `booking.changed` and `notification.created`. Pure
/// accelerator: if no broadcaster is configured, or the socket drops, the
/// periodic poll in [securityNotificationsProvider] still carries the feed.
final securityNotificationsRealtimeProvider =
    FutureProvider.family<void, String>((ref, token) async {
  final config = (await ref.watch(appConfigProvider.future)).realtime;
  if (config == null) {
    debugPrint('securityNotificationsRealtime: no broadcaster configured — polling only.');
    return;
  }

  final channel = SosChannel(config: config, channel: 'security', events: const {});
  channel.connect(
    onChanged: () {},
    onNotification: () => ref.invalidate(securityNotificationsProvider(token)),
  );

  ref.onDispose(channel.dispose);
});

/// Respond to and resolve incidents. Both refresh the overview so the screen
/// always renders from the server — the one source of truth during an incident.
class SecurityActions {
  const SecurityActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  Future<SecurityIncident> respond(int incidentId) async {
    final incident =
        await _ref.read(securityRepositoryProvider).respond(_token, incidentId);
    _ref.invalidate(securityOverviewProvider(_token));
    _ref.invalidate(incidentLogsProvider(_token));
    return incident;
  }

  Future<SecurityIncident> resolve(int incidentId) async {
    final incident =
        await _ref.read(securityRepositoryProvider).resolve(_token, incidentId);
    _ref.invalidate(securityOverviewProvider(_token));
    _ref.invalidate(incidentLogsProvider(_token));
    return incident;
  }

  /// Look up a visitor by their quoted code. A read-only lookup — it changes
  /// nothing, so it invalidates nothing.
  Future<VisitorPassRecord> verifyVisitor(String code) =>
      _ref.read(securityRepositoryProvider).verifyCode(_token, code);

  /// Admit the visitor, then refresh the list + dashboard counters from the
  /// server so every screen renders one source of truth.
  Future<VisitorPassRecord> grantVisitor(int passId) async {
    final pass =
        await _ref.read(securityRepositoryProvider).grantVisitor(_token, passId);
    _ref.invalidate(securityVisitorsProvider(_token));
    _ref.invalidate(securityOverviewProvider(_token));
    return pass;
  }

  /// Turn the visitor away, then refresh the list so the denial reflects.
  Future<VisitorPassRecord> denyVisitor(int passId) async {
    final pass =
        await _ref.read(securityRepositoryProvider).denyVisitor(_token, passId);
    _ref.invalidate(securityVisitorsProvider(_token));
    _ref.invalidate(securityOverviewProvider(_token));
    return pass;
  }

  /// Mark a verified visitor as having left, then refresh the list.
  Future<VisitorPassRecord> exitVisitor(int passId) async {
    final pass =
        await _ref.read(securityRepositoryProvider).exitVisitor(_token, passId);
    _ref.invalidate(securityVisitorsProvider(_token));
    _ref.invalidate(securityOverviewProvider(_token));
    return pass;
  }
}

final securityActionsProvider = Provider.family<SecurityActions, String>(
  (ref, token) => SecurityActions(ref, token),
);
