import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_overview.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_widgets.dart';

/// The reception dashboard's data model and cards.
Widget _host(Widget child) => MaterialApp(
  home: Scaffold(body: SizedBox(width: 380, child: child)),
);

void main() {
  group('ReceptionOverview.fromJson', () {
    test('parses counters, lists, alerts and room status', () {
      final overview = ReceptionOverview.fromJson({
        'receptionist': {'name': 'Daniel Ubani', 'role': 'Reception'},
        'stats': {
          'arrivals_today': 2,
          'check_ins_today': 0,
          'check_outs_today': 1,
          'visitor_pass_check_ins': 4,
        },
        'arrivals': [
          {
            'id': 137,
            'reference': 'BK-0137',
            'guest_name': 'Daniel Ubani',
            'room_label': 'Brisa Residence · Room 201',
            'date_label': 'Jul 24, 2026',
            'status': 'paid',
            'is_walk_in': true,
            'origin_label': 'Walk-in',
          },
        ],
        'departures': [],
        'alerts': [
          {
            'id': 1,
            'title': 'Emergency SOS — Room 102',
            'time_label': '5m ago',
            'severity': 'high',
          },
        ],
        'incidents': [
          {
            'id': 1,
            'case_no': 'SOS-2607-001',
            'status': 'active',
            'room_number': '102',
            'suite_name': 'Brisa Residence',
            'guest_name': 'Ada Lovelace',
            'raised_at': '2026-07-25T09:00:00Z',
          },
        ],
        'room_status': {'occupied': 5, 'dirty': 1, 'maintenance': 2},
      });

      expect(overview.receptionistName, 'Daniel Ubani');
      expect(overview.arrivalsToday, 2);
      expect(overview.checkOutsToday, 1);
      expect(overview.visitorPassCheckIns, 4);
      expect(overview.arrivals.single.guestName, 'Daniel Ubani');
      expect(overview.arrivals.single.reference, 'BK-0137');
      expect(overview.arrivals.single.isWalkIn, isTrue);
      expect(overview.arrivals.single.originLabel, 'Walk-in');
      expect(overview.departures, isEmpty);
      expect(overview.alerts.single.severity, AlertSeverity.high);
      // The open SOS incident is parsed in full incident shape for the overlay.
      expect(overview.incidents.single.roomNumber, '102');
      expect(overview.incidents.single.isActive, isTrue);
      expect(overview.incidents.single.caseNo, 'SOS-2607-001');
      expect(overview.roomStatus.occupied, 5);
      expect(overview.roomStatus.maintenance, 2);
    });

    test('a malformed payload degrades to the empty overview shape', () {
      final overview = ReceptionOverview.fromJson(const {});
      expect(overview.arrivals, isEmpty);
      expect(overview.incidents, isEmpty);
      expect(overview.arrivalsToday, 0);
      expect(overview.roomStatus.occupied, 0);
    });
  });

  const arrival = ReceptionBooking(
    id: 137,
    reference: 'BK-0137',
    guestName: 'Daniel Ubani',
    roomLabel: 'Brisa Residence · Room 201',
    dateLabel: 'Jul 24, 2026',
    status: 'paid',
  );

  group('ReceptionBookingCard', () {
    testWidgets('renders the guest, room, date and reference', (tester) async {
      await tester.pumpWidget(
        _host(
          ReceptionBookingCard(
            booking: arrival,
            busy: false,
            onCheckIn: () {},
            onCheckOut: () {},
          ),
        ),
      );

      expect(find.text('Daniel Ubani'), findsOneWidget);
      expect(find.text('Brisa Residence · Room 201'), findsOneWidget);
      expect(find.text('Jul 24, 2026'), findsOneWidget);
      expect(find.text('BK-0137'), findsOneWidget);
      // A guest yet to arrive (paid) shows Check In, like admin.
      expect(find.text('Check In'), findsOneWidget);
    });

    testWidgets('a paid guest checks in', (tester) async {
      var checkedIn = false;
      await tester.pumpWidget(
        _host(
          ReceptionBookingCard(
            booking: arrival, // paid
            busy: false,
            onCheckIn: () => checkedIn = true,
            onCheckOut: () {},
          ),
        ),
      );

      await tester.tap(find.text('Check In'));
      await tester.pump();
      expect(checkedIn, isTrue);
    });

    testWidgets('an in-house guest shows Check Out and checks out', (
      tester,
    ) async {
      var checkedOut = false;
      await tester.pumpWidget(
        _host(
          ReceptionBookingCard(
            booking: const ReceptionBooking(
              id: 137,
              reference: 'BK-0137',
              guestName: 'Already In',
              roomLabel: 'Brisa Residence · Room 201',
              dateLabel: 'Jul 24, 2026',
              status: 'checked_in',
              statusLabel: 'Checked In',
            ),
            busy: false,
            onCheckIn: () {},
            onCheckOut: () => checkedOut = true,
          ),
        ),
      );

      // The same guest, now in-house, offers Check Out — reception drives both.
      expect(find.text('Check Out'), findsOneWidget);
      expect(find.text('Check In'), findsNothing);
      await tester.tap(find.text('Check Out'));
      await tester.pump();
      expect(checkedOut, isTrue);
    });

    testWidgets('a departed guest shows a terminal status, no action', (
      tester,
    ) async {
      await tester.pumpWidget(
        _host(
          ReceptionBookingCard(
            booking: const ReceptionBooking(
              id: 137,
              reference: 'BK-0137',
              guestName: 'Gone Home',
              roomLabel: 'Brisa Residence · Room 201',
              dateLabel: 'Jul 24, 2026',
              status: 'checked_out',
              statusLabel: 'Checked Out',
            ),
            busy: false,
            onCheckIn: () {},
            onCheckOut: () {},
          ),
        ),
      );

      expect(find.text('Checked Out'), findsOneWidget);
      expect(find.text('Check In'), findsNothing);
      expect(find.text('Check Out'), findsNothing);
    });

    testWidgets('a busy card shows a spinner and ignores taps', (tester) async {
      var tapped = false;
      await tester.pumpWidget(
        _host(
          ReceptionBookingCard(
            booking: arrival,
            busy: true,
            onCheckIn: () => tapped = true,
            onCheckOut: () {},
          ),
        ),
      );

      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(find.text('Check In'), findsNothing);
      await tester.tap(find.byType(InkWell));
      await tester.pump();
      expect(tapped, isFalse);
    });
  });

  testWidgets('room-status tiles show all three counts', (tester) async {
    await tester.pumpWidget(
      _host(
        const ReceptionRoomStatusTiles(
          status: ReceptionRoomStatus(occupied: 5, dirty: 1, maintenance: 2),
        ),
      ),
    );

    expect(find.text('Occupied'), findsOneWidget);
    expect(find.text('Dirty'), findsOneWidget);
    expect(find.text('Maint.'), findsOneWidget);
    expect(find.text('5'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('the arrivals empty state prompts for a room number', (
    tester,
  ) async {
    await tester.pumpWidget(_host(const ReceptionArrivalsEmpty()));
    expect(find.textContaining('Type a room number'), findsOneWidget);
  });
}
