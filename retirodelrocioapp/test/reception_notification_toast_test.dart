import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/core/navigation/root_messenger.dart';
import 'package:retirodelrocioapp/features/reception/notifications/domain/reception_notification.dart';
import 'package:retirodelrocioapp/features/reception/notifications/presentation/widgets/reception_notification_toast.dart';

/// A new front-desk notification surfaces as a lightweight toast — same
/// pattern as the guest tablet's, deliberately NOT the blocking SOS overlay,
/// since a paid extension or a new reservation is not an emergency.
void main() {
  Future<void> pumpApp(WidgetTester tester) async {
    await tester.pumpWidget(
      MaterialApp(
        scaffoldMessengerKey: rootScaffoldMessengerKey,
        home: const Scaffold(body: SizedBox()),
      ),
    );
  }

  testWidgets('does nothing when no ScaffoldMessenger is mounted yet', (
    tester,
  ) async {
    expect(
      () => showReceptionNotificationToast(
        ReceptionNotification(
          id: 1,
          category: ReceptionNotificationCategory.message,
          title: 'Hello',
          message: 'World',
          time: DateTime.now(),
        ),
      ),
      returnsNormally,
    );
  });

  testWidgets(
    'shows a dismissible toast with the notification title and message',
    (tester) async {
      await pumpApp(tester);

      showReceptionNotificationToast(
        ReceptionNotification(
          id: 1,
          category: ReceptionNotificationCategory.payment,
          title: 'Stay Extension Paid',
          message: 'Daniel Ubani in Room 101 extended their stay.',
          time: DateTime.now(),
        ),
      );
      await tester.pump();

      expect(find.text('Stay Extension Paid'), findsOneWidget);
      expect(
        find.text('Daniel Ubani in Room 101 extended their stay.'),
        findsOneWidget,
      );
      // A SnackBar, not a full-screen blocking overlay.
      expect(find.byType(SnackBar), findsOneWidget);
      expect(find.byType(Dialog), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('a second notification replaces the first rather than stacking', (
    tester,
  ) async {
    await pumpApp(tester);

    showReceptionNotificationToast(
      ReceptionNotification(
        id: 1,
        category: ReceptionNotificationCategory.booking,
        title: 'New Reservation',
        message: 'A new booking just came in.',
        time: DateTime.now(),
      ),
    );
    await tester.pump();

    showReceptionNotificationToast(
      ReceptionNotification(
        id: 2,
        category: ReceptionNotificationCategory.payment,
        title: 'Stay Extension Paid',
        message: 'A guest extended their stay.',
        time: DateTime.now(),
      ),
    );
    await tester.pump();

    expect(find.byType(SnackBar), findsOneWidget);
    expect(find.text('Stay Extension Paid'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
