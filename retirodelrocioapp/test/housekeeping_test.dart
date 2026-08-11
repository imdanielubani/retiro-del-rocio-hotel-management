import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_guest_request.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_overview.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_room.dart';
import 'package:retirodelrocioapp/features/housekeeping/presentation/widgets/housekeeping_widgets.dart';

Widget _host(Widget child) => MaterialApp(
  home: Scaffold(body: SizedBox(width: 420, child: child)),
);

void main() {
  group('domain parsing', () {
    test('HousekeepingOverview parses stats, rooms and requests', () {
      final overview = HousekeepingOverview.fromJson({
        'stats': {
          'needs_attention': 2,
          'dirty': 1,
          'out_of_order': 1,
          'pending_requests': 1,
        },
        'rooms': [
          {
            'id': 1,
            'number': '204',
            'room_name': 'Brisa Residence',
            'occupancy': 'occupied',
            'occupancy_label': 'Occupied',
            'housekeeping_status': 'dirty',
            'housekeeping_status_label': 'Dirty',
            'guest_name': 'Ada Lovelace',
            'checkout_today': true,
            'updated_label': '2 hours ago',
          },
        ],
        'requests': [
          {
            'id': 5,
            'room_unit_id': 1,
            'room_number': '204',
            'type': 'towels',
            'type_label': 'Towels',
            'notes': null,
            'status': 'pending',
            'is_pending': true,
            'created_label': '10 min ago',
          },
        ],
      });

      expect(overview.needsAttention, 2);
      expect(overview.dirty, 1);
      expect(overview.rooms, hasLength(1));
      expect(overview.rooms.first.number, '204');
      expect(overview.rooms.first.checkoutToday, isTrue);
      expect(overview.rooms.first.needsAttention, isTrue);
      expect(overview.requests, hasLength(1));
      expect(overview.requests.first.typeLabel, 'Towels');
    });

    test('HousekeepingOverview.empty has nothing', () {
      expect(HousekeepingOverview.empty.rooms, isEmpty);
      expect(HousekeepingOverview.empty.requests, isEmpty);
      expect(HousekeepingOverview.empty.needsAttention, 0);
    });

    test('a clean room does not need attention', () {
      final room = HousekeepingRoom.fromJson({
        'id': 2,
        'number': '305',
        'occupancy': 'available',
        'occupancy_label': 'Available',
        'housekeeping_status': 'clean',
        'housekeeping_status_label': 'Clean',
      });

      expect(room.needsAttention, isFalse);
    });
  });

  const dirtyRoom = HousekeepingRoom(
    id: 1,
    number: '204',
    roomName: 'Brisa Residence',
    occupancy: 'occupied',
    occupancyLabel: 'Occupied',
    housekeepingStatus: 'dirty',
    housekeepingStatusLabel: 'Dirty',
    guestName: 'Ada Lovelace',
    checkoutToday: true,
  );

  const pendingRequest = HousekeepingGuestRequest(
    id: 5,
    roomUnitId: 1,
    roomNumber: '204',
    type: 'towels',
    typeLabel: 'Towels',
    status: 'pending',
    isPending: true,
    createdLabel: '10 min ago',
  );

  const completedRequest = HousekeepingGuestRequest(
    id: 6,
    roomUnitId: 1,
    roomNumber: '204',
    type: 'dnd',
    typeLabel: 'Do Not Disturb',
    status: 'completed',
    isPending: false,
  );

  testWidgets('a dirty checkout-today room shows its status, guest and a Mark Preparing action', (tester) async {
    var tappedStatus = '';
    await tester.pumpWidget(
      _host(HousekeepingRoomCard(room: dirtyRoom, onMarkStatus: (s) => tappedStatus = s)),
    );

    expect(find.text('Room 204'), findsOneWidget);
    expect(find.text('Dirty'), findsOneWidget);
    expect(find.text('Checkout today'), findsOneWidget);
    expect(find.text('Ada Lovelace'), findsOneWidget); // now its own prominent line
    expect(find.text('Mark Preparing'), findsOneWidget);

    await tester.tap(find.text('Mark Preparing'));
    await tester.pump();
    expect(tappedStatus, 'preparing');
  });

  testWidgets('a room being prepared offers Mark Clean once the turnover is done', (tester) async {
    const room = HousekeepingRoom(
      id: 2,
      number: '204',
      roomName: 'Brisa Residence',
      occupancy: 'available',
      occupancyLabel: 'Available',
      housekeepingStatus: 'preparing',
      housekeepingStatusLabel: 'Preparing',
    );
    var tappedStatus = '';
    await tester.pumpWidget(
      _host(HousekeepingRoomCard(room: room, onMarkStatus: (s) => tappedStatus = s)),
    );

    expect(find.text('Preparing'), findsOneWidget);
    expect(find.text('Mark Clean'), findsOneWidget);

    await tester.tap(find.text('Mark Clean'));
    await tester.pump();
    expect(tappedStatus, 'clean');
  });

  testWidgets('an out-of-order room offers Mark Fixed instead', (tester) async {
    const room = HousekeepingRoom(
      id: 3,
      number: '410',
      occupancy: 'available',
      occupancyLabel: 'Available',
      housekeepingStatus: 'out_of_order',
      housekeepingStatusLabel: 'Out of Order',
    );
    var tappedStatus = '';
    await tester.pumpWidget(
      _host(HousekeepingRoomCard(room: room, onMarkStatus: (s) => tappedStatus = s)),
    );

    expect(find.text('Mark Fixed'), findsOneWidget);
    await tester.tap(find.text('Mark Fixed'));
    await tester.pump();
    expect(tappedStatus, 'clean');
  });

  testWidgets('a pending request shows a Mark Complete button that reports the tap', (tester) async {
    var completed = false;
    await tester.pumpWidget(
      _host(HousekeepingRequestCard(request: pendingRequest, onComplete: () => completed = true)),
    );

    expect(find.text('Towels'), findsOneWidget);
    expect(find.text('Mark Complete'), findsOneWidget);

    await tester.tap(find.text('Mark Complete'));
    await tester.pump();
    expect(completed, isTrue);
  });

  testWidgets('the overflow menu offers Report Fault when wired up, and reports the tap', (tester) async {
    var faultReported = false;
    await tester.pumpWidget(
      _host(
        HousekeepingRoomCard(
          room: dirtyRoom,
          onMarkStatus: (_) {},
          onReportFault: () => faultReported = true,
        ),
      ),
    );

    await tester.tap(find.byIcon(Icons.more_vert_rounded));
    await tester.pumpAndSettle();
    expect(find.text('Report Fault'), findsOneWidget);

    await tester.tap(find.text('Report Fault'));
    await tester.pumpAndSettle();
    expect(faultReported, isTrue);
  });

  testWidgets('the overflow menu omits Report Fault when it is not wired up', (tester) async {
    await tester.pumpWidget(_host(HousekeepingRoomCard(room: dirtyRoom, onMarkStatus: (_) {})));

    await tester.tap(find.byIcon(Icons.more_vert_rounded));
    await tester.pumpAndSettle();
    expect(find.text('Report Fault'), findsNothing);
  });

  testWidgets('a completed request shows Completed instead of a Mark Complete button', (tester) async {
    await tester.pumpWidget(
      _host(HousekeepingRequestCard(request: completedRequest, onComplete: () {})),
    );

    expect(find.text('Do Not Disturb'), findsOneWidget);
    expect(find.text('Completed'), findsOneWidget);
    expect(find.text('Mark Complete'), findsNothing);
  });

  test('HousekeepingGuestRequest.fromJson parses the requesting guest', () {
    final request = HousekeepingGuestRequest.fromJson({
      'id': 7,
      'room_unit_id': 1,
      'room_number': '204',
      'guest_name': 'Grace Hopper',
      'type': 'towels',
      'type_label': 'Towels',
      'status': 'pending',
      'is_pending': true,
    });

    expect(request.guestName, 'Grace Hopper');
  });

  testWidgets('a request card shows the guest it was raised for', (tester) async {
    const request = HousekeepingGuestRequest(
      id: 8,
      roomUnitId: 1,
      roomNumber: '204',
      roomName: 'Brisa Residence',
      guestName: 'Grace Hopper',
      type: 'towels',
      typeLabel: 'Towels',
      status: 'pending',
      isPending: true,
    );

    await tester.pumpWidget(_host(HousekeepingRequestCard(request: request, onComplete: () {})));

    expect(find.textContaining('Grace Hopper'), findsOneWidget);
  });
}
