import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/media/ambient_video_provider.dart';

/// Wraps the app so the [ambientVideoProvider] is created at startup and stays
/// alive for the whole session, and resumes playback whenever the app returns
/// to the foreground — keeping the ambient video "always playing".
class AmbientVideoScope extends ConsumerStatefulWidget {
  const AmbientVideoScope({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<AmbientVideoScope> createState() => _AmbientVideoScopeState();
}

class _AmbientVideoScopeState extends ConsumerState<AmbientVideoScope>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed) return;
    final controller = ref.read(ambientVideoProvider).value;
    if (controller != null &&
        controller.value.isInitialized &&
        !controller.value.isPlaying) {
      controller.play();
    }
  }

  @override
  Widget build(BuildContext context) {
    // Instantiate + keep the ambient video alive for the whole session.
    ref.watch(ambientVideoProvider);
    return widget.child;
  }
}
