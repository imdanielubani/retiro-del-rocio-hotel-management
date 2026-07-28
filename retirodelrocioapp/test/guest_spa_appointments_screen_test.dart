import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/data/guest_notification_repository.dart';
import 'package:retirodelrocioapp/features/guest/notifications/domain/guest_notification.dart';
import 'package:retirodelrocioapp/features/guest/spa/application/spa_providers.dart';
import 'package:retirodelrocioapp/features/guest/spa/data/spa_repository.dart';
import 'package:retirodelrocioapp/features/guest/spa/domain/spa_service.dart';
import 'package:retirodelrocioapp/features/guest/spa/presentation/screens/guest_spa_appointments_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';

class _FakeSpaRepository implements SpaRepository {
  _FakeSpaRepository(this._appointments);

  final List<SpaAppointment> _appointments;

  @override
  Future<SpaCatalog> fetch(String deviceToken) async => SpaCatalog.empty;

  @override
  Future<List<SpaAppointment>> appointments(String deviceToken) async =>
      _appointments;

  @override
  Future<SpaBookingConfirmation> bookToRoom(
    String deviceToken,
    String serviceSlug,
    String time,
  ) async => throw UnimplementedError('not exercised in this test');

  @override
  Future<SpaBookingQuote> initializePaystack(
    String deviceToken,
    String serviceSlug,
    String time,
  ) async => throw UnimplementedError('not exercised in this test');

  @override
  Future<SpaBookingConfirmation> confirmPaystack(
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
    List<SpaAppointment> appointments,
  ) async {
    tester.view.physicalSize = const Size(1280, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          spaRepositoryProvider.overrideWithValue(
            _FakeSpaRepository(appointments),
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
          home: GuestSpaAppointmentsScreen(device: device, status: status),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();
  }

  testWidgets('lists confirmed appointments with status and payment labels', (
    tester,
  ) async {
    await pumpScreen(tester, const [
      SpaAppointment(
        id: 1,
        reference: 'SPA-001',
        serviceName: 'Signature Deep Tissue Massage',
        dateLabel: 'Wed, Jul 9, 2026',
        time: '10:30 AM',
        status: 'confirmed',
        statusLabel: 'Confirmed',
        chargedToRoom: true,
        paymentMethodLabel: 'Charged to Room',
        totalLabel: 'NGN 37,625',
      ),
      SpaAppointment(
        id: 2,
        reference: 'SPA-002',
        serviceName: 'Hot Stone Therapy',
        dateLabel: 'Thu, Jul 10, 2026',
        time: '2:00 PM',
        status: 'confirmed',
        statusLabel: 'Confirmed',
        chargedToRoom: false,
        paymentMethodLabel: 'Paid Online',
        totalLabel: 'NGN 42,000',
      ),
    ]);

    expect(find.text('My Appointments'), findsOneWidget);
    expect(find.text('Signature Deep Tissue Massage'), findsOneWidget);
    expect(find.text('Hot Stone Therapy'), findsOneWidget);
    expect(find.text('Charged to Room'), findsOneWidget);
    expect(find.text('Paid Online'), findsOneWidget);
    expect(find.text('NGN 37,625'), findsOneWidget);
    expect(find.byIcon(Icons.arrow_back_rounded), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('shows an empty state when there are no appointments', (
    tester,
  ) async {
    await pumpScreen(tester, const []);

    expect(find.text('No spa appointments yet'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
