import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/cinema/application/cinema_providers.dart';
import 'package:retirodelrocioapp/features/guest/cinema/data/cinema_repository.dart';
import 'package:retirodelrocioapp/features/guest/cinema/domain/cinema_service.dart';
import 'package:retirodelrocioapp/features/guest/cinema/presentation/screens/guest_cinema_screen.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/guest/notifications/data/guest_notification_repository.dart';
import 'package:retirodelrocioapp/features/guest/notifications/domain/guest_notification.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';

/// A fake backing store standing in for `/tablets/cinema/*`, so the screen's
/// real selection/booking flow exercises real widget behaviour without a
/// network call.
class _FakeCinemaRepository implements CinemaRepository {
  _FakeCinemaRepository(this.catalog);

  final CinemaCatalog catalog;
  bool bookedToRoom = false;
  Map<String, dynamic>? lastPayload;

  @override
  Future<CinemaCatalog> fetch(String deviceToken) async => catalog;

  @override
  Future<List<String>> roomAvailability(
    String deviceToken,
    String movieSlug,
    String date,
    String time,
  ) async => const [];

  @override
  Future<List<CinemaBookingAppointment>> bookings(String deviceToken) async =>
      const [];

  @override
  Future<CinemaBookingConfirmation> bookToRoom(
    String deviceToken,
    Map<String, dynamic> payload,
  ) async {
    bookedToRoom = true;
    lastPayload = payload;
    return const CinemaBookingConfirmation(
      reference: 'CIN-TEST-1',
      movieTitle: 'The Great Escape',
      room: 'Room 1',
      showTime: '2:00 PM',
      totalLabel: 'NGN 43,000',
    );
  }

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

  const catalog = CinemaCatalog(
    movies: [
      Movie(
        id: 1,
        slug: 'the-great-escape',
        title: 'The Great Escape',
        genre: 'Action',
        duration: '2h 10m',
        rating: 'PG-13',
        synopsis: 'A thrilling escape.',
        posterUrl: 'https://example.test/poster.jpg',
        backdropUrl: 'https://example.test/backdrop.jpg',
        roomPrice: 40000,
        roomPriceLabel: 'NGN 40,000',
        classification: 'now_showing',
        classificationLabel: 'Now Showing',
        showtimes: ['2:00 PM', '6:00 PM'],
        isFeatured: false,
      ),
    ],
    snacks: [
      CinemaSnack(
        id: 1,
        name: 'Large Popcorn',
        price: 3500,
        priceLabel: 'NGN 3,500',
        imageUrl: 'https://example.test/popcorn.jpg',
      ),
    ],
    rooms: ['Room 1', 'Room 2'],
    seatsPerRoom: 4,
  );

  Future<_FakeCinemaRepository> pumpScreen(WidgetTester tester) async {
    tester.view.physicalSize = const Size(1280, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    final repo = _FakeCinemaRepository(catalog);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          cinemaRepositoryProvider.overrideWithValue(repo),
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
          home: GuestCinemaScreen(device: device, status: status),
        ),
      ),
    );
    await tester.pump();
    await tester.pump(); // let the FutureProviders resolve

    return repo;
  }

  testWidgets('renders the movie catalogue and today\'s date', (tester) async {
    await pumpScreen(tester);

    expect(find.text('Cinema'), findsOneWidget);
    expect(find.text('The Great Escape'), findsOneWidget);
    expect(find.text('NGN 40,000'), findsOneWidget);
    expect(find.text('Select a Movie, Date & Room'), findsOneWidget);
    expect(find.text(DateFormat('d').format(DateTime.now())), findsWidgets);
    // No left navigation rail — a back button reaches this screen instead.
    expect(find.byIcon(Icons.arrow_back_rounded), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'picking a movie, date, showtime, room and Charge to Room enables BOOK NOW and confirms the booking',
    (tester) async {
      final repo = await pumpScreen(tester);

      await tester.tap(find.text('The Great Escape'));
      await tester.pump();

      // The showtime panel is now up.
      await tester.tap(find.text('2:00 PM'));
      await tester.pump();

      // Pick today from the date strip.
      await tester.tap(find.text(DateFormat('d').format(DateTime.now())).first);
      await tester.pump();

      // The room panel is now up (both rooms free).
      await tester.tap(find.text('Room 1'));
      await tester.pump();

      await tester.ensureVisible(find.text('Charge to Room'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Charge to Room'));
      await tester.pump();

      expect(find.text('BOOK NOW'), findsOneWidget);

      await tester.ensureVisible(find.text('BOOK NOW'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('BOOK NOW'));
      await tester.pumpAndSettle();

      // The Payment Summary popup is up, priced with VAT.
      expect(find.text('Payment Summary'), findsOneWidget);
      expect(find.text('NGN 43,000'), findsOneWidget); // 40,000 + 7.5%
      expect(find.text('BOOK NOW'), findsNWidgets(2));

      await tester.tap(
        find.descendant(
          of: find.byType(Dialog),
          matching: find.text('BOOK NOW'),
        ),
      );
      await tester.pumpAndSettle();

      expect(repo.bookedToRoom, isTrue);
      expect(repo.lastPayload?['movie_slug'], 'the-great-escape');
      expect(repo.lastPayload?['time'], '2:00 PM');
      expect(repo.lastPayload?['room'], 'Room 1');
      expect(find.text('Cinema Booking Confirmed'), findsOneWidget);

      await tester.tap(find.text('Done'));
      await tester.pumpAndSettle();

      expect(find.text('Payment Summary'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('picking a snack includes it in the booking payload', (
    tester,
  ) async {
    final repo = await pumpScreen(tester);

    await tester.tap(find.text('The Great Escape'));
    await tester.pump();
    await tester.tap(find.text('2:00 PM'));
    await tester.pump();
    await tester.tap(find.text(DateFormat('d').format(DateTime.now())).first);
    await tester.pump();
    await tester.tap(find.text('Room 1'));
    await tester.pump();

    // Add one large popcorn.
    await tester.ensureVisible(find.byIcon(Icons.add_rounded).last);
    await tester.pumpAndSettle();
    await tester.tap(find.byIcon(Icons.add_rounded).last);
    await tester.pump();
    expect(find.text('1'), findsWidgets);

    await tester.ensureVisible(find.text('Charge to Room'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Charge to Room'));
    await tester.pump();

    await tester.ensureVisible(find.text('BOOK NOW'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('BOOK NOW'));
    await tester.pumpAndSettle();
    await tester.tap(
      find.descendant(of: find.byType(Dialog), matching: find.text('BOOK NOW')),
    );
    await tester.pumpAndSettle();

    expect(repo.bookedToRoom, isTrue);
    expect(repo.lastPayload?['snacks'], [
      {'id': 1, 'qty': 1},
    ]);
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
