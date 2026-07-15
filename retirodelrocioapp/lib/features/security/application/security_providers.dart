import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/sos_channel.dart';
import 'package:retirodelrocioapp/features/security/data/security_repository.dart';
import 'package:retirodelrocioapp/features/security/domain/security_incident.dart';
import 'package:retirodelrocioapp/features/security/domain/security_overview.dart';

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
}

final securityActionsProvider = Provider.family<SecurityActions, String>(
  (ref, token) => SecurityActions(ref, token),
);
