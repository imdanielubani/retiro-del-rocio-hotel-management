import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/welcome/data/room_status_repository.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

final roomStatusRepositoryProvider = Provider<RoomStatusRepository>(
  (ref) => RoomStatusRepository(),
);

/// The tablet's live room status, keyed by its device token.
///
/// [roomRealtimeProvider] pushes an update the instant reception checks a
/// guest in or out (see `WelcomeScreen`'s stack-unwind on the occupied→empty
/// edge); this poll is the backstop for when the socket is down or the room's
/// Wi-Fi drops, kept tight so a checkout still lands within a few seconds
/// rather than up to 20.
final roomStatusProvider = FutureProvider.family<RoomStatus?, String>((
  ref,
  deviceToken,
) async {
  final repo = ref.watch(roomStatusRepositoryProvider);

  final timer = Timer(const Duration(seconds: 3), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.fetch(deviceToken);
});
