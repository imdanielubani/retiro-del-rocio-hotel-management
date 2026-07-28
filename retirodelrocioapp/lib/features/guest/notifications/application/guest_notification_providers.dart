import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/guest/notifications/data/guest_notification_repository.dart';
import 'package:retirodelrocioapp/features/guest/notifications/domain/guest_notification.dart';

final guestNotificationRepositoryProvider =
    Provider<GuestNotificationRepository>(
      (ref) => GuestNotificationRepository(),
    );

/// The guest's notification feed, keyed by the tablet's device token.
///
/// Re-polled every 20 seconds as a belt-and-braces backstop; the realtime
/// channel (see `roomRealtimeProvider`) invalidates this the moment a new
/// notification actually lands, so the poll rarely does the work — it just
/// means a dropped socket self-corrects within 20 seconds, same as room
/// status.
final guestNotificationsProvider =
    FutureProvider.family<List<GuestNotification>, String>((
      ref,
      deviceToken,
    ) async {
      final repo = ref.watch(guestNotificationRepositoryProvider);

      final timer = Timer(const Duration(seconds: 20), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.fetch(deviceToken);
    });

/// Unread count for the badge on [GuestTopBar]'s bell — usable from any guest
/// screen without each one re-deriving it from the full list.
final guestUnreadNotificationsProvider = Provider.family<int, String>((
  ref,
  deviceToken,
) {
  final list = ref.watch(guestNotificationsProvider(deviceToken)).value;
  return list?.where((n) => !n.read).length ?? 0;
});

class GuestNotificationActions {
  const GuestNotificationActions(this._ref, this._deviceToken);

  final Ref _ref;
  final String _deviceToken;

  Future<void> markRead(int id) async {
    await _ref
        .read(guestNotificationRepositoryProvider)
        .markRead(_deviceToken, id);
    _ref.invalidate(guestNotificationsProvider(_deviceToken));
  }

  Future<void> markAllRead() async {
    await _ref
        .read(guestNotificationRepositoryProvider)
        .markAllRead(_deviceToken);
    _ref.invalidate(guestNotificationsProvider(_deviceToken));
  }
}

final guestNotificationActionsProvider =
    Provider.family<GuestNotificationActions, String>(
      (ref, deviceToken) => GuestNotificationActions(ref, deviceToken),
    );
