import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/data/guest_notification_repository.dart';
import 'package:retirodelrocioapp/features/guest/notifications/domain/guest_notification.dart';
import 'package:retirodelrocioapp/features/guest/service_requests/application/service_request_providers.dart';
import 'package:retirodelrocioapp/features/guest/service_requests/data/service_request_repository.dart';
import 'package:retirodelrocioapp/features/guest/service_requests/domain/guest_service_request.dart';
import 'package:retirodelrocioapp/features/guest/service_requests/presentation/screens/guest_service_request_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';

/// A fake backing store standing in for `/service-requests`, so the screen's
/// real category/form/submit flow exercises real widget behaviour without a
/// network call.
class _FakeServiceRequestRepository implements ServiceRequestRepository {
  _FakeServiceRequestRepository([this.history = const []]);

  final List<GuestServiceRequest> history;
  bool createdHousekeeping = false;
  bool createdMaintenance = false;
  String? lastType;
  String? lastTitle;
  String? lastPriority;

  @override
  Future<List<GuestServiceRequest>> list(String deviceToken) async => history;

  @override
  Future<GuestServiceRequest> createHousekeeping(
    String deviceToken, {
    required String type,
    String? notes,
  }) async {
    createdHousekeeping = true;
    lastType = type;
    return GuestServiceRequest(
      id: 1,
      category: 'housekeeping',
      title: 'Towels',
      status: 'pending',
      statusLabel: 'Pending',
      isOpen: true,
    );
  }

  @override
  Future<GuestServiceRequest> createMaintenance(
    String deviceToken, {
    required String title,
    String? description,
    String priority = 'medium',
  }) async {
    createdMaintenance = true;
    lastTitle = title;
    lastPriority = priority;
    return GuestServiceRequest(
      id: 2,
      category: 'maintenance',
      title: title,
      status: 'new',
      statusLabel: 'Submitted',
      isOpen: true,
    );
  }
}

/// A no-op notification repository — the top bar's unread badge just needs
/// something that resolves without a real network call.
class _EmptyGuestNotificationRepository implements GuestNotificationRepository {
  @override
  Future<List<GuestNotification>> fetch(String deviceToken) async => const [];

  @override
  Future<void> markRead(String deviceToken, int id) async {}

  @override
  Future<void> markAllRead(String deviceToken) async {}
}

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

  Future<_FakeServiceRequestRepository> pumpScreen(
    WidgetTester tester, {
    List<GuestServiceRequest> history = const [],
  }) async {
    tester.view.physicalSize = const Size(1280, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    final repo = _FakeServiceRequestRepository(history);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          serviceRequestRepositoryProvider.overrideWithValue(repo),
          guestNotificationRepositoryProvider.overrideWithValue(
            _EmptyGuestNotificationRepository(),
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
          home: GuestServiceRequestScreen(device: device, status: status),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    return repo;
  }

  testWidgets('renders the category picker by default', (tester) async {
    await pumpScreen(tester);

    expect(find.text('Service Request'), findsOneWidget);
    expect(find.text('Housekeeping'), findsOneWidget);
    expect(find.text('Maintenance'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('picking Housekeeping, a type, and submitting sends the request', (
    tester,
  ) async {
    final repo = await pumpScreen(tester);

    await tester.tap(find.text('Housekeeping'));
    await tester.pump();

    expect(find.text('Housekeeping Request'), findsOneWidget);
    expect(find.text('Towels'), findsOneWidget); // default selected type chip

    await tester.tap(find.text('Do Not Disturb'));
    await tester.pump();

    await tester.tap(find.text('Send Request'));
    await tester.pumpAndSettle();

    expect(repo.createdHousekeeping, isTrue);
    expect(repo.lastType, 'dnd');
    expect(find.text('Request Sent!'), findsOneWidget);
  });

  testWidgets('picking Maintenance requires a title before it can submit', (
    tester,
  ) async {
    final repo = await pumpScreen(tester);

    await tester.tap(find.text('Maintenance'));
    await tester.pump();

    expect(find.text('Report a Fault'), findsOneWidget);

    await tester.tap(find.text('Send Request'));
    await tester.pump();

    expect(repo.createdMaintenance, isFalse);
    expect(find.text('Please describe the fault in a few words.'), findsOneWidget);

    await tester.enterText(find.byType(TextField).first, 'AC not cooling');
    await tester.tap(find.text('High'));
    await tester.pump();

    await tester.tap(find.text('Send Request'));
    await tester.pumpAndSettle();

    expect(repo.createdMaintenance, isTrue);
    expect(repo.lastTitle, 'AC not cooling');
    expect(repo.lastPriority, 'high');
    expect(find.text('Request Sent!'), findsOneWidget);
  });

  testWidgets('the request history lists past requests from both categories', (
    tester,
  ) async {
    await pumpScreen(
      tester,
      history: const [
        GuestServiceRequest(
          id: 1,
          category: 'housekeeping',
          title: 'Towels',
          status: 'completed',
          statusLabel: 'Completed',
          isOpen: false,
        ),
        GuestServiceRequest(
          id: 2,
          category: 'maintenance',
          title: 'Broken lamp',
          status: 'new',
          statusLabel: 'Submitted',
          isOpen: true,
        ),
      ],
    );

    expect(find.text('REQUEST HISTORY'), findsOneWidget);
    expect(find.text('Towels'), findsOneWidget);
    expect(find.text('Completed'), findsOneWidget);
    expect(find.text('Broken lamp'), findsOneWidget);
    expect(find.text('Submitted'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('an empty history shows the empty state', (tester) async {
    await pumpScreen(tester);

    expect(find.text('No requests yet'), findsOneWidget);
  });

  testWidgets('tapping the notification bell opens the notification screen', (
    tester,
  ) async {
    await pumpScreen(tester);

    await tester.tap(find.byIcon(Icons.notifications_none_rounded));
    await tester.pumpAndSettle();

    expect(find.text('Notification'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
