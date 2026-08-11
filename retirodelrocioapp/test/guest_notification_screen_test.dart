import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/data/guest_notification_repository.dart';
import 'package:retirodelrocioapp/features/guest/notifications/domain/guest_notification.dart';
import 'package:retirodelrocioapp/features/guest/notifications/presentation/screens/guest_notification_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';

/// A fake backing store standing in for the tablet's own `GET/POST
/// /tablets/notifications*` endpoints, so the screen's real fetch/markRead/
/// markAllRead calls exercise real widget behaviour without a network call.
class _FakeGuestNotificationRepository implements GuestNotificationRepository {
  _FakeGuestNotificationRepository(this.notifications);

  final List<GuestNotification> notifications;

  @override
  Future<List<GuestNotification>> fetch(String deviceToken) async =>
      List.of(notifications);

  @override
  Future<void> markRead(String deviceToken, int id) async {
    final i = notifications.indexWhere((n) => n.id == id);
    if (i != -1) notifications[i] = notifications[i].copyWith(read: true);
  }

  @override
  Future<void> markAllRead(String deviceToken) async {
    for (var i = 0; i < notifications.length; i++) {
      notifications[i] = notifications[i].copyWith(read: true);
    }
  }
}

/// 2.7 — Notification Screen (Figma 140:3647).
void main() {
  const device = ProvisionedDevice(
    token: 'test-token',
    deviceCode: 'RDR-001',
    deviceName: 'Room 101 Tablet',
    mode: 'guest',
    suiteName: 'Alba Suite',
    roomNumber: '101',
  );

  final status = RoomStatus(
    occupancy: Occupancy.occupied,
    suiteName: 'Alba Suite',
    roomNumber: '101',
    guest: GuestInfo(
      name: 'Daniel Ubani',
      checkIn: DateTime(2026, 7, 9, 15),
      checkOut: DateTime(2026, 7, 14, 11),
    ),
  );

  List<GuestNotification> seed() => [
    GuestNotification(
      id: 1,
      category: NotificationCategory.dining,
      title: 'Order Ready',
      message: 'Your Pan-Seared Salmon is being delivered to Room 101.',
      time: DateTime.now().subtract(const Duration(minutes: 2)),
    ),
    GuestNotification(
      id: 2,
      category: NotificationCategory.spa,
      title: 'Spa Appointment',
      message: 'Reminder: Your spa appointment is in 30 minutes.',
      time: DateTime.now().subtract(const Duration(minutes: 28)),
      read: true,
    ),
    GuestNotification(
      id: 3,
      category: NotificationCategory.spa,
      title: 'Spa Appointment',
      message: 'Reminder: Your spa appointment is in 30 minutes.',
      time: DateTime.now().subtract(const Duration(minutes: 28)),
      read: true,
    ),
  ];

  Future<void> pumpScreen(
    WidgetTester tester,
    _FakeGuestNotificationRepository repo,
  ) async {
    tester.view.physicalSize = const Size(1280, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          guestNotificationRepositoryProvider.overrideWithValue(repo),
          weatherProvider.overrideWith(
            (ref) async => const Weather(
              temperatureC: 34,
              condition: 'Clear',
              emoji: '☀️',
              city: 'Jos',
            ),
          ),
        ],
        child: MaterialApp(
          home: GuestNotificationScreen(device: device, status: status),
        ),
      ),
    );
    await tester.pump();
    await tester.pump(); // let the FutureProvider resolve
  }

  testWidgets('renders the feed with the seeded unread count', (
    tester,
  ) async {
    await pumpScreen(tester, _FakeGuestNotificationRepository(seed()));

    expect(find.text('Notification'), findsOneWidget);
    expect(find.text('1 unread'), findsOneWidget);
    expect(find.text('Order Ready'), findsOneWidget);
    expect(find.text('Spa Appointment'), findsNWidgets(2));
    expect(tester.takeException(), isNull);
  });

  testWidgets('the Unread chip is scoped to only unread notifications', (
    tester,
  ) async {
    await pumpScreen(tester, _FakeGuestNotificationRepository(seed()));

    await tester.tap(find.text('Unread'));
    await tester.pump();

    expect(find.text('Order Ready'), findsOneWidget);
    expect(find.text('Spa Appointment'), findsNothing);
  });

  testWidgets('a category chip scopes the feed to that category', (
    tester,
  ) async {
    await pumpScreen(tester, _FakeGuestNotificationRepository(seed()));

    await tester.tap(find.text('Spa'));
    await tester.pump();

    expect(find.text('Order Ready'), findsNothing);
    expect(find.text('Spa Appointment'), findsNWidgets(2));
  });

  testWidgets('tapping an unread card marks it read on the server and clears the badge', (
    tester,
  ) async {
    final repo = _FakeGuestNotificationRepository(seed());
    await pumpScreen(tester, repo);

    expect(find.text('1 unread'), findsOneWidget);

    await tester.tap(find.text('Order Ready'));
    await tester.pump(); // busy spinner
    await tester.pump(); // markRead resolves + refetch
    await tester.pump();

    expect(find.text('0 unread'), findsOneWidget);
    expect(repo.notifications.firstWhere((n) => n.id == 1).read, isTrue);
  });

  testWidgets('Mark all read clears every unread notification on the server', (
    tester,
  ) async {
    final repo = _FakeGuestNotificationRepository(seed());
    await pumpScreen(tester, repo);

    await tester.tap(find.text('Mark all read'));
    await tester.pump();
    await tester.pump();
    await tester.pump();

    expect(find.text('0 unread'), findsOneWidget);
    expect(repo.notifications.every((n) => n.read), isTrue);

    // Now empty under the Unread filter.
    await tester.tap(find.text('Unread'));
    await tester.pump();
    expect(find.text('No notifications here'), findsOneWidget);
  });

  testWidgets('the back button pops the screen', (tester) async {
    tester.view.physicalSize = const Size(1280, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          guestNotificationRepositoryProvider.overrideWithValue(
            _FakeGuestNotificationRepository(seed()),
          ),
          weatherProvider.overrideWith(
            (ref) async => const Weather(
              temperatureC: 34,
              condition: 'Clear',
              emoji: '☀️',
              city: 'Jos',
            ),
          ),
        ],
        child: MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: Center(
                child: ElevatedButton(
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) =>
                          GuestNotificationScreen(device: device, status: status),
                    ),
                  ),
                  child: const Text('open'),
                ),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    expect(find.text('Notification'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.arrow_back_rounded));
    await tester.pumpAndSettle();
    expect(find.text('Notification'), findsNothing);
    expect(find.text('open'), findsOneWidget);
  });
}
