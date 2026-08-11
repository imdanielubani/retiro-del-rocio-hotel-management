import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/audio/notification_chime.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/maintenance_notification.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/data/maintenance_notification_repository.dart';
import 'package:retirodelrocioapp/features/maintenance/notifications/presentation/widgets/maintenance_notification_toast.dart';

final maintenanceNotificationRepositoryProvider = Provider<MaintenanceNotificationRepository>(
  (ref) => MaintenanceNotificationRepository(),
);

/// Maintenance's notification feed, keyed by the technician's staff token.
/// Re-polled every 20 seconds as a belt-and-braces backstop; the realtime
/// `maintenance` channel invalidates this the moment a new notification
/// actually lands.
final maintenanceNotificationsProvider = FutureProvider.family<List<MaintenanceNotification>, String>((
  ref,
  token,
) async {
  final repo = ref.watch(maintenanceNotificationRepositoryProvider);

  final timer = Timer(const Duration(seconds: 20), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.fetch(token);
});

/// Unread count for the badge on [StaffTopBar]'s bell.
final maintenanceUnreadNotificationsProvider = Provider.family<int, String>((ref, token) {
  final list = ref.watch(maintenanceNotificationsProvider(token)).value;
  return list?.where((n) => !n.read).length ?? 0;
});

/// Rings the chime and toasts any notification that's new since the feed was
/// last read — watched by every maintenance screen.
final maintenanceNotificationChimeProvider = Provider.family<void, String>((ref, token) {
  final chime = NotificationChime();
  ref.onDispose(chime.dispose);

  Set<int>? previousIds;

  ref.listen<AsyncValue<List<MaintenanceNotification>>>(
    maintenanceNotificationsProvider(token),
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
            showMaintenanceNotificationToast(n);
          }
        }
      }
      previousIds = ids;
    },
    fireImmediately: true,
  );
});

class MaintenanceNotificationActions {
  const MaintenanceNotificationActions(this._ref, this._token);

  final Ref _ref;
  final String _token;

  Future<void> markRead(int id) async {
    await _ref.read(maintenanceNotificationRepositoryProvider).markRead(_token, id);
    _ref.invalidate(maintenanceNotificationsProvider(_token));
  }

  Future<void> markAllRead() async {
    await _ref.read(maintenanceNotificationRepositoryProvider).markAllRead(_token);
    _ref.invalidate(maintenanceNotificationsProvider(_token));
  }
}

final maintenanceNotificationActionsProvider = Provider.family<MaintenanceNotificationActions, String>(
  (ref, token) => MaintenanceNotificationActions(ref, token),
);
