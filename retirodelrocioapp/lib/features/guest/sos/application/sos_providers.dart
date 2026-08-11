import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/sos_channel.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/sos/data/sos_repository.dart';
import 'package:retirodelrocioapp/features/guest/sos/domain/sos_alert.dart';

final sosRepositoryProvider = Provider<SosRepository>((ref) => SosRepository());

/// The open emergency for this tablet's room, if any.
///
/// Re-polled every 15 seconds so the guest still sees security acknowledge the
/// alert even if the realtime socket dropped — the same belt-and-braces rule as
/// room status. During an emergency, stale state is not acceptable.
final activeSosAlertProvider = FutureProvider.family<SosAlert?, String>((
  ref,
  deviceToken,
) async {
  final repo = ref.watch(sosRepositoryProvider);

  final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.active(deviceToken);
});

/// Subscribes the guest tablet to its own room's `rooms.{id}` channel and
/// refreshes [activeSosAlertProvider] the instant the alert changes — so when an
/// officer acknowledges, "Help is on the way" flips to "Security is on their
/// way" at once rather than up to 15 seconds later. Pure accelerator: with no
/// broadcaster, or a dropped socket, the poll still carries the screen.
final activeSosRealtimeProvider =
    FutureProvider.family<void, ProvisionedDevice>((ref, device) async {
      final roomUnitId = device.roomUnitId;
      if (roomUnitId == null) return; // not bound to a room

      final config = (await ref.watch(appConfigProvider.future)).realtime;
      if (config == null) {
        debugPrint(
          'activeSosRealtime: no broadcaster configured — polling only.',
        );
        return;
      }

      final channel = SosChannel(config: config, channel: 'rooms.$roomUnitId');
      channel.connect(
        onChanged: () => ref.invalidate(activeSosAlertProvider(device.token)),
      );

      ref.onDispose(channel.dispose);
    });

/// Raise and stand down the emergency. Both refresh [activeSosAlertProvider], so
/// the screen renders from one source of truth — the server — rather than from
/// whatever the button happened to return.
class SosActions {
  const SosActions(this._ref, this._deviceToken);

  final Ref _ref;
  final String _deviceToken;

  Future<SosAlert> raise() async {
    final alert = await _ref.read(sosRepositoryProvider).raise(_deviceToken);
    _ref.invalidate(activeSosAlertProvider(_deviceToken));
    return alert;
  }

  Future<SosAlert> cancel(int alertId) async {
    final alert = await _ref
        .read(sosRepositoryProvider)
        .cancel(_deviceToken, alertId);
    _ref.invalidate(activeSosAlertProvider(_deviceToken));
    return alert;
  }
}

final sosActionsProvider = Provider.family<SosActions, String>(
  (ref, deviceToken) => SosActions(ref, deviceToken),
);
