import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/audio/notification_chime.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_notification.dart';
import 'package:retirodelrocioapp/features/kitchen/notifications/data/kitchen_notification_repository.dart';
import 'package:retirodelrocioapp/features/kitchen/notifications/presentation/widgets/kitchen_notification_toast.dart';

final kitchenNotificationRepositoryProvider =
    Provider<KitchenNotificationRepository>(
      (ref) => KitchenNotificationRepository(),
    );

/// The Kitchen Tablet's notification feed, keyed by the staffer's token.
/// Re-polled every 15 seconds as a belt-and-braces backstop; the realtime
/// `kitchen` channel invalidates this the moment a new order actually lands.
final kitchenNotificationsProvider =
    FutureProvider.family<List<KitchenNotification>, String>((
      ref,
      token,
    ) async {
      final repo = ref.watch(kitchenNotificationRepositoryProvider);

      final timer = Timer(const Duration(seconds: 15), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.fetch(token);
    });

/// Unread count for the badge on [StaffTopBar]'s bell.
final kitchenUnreadNotificationsProvider = Provider.family<int, String>((
  ref,
  token,
) {
  final list = ref.watch(kitchenNotificationsProvider(token)).value;
  return list?.where((n) => !n.read).length ?? 0;
});

/// Rings the chime and pops the new-order alert toast for any notification
/// that's new since the feed was last read — watched by every kitchen screen.
final kitchenNotificationChimeProvider = Provider.family<void, String>((
  ref,
  token,
) {
  final chime = NotificationChime();
  ref.onDispose(chime.dispose);

  Set<int>? previousIds;

  ref.listen<AsyncValue<List<KitchenNotification>>>(
    kitchenNotificationsProvider(token),
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
            showKitchenNotificationToast(n);
          }
        }
      }
      previousIds = ids;
    },
    fireImmediately: true,
  );
});

class KitchenNotificationActions {
  const KitchenNotificationActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  Future<void> markRead(int id) async {
    await _ref.read(kitchenNotificationRepositoryProvider).markRead(_token, id);
    _ref.invalidate(kitchenNotificationsProvider(_token));
  }

  Future<void> markAllRead() async {
    await _ref.read(kitchenNotificationRepositoryProvider).markAllRead(_token);
    _ref.invalidate(kitchenNotificationsProvider(_token));
  }
}

final kitchenNotificationActionsProvider =
    Provider.family<KitchenNotificationActions, String>(
      (ref, token) => KitchenNotificationActions(ref, token),
    );
