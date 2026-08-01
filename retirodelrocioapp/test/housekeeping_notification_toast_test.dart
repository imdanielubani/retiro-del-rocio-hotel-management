import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/core/navigation/root_messenger.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/domain/housekeeping_notification.dart';
import 'package:retirodelrocioapp/features/housekeeping/notifications/presentation/widgets/housekeeping_notification_toast.dart';

/// A new housekeeping notification surfaces as a lightweight toast — same
/// pattern as the reception/security tablets', deliberately NOT the blocking
/// SOS overlay, since a guest asking for towels is not an emergency.
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
      () => showHousekeepingNotificationToast(
        HousekeepingNotification(
          id: 1,
          category: HousekeepingNotificationCategory.message,
          title: 'Hello',
          message: 'World',
          time: DateTime.now(),
        ),
      ),
      returnsNormally,
    );
  });

  testWidgets('shows a dismissible toast with the notification title and message', (
    tester,
  ) async {
    await pumpApp(tester);

    showHousekeepingNotificationToast(
      HousekeepingNotification(
        id: 1,
        category: HousekeepingNotificationCategory.guest,
        title: 'New Housekeeping Request',
        message: 'Daniel Ubani in Room 101 requested Towels.',
        time: DateTime.now(),
      ),
    );
    await tester.pump();

    expect(find.text('New Housekeeping Request'), findsOneWidget);
    expect(
      find.text('Daniel Ubani in Room 101 requested Towels.'),
      findsOneWidget,
    );
    expect(find.byType(SnackBar), findsOneWidget);
    expect(find.byType(Dialog), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('a second notification replaces the first rather than stacking', (
    tester,
  ) async {
    await pumpApp(tester);

    showHousekeepingNotificationToast(
      HousekeepingNotification(
        id: 1,
        category: HousekeepingNotificationCategory.guest,
        title: 'New Housekeeping Request',
        message: 'A request was just raised.',
        time: DateTime.now(),
      ),
    );
    await tester.pump();

    showHousekeepingNotificationToast(
      HousekeepingNotification(
        id: 2,
        category: HousekeepingNotificationCategory.guest,
        title: 'Another Request',
        message: 'Another request was just raised.',
        time: DateTime.now(),
      ),
    );
    await tester.pump();

    expect(find.byType(SnackBar), findsOneWidget);
    expect(find.text('Another Request'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
