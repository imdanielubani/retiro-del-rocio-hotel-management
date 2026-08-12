import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/core/media/ambient_video_provider.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/welcome/application/room_status_providers.dart';
import 'package:retirodelrocioapp/features/welcome/application/weather_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';
import 'package:retirodelrocioapp/features/welcome/domain/weather.dart';
import 'package:retirodelrocioapp/features/welcome/presentation/screens/welcome_screen.dart';

/// Holds the room status a test is currently simulating. Changing it is how a
/// test plays out a reception check-out reaching the tablet on its next poll.
class _LiveStatus extends Notifier<RoomStatus?> {
  @override
  RoomStatus? build() => null;

  void set(RoomStatus? status) => state = status;
}

final liveStatus = NotifierProvider<_LiveStatus, RoomStatus?>(_LiveStatus.new);

ProviderContainer _container(WidgetTester tester) =>
    ProviderScope.containerOf(tester.element(find.byType(MaterialApp)));

void main() {
  const device = ProvisionedDevice(
    token: 'test-token',
    deviceCode: 'RDR-101',
    deviceName: 'Room 101 Tablet',
    mode: 'guest',
    suiteName: 'Alba Suite',
    roomNumber: '101',
  );

  const available = RoomStatus(
    occupancy: Occupancy.available,
    suiteName: 'Alba Suite',
    roomNumber: '101',
  );

  final occupied = RoomStatus(
    occupancy: Occupancy.occupied,
    suiteName: 'Alba Suite',
    roomNumber: '101',
    guest: GuestInfo(
      name: 'Daniel Ubani',
      nights: 5,
      checkIn: DateTime(2026, 7, 9, 15),
      checkOut: DateTime(2026, 7, 14, 11),
    ),
  );

  Future<void> pumpWelcome(WidgetTester tester, RoomStatus status) async {
    tester.view.physicalSize = const Size(1280, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          ambientVideoProvider.overrideWith((ref) async => null),
          roomStatusProvider.overrideWith(
            (ref, token) async => ref.watch(liveStatus),
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
        child: const MaterialApp(home: WelcomeScreen(device: device)),
      ),
    );

    _container(tester).read(liveStatus.notifier).set(status);
    await tester.pump();
    await tester.pump();
  }

  testWidgets('an empty room offers no way in', (tester) async {
    await pumpWelcome(tester, available);

    expect(find.text('This room is currently available.'), findsOneWidget);
    expect(find.text('Explore'), findsNothing);
  });

  testWidgets('a check-in shows the room as occupied, behind Explore', (
    tester,
  ) async {
    await pumpWelcome(tester, occupied);

    // The tablet reports the check-in but does not jump into the guest screens.
    expect(find.text('This room is currently occupied.'), findsOneWidget);
    expect(find.text('Explore'), findsOneWidget);
    expect(find.text('Welcome Back,'), findsNothing);

    await tester.tap(find.text('Explore'));
    await tester.pump();

    // Only now does the guest's own welcome appear.
    expect(find.text('Welcome Back,'), findsOneWidget);
    expect(find.text('Daniel Ubani.'), findsOneWidget);
  });

  testWidgets(
    'a check-out hides the in-place guest welcome once nothing is pushed on top',
    (tester) async {
      await pumpWelcome(tester, occupied);

      await tester.tap(find.text('Explore'));
      await tester.pump();
      expect(find.text('Welcome Back,'), findsOneWidget);

      // Reception checks them out; the tablet's next poll sees the empty room.
      // Nothing was pushed on top of this screen, so it's still the one
      // rebuilding — this is the one piece of the reset WelcomeScreen owns
      // itself. Unwinding any *pushed* screens (My Stay, Bills, …) back down
      // to here is [CheckoutResetWatcher]'s job, covered in its own test —
      // this screen doesn't reliably rebuild once anything sits on top of it,
      // so it can't be the one responsible for that part.
      _container(tester).read(liveStatus.notifier).set(available);
      await tester.pumpAndSettle();

      expect(find.text('Welcome Back,'), findsNothing);
      expect(find.text('This room is currently available.'), findsOneWidget);
      expect(find.text('Explore'), findsNothing);
    },
  );
}
