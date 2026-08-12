import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_bill.dart';
import 'package:retirodelrocioapp/features/reception/presentation/widgets/reception_widgets.dart';

Widget _host(Widget child) => MaterialApp(
  home: Scaffold(body: SizedBox(width: 520, child: child)),
);

void main() {
  group('domain parsing', () {
    test('ReceptionBillsOverview parses the summary and every row', () {
      final overview = ReceptionBillsOverview.fromJson({
        'summary': {
          'total_outstanding': 26250,
          'total_outstanding_label': 'NGN 26,250',
          'guests_with_balance': 1,
        },
        'bills': [
          {
            'booking_id': 1,
            'guest_name': 'Ada Lovelace',
            'room_label': 'Room 204',
            'check_out_label': 'Jul 14, 2026',
            'total_due': 21250,
            'total_due_label': 'NGN 21,250',
            'has_balance': true,
          },
          {
            'booking_id': 2,
            'guest_name': 'Grace Hopper',
            'room_label': 'Room 101',
            'check_out_label': 'Jul 12, 2026',
            'total_due': 0,
            'total_due_label': 'NGN 0',
            'has_balance': false,
          },
        ],
      });

      expect(overview.totalOutstandingLabel, 'NGN 26,250');
      expect(overview.guestsWithBalance, 1);
      expect(overview.rows, hasLength(2));
      expect(overview.rows.first.guestName, 'Ada Lovelace');
      expect(overview.rows.first.hasBalance, isTrue);
      expect(overview.rows.last.hasBalance, isFalse);
    });

    test('ReceptionBillsOverview.empty has no rows', () {
      expect(ReceptionBillsOverview.empty.rows, isEmpty);
      expect(ReceptionBillsOverview.empty.guestsWithBalance, 0);
    });

    test('ReceptionBillDetail parses reservation, categories and summary', () {
      final detail = ReceptionBillDetail.fromJson({
        'reservation': {
          'guest_name': 'Ada Lovelace',
          'room_name': 'Brisa Residence',
          'unit_label': 'Room 204',
          'check_in_label': 'Jul 9, 2026',
          'check_out_label': 'Jul 14, 2026',
        },
        'categories': [
          {
            'key': 'room',
            'label': 'Room Charges',
            'item_count': 1,
            'amount': 21250,
            'amount_label': 'NGN 21,250',
            'has_charges': true,
            'items': [
              {
                'label': 'Room Rate',
                'sub': 'NGN 4,250 × 5',
                'amount_label': 'NGN 21,250',
              },
            ],
          },
          {
            'key': 'spa',
            'label': 'Spa & Wellness',
            'item_count': 0,
            'amount': 0,
            'amount_label': null,
            'has_charges': false,
            'items': [],
          },
        ],
        'summary': {
          'lines': [
            {'label': 'Room Charges', 'amount_label': 'NGN 21,250'},
          ],
          'total_due': 21250,
          'total_due_label': 'NGN 21,250',
        },
      });

      expect(detail.guestName, 'Ada Lovelace');
      expect(detail.roomName, 'Brisa Residence');
      expect(detail.unitLabel, 'Room 204');
      expect(detail.categories, hasLength(2));
      expect(detail.categories.first.hasCharges, isTrue);
      expect(detail.categories.last.hasCharges, isFalse);
      expect(detail.summaryLines.single.label, 'Room Charges');
      expect(detail.totalDueLabel, 'NGN 21,250');
    });
  });

  const owing = ReceptionBillRow(
    bookingId: 1,
    guestName: 'Ada Lovelace',
    roomLabel: 'Room 204',
    checkOutLabel: 'Jul 14, 2026',
    totalDueLabel: 'NGN 21,250',
    hasBalance: true,
  );

  const settled = ReceptionBillRow(
    bookingId: 2,
    guestName: 'Grace Hopper',
    roomLabel: 'Room 101',
    checkOutLabel: 'Jul 12, 2026',
    totalDueLabel: 'NGN 0',
    hasBalance: false,
  );

  testWidgets('a bill row shows the guest, room and balance, and taps', (
    tester,
  ) async {
    var tapped = false;
    await tester.pumpWidget(
      _host(ReceptionBillRowCard(row: owing, onTap: () => tapped = true)),
    );

    expect(find.text('Ada Lovelace'), findsOneWidget);
    expect(find.text('NGN 21,250'), findsOneWidget);
    expect(find.text('due'), findsOneWidget);

    await tester.tap(find.byType(InkWell));
    await tester.pump();
    expect(tapped, isTrue);
  });

  testWidgets('a settled bill row reads "settled" instead of "due"', (
    tester,
  ) async {
    await tester.pumpWidget(
      _host(ReceptionBillRowCard(row: settled, onTap: () {})),
    );

    expect(find.text('Grace Hopper'), findsOneWidget);
    expect(find.text('settled'), findsOneWidget);
    expect(find.text('due'), findsNothing);
  });
}
