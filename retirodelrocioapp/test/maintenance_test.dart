import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/maintenance_overview.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';
import 'package:retirodelrocioapp/features/maintenance/presentation/widgets/maintenance_widgets.dart';

Widget _host(Widget child) => MaterialApp(
  home: Scaffold(body: SizedBox(width: 420, child: child)),
);

void main() {
  group('domain parsing', () {
    test('MaintenanceOverview parses stats and work orders', () {
      final overview = MaintenanceOverview.fromJson({
        'stats': {'new': 2, 'in_progress': 1, 'urgent': 1, 'completed_today': 3},
        'work_orders': [
          {
            'id': 1,
            'title': 'Water leak',
            'description': 'Bathroom ceiling dripping',
            'room_number': '204',
            'asset_label': null,
            'location_label': 'Room 204',
            'priority': 'urgent',
            'priority_label': 'Urgent',
            'status': 'new',
            'status_label': 'New',
            'reported_by': 'Front desk',
            'assigned_to_name': null,
            'created_label': '5 min ago',
          },
        ],
      });

      expect(overview.newCount, 2);
      expect(overview.urgent, 1);
      expect(overview.workOrders, hasLength(1));
      expect(overview.workOrders.first.title, 'Water leak');
      expect(overview.workOrders.first.isNew, isTrue);
    });

    test('MaintenanceOverview.empty has nothing', () {
      expect(MaintenanceOverview.empty.workOrders, isEmpty);
      expect(MaintenanceOverview.empty.newCount, 0);
    });

    test('WorkOrder status flags reflect the lifecycle stage', () {
      final accepted = WorkOrder.fromJson({
        'id': 2,
        'title': 'AC repair',
        'location_label': 'Room 101',
        'priority': 'high',
        'priority_label': 'High',
        'status': 'accepted',
        'status_label': 'Accepted',
      });

      expect(accepted.isNew, isFalse);
      expect(accepted.isAccepted, isTrue);
      expect(accepted.isInProgress, isFalse);
      expect(accepted.isDone, isFalse);
    });

    test('MaintenanceRoomOption parses the picker shape', () {
      final room = MaintenanceRoomOption.fromJson({'id': 3, 'number': '305', 'room_name': 'Alba Suite'});
      expect(room.number, '305');
      expect(room.roomName, 'Alba Suite');
    });
  });

  const newOrder = WorkOrder(
    id: 1,
    title: 'Water leak',
    description: 'Bathroom ceiling dripping',
    roomNumber: '204',
    locationLabel: 'Room 204',
    priority: 'urgent',
    priorityLabel: 'Urgent',
    status: 'new',
    statusLabel: 'New',
    createdLabel: '5 min ago',
  );

  const doneOrder = WorkOrder(
    id: 2,
    title: 'Broken lamp',
    locationLabel: 'Room 101',
    priority: 'low',
    priorityLabel: 'Low',
    status: 'done',
    statusLabel: 'Done',
  );

  testWidgets('a new urgent order shows an Accept button and reports the tap', (tester) async {
    var accepted = false;
    await tester.pumpWidget(
      _host(
        WorkOrderCard(
          order: newOrder,
          onAccept: () => accepted = true,
          onStart: () {},
          onComplete: () {},
        ),
      ),
    );

    expect(find.text('Water leak'), findsOneWidget);
    expect(find.text('Urgent'), findsOneWidget);
    expect(find.text('Accept'), findsOneWidget);

    await tester.tap(find.text('Accept'));
    await tester.pump();
    expect(accepted, isTrue);
  });

  testWidgets('a done order shows Done instead of an action button', (tester) async {
    await tester.pumpWidget(
      _host(
        WorkOrderCard(order: doneOrder, onAccept: () {}, onStart: () {}, onComplete: () {}),
      ),
    );

    expect(find.text('Broken lamp'), findsOneWidget);
    expect(find.text('Done'), findsWidgets); // status pill + action label
    expect(find.text('Accept'), findsNothing);
    expect(find.text('Start'), findsNothing);
    expect(find.text('Complete'), findsNothing);
  });

  testWidgets('a busy order shows a spinner instead of an action button', (tester) async {
    await tester.pumpWidget(
      _host(
        WorkOrderCard(order: newOrder, onAccept: () {}, onStart: () {}, onComplete: () {}, busy: true),
      ),
    );

    expect(find.text('Accept'), findsNothing);
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
  });
}
