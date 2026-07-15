import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/authentication/application/auth_providers.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/reauthenticate_dialog.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/dialogs/session_timeout_dialog.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/screens/session_lock_screen.dart';
import 'package:retirodelrocioapp/features/authentication/presentation/widgets/session_expiring_banner.dart';

/// Wraps the authenticated staff area and enforces session security:
///  • **Session lock** after inactivity (re-enter password to resume)
///  • **Expiring banner** shortly before the JWT expires ("Stay signed in")
///  • **Session timeout** when the JWT expires → back to sign-in.
class SessionGuard extends ConsumerStatefulWidget {
  const SessionGuard({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<SessionGuard> createState() => _SessionGuardState();
}

class _SessionGuardState extends ConsumerState<SessionGuard> {
  /// Lock the screen after this much inactivity.
  static const _idleLock = Duration(minutes: 30);

  /// Show the expiring banner once the session has this little left.
  static const _warnBefore = Duration(minutes: 2);

  Timer? _ticker;
  DateTime _lastActivity = DateTime.now();
  bool _locked = false;
  bool _handlingTimeout = false;

  @override
  void initState() {
    super.initState();
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  void _bump() => _lastActivity = DateTime.now();

  void _tick() {
    if (!mounted) return;
    final session = ref.read(authControllerProvider).value;
    if (session == null) return;

    if (session.isExpired) {
      _onTimeout();
      return;
    }

    final idle = DateTime.now().difference(_lastActivity) >= _idleLock;
    if (idle && !_locked) {
      setState(() => _locked = true);
      return;
    }

    // Refresh the countdown only while the banner or lock is on screen.
    final warning = (session.timeLeft ?? Duration.zero) <= _warnBefore;
    if (warning || _locked) setState(() {});
  }

  Future<void> _onTimeout() async {
    if (_handlingTimeout) return;
    _handlingTimeout = true;
    await ref.read(authControllerProvider.notifier).logout();
    if (!mounted) return;
    await showSessionTimeoutDialog(context);
    if (mounted) Navigator.of(context).pop(); // dashboard → welcome / sign-in
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authControllerProvider).value;
    final timeLeft = session?.timeLeft;
    final showBanner =
        !_locked &&
        session != null &&
        timeLeft != null &&
        !timeLeft.isNegative &&
        timeLeft <= _warnBefore;

    return Listener(
      behavior: HitTestBehavior.translucent,
      onPointerDown: (_) => _bump(),
      onPointerMove: (_) => _bump(),
      child: Stack(
        children: [
          widget.child,
          if (showBanner)
            Positioned(
              top: 0,
              left: 0,
              right: 0,
              child: SafeArea(
                child: SessionExpiringBanner(
                  timeLeft: timeLeft,
                  onStay: () => showReAuthenticateDialog(context),
                ),
              ),
            ),
          if (_locked && session != null)
            Positioned.fill(
              child: SessionLockScreen(
                session: session,
                onUnlocked: () {
                  _bump();
                  setState(() => _locked = false);
                },
              ),
            ),
        ],
      ),
    );
  }
}
