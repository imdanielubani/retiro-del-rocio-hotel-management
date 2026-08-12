import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/staff_intercom_channel.dart';
import 'package:retirodelrocioapp/core/utils/resume_refresher.dart';
import 'package:retirodelrocioapp/features/intercom_call/data/intercom_call_repository.dart';
import 'package:retirodelrocioapp/features/intercom_call/domain/intercom_call.dart';

final receptionIntercomCallRepositoryProvider =
    Provider<IntercomCallRepository>(
      (ref) => IntercomCallRepository(basePath: 'reception/intercom/calls'),
    );

/// The front desk's Intercom call, either side — calling a guest's room, or
/// a guest calling in. Kept alive wherever a reception screen mounts it (see
/// `reception_scaffold.dart` and `reception_dashboard_screen.dart`) so an
/// incoming call rings no matter which screen the desk has open.
///
/// Unlike the guest tablet, Reception has no always-on realtime connection
/// to piggyback on, so this notifier owns its own `StaffIntercomChannel`
/// subscription (`staff-intercom.reception`) — mirrors how
/// `ReceptionGuestTypingNotifier` connects its own transient channel.
/// Refreshed by that signal and by a light poll every few seconds as a
/// backstop, the same "accelerator, not a dependency" pattern every other
/// realtime feature here follows.
final receptionIntercomCallProvider =
    NotifierProvider.family<
      ReceptionIntercomCallNotifier,
      IntercomCall?,
      String
    >(ReceptionIntercomCallNotifier.new);

class ReceptionIntercomCallNotifier extends Notifier<IntercomCall?> {
  ReceptionIntercomCallNotifier(this.token);

  final String token;
  Timer? _poll;
  StaffIntercomChannel? _channel;
  ResumeRefresher? _resumeRefresher;

  @override
  IntercomCall? build() {
    _resumeRefresher = ResumeRefresher(() => unawaited(refresh()));
    ref.onDispose(() {
      _poll?.cancel();
      _channel?.dispose();
      _resumeRefresher?.dispose();
    });
    _poll = Timer.periodic(
      const Duration(seconds: 4),
      (_) => unawaited(refresh()),
    );
    unawaited(refresh());
    unawaited(_connect());
    return null;
  }

  Future<void> _connect() async {
    final config = (await ref.read(appConfigProvider.future)).realtime;
    if (config == null) return;

    final channel = StaffIntercomChannel(
      config: config,
      channel: 'staff-intercom.reception',
    );
    _channel = channel;
    channel.connect(onSignal: () => unawaited(refresh()));
  }

  Future<void> refresh() async {
    state = await ref
        .read(receptionIntercomCallRepositoryProvider)
        .current(token);
  }

  /// Call a checked-in guest's room.
  Future<void> place(int bookingId) async {
    state = await ref.read(receptionIntercomCallRepositoryProvider).place(
      token,
      {'booking_id': bookingId},
    );
  }

  Future<void> answer(int callId) async {
    state = await ref
        .read(receptionIntercomCallRepositoryProvider)
        .answer(token, callId);
  }

  Future<void> decline(int callId) async {
    state = await ref
        .read(receptionIntercomCallRepositoryProvider)
        .decline(token, callId);
  }

  Future<void> end(int callId) async {
    state = await ref
        .read(receptionIntercomCallRepositoryProvider)
        .end(token, callId);
  }
}
