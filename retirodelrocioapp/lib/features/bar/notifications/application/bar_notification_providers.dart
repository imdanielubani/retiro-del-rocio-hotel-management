import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/audio/notification_chime.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_notification.dart';
import 'package:retirodelrocioapp/features/bar/notifications/data/bar_notification_repository.dart';
import 'package:retirodelrocioapp/features/bar/notifications/presentation/widgets/bar_notification_toast.dart';

final barNotificationRepositoryProvider = Provider<BarNotificationRepository>(
  (ref) => BarNotificationRepository(),
);

/// The Bar Tablet's notification feed, keyed by the staffer's token.
/// Re-polled every 15 seconds as a belt-and-braces backstop; the realtime
/// `bar` channel invalidates this the moment a new order actually lands.
final barNotificationsProvider =
    FutureProvider.family<List<BarNotification>, String>((ref, token) async {
      final repo = ref.watch(barNotificationRepositoryProvider);

      final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.fetch(token);
    });

/// Unread count for the badge on [StaffTopBar]'s bell.
final barUnreadNotificationsProvider = Provider.family<int, String>((
  ref,
  token,
) {
  final list = ref.watch(barNotificationsProvider(token)).value;
  return list?.where((n) => !n.read).length ?? 0;
});

/// Rings the chime and pops the new-order alert toast for any notification
/// that's new since the feed was last read — watched by every bar screen.
final barNotificationChimeProvider = Provider.family<void, String>((
  ref,
  token,
) {
  final chime = NotificationChime();
  ref.onDispose(chime.dispose);

  Set<int>? previousIds;

  ref.listen<AsyncValue<List<BarNotification>>>(
    barNotificationsProvider(token),
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
            showBarNotificationToast(n);
          }
        }
      }
      previousIds = ids;
    },
    fireImmediately: true,
  );
});

class BarNotificationActions {
  const BarNotificationActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  Future<void> markRead(int id) async {
    await _ref.read(barNotificationRepositoryProvider).markRead(_token, id);
    _ref.invalidate(barNotificationsProvider(_token));
  }

  Future<void> markAllRead() async {
    await _ref.read(barNotificationRepositoryProvider).markAllRead(_token);
    _ref.invalidate(barNotificationsProvider(_token));
  }
}

final barNotificationActionsProvider =
    Provider.family<BarNotificationActions, String>(
      (ref, token) => BarNotificationActions(ref, token),
    );
