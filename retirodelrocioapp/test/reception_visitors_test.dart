import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_visitor.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_widgets.dart';

Widget _host(Widget child) => MaterialApp(
      home: Scaffold(body: SizedBox(width: 520, child: child)),
    );

void main() {
  group('domain parsing', () {
    test('ReceptionVisitorsOverview parses the summary and every visitor', () {
      final overview = ReceptionVisitorsOverview.fromJson({
        'summary': {'expected': 1, 'inside': 1, 'today': 2},
        'visitors': [
          {
            'id': 1,
            'reference': 'VP-2608-001',
            'visitor_name': 'Michael Brown',
            'initials': 'MB',
            'host_name': 'Daniel Ubani',
            'room_number': '101',
            'suite_name': 'Alba Suite',
            'email': 'm.brown@mail.com',
            'phone': '+44 7700 900111',
            'status': 'pending',
            'status_label': 'Pending',
            'invited_label': 'Aug 1, 9:00AM',
            'arrival_label': null,
            'is_inside': false,
          },
          {
            'id': 2,
            'reference': 'VP-2608-002',
            'visitor_name': 'Sarah Connor',
            'initials': 'SC',
            'host_name': 'John Doe',
            'room_number': '202',
            'suite_name': null,
            'email': null,
            'phone': null,
            'status': 'inside',
            'status_label': 'Inside',
            'invited_label': 'Jul 31, 3:00PM',
            'arrival_label': '4:10PM',
            'is_inside': true,
          },
        ],
      });

      expect(overview.expected, 1);
      expect(overview.inside, 1);
      expect(overview.today, 2);
      expect(overview.visitors, hasLength(2));
      expect(overview.visitors.first.visitorName, 'Michael Brown');
      expect(overview.visitors.first.status, 'pending');
      expect(overview.visitors.last.isInside, isTrue);
      expect(overview.visitors.last.arrivalLabel, '4:10PM');
    });

    test('ReceptionVisitorsOverview.empty has no visitors', () {
      expect(ReceptionVisitorsOverview.empty.visitors, isEmpty);
      expect(ReceptionVisitorsOverview.empty.expected, 0);
    });
  });

  const expected = ReceptionVisitor(
    id: 1,
    reference: 'VP-2608-001',
    visitorName: 'Michael Brown',
    initials: 'MB',
    hostName: 'Daniel Ubani',
    roomNumber: '101',
    suiteName: 'Alba Suite',
    email: 'm.brown@mail.com',
    phone: '+44 7700 900111',
    status: 'pending',
    statusLabel: 'Pending',
    invitedLabel: 'Aug 1, 9:00AM',
    arrivalLabel: null,
    isInside: false,
  );

  const arrived = ReceptionVisitor(
    id: 2,
    reference: 'VP-2608-002',
    visitorName: 'Sarah Connor',
    initials: 'SC',
    hostName: 'John Doe',
    roomNumber: '202',
    suiteName: null,
    email: null,
    phone: null,
    status: 'inside',
    statusLabel: 'Inside',
    invitedLabel: 'Jul 31, 3:00PM',
    arrivalLabel: '4:10PM',
    isInside: true,
  );

  testWidgets('an expected visitor row shows the host, room and Pending status', (tester) async {
    await tester.pumpWidget(_host(ReceptionVisitorRowCard(visitor: expected)));

    expect(find.text('Michael Brown'), findsOneWidget);
    expect(find.text('Visiting Daniel Ubani  ·  Alba Suite'), findsOneWidget);
    expect(find.text('Pending'), findsOneWidget);
    expect(find.text('Invited Aug 1, 9:00AM'), findsOneWidget);
  });

  testWidgets('an arrived visitor row shows the Inside status and arrival time', (tester) async {
    await tester.pumpWidget(_host(ReceptionVisitorRowCard(visitor: arrived)));

    expect(find.text('Sarah Connor'), findsOneWidget);
    expect(find.text('Visiting John Doe  ·  Room 202'), findsOneWidget);
    expect(find.text('Inside'), findsOneWidget);
    expect(find.text('Arrived 4:10PM'), findsOneWidget);
  });
}
