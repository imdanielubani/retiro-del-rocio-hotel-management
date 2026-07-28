import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/bills/application/bills_providers.dart';
import 'package:retirodelrocioapp/features/guest/bills/data/bills_repository.dart';
import 'package:retirodelrocioapp/features/guest/bills/domain/bill.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/application/my_stay_providers.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/data/my_stay_repository.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/domain/guest_stay.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/presentation/screens/my_stay_screen.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/data/guest_notification_repository.dart';
import 'package:retirodelrocioapp/features/guest/notifications/domain/guest_notification.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';

class _FakeMyStayRepository implements MyStayRepository {
  _FakeMyStayRepository(this.stay);

  final GuestStay stay;

  @override
  Future<GuestStay> fetch(String deviceToken) async => stay;

  @override
  Future<ExtensionQuote> initializeExtension(
    String deviceToken,
    DateTime newCheckOut,
  ) async => throw UnimplementedError();

  @override
  Future<GuestStay> extend(
    String deviceToken,
    DateTime newCheckOut,
    String reference,
  ) async => throw UnimplementedError();

  @override
  Future<GuestStay> chargeToRoom(String deviceToken, DateTime newCheckOut) async =>
      throw UnimplementedError();
}

class _FakeBillsRepository implements BillsRepository {
  @override
  Future<Bill> fetch(String deviceToken) async => Bill.empty;

  @override
  Future<BillPaymentQuote> initializePaystack(String deviceToken) async =>
      throw UnimplementedError();

  @override
  Future<BillPaymentConfirmation> confirmPaystack(
    String deviceToken,
    String reference,
  ) async => throw UnimplementedError();
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
    summaryLines: const [
      StaySummaryLine(label: 'Room Rate', sub: 'NGN 4,250 × 5', amountLabel: 'NGN 21,250'),
      StaySummaryLine(label: 'Stay Extension', sub: null, amountLabel: 'NGN 4,250'),
    ],
    summaryTotalLabel: 'NGN 25,500',
    currentBillLabel: 'NGN 4,569',
    currentBillDue: true,
    inclusions: const [],
  );

  testWidgets(
    'tapping Current Bill opens My Bill, and Stay Summary lists every charge',
    (tester) async {
      tester.view.physicalSize = const Size(1280, 800);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.reset);

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            myStayRepositoryProvider.overrideWithValue(_FakeMyStayRepository(stay)),
            billsRepositoryProvider.overrideWithValue(_FakeBillsRepository()),
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
            home: MyStayScreen(device: device, status: status),
          ),
        ),
      );
      await tester.pump();
      await tester.pump();

      // Stay Summary shows every charge, including the extension.
      expect(find.text('Room Rate'), findsOneWidget);
      expect(find.text('Stay Extension'), findsOneWidget);
      expect(find.text('NGN 25,500'), findsOneWidget); // summary total

      // Current Bill shows only the outstanding room-charge balance.
      expect(find.text('CURRENT BILL'), findsOneWidget);
      expect(find.text('NGN 4,569'), findsOneWidget);

      await tester.tap(find.text('NGN 4,569'));
      await tester.pumpAndSettle();

      expect(find.text('My Bill'), findsOneWidget);
    },
  );
}
