import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/intercom_call/data/intercom_call_repository.dart';
import 'package:retirodelrocioapp/features/intercom_call/domain/intercom_call.dart';

final guestIntercomCallRepositoryProvider = Provider<IntercomCallRepository>(
  (ref) => IntercomCallRepository(basePath: 'intercom/calls'),
);

/// This room's Intercom call, either side — placing one, or Reception
/// calling in. Kept alive at the guest app's root (see `welcome_screen.dart`)
/// so an incoming call rings no matter which screen the guest has open.
///
/// Refreshed by the room's existing realtime connection (`RoomChannel`'s
/// `intercom.ringing`/`intercom.updated` signals, wired in
/// `room_realtime_provider.dart`) and by a light poll every few seconds as a
/// backstop — the same "accelerator, not a dependency" pattern every other
/// realtime feature here follows.
final guestIntercomCallProvider =
    NotifierProvider.family<GuestIntercomCallNotifier, IntercomCall?, String>(
      GuestIntercomCallNotifier.new,
    );

class GuestIntercomCallNotifier extends Notifier<IntercomCall?> {
  GuestIntercomCallNotifier(this.token);

  final String token;
  Timer? _poll;

  @override
  IntercomCall? build() {
    ref.onDispose(() => _poll?.cancel());
    _poll = Timer.periodic(
      const Duration(seconds: 4),
      (_) => unawaited(refresh()),
    );
    unawaited(refresh());
    return null;
  }

  Future<void> refresh() async {
    state = await ref.read(guestIntercomCallRepositoryProvider).current(token);
  }

  /// Call Reception from this room.
  Future<void> place() async {
    state = await ref.read(guestIntercomCallRepositoryProvider).place(token);
  }

  Future<void> answer(int callId) async {
    state = await ref
        .read(guestIntercomCallRepositoryProvider)
        .answer(token, callId);
  }

  Future<void> decline(int callId) async {
    state = await ref
        .read(guestIntercomCallRepositoryProvider)
        .decline(token, callId);
  }

  Future<void> end(int callId) async {
    state = await ref
        .read(guestIntercomCallRepositoryProvider)
        .end(token, callId);
  }
}
