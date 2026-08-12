import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/application/my_stay_providers.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/data/my_stay_repository.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/domain/guest_stay.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/presentation/screens/extend_stay_screen.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/data/guest_notification_repository.dart';
import 'package:retirodelrocioapp/features/guest/notifications/domain/guest_notification.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';

/// A fake backing store standing in for `/tablets/extend-stay/*`, so the
/// screen's real Charge to Room flow exercises real widget behaviour without
/// a network call.
class _FakeMyStayRepository implements MyStayRepository {
  _FakeMyStayRepository(this.stay);

  final GuestStay stay;
  bool chargedToRoom = false;

  @override
  Future<GuestStay> fetch(String deviceToken) async => stay;

  @override
  Future<ExtensionQuote> initializeExtension(
    String deviceToken,
    DateTime newCheckOut,
  ) async => throw UnimplementedError('not exercised in this test');

  @override
  Future<GuestStay> extend(
    String deviceToken,
    DateTime newCheckOut,
    String reference,
  ) async => throw UnimplementedError('not exercised in this test');

  @override
  Future<GuestStay> chargeToRoom(
    String deviceToken,
    DateTime newCheckOut,
  ) async {
    chargedToRoom = true;
    return GuestStay(
      reservation: stay.reservation,
      guests: stay.guests,
      visitors: stay.visitors,
      summaryLines: stay.summaryLines,
      summaryTotalLabel: stay.summaryTotalLabel,
      currentBillLabel: stay.currentBillLabel,
      currentBillDue: stay.currentBillDue,
      inclusions: stay.inclusions,
      extension: StayExtension(
        additionalNights: newCheckOut
            .difference(stay.reservation.checkOut!)
            .inDays,
        additionalCost: 8500,
        additionalCostLabel: 'NGN 8,500',
        newCheckOut: newCheckOut,
        newCheckOutLabel: 'August 1, 2026',
      ),
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

  final stay = GuestStay(
    reservation: StayReservation(
      roomName: 'Alba Suite',
      unitLabel: 'Room 101',
      checkIn: DateTime(2026, 7, 9, 15),
      checkOut: DateTime(2026, 7, 14, 11),
      nights: 5,
      ratePerNight: 4250,
      rateLabel: 'NGN 4,250/night',
    ),
    guests: const StayGuests(primaryName: 'Daniel Ubani', partySize: 2),
    visitors: const [],
    summaryLines: const [],
    summaryTotalLabel: 'NGN 21,250',
    currentBillLabel: 'NGN 0',
    currentBillDue: false,
    inclusions: const [],
  );

  Future<_FakeMyStayRepository> pumpScreen(WidgetTester tester) async {
    tester.view.physicalSize = const Size(1280, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    final repo = _FakeMyStayRepository(stay);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          myStayRepositoryProvider.overrideWithValue(repo),
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
          home: ExtendStayScreen(device: device, stay: stay),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    return repo;
  }

  testWidgets(
    'picking a date and Charge to Room enables Confirm and books the extension',
    (tester) async {
      final repo = await pumpScreen(tester);

      expect(find.text('Charge to Room'), findsOneWidget);
      expect(find.text('Paystack'), findsOneWidget);

      // Pick the day right after the current checkout (July 15).
      await tester.tap(find.text('15').first);
      await tester.pump();
      await tester.tap(find.text('Charge to Room'));
      await tester.pump();

      await tester.ensureVisible(find.text('Confirm Extension'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm Extension'));
      await tester.pumpAndSettle();

      expect(find.text('Payment Summary'), findsOneWidget);
      expect(find.text('BOOK NOW'), findsWidgets);

      await tester.tap(
        find.descendant(
          of: find.byType(Dialog),
          matching: find.text('BOOK NOW'),
        ),
      );
      await tester.pumpAndSettle();

      expect(repo.chargedToRoom, isTrue);
      expect(find.text('Stay Extended!'), findsOneWidget);

      await tester.tap(find.text('Back to My Stay'));
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull);
    },
  );

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
