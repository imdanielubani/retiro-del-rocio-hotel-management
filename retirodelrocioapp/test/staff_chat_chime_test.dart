import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/core/navigation/root_messenger.dart';
import 'package:retirodelrocioapp/features/staff_chat/application/staff_chat_providers.dart';
import 'package:retirodelrocioapp/features/staff_chat/data/staff_chat_repository.dart';
import 'package:retirodelrocioapp/features/staff_chat/domain/staff_channel.dart';

/// A fake backing store standing in for `GET /staff/chat/channels`, so the
/// chime/toast diffing logic in `staffChatChimeProvider` runs against real
/// widget behaviour without a network call. Whatever [channels] holds is
/// what the next poll (or manual invalidate) returns.
class _FakeStaffChatRepository extends StaffChatRepository {
  List<StaffChannel> channelsToReturn = const [];

  @override
  Future<List<StaffChannel>> channels(String token) async => channelsToReturn;
}

StaffChannel _channel(
  int userId,
  String role, {
  int unreadCount = 0,
  String? lastMessage,
}) => StaffChannel(
  userId: userId,
  name: role[0].toUpperCase() + role.substring(1),
  role: role,
  roleLabel: role[0].toUpperCase() + role.substring(1),
  online: true,
  lastMessage: lastMessage,
  lastMessageLabel: null,
  unreadCount: unreadCount,
);

void main() {
  const token = 'test-token';

  Future<ProviderContainer> pumpApp(
    WidgetTester tester,
    _FakeStaffChatRepository repo,
  ) async {
    final container = ProviderContainer(
      overrides: [staffChatRepositoryProvider.overrideWithValue(repo)],
    );

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp(
          scaffoldMessengerKey: rootScaffoldMessengerKey,
          home: Scaffold(
            body: Consumer(
              builder: (context, ref, _) {
                // Keeps the listener alive, same as every station's scaffold
                // does via `ref.watch(staffChatChimeProvider(session.token))`.
                ref.watch(staffChatChimeProvider(token));
                return const SizedBox();
              },
            ),
          ),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    return container;
  }

  testWidgets(
    'toasts a channel whose unread count just went up, but not on the '
    'first poll that establishes the baseline',
    (tester) async {
      final repo = _FakeStaffChatRepository()
        ..channelsToReturn = [_channel(1, 'maintenance', unreadCount: 0)];
      final container = await pumpApp(tester, repo);

      // The first fetch establishes the baseline — nothing "arrived" yet.
      expect(find.byType(SnackBar), findsNothing);

      // Maintenance sends a message — housekeeping's next poll sees the
      // unread count go from 0 to 1.
      repo.channelsToReturn = [
        _channel(1, 'maintenance', unreadCount: 1, lastMessage: 'AC is fixed.'),
      ];
      container.invalidate(staffChatChannelsProvider(token));
      await tester.pump();
      await tester.pump();

      expect(find.text('Maintenance — New Message'), findsOneWidget);
      expect(find.text('AC is fixed.'), findsOneWidget);
      expect(find.byType(SnackBar), findsOneWidget);
      expect(tester.takeException(), isNull);
      container.dispose();
    },
  );

  testWidgets(
    'unread count dropping to zero (opening the thread) never toasts',
    (tester) async {
      final repo = _FakeStaffChatRepository()
        ..channelsToReturn = [_channel(2, 'reception', unreadCount: 3)];
      final container = await pumpApp(tester, repo);

      repo.channelsToReturn = [_channel(2, 'reception', unreadCount: 0)];
      container.invalidate(staffChatChannelsProvider(token));
      await tester.pump();
      await tester.pump();

      expect(find.byType(SnackBar), findsNothing);
      expect(tester.takeException(), isNull);
      container.dispose();
    },
  );

  testWidgets('a repeated poll with no changes does not re-toast', (
    tester,
  ) async {
    final repo = _FakeStaffChatRepository()
      ..channelsToReturn = [_channel(3, 'security', unreadCount: 2)];
    final container = await pumpApp(tester, repo);

    container.invalidate(staffChatChannelsProvider(token));
    await tester.pump();
    await tester.pump();

    expect(find.byType(SnackBar), findsNothing);
    expect(tester.takeException(), isNull);
    container.dispose();
  });
}
