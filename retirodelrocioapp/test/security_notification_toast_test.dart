import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/core/navigation/root_messenger.dart';
import 'package:retirodelrocioapp/features/security/notifications/domain/security_notification.dart';
import 'package:retirodelrocioapp/features/security/notifications/presentation/widgets/security_notification_toast.dart';

/// A new security notification surfaces as a lightweight toast — same
/// pattern as the reception tablet's, deliberately NOT the blocking SOS
/// overlay, since a guest inviting a visitor is not an emergency.
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
      () => showSecurityNotificationToast(
        SecurityNotification(
          id: 1,
          category: SecurityNotificationCategory.message,
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

    showSecurityNotificationToast(
      SecurityNotification(
        id: 1,
        category: SecurityNotificationCategory.guest,
        title: 'New Visitor Invited',
        message: 'Daniel Ubani in Room 101 invited Michael Brown.',
        time: DateTime.now(),
      ),
    );
    await tester.pump();

    expect(find.text('New Visitor Invited'), findsOneWidget);
    expect(
      find.text('Daniel Ubani in Room 101 invited Michael Brown.'),
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

    showSecurityNotificationToast(
      SecurityNotification(
        id: 1,
        category: SecurityNotificationCategory.guest,
        title: 'New Visitor Invited',
        message: 'A visitor was just invited.',
        time: DateTime.now(),
      ),
    );
    await tester.pump();

    showSecurityNotificationToast(
      SecurityNotification(
        id: 2,
        category: SecurityNotificationCategory.guest,
        title: 'Another Visitor Invited',
        message: 'Another visitor was just invited.',
        time: DateTime.now(),
      ),
    );
    await tester.pump();

    expect(find.byType(SnackBar), findsOneWidget);
    expect(find.text('Another Visitor Invited'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
