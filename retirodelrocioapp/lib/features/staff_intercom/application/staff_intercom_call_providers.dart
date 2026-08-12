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

/// A staff tablet's own Intercom call, either side — calling another
/// station, or being called by one (or by a guest, if this station is
/// Reception — see `reception_intercom_call_providers.dart`, which serves
/// that case with its own dedicated endpoint over the same underlying
/// table). Keyed by (token, role) since a single generic notifier serves
/// Housekeeping, Maintenance and Security alike.
///
/// Owns its own `StaffIntercomChannel` subscription (`staff-intercom.
/// {role}`) so an incoming call rings the moment it's placed, and a light
/// poll every few seconds as a backstop — the same "accelerator, not a
/// dependency" pattern every other realtime feature here follows.
final staffIntercomCallProvider =
    NotifierProvider.family<
      StaffIntercomCallNotifier,
      IntercomCall?,
      (String token, String role)
    >(StaffIntercomCallNotifier.new);

class StaffIntercomCallNotifier extends Notifier<IntercomCall?> {
  StaffIntercomCallNotifier(this.arg);

  final (String token, String role) arg;
  String get _token => arg.$1;
  String get _role => arg.$2;

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

    final channel = StaffIntercomChannel(config: config, role: _role);
    _channel = channel;
    channel.connect(onSignal: () => unawaited(refresh()));
  }

  Future<void> refresh() async {
    state = await ref.read(staffIntercomCallRepositoryProvider).current(_token);
  }

  /// Call another station.
  Future<void> place(String targetRole) async {
    state = await ref.read(staffIntercomCallRepositoryProvider).place(_token, {
      'role': targetRole,
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
