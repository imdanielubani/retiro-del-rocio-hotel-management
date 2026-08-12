import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/audio/notification_chime.dart';
import 'package:retirodelrocioapp/features/security/notifications/data/security_notification_repository.dart';
import 'package:retirodelrocioapp/features/security/notifications/domain/security_notification.dart';
import 'package:retirodelrocioapp/features/security/notifications/presentation/widgets/security_notification_toast.dart';

final securityNotificationRepositoryProvider =
    Provider<SecurityNotificationRepository>(
      (ref) => SecurityNotificationRepository(),
    );

/// Security's notification feed, keyed by the officer's staff token.
///
/// Re-polled every 20 seconds as a belt-and-braces backstop; the realtime
/// `security` channel (see `securityNotificationsRealtimeProvider`)
/// invalidates this the moment a new notification actually lands, so the
/// poll rarely does the work — it just means a dropped socket self-corrects
/// within 20 seconds, same as the reception tablet's notifications feed.
final securityNotificationsProvider =
    FutureProvider.family<List<SecurityNotification>, String>((
      ref,
      token,
    ) async {
      final repo = ref.watch(securityNotificationRepositoryProvider);

      final timer = Timer(const Duration(seconds: 20), ref.invalidateSelf);
      ref.onDispose(timer.cancel);

      return repo.fetch(token);
    });

/// Unread count for the badge on [SecurityTopBar]'s bell — usable from any
/// security screen without each one re-deriving it from the full list.
final securityUnreadNotificationsProvider = Provider.family<int, String>((
  ref,
  token,
) {
  final list = ref.watch(securityNotificationsProvider(token)).value;
  return list?.where((n) => !n.read).length ?? 0;
});

/// Rings the chime and toasts any notification that's new since the feed was
/// last read — watched by the security dashboard so the officer hears it no
/// matter how the new notification was discovered: the realtime `security`
/// socket invalidates [securityNotificationsProvider] the instant one lands,
/// but this listener reacts to the resulting refresh either way — including
/// the plain 20-second poll, so an officer still hears the chime even when
/// the socket is down.
final securityNotificationChimeProvider = Provider.family<void, String>((
  ref,
  token,
) {
  final chime = NotificationChime();
  ref.onDispose(chime.dispose);

  Set<int>? previousIds;

  ref.listen<AsyncValue<List<SecurityNotification>>>(
    securityNotificationsProvider(token),
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
            showSecurityNotificationToast(n);
          }
        }
      }
      previousIds = ids;
    },
    fireImmediately: true,
  );
});

class SecurityNotificationActions {
  const SecurityNotificationActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  Future<void> markRead(int id) async {
    await _ref
        .read(securityNotificationRepositoryProvider)
        .markRead(_token, id);
    _ref.invalidate(securityNotificationsProvider(_token));
  }

  Future<void> markAllRead() async {
    await _ref.read(securityNotificationRepositoryProvider).markAllRead(_token);
    _ref.invalidate(securityNotificationsProvider(_token));
  }
}

final securityNotificationActionsProvider =
    Provider.family<SecurityNotificationActions, String>(
      (ref, token) => SecurityNotificationActions(ref, token),
    );
