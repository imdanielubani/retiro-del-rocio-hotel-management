import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/staff_intercom_channel.dart';
import 'package:retirodelrocioapp/core/utils/resume_refresher.dart';
import 'package:retirodelrocioapp/features/intercom_call/data/intercom_call_repository.dart';
import 'package:retirodelrocioapp/features/intercom_call/domain/intercom_call.dart';

final staffIntercomCallRepositoryProvider = Provider<IntercomCallRepository>(
  (ref) => IntercomCallRepository(basePath: 'staff/intercom/calls'),
);

/// A staff member's own Intercom call, either side — calling another
/// individual staffer, or being called by one (or by a guest, if this
/// station is Reception — see `reception_intercom_call_providers.dart`,
/// which serves that case with its own dedicated endpoint over the same
/// underlying table). Keyed by (token, userId) — a single generic notifier
/// serves every department, and two people holding the same role are
/// addressed separately since a call rings one specific person, not "any
/// device signed in as this role".
///
/// Owns its own `StaffIntercomChannel` subscription
/// (`staff-intercom.user.{userId}`) so an incoming call rings the moment
/// it's placed, and a light poll every few seconds as a backstop — the
/// same "accelerator, not a dependency" pattern every other realtime
/// feature here follows.
final staffIntercomCallProvider =
    NotifierProvider.family<
      StaffIntercomCallNotifier,
      IntercomCall?,
      (String token, int userId)
    >(StaffIntercomCallNotifier.new);

class StaffIntercomCallNotifier extends Notifier<IntercomCall?> {
  StaffIntercomCallNotifier(this.arg);

  final (String token, int userId) arg;
  String get _token => arg.$1;
  int get _userId => arg.$2;

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
      channel: 'staff-intercom.user.$_userId',
    );
    _channel = channel;
    channel.connect(onSignal: () => unawaited(refresh()));
  }

  Future<void> refresh() async {
    state = await ref.read(staffIntercomCallRepositoryProvider).current(_token);
  }

  /// Call another staff member.
  Future<void> place(int targetUserId) async {
    state = await ref.read(staffIntercomCallRepositoryProvider).place(_token, {
      'user_id': targetUserId,
    });
  }

  Future<void> answer(int callId) async {
    state = await ref
        .read(staffIntercomCallRepositoryProvider)
        .answer(_token, callId);
  }

  Future<void> decline(int callId) async {
    state = await ref
        .read(staffIntercomCallRepositoryProvider)
        .decline(_token, callId);
  }

  Future<void> end(int callId) async {
    state = await ref
        .read(staffIntercomCallRepositoryProvider)
        .end(_token, callId);
  }
}
