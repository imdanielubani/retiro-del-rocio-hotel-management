import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/core/navigation/root_messenger.dart';
import 'package:retirodelrocioapp/features/guest/notifications/domain/guest_notification.dart';
import 'package:retirodelrocioapp/features/guest/notifications/presentation/widgets/guest_notification_toast.dart';

/// A new guest notification surfaces as a lightweight toast — deliberately
/// NOT the blocking SOS overlay, since a hotel update is not an emergency.
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
    // No pumpApp — the global key's currentState is null. Must not throw.
    expect(
      () => showGuestNotificationToast(
        GuestNotification(
          id: 1,
          category: NotificationCategory.message,
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

    showGuestNotificationToast(
      GuestNotification(
        id: 1,
        category: NotificationCategory.payment,
        title: 'Stay Extended',
        message: 'Your stay has been extended to July 30, 2026.',
        time: DateTime.now(),
      ),
    );
    await tester.pump();

    expect(find.text('Stay Extended'), findsOneWidget);
    expect(
      find.text('Your stay has been extended to July 30, 2026.'),
      findsOneWidget,
    );
    // A SnackBar, not a full-screen blocking overlay.
    expect(find.byType(SnackBar), findsOneWidget);
    expect(find.byType(Dialog), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('a second notification replaces the first rather than stacking', (
    tester,
  ) async {
    await pumpApp(tester);

    showGuestNotificationToast(
      GuestNotification(
        id: 1,
        category: NotificationCategory.spa,
        title: 'Spa Appointment',
        message: 'Reminder: your spa appointment is in 30 minutes.',
        time: DateTime.now(),
      ),
    );
    await tester.pump();

    showGuestNotificationToast(
      GuestNotification(
        id: 2,
        category: NotificationCategory.payment,
        title: 'Stay Extended',
        message: 'Your stay has been extended.',
        time: DateTime.now(),
      ),
    );
    await tester.pump();

    expect(find.byType(SnackBar), findsOneWidget);
    expect(find.text('Stay Extended'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
