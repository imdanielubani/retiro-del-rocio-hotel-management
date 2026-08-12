import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/app/app.dart';
import 'package:retirodelrocioapp/core/media/ambient_video_provider.dart';

Future<void> pumpApp(WidgetTester tester) => tester.pumpWidget(
  ProviderScope(
    // No network/video in tests: the ambient video resolves to nothing.
    overrides: [ambientVideoProvider.overrideWith((ref) async => null)],
    child: const RocioTabletApp(),
  ),
);

void main() {
  testWidgets('Splash screen shows the brand wordmark', (tester) async {
    await pumpApp(tester);

    expect(find.text('RETIRO DEL ROCIO'), findsOneWidget);
    expect(find.text('INITIALIZING EXPERIENCE'), findsOneWidget);
  });

  testWidgets('the kiosk ignores the tablet system font-size setting', (
    tester,
  ) async {
    // A guest (or a technician) raising the device's font size would otherwise
    // inflate our text and overflow the fixed-height cards and bars.
    tester.platformDispatcher.textScaleFactorTestValue = 1.3;
    addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);

    await pumpApp(tester);

    final context = tester.element(find.text('RETIRO DEL ROCIO'));
    expect(MediaQuery.textScalerOf(context), TextScaler.noScaling);
  });
}
