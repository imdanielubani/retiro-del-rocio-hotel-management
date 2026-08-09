import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';
import 'package:retirodelrocioapp/features/intercom_call/presentation/screens/intercom_call_screen.dart';
import 'package:retirodelrocioapp/features/reception/intercom/application/reception_intercom_call_providers.dart';

/// Pushes the shared Intercom call overlay the moment the desk transitions
/// from no call to having one (placed by reception, or an incoming call
/// from a guest) — `ref.listen` only fires on a genuine change, so this
/// never double-pushes across rebuilds or repeated polls of the same call.
/// The screen itself watches the same provider and pops when the call
/// clears.
///
/// Call this once per `build()` from any reception screen that should ring
/// regardless of what's on screen — today that's `ReceptionScaffold` (every
/// non-dashboard screen) and `ReceptionDashboardScreen` (which uses its own
/// inline layout instead of the shared shell), mirroring how
/// `receptionNotificationChimeProvider` is watched in both places.
void watchReceptionIntercomCall(
  BuildContext context,
  WidgetRef ref,
  StaffSession session,
) {
  ref.listen(receptionIntercomCallProvider(session.token), (previous, next) {
    if (previous == null && next != null) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => IntercomCallScreen(
            watchCall: (ref) =>
                ref.watch(receptionIntercomCallProvider(session.token)),
            myRole: 'reception',
            onAnswer: (id) => ref
                .read(receptionIntercomCallProvider(session.token).notifier)
                .answer(id),
            onDecline: (id) => ref
                .read(receptionIntercomCallProvider(session.token).notifier)
                .decline(id),
            onEnd: (id) => ref
                .read(receptionIntercomCallProvider(session.token).notifier)
                .end(id),
            onFetchToken: (callId) => ref
                .read(receptionIntercomCallRepositoryProvider)
                .fetchToken(session.token, callId),
          ),
        ),
      );
    }
  });
}
