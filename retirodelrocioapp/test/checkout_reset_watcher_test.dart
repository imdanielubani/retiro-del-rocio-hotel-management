import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/app/app_router.dart';
import 'package:retirodelrocioapp/core/session/checkout_reset_watcher.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/welcome/application/room_status_providers.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// Holds the room status a test is currently simulating. Changing it is how a
/// test plays out a reception check-out reaching the tablet on its next poll.
class _LiveStatus extends Notifier<RoomStatus?> {
  @override
  RoomStatus? build() => null;

  void set(RoomStatus? status) => state = status;
}

final _liveStatus = NotifierProvider<_LiveStatus, RoomStatus?>(_LiveStatus.new);

const _device = ProvisionedDevice(
  token: 'test-token',
  deviceCode: 'RDR-101',
  deviceName: 'Room 101 Tablet',
  mode: 'guest',
  suiteName: 'Alba Suite',
  roomNumber: '101',
  roomUnitId: 5,
);

final _occupied = RoomStatus(
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

const _available = RoomStatus(
  occupancy: Occupancy.available,
  suiteName: 'Alba Suite',
  roomNumber: '101',
);

ProviderContainer _container(WidgetTester tester) =>
    ProviderScope.containerOf(tester.element(find.byType(MaterialApp)));

/// A stand-in for the guest's screen stack: each level pushes the next,
/// mirroring how the real app builds up Welcome → Guest Home → My Stay →
/// deeper service screens, without needing every real screen's own data
/// dependencies wired up.
class _Level extends StatelessWidget {
  const _Level(this.depth);

  final int depth;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Level $depth'),
            ElevatedButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => _Level(depth + 1)),
              ),
              child: const Text('Push deeper'),
            ),
          ],
        ),
      ),
    );
  }
}

void main() {
  Future<void> pump(WidgetTester tester, RoomStatus? initial) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          bootstrapDeviceProvider.overrideWithValue(_device),
          roomStatusProvider
              .overrideWith((ref, token) async => ref.watch(_liveStatus)),
        ],
        child: MaterialApp(
          navigatorKey: rootNavigatorKey,
          builder: (context, child) =>
              CheckoutResetWatcher(child: child ?? const SizedBox.shrink()),
          home: const _Level(1),
        ),
      ),
    );

    _container(tester).read(_liveStatus.notifier).set(initial);
    await tester.pump();
    await tester.pump();
  }

  testWidgets(
    'a check-out pops back to the root no matter how many screens are pushed',
    (tester) async {
      await pump(tester, _occupied);

      // Three screens deep — Level 1 (root) → 2 → 3 → 4.
      for (var i = 0; i < 3; i++) {
        await tester.tap(find.text('Push deeper'));
        await tester.pumpAndSettle();
      }
      expect(find.text('Level 4'), findsOneWidget);

      // Reception checks the guest out while they're buried at Level 4.
      _container(tester).read(_liveStatus.notifier).set(_available);
      await tester.pumpAndSettle();

      // Every pushed screen is gone — back to the root, unwound in one go.
      expect(find.text('Level 4'), findsNothing);
      expect(find.text('Level 3'), findsNothing);
      expect(find.text('Level 2'), findsNothing);
      expect(find.text('Level 1'), findsOneWidget);
    },
  );

  testWidgets('no check-out means no reset — the stack stays put', (
    tester,
  ) async {
    await pump(tester, _occupied);

    await tester.tap(find.text('Push deeper'));
    await tester.pumpAndSettle();
    expect(find.text('Level 2'), findsOneWidget);

    // Room stays occupied — nothing should move.
    _container(tester).read(_liveStatus.notifier).set(_occupied);
    await tester.pumpAndSettle();

    expect(find.text('Level 2'), findsOneWidget);
    expect(find.text('Level 1'), findsNothing);
  });

  testWidgets('a staff tablet is never reset by room occupancy', (
    tester,
  ) async {
    const staffDevice = ProvisionedDevice(
      token: 'staff-token',
      deviceCode: 'RDR-ST1',
      deviceName: 'Reception Desk',
      mode: 'staff',
      role: 'reception',
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          bootstrapDeviceProvider.overrideWithValue(staffDevice),
          roomStatusProvider
              .overrideWith((ref, token) async => ref.watch(_liveStatus)),
        ],
        child: MaterialApp(
          navigatorKey: rootNavigatorKey,
          builder: (context, child) =>
              CheckoutResetWatcher(child: child ?? const SizedBox.shrink()),
          home: const _Level(1),
        ),
      ),
    );
    await tester.pump();

    await tester.tap(find.text('Push deeper'));
    await tester.pumpAndSettle();
    expect(find.text('Level 2'), findsOneWidget);

    // A staff tablet has no room to occupy — flipping room status must never
    // touch its own navigation stack.
    _container(tester).read(_liveStatus.notifier).set(_available);
    await tester.pumpAndSettle();

    expect(find.text('Level 2'), findsOneWidget);
  });
}
