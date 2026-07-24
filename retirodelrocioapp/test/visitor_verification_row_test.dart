import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/security/domain/visitor_pass_record.dart';
import 'package:retirodelrocioapp/features/security/presentation/widgets/visitor_verification_row.dart';

/// The expanded contact block on a Visitor Verification row.
///
/// The values are right-aligned against a common edge, so they read as a column
/// rather than as ragged text. A `Spacer` + `Flexible` pair used to split the
/// leftover width in half, which both truncated long values early and moved the
/// right edge around from row to row — so these assert the geometry, not just
/// that the text is present.
const _pass = VisitorPassRecord(
  id: 1,
  reference: 'VP-2607-011',
  status: VisitorPassStatus.pending,
  visitorName: 'Ahmed Al-Rashid',
  code: '305671',
  offlineCode: '305671',
  hostName: 'Daniel Ubani',
  roomNumber: '101',
  suiteName: 'Alba Suite',
  email: 'ahmed.al-rashid@somewhat-long-domain.com',
  phone: '+234 808 270 1895',
  submittedLabel: '10:32PM',
);

Future<void> _pumpRow(WidgetTester tester, {double width = 420}) {
  return tester.pumpWidget(
    MaterialApp(
      home: Scaffold(
        body: SizedBox(
          width: width,
          child: VisitorVerificationRow(
            pass: _pass,
            expanded: true,
            onTap: () {},
          ),
        ),
      ),
    ),
  );
}

void main() {
  testWidgets('the expanded contact rows share one right edge', (tester) async {
    await _pumpRow(tester);

    expect(tester.takeException(), isNull);

    // Every value ends at the same x, whatever its length.
    final rights = <String, double>{};
    for (final value in [
      'ahmed.al-rashid@somewhat-long-domain.com',
      '+234 808 270 1895',
      '10:32PM',
    ]) {
      final finder = find.text(value);
      expect(finder, findsOneWidget, reason: '"$value" should be rendered');
      rights[value] = tester.getRect(finder).right;
    }

    final edges = rights.values.toList();
    for (final edge in edges) {
      expect(
        edge,
        moreOrLessEquals(edges.first, epsilon: 0.5),
        reason: 'contact values must line up: $rights',
      );
    }
  });

  testWidgets('a long value keeps its full width instead of halving it', (
    tester,
  ) async {
    await _pumpRow(tester);

    final label = tester.getRect(find.text('Email'));
    final value = tester.getRect(
      find.text('ahmed.al-rashid@somewhat-long-domain.com'),
    );

    // The value owns everything to the right of the label, not half of it.
    // Under the old Spacer + Flexible split this was ~half the gap.
    expect(value.left, lessThan(label.right + 40));
    expect(value.width, greaterThan(200));
  });

  testWidgets('a narrow row still lays out without overflowing', (
    tester,
  ) async {
    await _pumpRow(tester, width: 300);

    expect(tester.takeException(), isNull);
    expect(find.text('Ahmed Al-Rashid'), findsOneWidget);
    expect(find.text('VP-2607-011'), findsOneWidget);
  });
}
