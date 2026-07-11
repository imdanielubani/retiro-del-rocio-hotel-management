import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/welcome/data/room_status_repository.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

final roomStatusRepositoryProvider =
    Provider<RoomStatusRepository>((ref) => RoomStatusRepository());

/// The tablet's live room status, keyed by its device token and re-polled every
/// 20 seconds so a reception check-in reflects on the tablet almost immediately.
final roomStatusProvider =
    FutureProvider.family<RoomStatus?, String>((ref, deviceToken) async {
  final repo = ref.watch(roomStatusRepositoryProvider);

  final timer = Timer(const Duration(seconds: 20), ref.invalidateSelf);
  ref.onDispose(timer.cancel);

  return repo.fetch(deviceToken);
});
