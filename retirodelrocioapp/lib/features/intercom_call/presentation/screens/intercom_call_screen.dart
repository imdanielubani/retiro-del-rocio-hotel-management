import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/core/audio/incoming_call_ringtone.dart';
import 'package:retirodelrocioapp/core/config/app_config.dart';
import 'package:retirodelrocioapp/core/realtime/intercom_webrtc_signal_channel.dart';
import 'package:retirodelrocioapp/core/theme/app_colors.dart';
import 'package:retirodelrocioapp/core/theme/app_typography.dart';
import 'package:retirodelrocioapp/features/intercom_call/data/intercom_webrtc_session.dart';
import 'package:retirodelrocioapp/features/intercom_call/domain/intercom_call.dart';

const _greenColor = Color(0xFF34D399);
const _redColor = Color(0xFFEF4444);

/// The full-screen Intercom call overlay — Calling…, an incoming call,
/// Connected, and Call Ended (Figma 343:11744 / 343:11934 / 343:12033 /
/// 344:12702) — shared by the guest tablet and Reception, since both sides
/// of a call render the exact same states, just from opposite identities.
///
/// Pushed on top of whatever screen is showing when a call needs attention,
/// and watches the call itself via [watchCall] so it stays live (ringing →
/// connected → ended) without the caller having to re-push it — and pops
/// itself the moment the call clears, so the caller only needs to push it
/// once. [watchCall] is a plain `ref.watch(...)` closure rather than the
/// provider reference itself, since `flutter_riverpod` doesn't expose a
/// public type to hold one.
class IntercomCallScreen extends ConsumerStatefulWidget {
  const IntercomCallScreen({
    super.key,
    required this.watchCall,
    this.myRoomUnitId,
    this.myRole,
    required this.onAnswer,
    required this.onDecline,
    required this.onEnd,
    required this.onSignal,
  });

  final IntercomCall? Function(WidgetRef ref) watchCall;

  /// Exactly one of [myRoomUnitId] / [myRole] is set — the guest's own room,
  /// or the staff role this tablet is signed in as.
  final int? myRoomUnitId;
  final String? myRole;

  final Future<void> Function(int callId) onAnswer;
  final Future<void> Function(int callId) onDecline;
  final Future<void> Function(int callId) onEnd;

  /// Relays one WebRTC signal (an offer/answer/ICE candidate) to the other
  /// side of the call — the real audio connection's only way to negotiate,
  /// since the two devices have no other channel to each other.
  final Future<void> Function(
    int callId,
    String type,
    Map<String, dynamic> data,
  )
  onSignal;

  @override
  ConsumerState<IntercomCallScreen> createState() => _IntercomCallScreenState();
}

class _IntercomCallScreenState extends ConsumerState<IntercomCallScreen> {
  final _ringtone = IncomingCallRingtone();
  bool _busy = false;
  bool _ringtonePlaying = false;
  IntercomCall? _previous;

  // --- Real audio: the peer connection for the call currently on screen,
  // and the per-call channel that carries its offer/answer/ICE candidates.
  // Both are keyed to a call id so a new call after this one never reuses a
  // stale connection, and both are torn down the instant the call stops
  // being answerable-or-connected (declined/cancelled/ended, or disposed).
  IntercomWebRtcSession? _webrtc;
  int? _webrtcCallId;
  IntercomWebrtcSignalChannel? _signalChannel;
  int? _signalChannelCallId;
  bool _muted = false;
  bool _speakerOn = false;

  bool _isMe(IntercomParty p) => widget.myRoomUnitId != null
      ? p.roomUnitId == widget.myRoomUnitId
      : p.role == widget.myRole;

  @override
  void dispose() {
    unawaited(_ringtone.dispose());
    unawaited(_teardownCall());
    super.dispose();
  }

  void _syncRingtone(IntercomCall? call) {
    final shouldRing = call != null && call.isRinging && !_isMe(call.from);
    if (shouldRing && !_ringtonePlaying) {
      _ringtonePlaying = true;
      unawaited(_ringtone.start());
    } else if (!shouldRing && _ringtonePlaying) {
      _ringtonePlaying = false;
      unawaited(_ringtone.stop());
    }
  }

  /// Keeps the signal channel and the WebRTC session in sync with the call
  /// actually on screen: subscribes to this call's own signaling channel as
  /// soon as it exists (an incoming offer can arrive the instant the other
  /// side sees "accepted", which may be before this side's own poll/realtime
  /// notices it), and opens the real peer connection once the call is
  /// [IntercomCallStatus.accepted] — never earlier, so ringing never asks
  /// for the microphone.
  void _syncWebRtc(IntercomCall? call) {
    if (call == null || call.hasEnded) {
      unawaited(_teardownCall());
      return;
    }

    if (_signalChannelCallId != call.id) {
      unawaited(_connectSignalChannel(call.id));
    }

    if (call.status == IntercomCallStatus.accepted &&
        _webrtcCallId != call.id) {
      _webrtcCallId = call.id;
      final session = IntercomWebRtcSession(
        isCaller: _isMe(call.from),
        sendSignal: (type, data) => widget.onSignal(call.id, type, data),
      );
      _webrtc = session;
      _muted = false;
      unawaited(session.start());
    }
  }

  Future<void> _connectSignalChannel(int callId) async {
    _signalChannelCallId = callId;
    _signalChannel?.dispose();
    _signalChannel = null;

    final config = (await ref.read(appConfigProvider.future)).realtime;
    if (config == null || !mounted || _signalChannelCallId != callId) return;

    final channel = IntercomWebrtcSignalChannel(config: config, callId: callId);
    _signalChannel = channel;
    channel.connect(
      onSignal: (from, type, data) {
        // Every party subscribes to the same public per-call channel, so
        // this device also receives the echo of whatever it just sent
        // itself — `from` identifies which side of the call originated the
        // signal, and only the *other* side's is ever meaningful here.
        final call = widget.watchCall(ref);
        if (call == null || call.id != callId) return;
        final selfFrom = _isMe(call.from) ? 'caller' : 'callee';
        if (from == selfFrom) return;

        unawaited(_webrtc?.handleRemoteSignal(type, data));
      },
    );
  }

  Future<void> _teardownCall() async {
    _webrtcCallId = null;
    final session = _webrtc;
    _webrtc = null;
    await session?.dispose();

    _signalChannelCallId = null;
    _signalChannel?.dispose();
    _signalChannel = null;
  }

  Future<void> _act(Future<void> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
    } catch (_) {
      // A failed action just leaves the current state on screen — the
      // provider's own poll/realtime signal will correct it moments later.
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final call = widget.watchCall(ref);
    _syncRingtone(call);
    _syncWebRtc(call);

    // The call cleared (ended long enough ago to drop out of "current") —
    // this screen has nothing left to show, so it dismisses itself. Uses a
    // real `pop()`, not `maybePop()`: `maybePop` respects this same route's
    // `PopScope(canPop: false)` below (that guard exists to stop the user
    // swiping/back-button-ing out of a live call, not to block this
    // intentional auto-close), so calling it here left the screen stuck
    // forever once the call cleared — showing nothing but the loading
    // spinner below with no way off the screen.
    final ending = _previous != null && call == null;
    if (ending) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) Navigator.of(context).pop();
      });
    }

    // While that pop goes through, keep rendering the last known call
    // (frozen on its "Call Ended"/"Declined"/"Cancelled" state) instead of
    // the loading spinner — the spinner is only for the genuine first-load
    // case, before any call data has ever arrived.
    final display = call ?? (ending ? _previous : null);
    _previous = call;

    return PopScope(
      canPop: false,
      child: Scaffold(
        backgroundColor: Colors.black,
        body: Stack(
          fit: StackFit.expand,
          children: [
            Image.asset('assets/images/3365.jpg', fit: BoxFit.cover),
            const ColoredBox(color: Color.fromARGB(230, 0, 0, 0)),
            Center(
              child: display == null
                  ? const CircularProgressIndicator(color: AppColors.gold)
                  : _body(display),
            ),
          ],
        ),
      ),
    );
  }

  Widget _body(IntercomCall call) {
    final iAmCaller = _isMe(call.from);
    final other = iAmCaller ? call.to : call.from;

    if (call.status == IntercomCallStatus.ringing && !iAmCaller) {
      return _IncomingCallView(
        other: other,
        busy: _busy,
        onAnswer: () => _act(() => widget.onAnswer(call.id)),
        onDecline: () => _act(() => widget.onDecline(call.id)),
      );
    }

    if (call.status == IntercomCallStatus.accepted) {
      return _ConnectedView(
        other: other,
        answeredAt: call.answeredAt ?? call.startedAt,
        busy: _busy,
        muted: _muted,
        speakerOn: _speakerOn,
        onToggleMute: () {
          final session = _webrtc;
          if (session == null) return;
          setState(() => _muted = session.toggleMute());
        },
        onToggleSpeaker: () {
          final on = !_speakerOn;
          setState(() => _speakerOn = on);
          unawaited(_webrtc?.setSpeaker(on));
        },
        onEnd: () => _act(() async {
          await _teardownCall();
          await widget.onEnd(call.id);
        }),
      );
    }

    if (call.status == IntercomCallStatus.ringing) {
      return _CallingView(
        other: other,
        busy: _busy,
        onEnd: () => _act(() => widget.onEnd(call.id)),
      );
    }

    return _CallEndedView(other: other, status: call.status);
  }
}

/// The pulsing gold ring behind the phone icon — three faint concentric
/// borders, matching the Figma "ringing" treatment.
class _PulseRings extends StatelessWidget {
  const _PulseRings({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return Stack(
      alignment: Alignment.center,
      children: [_ring(136, 0.10), _ring(170, 0.06), _ring(202, 0.03)],
    );
  }

  Widget _ring(double size, double alpha) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      border: Border.all(color: color.withValues(alpha: alpha), width: 1),
    ),
  );
}

Widget _avatarBadge({
  required Color color,
  required IconData icon,
  Widget? corner,
}) {
  return SizedBox(
    width: 96,
    height: 96,
    child: Stack(
      alignment: Alignment.center,
      clipBehavior: Clip.none,
      children: [
        Container(
          width: 96,
          height: 96,
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.09),
            shape: BoxShape.circle,
            border: Border.all(color: color.withValues(alpha: 0.31), width: 2),
          ),
          child: Icon(icon, size: 50, color: color),
        ),
        if (corner != null) Positioned(right: 0, bottom: 0, child: corner),
      ],
    ),
  );
}

Widget _actionButton({
  required IconData icon,
  required String label,
  required Color color,
  required VoidCallback? onTap,
  double size = 56,
  double iconSize = 22,
}) {
  return Column(
    mainAxisSize: MainAxisSize.min,
    children: [
      Material(
        color: color.withValues(alpha: 0.15),
        shape: CircleBorder(
          side: BorderSide(color: color.withValues(alpha: 0.4), width: 1),
        ),
        child: InkWell(
          onTap: onTap,
          customBorder: const CircleBorder(),
          child: SizedBox(
            width: size,
            height: size,
            child: Icon(icon, size: iconSize, color: color),
          ),
        ),
      ),
      const SizedBox(height: 8),
      Text(
        label,
        style: AppTypography.style(
          color: Colors.white.withValues(alpha: 0.3),
          fontSize: 10,
        ),
      ),
    ],
  );
}

class _CallingView extends StatelessWidget {
  const _CallingView({
    required this.other,
    required this.busy,
    required this.onEnd,
  });

  final IntercomParty other;
  final bool busy;
  final VoidCallback onEnd;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Stack(
          alignment: Alignment.center,
          children: [
            const _PulseRings(color: AppColors.gold),
            _avatarBadge(color: AppColors.gold, icon: Icons.call_rounded),
          ],
        ),
        const SizedBox(height: 29),
        Text(
          'CALLING…',
          style: AppTypography.style(
            color: AppColors.gold,
            fontSize: 11,
            fontWeight: FontWeight.w700,
            letterSpacing: 2.2,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          other.label,
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 32,
            fontWeight: FontWeight.w700,
          ),
        ),
        if ((other.sublabel ?? '').isNotEmpty) ...[
          const SizedBox(height: 4),
          Text(
            other.sublabel!,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 14,
            ),
          ),
        ],
        const SizedBox(height: 40),
        _actionButton(
          icon: Icons.call_end_rounded,
          label: 'End Call',
          color: _redColor,
          onTap: busy ? null : onEnd,
          size: 72,
          iconSize: 26,
        ),
      ],
    );
  }
}

class _ConnectedView extends StatefulWidget {
  const _ConnectedView({
    required this.other,
    required this.answeredAt,
    required this.busy,
    required this.muted,
    required this.speakerOn,
    required this.onToggleMute,
    required this.onToggleSpeaker,
    required this.onEnd,
  });

  final IntercomParty other;
  final DateTime answeredAt;
  final bool busy;

  /// Real state of the WebRTC session's local mic track / speakerphone,
  /// owned by the parent screen (which owns the session itself) rather than
  /// this widget, so it actually reflects what the other side hears.
  final bool muted;
  final bool speakerOn;
  final VoidCallback onToggleMute;
  final VoidCallback onToggleSpeaker;
  final VoidCallback onEnd;

  @override
  State<_ConnectedView> createState() => _ConnectedViewState();
}

class _ConnectedViewState extends State<_ConnectedView> {
  Timer? _ticker;
  Duration _elapsed = Duration.zero;

  @override
  void initState() {
    super.initState();
    _tick();
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) _tick();
    });
  }

  void _tick() {
    setState(
      () => _elapsed = DateTime.now().difference(widget.answeredAt).isNegative
          ? Duration.zero
          : DateTime.now().difference(widget.answeredAt),
    );
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  String get _timeLabel {
    final m = _elapsed.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = _elapsed.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        _avatarBadge(
          color: _greenColor,
          icon: Icons.call_rounded,
          corner: Container(
            width: 28,
            height: 28,
            decoration: const BoxDecoration(
              color: _greenColor,
              shape: BoxShape.circle,
              border: Border.fromBorderSide(
                BorderSide(color: Color(0xFF040810), width: 2),
              ),
            ),
            alignment: Alignment.center,
            child: const SizedBox(
              width: 10,
              height: 10,
              child: DecoratedBox(
                decoration: BoxDecoration(
                  color: Colors.white,
                  shape: BoxShape.circle,
                ),
              ),
            ),
          ),
        ),
        const SizedBox(height: 29),
        Text(
          'CONNECTED',
          style: AppTypography.style(
            color: _greenColor,
            fontSize: 11,
            fontWeight: FontWeight.w700,
            letterSpacing: 2.2,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          widget.other.label,
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 32,
            fontWeight: FontWeight.w700,
          ),
        ),
        if ((widget.other.sublabel ?? '').isNotEmpty) ...[
          const SizedBox(height: 4),
          Text(
            widget.other.sublabel!,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 14,
            ),
          ),
        ],
        const SizedBox(height: 8),
        Text(
          _timeLabel,
          style: AppTypography.style(color: _greenColor, fontSize: 13),
        ),
        const SizedBox(height: 40),
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            _actionButton(
              icon: widget.muted
                  ? Icons.mic_off_rounded
                  : Icons.mic_none_rounded,
              label: 'Mute',
              color: widget.muted ? _redColor : Colors.white,
              onTap: widget.onToggleMute,
            ),
            const SizedBox(width: 24),
            _actionButton(
              icon: Icons.call_end_rounded,
              label: 'End Call',
              color: _redColor,
              onTap: widget.busy ? null : widget.onEnd,
              size: 72,
              iconSize: 26,
            ),
            const SizedBox(width: 24),
            _actionButton(
              icon: widget.speakerOn
                  ? Icons.volume_up_rounded
                  : Icons.volume_down_rounded,
              label: 'Speaker',
              color: widget.speakerOn ? AppColors.goldAccent : Colors.white,
              onTap: widget.onToggleSpeaker,
            ),
          ],
        ),
      ],
    );
  }
}

class _IncomingCallView extends StatelessWidget {
  const _IncomingCallView({
    required this.other,
    required this.busy,
    required this.onAnswer,
    required this.onDecline,
  });

  final IntercomParty other;
  final bool busy;
  final VoidCallback onAnswer;
  final VoidCallback onDecline;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Stack(
          alignment: Alignment.center,
          children: [
            const _PulseRings(color: AppColors.gold),
            _avatarBadge(color: AppColors.gold, icon: Icons.call_rounded),
          ],
        ),
        const SizedBox(height: 42),
        Text(
          'INCOMING INTERCOM CALL',
          style: AppTypography.style(
            color: AppColors.gold,
            fontSize: 10,
            letterSpacing: 2,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          other.label,
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 32,
            fontWeight: FontWeight.w700,
          ),
        ),
        if ((other.sublabel ?? '').isNotEmpty) ...[
          const SizedBox(height: 4),
          Text(
            other.sublabel!,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 14,
            ),
          ),
        ],
        const SizedBox(height: 42),
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            _actionButton(
              icon: Icons.call_end_rounded,
              label: 'Decline',
              color: _redColor,
              onTap: busy ? null : onDecline,
              size: 64,
              iconSize: 24,
            ),
            const SizedBox(width: 32),
            _actionButton(
              icon: Icons.call_rounded,
              label: 'Answer',
              color: _greenColor,
              onTap: busy ? null : onAnswer,
              size: 64,
              iconSize: 24,
            ),
          ],
        ),
      ],
    );
  }
}

class _CallEndedView extends StatelessWidget {
  const _CallEndedView({required this.other, required this.status});

  final IntercomParty other;
  final IntercomCallStatus status;

  String get _label => switch (status) {
    IntercomCallStatus.declined => 'CALL DECLINED',
    IntercomCallStatus.cancelled => 'CALL CANCELLED',
    _ => 'CALL ENDED',
  };

  String get _caption => switch (status) {
    IntercomCallStatus.declined => 'The call was declined.',
    IntercomCallStatus.cancelled => 'The call was cancelled.',
    _ => 'Call ended',
  };

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        _avatarBadge(color: AppColors.gold, icon: Icons.call_rounded),
        const SizedBox(height: 29),
        Text(
          _label,
          style: AppTypography.style(
            color: AppColors.gold,
            fontSize: 11,
            fontWeight: FontWeight.w700,
            letterSpacing: 2.2,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          other.label,
          style: AppTypography.style(
            color: Colors.white,
            fontSize: 32,
            fontWeight: FontWeight.w700,
          ),
        ),
        if ((other.sublabel ?? '').isNotEmpty) ...[
          const SizedBox(height: 4),
          Text(
            other.sublabel!,
            style: AppTypography.style(
              color: Colors.white.withValues(alpha: 0.4),
              fontSize: 14,
            ),
          ),
        ],
        const SizedBox(height: 32),
        Text(
          _caption,
          style: AppTypography.style(
            color: Colors.white.withValues(alpha: 0.4),
            fontSize: 14,
          ),
        ),
      ],
    );
  }
}
