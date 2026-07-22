import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/screens/session_lock_screen.dart';

void main() {
  // The lock is an overlay sibling of the guarded screen's Scaffold, so it sits
  // outside any Material. It must supply its own — otherwise the password field
  // and "Log out" button throw "No Material widget found". This pumps it with no
  // Material ancestor to reproduce exactly that condition.
  testWidgets('session lock renders without a Material ancestor', (tester) async {
    const session = StaffSession(
      token: 't',
      name: 'Daniel Ubani',
      email: 'daniel@rocio.test',
      role: 'security',
    );

    await tester.pumpWidget(
      ProviderScope(
        child: MaterialApp(
          home: Stack(
            children: [
              const SizedBox.expand(),
              Positioned.fill(
                child: SessionLockScreen(session: session, onUnlocked: () {}),
              ),
            ],
          ),
        ),
      ),
    );
    await tester.pump();

    expect(tester.takeException(), isNull);
    expect(find.text('Session Locked'), findsOneWidget);
    expect(find.text('Not you? Log out'), findsOneWidget);
  });
}
