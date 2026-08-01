import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/audio/notification_chime.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/room_channel.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/presentation/widgets/guest_notification_toast.dart';
import 'package:retirodelrocioapp/features/guest/service_requests/application/service_request_providers.dart';
import 'package:retirodelrocioapp/features/welcome/application/room_status_providers.dart';

/// Subscribes the tablet to its own room, so a check-in or check-out lands in
/// the moment reception clicks — not up to 20 seconds later. The same
/// subscription also carries new-notification signals: the moment one lands,
/// the feed is refreshed, a chime plays and a toast surfaces it — deliberately
/// a toast, not the blocking SOS overlay, since a hotel update is not an
/// emergency. This is watched at the root of the guest app (the welcome
/// screen, always mounted) rather than on any one screen — a notification
/// should sound and toast no matter what the guest has open.
///
/// The broadcast is only a signal; this re-fetches with the device's own
/// token, so no guest data ever travels over the socket.
///
/// The periodic polls in [roomStatusProvider] and [guestNotificationsProvider]
/// deliberately keep running: if Reverb is down, or the room's Wi-Fi drops and
/// the tablet misses a broadcast, both still correct themselves within 20
/// seconds. This is an accelerator, never a single point of failure.
final roomRealtimeProvider =
    FutureProvider.family<void, ProvisionedDevice>((ref, device) async {
  final roomUnitId = device.roomUnitId;
  if (roomUnitId == null) return; // staff tablet, or not bound to a room

  final config = (await ref.watch(appConfigProvider.future)).realtime;
  if (config == null) {
    debugPrint('roomRealtime: no broadcaster configured — polling only.');
    return;
  }

  final chime = NotificationChime();
  ref.onDispose(chime.dispose);

  final channel = RoomChannel(config: config, roomUnitId: roomUnitId);
  channel.connect(
    onChanged: () => ref.invalidate(roomStatusProvider(device.token)),
    onNotification: () => unawaited(
      _notifyGuest(ref, device.token, chime),
    ),
  );

  ref.onDispose(channel.dispose);
});

/// Refresh the feed, play the chime, then toast whichever notification(s) are
/// new since the last known list — diffed by id, so a toast never re-shows for
/// one the guest has already seen.
Future<void> _notifyGuest(
  Ref ref,
  String deviceToken,
  NotificationChime chime,
) async {
  final previousIds =
      ref.read(guestNotificationsProvider(deviceToken)).value
          ?.map((n) => n.id)
          .toSet() ??
      const {};

  ref.invalidate(guestNotificationsProvider(deviceToken));
  // Housekeeping/maintenance marking a request complete lands on this same
  // signal (see GuestNotification::notify usage in
  // HousekeepingController/MaintenanceController), so the guest's Service
  // Request history reflects it within a couple of seconds instead of
  // waiting on its own 20s poll.
  ref.invalidate(guestServiceRequestsProvider(deviceToken));
  unawaited(chime.play());

  try {
    final updated = await ref.read(
      guestNotificationsProvider(deviceToken).future,
    );
    final arrived = updated.where((n) => !previousIds.contains(n.id));
    for (final n in arrived) {
      showGuestNotificationToast(n);
    }
  } catch (error) {
    debugPrint('roomRealtime: could not toast the new notification — $error');
  }
}
