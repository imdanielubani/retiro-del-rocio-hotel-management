import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/room_channel.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/welcome/application/room_status_providers.dart';

/// Subscribes the tablet to its own room, so a check-in or check-out lands in
/// the moment reception clicks — not up to 20 seconds later.
///
/// The broadcast is only a signal; this re-fetches `room-status` with the
/// device's own token, so no guest data ever travels over the socket.
///
/// The periodic poll in [roomStatusProvider] deliberately keeps running: if
/// Reverb is down, or the room's Wi-Fi drops and the tablet misses a broadcast,
/// it still corrects itself within 20 seconds. This is an accelerator, never a
/// single point of failure.
final roomRealtimeProvider =
    FutureProvider.family<void, ProvisionedDevice>((ref, device) async {
  final roomUnitId = device.roomUnitId;
  if (roomUnitId == null) return; // staff tablet, or not bound to a room

  final config = (await ref.watch(appConfigProvider.future)).realtime;
  if (config == null) {
    debugPrint('roomRealtime: no broadcaster configured — polling only.');
    return;
  }

  final channel = RoomChannel(config: config, roomUnitId: roomUnitId);
  channel.connect(
    onChanged: () => ref.invalidate(roomStatusProvider(device.token)),
  );

  ref.onDispose(channel.dispose);
});
