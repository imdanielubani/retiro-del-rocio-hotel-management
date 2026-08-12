import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/housekeeping/lost_found/domain/lost_found_item.dart';
import 'package:retirodelrocioapp/features/housekeeping/lost_found/presentation/widgets/lost_found_item_card.dart';

Widget _host(Widget child) => MaterialApp(
  home: Scaffold(body: SizedBox(width: 420, child: child)),
);

void main() {
  group('domain parsing', () {
    test('LostFoundItem.fromJson parses a logged item', () {
      final item = LostFoundItem.fromJson({
        'id': 1,
        'room_unit_id': 3,
        'room_number': '204',
        'room_name': 'Brisa Residence',
        'item_description': 'Blue phone charger',
        'notes': 'Found under the bed',
        'found_by_name': 'Ada Lovelace',
        'found_label': '10 min ago',
        'status': 'unclaimed',
        'status_label': 'Unclaimed',
        'is_unclaimed': true,
      });

      expect(item.itemDescription, 'Blue phone charger');
      expect(item.roomNumber, '204');
      expect(item.foundByName, 'Ada Lovelace');
      expect(item.isUnclaimed, isTrue);
    });

    test('LostFoundItem.fromJson defaults missing fields sensibly', () {
      final item = LostFoundItem.fromJson({'id': 2});

      expect(item.itemDescription, '');
      expect(item.status, 'unclaimed');
      expect(item.isUnclaimed, isTrue);
      expect(item.roomNumber, isNull);
    });
  });

  const unclaimedItem = LostFoundItem(
    id: 1,
    roomUnitId: 3,
    roomNumber: '204',
    roomName: 'Brisa Residence',
    itemDescription: 'Blue phone charger',
    foundByName: 'Ada Lovelace',
    foundLabel: '10 min ago',
    status: 'unclaimed',
    statusLabel: 'Unclaimed',
    isUnclaimed: true,
  );

  const returnedItem = LostFoundItem(
    id: 2,
    itemDescription: 'Sunglasses',
    status: 'returned',
    statusLabel: 'Returned',
    isUnclaimed: false,
    claimantName: 'Grace Hopper',
  );

  const disposedItem = LostFoundItem(
    id: 3,
    itemDescription: 'Odd sock',
    status: 'disposed',
    statusLabel: 'Disposed',
    isUnclaimed: false,
  );

  testWidgets(
    'an unclaimed item shows a Mark Returned button that reports the tap',
    (tester) async {
      var returnedTapped = false;
      await tester.pumpWidget(
        _host(
          LostFoundItemCard(
            item: unclaimedItem,
            onMarkReturned: () => returnedTapped = true,
            onMarkDisposed: () {},
          ),
        ),
      );

      expect(find.text('Blue phone charger'), findsOneWidget);
      expect(find.textContaining('Room 204'), findsOneWidget);
      expect(find.textContaining('Found by Ada Lovelace'), findsOneWidget);
      expect(find.text('Mark Returned'), findsOneWidget);

      await tester.tap(find.text('Mark Returned'));
      await tester.pump();
      expect(returnedTapped, isTrue);
    },
  );

  testWidgets(
    'a returned item shows who it was returned to, not the action button',
    (tester) async {
      await tester.pumpWidget(
        _host(
          LostFoundItemCard(
            item: returnedItem,
            onMarkReturned: () {},
            onMarkDisposed: () {},
          ),
        ),
      );

      expect(find.text('Returned to Grace Hopper'), findsOneWidget);
      expect(find.text('Mark Returned'), findsNothing);
    },
  );

  testWidgets('a disposed item shows a Disposed pill, not the action button', (
    tester,
  ) async {
    await tester.pumpWidget(
      _host(
        LostFoundItemCard(
          item: disposedItem,
          onMarkReturned: () {},
          onMarkDisposed: () {},
        ),
      ),
    );

    expect(find.text('Disposed'), findsOneWidget);
    expect(find.text('Mark Returned'), findsNothing);
  });
}
