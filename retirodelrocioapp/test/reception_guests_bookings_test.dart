import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_booking_row.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_guest.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_widgets.dart';

Widget _host(Widget child) => MaterialApp(
      home: Scaffold(body: SizedBox(width: 520, child: child)),
    );

void main() {
  group('domain parsing', () {
    test('ReceptionGuestProfile parses stats, preferences and history', () {
      final profile = ReceptionGuestProfile.fromJson({
        'key': 'email:ada@mail.com',
        'name': 'Ada Lovelace',
        'email': 'ada@mail.com',
        'phone': '+44 7700 900111',
        'in_house': true,
        'stats': {
          'total_stays': 3,
          'total_nights': 8,
          'total_spend_label': '₦1,050,000',
          'first_seen_label': 'Jan 2025',
        },
        'preferences': {
          'favourite_room': 'Brisa Residence',
          'usual_party_size': 2,
          'uses_airport_pickup': true,
        },
        'history': [
          {
            'id': 1,
            'reference': 'BK-0001',
            'guest_name': 'Ada Lovelace',
            'room_label': 'Brisa Residence · Room 201',
            'check_in_label': 'Jul 24',
            'check_out_label': 'Jul 26, 2026',
            'nights': 2,
            'amount_label': '₦300,000',
            'status': 'checked_in',
            'status_label': 'Checked In',
          },
        ],
      });

      expect(profile.name, 'Ada Lovelace');
      expect(profile.inHouse, isTrue);
      expect(profile.stats.totalStays, 3);
      expect(profile.preferences.favouriteRoom, 'Brisa Residence');
      expect(profile.preferences.usualPartySize, 2);
      expect(profile.history.single.reference, 'BK-0001');
      expect(profile.initials, 'AL');
    });

    test('ReceptionGuestSummary tolerates missing contact fields', () {
      final g = ReceptionGuestSummary.fromJson({'key': 'name:jo', 'name': 'Jo'});
      expect(g.stays, 0);
      expect(g.inHouse, isFalse);
      expect(g.email, isNull);
    });
  });

  const guest = ReceptionGuestSummary(
    key: 'email:ada@mail.com',
    name: 'Ada Lovelace',
    email: 'ada@mail.com',
    phone: '+44 7700 900111',
    stays: 3,
    inHouse: true,
  );

  testWidgets('a guest card shows name, stays and the in-house tag and taps', (tester) async {
    var tapped = false;
    await tester.pumpWidget(_host(ReceptionGuestCard(guest: guest, onTap: () => tapped = true)));

    expect(find.text('Ada Lovelace'), findsOneWidget);
    expect(find.text('3'), findsOneWidget);
    expect(find.text('stays'), findsOneWidget);
    expect(find.text('In-House'), findsOneWidget);

    await tester.tap(find.byType(InkWell));
    await tester.pump();
    expect(tapped, isTrue);
  });

  testWidgets('a booking row shows the reference, status pill and stay span', (tester) async {
    const row = ReceptionBookingRow(
      id: 1,
      reference: 'BK-0137',
      guestName: 'Ada Lovelace',
      roomLabel: 'Brisa Residence · Room 201',
      checkInLabel: 'Jul 24',
      checkOutLabel: 'Jul 26, 2026',
      nights: 2,
      amountLabel: '₦300,000',
      status: 'checked_in',
      statusLabel: 'Checked In',
    );

    await tester.pumpWidget(_host(const ReceptionBookingRowCard(row: row)));

    expect(find.text('BK-0137'), findsOneWidget);
    expect(find.text('Checked In'), findsOneWidget);
    expect(find.text('₦300,000'), findsOneWidget);
    expect(find.textContaining('Jul 24 → Jul 26, 2026'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  test('status colours map to the right accent', () {
    expect(receptionStatusColor('paid'), kReceptionGreen);
    expect(receptionStatusColor('checked_in'), kReceptionBlue);
    expect(receptionStatusColor('cancelled'), kReceptionRed);
  });
}
