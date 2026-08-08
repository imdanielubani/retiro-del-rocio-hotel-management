import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/intercom_call/presentation/screens/intercom_call_screen.dart';
import 'package:retirodelrocioapp/features/staff_intercom/application/staff_intercom_call_providers.dart';

/// Pushes the shared Intercom call overlay the moment this station
/// transitions from no call to having one (placed by this station, or an
/// incoming call from another) — `ref.listen` only fires on a genuine
/// change, so this never double-pushes across rebuilds or repeated polls of
/// the same call. The screen itself watches the same provider and pops when
/// the call clears.
///
/// Call this once per `build()` from any screen that should ring regardless
/// of what's on screen for this station — mirrors
/// `watchReceptionIntercomCall` in `reception_intercom_call_gate.dart`,
/// generalised to any of Housekeeping/Maintenance/Security via
/// [session.role].
void watchStaffIntercomCall(
  BuildContext context,
  WidgetRef ref,
  StaffSession session,
) {
  final key = (session.token, session.role);

  ref.listen(staffIntercomCallProvider(key), (previous, next) {
    if (previous == null && next != null) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => IntercomCallScreen(
            watchCall: (ref) => ref.watch(staffIntercomCallProvider(key)),
            myRole: session.role,
            onAnswer: (id) =>
                ref.read(staffIntercomCallProvider(key).notifier).answer(id),
            onDecline: (id) =>
                ref.read(staffIntercomCallProvider(key).notifier).decline(id),
            onEnd: (id) =>
                ref.read(staffIntercomCallProvider(key).notifier).end(id),
            onSignal: (callId, type, data) => ref
                .read(staffIntercomCallRepositoryProvider)
                .signal(session.token, callId, type, data),
          ),
        ),
      );
    }
  });
}
