import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/audio/notification_chime.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/data/housekeeping_notification_repository.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/domain/housekeeping_notification.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/presentation/widgets/housekeeping_notification_toast.dart';

final housekeepingNotificationRepositoryProvider =
    Provider<HousekeepingNotificationRepository>(
      (ref) => HousekeepingNotificationRepository(),
    );

/// Housekeeping's notification feed, keyed by the housekeeper's staff token.
///
/// Re-polled every 20 seconds as a belt-and-braces backstop; the realtime
/// `housekeeping` channel (see `housekeepingNotificationsRealtimeProvider`)
/// invalidates this the moment a new notification actually lands, so the
/// poll rarely does the work — it just means a dropped socket self-corrects
/// within 20 seconds, same as the reception/security tablets' feeds.
final housekeepingNotificationsProvider =
    FutureProvider.family<List<HousekeepingNotification>, String>((
      ref,
      token,
    ) async {
      final repo = ref.watch(housekeepingNotificationRepositoryProvider);

      final timer = Timer(const Duration(seconds: 20), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.fetch(token);
    });

/// Unread count for the badge on [StaffTopBar]'s bell — usable from any
/// housekeeping screen without each one re-deriving it from the full list.
final housekeepingUnreadNotificationsProvider = Provider.family<int, String>((
  ref,
  token,
) {
  final list = ref.watch(housekeepingNotificationsProvider(token)).value;
  return list?.where((n) => !n.read).length ?? 0;
});

/// Rings the chime and toasts any notification that's new since the feed was
/// last read — watched by every housekeeping screen so the housekeeper hears
/// it no matter which screen they're on, and no matter how the new
/// notification was discovered: the realtime `housekeeping` socket
/// invalidates [housekeepingNotificationsProvider] the instant one lands,
/// but this listener reacts to the resulting refresh either way — including
/// the plain 20-second poll, so a housekeeper still hears the chime even
/// when the socket is down.
final housekeepingNotificationChimeProvider = Provider.family<void, String>((
  ref,
  token,
) {
  final chime = NotificationChime();
  ref.onDispose(chime.dispose);

  Set<int>? previousIds;

  ref.listen<AsyncValue<List<HousekeepingNotification>>>(
    housekeepingNotificationsProvider(token),
    (previous, next) {
      final list = next.value;
      if (list == null) return;

      final ids = list.map((n) => n.id).toSet();
      final knownIds = previousIds;
      if (knownIds != null) {
        final arrived = list.where((n) => !knownIds.contains(n.id) && !n.read);
        if (arrived.isNotEmpty) {
          unawaited(chime.play());
          for (final n in arrived) {
            showHousekeepingNotificationToast(n);
          }
        }
      }
      previousIds = ids;
    },
    fireImmediately: true,
  );
});

class HousekeepingNotificationActions {
  const HousekeepingNotificationActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  Future<void> markRead(int id) async {
    await _ref
        .read(housekeepingNotificationRepositoryProvider)
        .markRead(_token, id);
    _ref.invalidate(housekeepingNotificationsProvider(_token));
  }

  Future<void> markAllRead() async {
    await _ref
        .read(housekeepingNotificationRepositoryProvider)
        .markAllRead(_token);
    _ref.invalidate(housekeepingNotificationsProvider(_token));
  }
}

final housekeepingNotificationActionsProvider =
    Provider.family<HousekeepingNotificationActions, String>(
      (ref, token) => HousekeepingNotificationActions(ref, token),
    );
