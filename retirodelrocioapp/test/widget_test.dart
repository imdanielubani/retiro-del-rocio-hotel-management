import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/app/app.dart';
import 'package:retirodelrocioapp/core/media/ambient_video_provider.dart';

void main() {
  testWidgets('Splash screen shows the brand wordmark', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        // No network/video in tests: the ambient video resolves to nothing.
        overrides: [ambientVideoProvider.overrideWith((ref) async => null)],
        child: const RocioTabletApp(),
      ),
    );

    expect(find.text('RETIRO DEL ROCIO'), findsOneWidget);
    expect(find.text('INITIALIZING EXPERIENCE'), findsOneWidget);
  });
}
