import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/screens/guest_home_screen.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/current_stay_card.dart';
import 'package:retirodelrocioapp/features/guest/home/presentation/widgets/quick_service_card.dart';
import 'package:retirodelrocioapp/features/guest/notifications/application/guest_notification_providers.dart';
import 'package:retirodelrocioapp/features/welcome/application/room_status_providers.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';

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

  Future<void> pumpHome(
    WidgetTester tester, {
    TextScaler textScaler = TextScaler.noScaling,
  }) async {
    tester.view.physicalSize = const Size(1280, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          roomStatusProvider.overrideWith((ref, token) async => status),
          guestNotificationsProvider.overrideWith((ref, token) async => []),
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
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context).copyWith(textScaler: textScaler),
            child: child!,
          ),
          home: GuestHomeScreen(device: device, status: status),
        ),
      ),
    );
    await tester.pump();
  }

  testWidgets('renders the guest home on a landscape tablet', (tester) async {
    await pumpHome(tester);

    expect(find.text('Daniel Ubani'), findsWidgets);
    expect(find.text('CURRENT STAY'), findsOneWidget);
    expect(find.text('Alba Suite • Room 101'), findsOneWidget);
    expect(find.text('Quick Services'), findsOneWidget);
    expect(find.text('Extend Stay'), findsOneWidget);
    expect(find.text('Emergency'), findsOneWidget);

    // All 12 service tiles, in three rows of four (Figma 84:3429 + Service
    // Request, added later).
    expect(find.byType(QuickServiceCard), findsNWidgets(12));
    expect(find.text('Dining'), findsOneWidget);
    expect(find.text('Hotel Information'), findsOneWidget);
    expect(find.text('Service Request'), findsOneWidget);

    expect(tester.takeException(), isNull);
  });

  testWidgets('only the service grid scrolls — the stay card stays put',
      (tester) async {
    await pumpHome(tester);

    final stayCardBefore = tester.getTopLeft(find.byType(CurrentStayCard));
    final headingBefore = tester.getTopLeft(find.text('Quick Services'));

    // The third row sits below the fold; scrolling the grid brings it up.
    final hotelInfo = find.text('Hotel Information');
    final tileBefore = tester.getTopLeft(hotelInfo);

    await tester.drag(find.byType(QuickServiceCard).first, const Offset(0, -160));
    await tester.pumpAndSettle();

    expect(tester.getTopLeft(hotelInfo).dy, lessThan(tileBefore.dy));
    expect(tester.getTopLeft(find.byType(CurrentStayCard)), stayCardBefore);
    expect(tester.getTopLeft(find.text('Quick Services')), headingBefore);
    expect(tester.takeException(), isNull);
  });

  testWidgets('the notification bell opens the Notifications screen', (
    tester,
  ) async {
    await pumpHome(tester);

    await tester.tap(find.byIcon(Icons.notifications_none_rounded));
    await tester.pumpAndSettle();

    expect(find.text('Notification'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('service cards hold their height under the kiosk font clamp',
      (tester) async {
    // The tablet's system font-size setting used to inflate the labels and
    // overflow these fixed-height cards. `RocioTabletApp` clamps scaling for the
    // whole kiosk (see app.dart); this mirrors that wrapper and pins the height.
    await pumpHome(tester);

    for (final card in tester.widgetList<QuickServiceCard>(
      find.byType(QuickServiceCard),
    )) {
      final size = tester.getSize(find.byWidget(card));
      expect(size.height, QuickServiceCard.height);
      expect(size.width, 280);
    }
    expect(tester.takeException(), isNull);
  });
}
