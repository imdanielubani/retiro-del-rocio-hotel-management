import 'package:flutter/widgets.dart';

/// Runs [onResumed] every time the app returns to the foreground.
///
/// Used to self-heal realtime state (e.g. an Intercom call notifier) after a
/// stretch where the OS paused the app — most notably a native permission
/// dialog (mic access, on a fresh install) landing at the same moment a
/// WebSocket handshake or poll timer needed to run, both of which Android
/// can throttle while the app isn't fully foregrounded.
class ResumeRefresher with WidgetsBindingObserver {
  ResumeRefresher(this.onResumed) {
    WidgetsBinding.instance.addObserver(this);
  }

  final VoidCallback onResumed;

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) onResumed();
  }

  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
  }
}
