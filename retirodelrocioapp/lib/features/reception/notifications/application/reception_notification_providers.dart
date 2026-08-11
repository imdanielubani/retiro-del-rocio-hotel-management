import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/audio/notification_chime.dart';
import 'package:retirodelrocioapp/features/reception/notifications/data/reception_notification_repository.dart';
import 'package:retirodelrocioapp/features/reception/notifications/domain/reception_notification.dart';
import 'package:retirodelrocioapp/features/reception/notifications/presentation/widgets/reception_notification_toast.dart';

final receptionNotificationRepositoryProvider =
    Provider<ReceptionNotificationRepository>(
      (ref) => ReceptionNotificationRepository(),
    );

/// The front desk's notification feed, keyed by the receptionist's staff
/// token.
///
/// Re-polled every 20 seconds as a belt-and-braces backstop; the realtime
/// `reception` channel (see `receptionBookingsRealtimeProvider`) invalidates
/// this the moment a new notification actually lands, so the poll rarely does
/// the work — it just means a dropped socket self-corrects within 20 seconds,
/// same as the guest tablet's notifications feed.
final receptionNotificationsProvider =
    FutureProvider.family<List<ReceptionNotification>, String>((
      ref,
      token,
    ) async {
      final repo = ref.watch(receptionNotificationRepositoryProvider);

      final timer = Timer(const Duration(seconds: 20), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.fetch(token);
    });

/// Unread count for the badge on [ReceptionTopBar]'s bell — usable from any
/// reception screen without each one re-deriving it from the full list.
final receptionUnreadNotificationsProvider = Provider.family<int, String>((
  ref,
  token,
) {
  final list = ref.watch(receptionNotificationsProvider(token)).value;
  return list?.where((n) => !n.read).length ?? 0;
});

/// Rings the chime and toasts any notification that's new since the feed was
/// last read — watched by every reception screen (via `ReceptionScaffold` and
/// the dashboard) so the desk hears it no matter which screen they're on, and
/// no matter how the new notification was discovered: the realtime `reception`
/// socket invalidates [receptionNotificationsProvider] the instant one lands,
/// but this listener reacts to the resulting refresh either way — including
/// the plain 20-second poll, so a receptionist still hears the chime even
/// when the socket is down.
final receptionNotificationChimeProvider = Provider.family<void, String>((
  ref,
  token,
) {
  final chime = NotificationChime();
  ref.onDispose(chime.dispose);

  Set<int>? previousIds;

  ref.listen<AsyncValue<List<ReceptionNotification>>>(
    receptionNotificationsProvider(token),
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
            showReceptionNotificationToast(n);
          }
        }
      }
      previousIds = ids;
    },
    fireImmediately: true,
  );
});

class ReceptionNotificationActions {
  const ReceptionNotificationActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  Future<void> markRead(int id) async {
    await _ref
        .read(receptionNotificationRepositoryProvider)
        .markRead(_token, id);
    _ref.invalidate(receptionNotificationsProvider(_token));
  }

  Future<void> markAllRead() async {
    await _ref
        .read(receptionNotificationRepositoryProvider)
        .markAllRead(_token);
    _ref.invalidate(receptionNotificationsProvider(_token));
  }
}

final receptionNotificationActionsProvider =
    Provider.family<ReceptionNotificationActions, String>(
      (ref, token) => ReceptionNotificationActions(ref, token),
    );
