import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/app/app_router.dart';
import 'package:retirodelrocioapp/features/welcome/application/room_realtime_provider.dart';
import 'package:retirodelrocioapp/features/welcome/application/room_status_providers.dart';

/// Drops a guest tablet back to the idle welcome screen the instant reception
/// checks the guest out — no matter how deep the guest has navigated (My Stay,
/// Bills, a Service Request, anywhere).
///
/// This has to live above the [Navigator] (wrapping `MaterialApp.router`'s
/// `builder`), not inside any pushed screen: Flutter does not rebuild a route
/// that isn't the current top of the stack, so a watcher placed on
/// `WelcomeScreen` or `GuestHomeScreen` only ever fires while that screen
/// itself happens to be topmost — which silently breaks the moment the guest
/// pushes so much as one more screen on top. Living here means this widget is
/// never buried, so it reacts every time regardless of stack depth.
///
/// Read-only: it never renders anything of its own besides [child], and the
/// pop is the only side effect. [rootNavigatorKey] targets the single
/// Navigator go_router manages, the same one every guest screen pushes onto.
class CheckoutResetWatcher extends ConsumerStatefulWidget {
  const CheckoutResetWatcher({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<CheckoutResetWatcher> createState() =>
      _CheckoutResetWatcherState();
}

class _CheckoutResetWatcherState extends ConsumerState<CheckoutResetWatcher> {
  bool _wasCheckedIn = false;

  @override
  Widget build(BuildContext context) {
    // The device persisted from a previous launch — covers every real tablet
    // sitting paired in a room, which is what this watcher exists for. A
    // device paired fresh in the *current* session (no restart yet) isn't
    // reflected here since this provider is only read once at boot; that's
    // the device-setup flow's own screen, not a guest mid-stay, so it's out
    // of scope for this reset.
    final device = ref.watch(bootstrapDeviceProvider);

    if (device == null || device.isStaff) return widget.child;

    // Accelerator: the realtime `rooms.{id}` channel pushes the moment
    // reception clicks; roomStatusProvider's own poll is the backstop.
    ref.watch(roomRealtimeProvider(device));
    final status = ref.watch(roomStatusProvider(device.token)).value;
    final checkedIn = status != null && status.isOccupied && status.hasGuest;

    if (_wasCheckedIn && !checkedIn) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        rootNavigatorKey.currentState?.popUntil((route) => route.isFirst);
      });
    }
    _wasCheckedIn = checkedIn;

    return widget.child;
  }
}
