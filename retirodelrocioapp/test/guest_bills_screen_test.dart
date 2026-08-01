import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/bills/application/bills_providers.dart';
import 'package:retirodelrocioapp/features/guest/bills/data/bills_repository.dart';
import 'package:retirodelrocioapp/features/guest/bills/domain/bill.dart';
import 'package:retirodelrocioapp/features/guest/bills/presentation/screens/guest_bills_screen.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/data/guest_notification_repository.dart';
import 'package:retirodelrocioapp/features/guest/notifications/domain/guest_notification.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';

/// A fake backing store standing in for `/tablets/my-bills/*`, so the
/// screen's real Pay Now flow exercises real widget behaviour without a
/// network call.
class _FakeBillsRepository implements BillsRepository {
  _FakeBillsRepository(this.bill);

  final Bill bill;
  bool initialized = false;

  @override
  Future<Bill> fetch(String deviceToken) async => bill;

  @override
  Future<BillPaymentQuote> initializePaystack(String deviceToken) async {
    initialized = true;
    return const BillPaymentQuote(
      authorizationUrl: 'https://paystack.test/pay',
      reference: 'BILL-TEST-1',
      callbackUrl: 'https://retirodelrocio.test/tablet/extend-return',
      totalLabel: 'NGN 21,250',
    );
  }

  @override
  Future<BillPaymentConfirmation> confirmPaystack(
    String deviceToken,
    String reference,
  ) async => const BillPaymentConfirmation(
    reference: 'BILL-TEST-1',
    totalLabel: 'NGN 21,250',
  );
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

  const bill = Bill(
    roomName: 'Alba Suite',
    unitLabel: 'Room 101',
    categories: [
      BillCategory(
        key: 'room',
        label: 'Room Charges',
        itemCount: 1,
        amountLabel: 'NGN 21,250',
        hasCharges: true,
        items: [
          BillLineItem(
            label: 'Stay Extension',
            sub: '1 night(s) to Jul 15, 2026',
            amountLabel: 'NGN 21,250',
          ),
        ],
      ),
      BillCategory(
        key: 'spa',
        label: 'Spa & Wellness',
        itemCount: 0,
        amountLabel: null,
        hasCharges: false,
        items: [],
      ),
      BillCategory(
        key: 'restaurant',
        label: 'Restaurant & Bar',
        itemCount: 0,
        amountLabel: null,
        hasCharges: false,
        items: [],
      ),
    ],
    summaryLines: [
      BillSummaryLine(label: 'Room Charges', amountLabel: 'NGN 21,250'),
    ],
    totalDueLabel: 'NGN 21,250',
    checkoutReminder: BillCheckoutReminder(
      checkOutLabel: 'Tue, Jul 14 at 11:00 AM',
      daysRemainingLabel: '5 days remaining',
    ),
    canPay: true,
  );

  Future<_FakeBillsRepository> pumpScreen(WidgetTester tester) async {
    tester.view.physicalSize = const Size(1280, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    final repo = _FakeBillsRepository(bill);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          billsRepositoryProvider.overrideWithValue(repo),
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
          home: GuestBillsScreen(device: device, status: status),
        ),
      ),
    );
    await tester.pump();
    await tester.pump(); // let the FutureProviders resolve

    return repo;
  }

  testWidgets('renders the itemised folio with the room charges expanded', (
    tester,
  ) async {
    await pumpScreen(tester);

    expect(find.text('My Bill'), findsOneWidget);
    expect(find.text('Room Charges'), findsWidgets);
    expect(find.text('Stay Extension'), findsOneWidget);
    expect(find.text('1 night(s) to Jul 15, 2026'), findsOneWidget);
    expect(find.text('Spa & Wellness'), findsOneWidget);
    expect(find.text('No charges'), findsWidgets);
    expect(find.text('Bill Summary'), findsOneWidget);
    expect(find.text('Total Due'), findsOneWidget);
    expect(find.text('PAY NOW'), findsOneWidget);
    expect(find.text('5 days remaining'), findsOneWidget);
    // No left navigation rail — a back button reaches this screen instead.
    expect(find.byIcon(Icons.arrow_back_rounded), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('a fully settled bill shows "All Settled" instead of PAY NOW', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1280, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    const settledBill = Bill(
      roomName: 'Alba Suite',
      unitLabel: 'Room 101',
      categories: [
        BillCategory(
          key: 'room',
          label: 'Room Charges',
          itemCount: 1,
          amountLabel: 'NGN 21,250',
          hasCharges: true,
          items: [
            BillLineItem(
              label: 'Room Rate',
              sub: 'NGN 4,250 × 5',
              amountLabel: 'NGN 21,250',
            ),
          ],
        ),
      ],
      summaryLines: [
        BillSummaryLine(label: 'Room Charges', amountLabel: 'NGN 21,250'),
        BillSummaryLine(label: 'Already Paid', amountLabel: '-NGN 21,250'),
      ],
      totalDueLabel: 'NGN 0',
      checkoutReminder: BillCheckoutReminder(
        checkOutLabel: 'Tue, Jul 14 at 11:00 AM',
        daysRemainingLabel: '5 days remaining',
      ),
      canPay: false,
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          billsRepositoryProvider.overrideWithValue(
            _FakeBillsRepository(settledBill),
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
          home: GuestBillsScreen(device: device, status: status),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    expect(find.text('All Settled'), findsOneWidget);
    expect(find.text('PAY NOW'), findsNothing);
    expect(tester.takeException(), isNull);
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
