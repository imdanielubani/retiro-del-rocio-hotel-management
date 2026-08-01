import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/cinema/application/cinema_providers.dart';
import 'package:retirodelrocioapp/features/guest/cinema/data/cinema_repository.dart';
import 'package:retirodelrocioapp/features/guest/cinema/domain/cinema_service.dart';
import 'package:retirodelrocioapp/features/guest/cinema/presentation/screens/guest_cinema_bookings_screen.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/data/guest_notification_repository.dart';
import 'package:retirodelrocioapp/features/guest/notifications/domain/guest_notification.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';

class _FakeCinemaRepository implements CinemaRepository {
  _FakeCinemaRepository(this._bookings);

  final List<CinemaBookingAppointment> _bookings;

  @override
  Future<CinemaCatalog> fetch(String deviceToken) async => CinemaCatalog.empty;

  @override
  Future<List<String>> roomAvailability(
    String deviceToken,
    String movieSlug,
    String date,
    String time,
  ) async => const [];

  @override
  Future<List<CinemaBookingAppointment>> bookings(String deviceToken) async =>
      _bookings;

  @override
  Future<CinemaBookingConfirmation> bookToRoom(
    String deviceToken,
    Map<String, dynamic> payload,
  ) async => throw UnimplementedError('not exercised in this test');

  @override
  Future<CinemaBookingQuote> initializePaystack(
    String deviceToken,
    Map<String, dynamic> payload,
  ) async => throw UnimplementedError('not exercised in this test');

  @override
  Future<CinemaBookingConfirmation> confirmPaystack(
    String deviceToken,
    String reference,
  ) async => throw UnimplementedError('not exercised in this test');
}

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

  Future<void> pumpScreen(
    WidgetTester tester,
    List<CinemaBookingAppointment> bookings,
  ) async {
    tester.view.physicalSize = const Size(1280, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          cinemaRepositoryProvider.overrideWithValue(
            _FakeCinemaRepository(bookings),
          ),
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
          home: GuestCinemaBookingsScreen(device: device, status: status),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();
  }

  testWidgets('lists confirmed bookings with status and payment labels', (
    tester,
  ) async {
    await pumpScreen(tester, const [
      CinemaBookingAppointment(
        id: 1,
        code: 'CIN-100001-RDR',
        reference: 'CIN-001',
        movieTitle: 'The Great Escape',
        showDateLabel: 'Wed, Jul 9, 2026',
        showTime: '2:00 PM',
        room: 'Room 1',
        guests: 2,
        snacksLabel: 'Large Popcorn ×2',
        status: 'confirmed',
        statusLabel: 'Confirmed',
        chargedToRoom: true,
        paymentMethodLabel: 'Charged to Room',
        totalLabel: 'NGN 50,525',
      ),
      CinemaBookingAppointment(
        id: 2,
        code: 'CIN-100002-RDR',
        reference: 'CIN-002',
        movieTitle: 'Reef Runners',
        showDateLabel: 'Thu, Jul 10, 2026',
        showTime: '6:00 PM',
        room: 'Room 2',
        guests: 1,
        snacksLabel: '—',
        status: 'confirmed',
        statusLabel: 'Confirmed',
        chargedToRoom: false,
        paymentMethodLabel: 'Paid Online',
        totalLabel: 'NGN 43,000',
      ),
    ]);

    expect(find.text('My Bookings'), findsOneWidget);
    expect(find.text('The Great Escape'), findsOneWidget);
    expect(find.text('Reef Runners'), findsOneWidget);
    expect(find.text('Charged to Room'), findsOneWidget);
    expect(find.text('Paid Online'), findsOneWidget);
    expect(find.text('NGN 50,525'), findsOneWidget);
    expect(find.text('Large Popcorn ×2'), findsOneWidget);
    expect(find.byIcon(Icons.arrow_back_rounded), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('shows an empty state when there are no bookings', (
    tester,
  ) async {
    await pumpScreen(tester, const []);

    expect(find.text('No cinema bookings yet'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('tapping the notification bell opens the notification screen', (
    tester,
  ) async {
    await pumpScreen(tester, const []);

    await tester.tap(find.byIcon(Icons.notifications_none_rounded));
    await tester.pumpAndSettle();

    expect(find.text('Notification'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
