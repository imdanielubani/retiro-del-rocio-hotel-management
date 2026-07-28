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
import 'package:retirodelrocioapp/features/guest/spa/presentation/screens/guest_spa_screen.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';

/// A fake backing store standing in for `/tablets/spa/*`, so the screen's
/// real selection/booking flow exercises real widget behaviour without a
/// network call.
class _FakeSpaRepository implements SpaRepository {
  _FakeSpaRepository(this.catalog);

  final SpaCatalog catalog;
  bool bookedToRoom = false;

  @override
  Future<SpaCatalog> fetch(String deviceToken) async => catalog;

  @override
  Future<List<SpaAppointment>> appointments(String deviceToken) async => const [];

  @override
  Future<SpaBookingConfirmation> bookToRoom(
    String deviceToken,
    String serviceSlug,
    String time,
  ) async {
    bookedToRoom = true;
    return SpaBookingConfirmation(
      reference: 'SPA-TEST-1',
      serviceName: 'Signature Deep Tissue Massage',
      time: time,
      totalLabel: 'NGN 37,625',
    );
  }

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

  const catalog = SpaCatalog(
    services: [
      SpaService(
        id: 1,
        slug: 'deep-tissue-massage',
        name: 'Signature Deep Tissue Massage',
        description: 'Full-body therapeutic massage.',
        price: 35000,
        priceLabel: 'NGN 35,000',
        durationMinutes: 90,
        durationLabel: '90 min',
        imageUrl: null,
        categoryId: 1,
        category: 'Massages',
        categoryColor: '#16a34a',
      ),
    ],
    categories: [SpaCategory(id: 1, slug: 'massages', name: 'Massages', color: '#16a34a')],
    availableTimes: ['9:00 AM', '10:30 AM', '12:00 PM'],
  );

  Future<_FakeSpaRepository> pumpScreen(WidgetTester tester) async {
    tester.view.physicalSize = const Size(1280, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    final repo = _FakeSpaRepository(catalog);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          spaRepositoryProvider.overrideWithValue(repo),
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
          home: GuestSpaScreen(device: device, status: status),
        ),
      ),
    );
    await tester.pump();
    await tester.pump(); // let the FutureProviders resolve

    return repo;
  }

  testWidgets('renders the catalogue and the fixed time slots', (
    tester,
  ) async {
    await pumpScreen(tester);

    expect(find.text('Spa & Wellness'), findsOneWidget);
    expect(find.text('Signature Deep Tissue Massage'), findsOneWidget);
    expect(find.text('NGN 35,000'), findsOneWidget);
    expect(find.text('9:00 AM'), findsOneWidget);
    expect(find.text('Select a Service & Time'), findsOneWidget);
    // The category chip and the card's badge both come from the real
    // admin-managed category, not a hardcoded "SPA" label.
    expect(find.text('All'), findsOneWidget);
    expect(find.text('Massages'), findsOneWidget);
    expect(find.text('MASSAGES'), findsOneWidget);
    expect(find.text('SPA'), findsNothing);
    // No left navigation rail — a back button reaches this screen instead.
    expect(find.byIcon(Icons.arrow_back_rounded), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'picking a service, a time and Charge to Room enables BOOK NOW and confirms the booking',
    (tester) async {
      final repo = await pumpScreen(tester);

      await tester.tap(find.text('Signature Deep Tissue Massage'));
      await tester.pump();
      await tester.tap(find.text('9:00 AM'));
      await tester.pump();
      await tester.tap(find.text('Charge to Room'));
      await tester.pump();

      expect(find.text('BOOK NOW'), findsOneWidget);

      await tester.tap(find.text('BOOK NOW'));
      await tester.pumpAndSettle();

      // The Payment Summary popup is up, priced with VAT.
      expect(find.text('Payment Summary'), findsOneWidget);
      expect(find.text('NGN 37,625'), findsOneWidget); // 35,000 + 7.5%
      // Two "BOOK NOW" now exist — the screen's own CTA underneath the
      // popup, and the popup's confirm button. Only the latter is reachable.
      expect(find.text('BOOK NOW'), findsNWidgets(2));

      await tester.tap(find.descendant(
        of: find.byType(Dialog),
        matching: find.text('BOOK NOW'),
      ));
      await tester.pumpAndSettle();

      expect(repo.bookedToRoom, isTrue);
      expect(find.text('Spa Booking Confirmed'), findsOneWidget);

      await tester.tap(find.text('Done'));
      await tester.pumpAndSettle();

      expect(find.text('Payment Summary'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
}
