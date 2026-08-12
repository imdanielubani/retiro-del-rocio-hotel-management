import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/core/navigation/root_messenger.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/application/housekeeping_notification_providers.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/data/housekeeping_notification_repository.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/domain/housekeeping_notification.dart';

/// A fake backing store standing in for `/housekeeping/notifications`, so
/// the chime/toast diffing logic runs against real widget behaviour without
/// a network call. Whatever [notifications] holds is what the next fetch
/// (poll or manual invalidate) returns — the test drives arrivals by
/// mutating this and invalidating the provider, exactly like a real poll
/// tick or a realtime socket refresh would.
class _FakeHousekeepingNotificationRepository
    extends HousekeepingNotificationRepository {
  List<HousekeepingNotification> notifications = const [];

  @override
  Future<List<HousekeepingNotification>> fetch(String token) async =>
      notifications;
}

HousekeepingNotification _notification(int id, {bool read = false}) =>
    HousekeepingNotification(
      id: id,
      category: HousekeepingNotificationCategory.guest,
      title: 'Notification $id',
      message: 'Message body $id',
      time: DateTime.now(),
      read: read,
    );

void main() {
  const token = 'test-token';

  Future<ProviderContainer> pumpApp(
    WidgetTester tester,
    _FakeHousekeepingNotificationRepository repo,
  ) async {
    final container = ProviderContainer(
      overrides: [
        housekeepingNotificationRepositoryProvider.overrideWithValue(repo),
      ],
    );

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp(
          scaffoldMessengerKey: rootScaffoldMessengerKey,
          home: Scaffold(
            body: Consumer(
              builder: (context, ref, _) {
                // Keeps the listener alive, same as the housekeeping
                // dashboard does.
                ref.watch(housekeepingNotificationChimeProvider(token));
                return const SizedBox();
              },
            ),
          ),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    return container;
  }

  testWidgets(
    'toasts a newly arrived unread notification but not the ones already known',
    (tester) async {
      final repo = _FakeHousekeepingNotificationRepository()
        ..notifications = [_notification(1)];
      final container = await pumpApp(tester, repo);

      // The first fetch establishes the baseline — nothing "arrived" yet.
      expect(find.byType(SnackBar), findsNothing);

      // A second notification lands.
      repo.notifications = [_notification(1), _notification(2)];
      container.invalidate(housekeepingNotificationsProvider(token));
      await tester.pump();
      await tester.pump();

      expect(find.text('Notification 2'), findsOneWidget);
      expect(find.byType(SnackBar), findsOneWidget);
      expect(tester.takeException(), isNull);
      container.dispose();
    },
  );

  testWidgets('an already-read notification never toasts', (tester) async {
    final repo = _FakeHousekeepingNotificationRepository()
      ..notifications = [_notification(1)];
    final container = await pumpApp(tester, repo);

    // A read notification (e.g. marked read on another device) appears for
    // the first time — it's not a new arrival worth interrupting for.
    repo.notifications = [_notification(1), _notification(2, read: true)];
    container.invalidate(housekeepingNotificationsProvider(token));
    await tester.pump();
    await tester.pump();

    expect(find.byType(SnackBar), findsNothing);
    expect(tester.takeException(), isNull);
    container.dispose();
  });

  testWidgets('a repeated poll with no changes does not re-toast', (
    tester,
  ) async {
    final repo = _FakeHousekeepingNotificationRepository()
      ..notifications = [_notification(1), _notification(2)];
    final container = await pumpApp(tester, repo);

    container.invalidate(housekeepingNotificationsProvider(token));
    await tester.pump();
    await tester.pump();

    expect(find.byType(SnackBar), findsNothing);
    expect(tester.takeException(), isNull);
    container.dispose();
  });
}
