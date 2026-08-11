import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/asset.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/maintenance_overview.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/parts_request.dart';
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

    test('WorkOrder.fromJson parses SLA breach and asset category', () {
      final order = WorkOrder.fromJson({
        'id': 4,
        'title': 'Water leak',
        'asset_label': 'AC Unit',
        'asset_category': 'HVAC',
        'location_label': 'Room 204',
        'priority': 'urgent',
        'priority_label': 'Urgent',
        'status': 'new',
        'status_label': 'New',
        'sla_breached': true,
      });

      expect(order.slaBreached, isTrue);
      expect(order.assetCategory, 'HVAC');
    });

    test('WorkOrder.fromJson defaults sla_breached to false when absent', () {
      final order = WorkOrder.fromJson({
        'id': 5,
        'title': 'Broken lamp',
        'location_label': 'Hotel-wide',
        'priority': 'low',
        'priority_label': 'Low',
        'status': 'done',
        'status_label': 'Done',
      });

      expect(order.slaBreached, isFalse);
    });

    test('Asset.fromJson parses PM schedule fields', () {
      final asset = Asset.fromJson({
        'id': 9,
        'name': 'Lobby Generator',
        'category': 'Electrical',
        'location_label': 'Hotel-wide',
        'service_interval_days': 30,
        'last_serviced_label': '5 days ago',
        'is_due_for_service': true,
      });

      expect(asset.isOnScheduledMaintenance, isTrue);
      expect(asset.isDueForService, isTrue);
      expect(asset.category, 'Electrical');
    });

    test('an asset with no service interval is not on a schedule', () {
      final asset = Asset.fromJson({
        'id': 10,
        'name': 'Lobby Chandelier',
        'location_label': 'Hotel-wide',
        'is_due_for_service': false,
      });

      expect(asset.isOnScheduledMaintenance, isFalse);
    });

    test('PartsRequest.fromJson parses status and location', () {
      final request = PartsRequest.fromJson({
        'id': 3,
        'work_order_id': 1,
        'work_order_title': 'Water leak',
        'location_label': 'Room 204',
        'part_name': 'Compressor capacitor',
        'quantity': 2,
        'status': 'pending',
        'status_label': 'Pending',
      });

      expect(request.isPending, isTrue);
      expect(request.quantity, 2);
      expect(request.partName, 'Compressor capacitor');
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

  const urgentBreachedOrder = WorkOrder(
    id: 3,
    title: 'Water leak',
    locationLabel: 'Room 204',
    priority: 'urgent',
    priorityLabel: 'Urgent',
    status: 'new',
    statusLabel: 'New',
    slaBreached: true,
  );

  testWidgets('a work order card shows an SLA Breached pill when the order is overdue', (tester) async {
    await tester.pumpWidget(
      _host(WorkOrderCard(order: urgentBreachedOrder, onAccept: () {}, onStart: () {}, onComplete: () {})),
    );

    expect(find.text('SLA Breached'), findsOneWidget);
  });

  testWidgets('a work order card with no breach shows no SLA pill', (tester) async {
    await tester.pumpWidget(
      _host(WorkOrderCard(order: newOrder, onAccept: () {}, onStart: () {}, onComplete: () {})),
    );

    expect(find.text('SLA Breached'), findsNothing);
  });

  testWidgets('tapping a work order card reports the tap via onTap', (tester) async {
    var tapped = false;
    await tester.pumpWidget(
      _host(
        WorkOrderCard(order: newOrder, onAccept: () {}, onStart: () {}, onComplete: () {}, onTap: () => tapped = true),
      ),
    );

    await tester.tap(find.text('Water leak'));
    await tester.pump();
    expect(tapped, isTrue);
  });

  testWidgets('tapping the Accept button reports its own tap, not the card tap', (tester) async {
    var accepted = false;
    var cardTapped = false;
    await tester.pumpWidget(
      _host(
        WorkOrderCard(
          order: newOrder,
          onAccept: () => accepted = true,
          onStart: () {},
          onComplete: () {},
          onTap: () => cardTapped = true,
        ),
      ),
    );

    await tester.tap(find.text('Accept'));
    await tester.pump();
    expect(accepted, isTrue);
    expect(cardTapped, isFalse);
  });

  const dueAsset = Asset(
    id: 1,
    name: 'Lobby Generator',
    category: 'Electrical',
    locationLabel: 'Hotel-wide',
    serviceIntervalDays: 30,
    isDueForService: true,
  );

  testWidgets('an asset card flags service due and reports its tap', (tester) async {
    var tapped = false;
    await tester.pumpWidget(_host(AssetCard(asset: dueAsset, onTap: () => tapped = true)));

    expect(find.text('Lobby Generator'), findsOneWidget);
    expect(find.text('Service Due'), findsOneWidget);

    await tester.tap(find.byType(AssetCard));
    await tester.pump();
    expect(tapped, isTrue);
  });

  const pendingRequest = PartsRequest(
    id: 1,
    workOrderId: 1,
    workOrderTitle: 'Water leak',
    locationLabel: 'Room 204',
    partName: 'Compressor capacitor',
    quantity: 2,
    status: 'pending',
    statusLabel: 'Pending',
  );

  testWidgets('a pending parts request offers Fulfil and Deny, and reports each tap', (tester) async {
    var fulfilled = false;
    var denied = false;
    await tester.pumpWidget(
      _host(
        PartsRequestCard(request: pendingRequest, onFulfill: () => fulfilled = true, onDeny: () => denied = true),
      ),
    );

    expect(find.text('2 × Compressor capacitor'), findsOneWidget);
    expect(find.text('Fulfill'), findsOneWidget);
    expect(find.text('Deny'), findsOneWidget);

    await tester.tap(find.text('Fulfill'));
    await tester.pump();
    expect(fulfilled, isTrue);
    expect(denied, isFalse);
  });

  testWidgets('a fulfilled parts request offers no actions', (tester) async {
    const fulfilledRequest = PartsRequest(
      id: 2,
      workOrderId: 1,
      partName: 'Filter',
      quantity: 1,
      status: 'fulfilled',
      statusLabel: 'Fulfilled',
    );

    await tester.pumpWidget(_host(PartsRequestCard(request: fulfilledRequest, onFulfill: () {}, onDeny: () {})));

    expect(find.text('Fulfilled'), findsOneWidget);
    expect(find.text('Fulfill'), findsNothing);
    expect(find.text('Deny'), findsNothing);
  });
}
