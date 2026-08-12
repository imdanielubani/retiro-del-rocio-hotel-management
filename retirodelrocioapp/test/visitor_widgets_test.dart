import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/security/domain/security_visitor.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/visitor_widgets.dart';

/// The security dashboard's two visitor lists (Figma 257:1133).
///
/// Both columns carry pending people as well as arrived ones, so the state
/// labels are the whole point of the rows: a visitor with no chip and no
/// presence pill reads as a rendering fault rather than as "not here yet".
Widget _host(Widget child) => MaterialApp(
  home: Scaffold(body: SizedBox(width: 420, child: child)),
);

void main() {
  group('VisitorRow (Visitors Today)', () {
    testWidgets('a visitor who has not arrived shows Pending / Not Inside', (
      tester,
    ) async {
      await tester.pumpWidget(
        _host(
          const VisitorRow(
            visitor: SecurityVisitor(
              id: 1,
              name: 'Sonia Ubani',
              reference: 'VP-2607-011',
              suiteName: 'Alba Suite',
              roomNumber: '101',
              arrivalLabel: '02:00PM',
              isInside: false,
              isVerified: false,
            ),
          ),
        ),
      );

      expect(find.text('Pending'), findsOneWidget);
      expect(find.text('Not Inside'), findsOneWidget);
      expect(find.text('VP-2607-011'), findsOneWidget);
      expect(find.text('✓ Verified'), findsNothing);
    });

    testWidgets('a visitor who is in shows Verified / Inside', (tester) async {
      await tester.pumpWidget(
        _host(
          const VisitorRow(
            visitor: SecurityVisitor(
              id: 2,
              name: 'Liu Yang',
              reference: 'VP-2607-012',
              suiteName: 'Alba Suite',
              roomNumber: '101',
              arrivalLabel: '02:00PM',
              isInside: true,
              isVerified: true,
            ),
          ),
        ),
      );

      expect(find.text('✓ Verified'), findsOneWidget);
      expect(find.text('Inside'), findsOneWidget);
      expect(find.text('Pending'), findsNothing);
    });

    testWidgets('a visitor who has checked out shows Exited, not Not Inside', (
      tester,
    ) async {
      await tester.pumpWidget(
        _host(
          const VisitorRow(
            visitor: SecurityVisitor(
              id: 3,
              name: 'Grace Hopper',
              reference: 'VP-2607-013',
              suiteName: 'Alba Suite',
              roomNumber: '101',
              arrivalLabel: '02:00PM',
              isInside: false,
              isVerified: true,
              isExited: true,
            ),
          ),
        ),
      );

      expect(find.text('✓ Verified'), findsOneWidget);
      expect(find.text('Exited'), findsOneWidget);
      expect(find.text('Not Inside'), findsNothing);
      expect(find.text('Inside'), findsNothing);
    });
  });

  group('VisitorPassRequestCard (Visitor Pass Requests)', () {
    testWidgets('a pending request shows Pending, its code and Verify Pass', (
      tester,
    ) async {
      await tester.pumpWidget(
        _host(
          VisitorPassRequestCard(
            request: const VisitorPassRequest(
              id: 3,
              name: 'Ahmed Al-Rashid',
              reference: 'VP-2607-013',
              suiteName: 'Alba Suite',
              roomNumber: '101',
              offlineCode: '305671',
              email: 'ahmed@mail.com',
              whatsapp: '+234 808 270 1895',
              submittedLabel: '10:32PM',
              isVerified: false,
            ),
            onVerify: () {},
          ),
        ),
      );

      expect(find.text('Pending'), findsOneWidget);
      expect(find.text('Verify Pass'), findsOneWidget);
      expect(find.textContaining('Offline Code  -  305671'), findsOneWidget);
      expect(find.textContaining('VP-2607-013'), findsOneWidget);

      // The contact block the officer needs to reach them (Figma 257:1359).
      expect(find.text('Email'), findsOneWidget);
      expect(find.text('ahmed@mail.com'), findsOneWidget);
      expect(find.text('WhatsApp Number'), findsOneWidget);
      expect(find.text('Submitted'), findsOneWidget);
      expect(find.text('10:32PM'), findsOneWidget);
    });

    testWidgets('a verified request drops the action but keeps the record', (
      tester,
    ) async {
      await tester.pumpWidget(
        _host(
          VisitorPassRequestCard(
            request: const VisitorPassRequest(
              id: 4,
              name: 'Liu Yang',
              reference: 'VP-2607-014',
              suiteName: 'Alba Suite',
              roomNumber: '101',
              onlineCode: '482913',
              arrivalLabel: '02:00PM',
              isVerified: true,
            ),
          ),
        ),
      );

      expect(find.text('✓ Verified'), findsOneWidget);
      expect(find.text('Verify Pass'), findsNothing);
      expect(find.textContaining('Online Code  -  482913'), findsOneWidget);
    });
  });

  testWidgets('Verify Pass reports the request to act on', (tester) async {
    VisitorPassRequest? tapped;
    const request = VisitorPassRequest(
      id: 6,
      name: 'Ahmed Al-Rashid',
      reference: 'VP-2607-016',
      roomNumber: '101',
      onlineCode: '482913',
      offlineCode: '305671',
    );

    await tester.pumpWidget(
      _host(
        VisitorPassRequestCard(
          request: request,
          onVerify: () => tapped = request,
        ),
      ),
    );

    await tester.tap(find.text('Verify Pass'));
    await tester.pump();

    // The online code is what the gate screen should be handed when present.
    expect(tapped, isNotNull);
    expect(tapped!.onlineCode, '482913');
  });

  testWidgets('a long requests list scrolls instead of overflowing', (
    tester,
  ) async {
    final requests = List.generate(
      12,
      (i) => VisitorPassRequest(
        id: i,
        name: 'Visitor $i',
        reference: 'VP-2607-0$i',
        suiteName: 'Alba Suite',
        roomNumber: '10$i',
        offlineCode: '30567$i',
        submittedLabel: '10:32PM',
      ),
    );

    // A short viewport: twelve cards cannot possibly fit, so the column must
    // scroll rather than run off the bottom.
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SizedBox(
            width: 368,
            height: 500,
            child: ListView.separated(
              itemCount: requests.length,
              separatorBuilder: (_, _) => const SizedBox(height: 17),
              itemBuilder: (_, i) =>
                  VisitorPassRequestCard(request: requests[i], onVerify: () {}),
            ),
          ),
        ),
      ),
    );

    expect(tester.takeException(), isNull);
    expect(find.text('Visitor 0'), findsOneWidget);
    expect(find.text('Visitor 11'), findsNothing); // below the fold

    await tester.scrollUntilVisible(
      find.text('Visitor 11'),
      300,
      scrollable: find.byType(Scrollable),
    );
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text('Visitor 11'), findsOneWidget);
  });

  testWidgets('neither list overflows at the dashboard column width', (
    tester,
  ) async {
    // The requests column is 368px wide on the tablet; long names and long
    // suite names must ellipsize rather than throw.
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SizedBox(
            width: 368,
            child: Column(
              children: [
                VisitorPassRequestCard(
                  request: const VisitorPassRequest(
                    id: 5,
                    name: 'Maximilian Featherstonehaugh-Ashcombe',
                    reference: 'VP-2607-015',
                    suiteName: 'Alba Suite · One bedroom Apartment',
                    roomNumber: '101',
                    offlineCode: '305671',
                    submittedLabel: '10:32PM',
                  ),
                  onVerify: () {},
                ),
              ],
            ),
          ),
        ),
      ),
    );

    expect(tester.takeException(), isNull);
    expect(find.text('Pending'), findsOneWidget);
  });
}
