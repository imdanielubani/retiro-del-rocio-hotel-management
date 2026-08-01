import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/core/navigation/root_messenger.dart';
import 'package:retirodelrocioapp/features/security/notifications/application/security_notification_providers.dart';
import 'package:retirodelrocioapp/features/security/notifications/data/security_notification_repository.dart';
import 'package:retirodelrocioapp/features/security/notifications/domain/security_notification.dart';

/// A fake backing store standing in for `/security/notifications`, so the
/// chime/toast diffing logic runs against real widget behaviour without a
/// network call. Whatever [notifications] holds is what the next fetch (poll
/// or manual invalidate) returns — the test drives arrivals by mutating this
/// and invalidating the provider, exactly like a real poll tick or a realtime
/// socket refresh would.
class _FakeSecurityNotificationRepository
    extends SecurityNotificationRepository {
  List<SecurityNotification> notifications = const [];

  @override
  Future<List<SecurityNotification>> fetch(String token) async =>
      notifications;
}

SecurityNotification _notification(int id, {bool read = false}) =>
    SecurityNotification(
      id: id,
      category: SecurityNotificationCategory.guest,
      title: 'Notification $id',
      message: 'Message body $id',
      time: DateTime.now(),
      read: read,
    );

void main() {
  const token = 'test-token';

  Future<ProviderContainer> pumpApp(
    WidgetTester tester,
    _FakeSecurityNotificationRepository repo,
  ) async {
    final container = ProviderContainer(
      overrides: [
        securityNotificationRepositoryProvider.overrideWithValue(repo),
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
                // Keeps the listener alive, same as the security dashboard does.
                ref.watch(securityNotificationChimeProvider(token));
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
      final repo = _FakeSecurityNotificationRepository()
        ..notifications = [_notification(1)];
      final container = await pumpApp(tester, repo);

      // The first fetch establishes the baseline — nothing "arrived" yet.
      expect(find.byType(SnackBar), findsNothing);

      // A second notification lands.
      repo.notifications = [_notification(1), _notification(2)];
      container.invalidate(securityNotificationsProvider(token));
      await tester.pump();
      await tester.pump();

      expect(find.text('Notification 2'), findsOneWidget);
      expect(find.byType(SnackBar), findsOneWidget);
      expect(tester.takeException(), isNull);
      container.dispose();
    },
  );

  testWidgets('an already-read notification never toasts', (tester) async {
    final repo = _FakeSecurityNotificationRepository()
      ..notifications = [_notification(1)];
    final container = await pumpApp(tester, repo);

    // A read notification (e.g. marked read on another device) appears for
    // the first time — it's not a new arrival worth interrupting the officer for.
    repo.notifications = [
      _notification(1),
      _notification(2, read: true),
    ];
    container.invalidate(securityNotificationsProvider(token));
    await tester.pump();
    await tester.pump();

    expect(find.byType(SnackBar), findsNothing);
    expect(tester.takeException(), isNull);
    container.dispose();
  });

  testWidgets('a repeated poll with no changes does not re-toast', (
    tester,
  ) async {
    final repo = _FakeSecurityNotificationRepository()
      ..notifications = [_notification(1), _notification(2)];
    final container = await pumpApp(tester, repo);

    container.invalidate(securityNotificationsProvider(token));
    await tester.pump();
    await tester.pump();

    expect(find.byType(SnackBar), findsNothing);
    expect(tester.takeException(), isNull);
    container.dispose();
  });
}
