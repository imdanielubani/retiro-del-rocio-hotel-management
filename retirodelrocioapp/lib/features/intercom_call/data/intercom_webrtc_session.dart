import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:permission_handler/permission_handler.dart';

/// The real audio side of an Intercom call — everything the signaling-only
/// [IntercomCall] state machine doesn't do: capturing this device's mic,
/// exchanging the SDP offer/answer and ICE candidates needed to open a
/// direct peer-to-peer connection to the other tablet, and playing back
/// whatever audio arrives on it.
///
/// One instance per call, owned by [IntercomCallScreen]. [isCaller] decides
/// who creates the offer — deterministic from the call's own `from`/`to`,
/// so there's never a race to negotiate. Every inbound signal is buffered
/// until this side's own setup ([start]) has finished, and every inbound
/// ICE candidate is buffered until the remote description is set — both
/// arrive over an independent realtime channel with no ordering guarantee
/// relative to this device's own async mic/peer-connection setup.
class IntercomWebRtcSession {
  IntercomWebRtcSession({required this.isCaller, required this.sendSignal});

  final bool isCaller;

  /// Sends one signal (an offer/answer/ICE candidate) to the other side of
  /// the call. Never throws — a dropped signal loses at most one candidate
  /// or, worst case, means the call falls back to no audio; it never blocks
  /// the ringing/answer/hang-up flow, which is why [IntercomCallRepository.
  /// signal] itself already swallows its own failures.
  final Future<void> Function(String type, Map<String, dynamic> data)
  sendSignal;

  static const _configuration = {
    'iceServers': [
      {'urls': 'stun:stun.l.google.com:19302'},
      {'urls': 'stun:stun1.l.google.com:19302'},
    ],
  };

  final Completer<void> _ready = Completer<void>();
  final List<RTCIceCandidate> _pendingCandidates = [];

  RTCPeerConnection? _pc;
  MediaStream? _localStream;
  bool _remoteDescriptionSet = false;
  bool _muted = false;
  bool _disposed = false;

  bool get muted => _muted;

  /// Requests the mic, opens the peer connection, and — if [isCaller] —
  /// creates and sends the offer. Safe to call once; failures (permission
  /// denied, no mic) leave the call visually connected but silent rather
  /// than crashing the screen — the same "never block the call flow for an
  /// audio problem" rule every sound class in this app follows.
  Future<void> start() async {
    if (_disposed) return;

    try {
      final micGranted = await Permission.microphone.request();
      if (!micGranted.isGranted) {
        debugPrint('IntercomWebRtcSession: microphone permission denied.');
        if (!_ready.isCompleted) _ready.complete();
        return;
      }

      final pc = await createPeerConnection(_configuration);
      if (_disposed) {
        await pc.close();
        return;
      }
      _pc = pc;

      pc.onIceCandidate = (candidate) {
        final value = candidate.candidate;
        if (value == null || value.isEmpty) return;
        unawaited(
          sendSignal(
            'ice-candidate',
            candidate.toMap() as Map<String, dynamic>,
          ),
        );
      };

      final stream = await navigator.mediaDevices.getUserMedia({
        'audio': true,
        'video': false,
      });
      if (_disposed) {
        await stream.dispose();
        return;
      }
      _localStream = stream;
      for (final track in stream.getAudioTracks()) {
        await pc.addTrack(track, stream);
      }

      if (!_ready.isCompleted) _ready.complete();

      if (isCaller) {
        final offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        await sendSignal('offer', offer.toMap() as Map<String, dynamic>);
      }
    } catch (error) {
      debugPrint('IntercomWebRtcSession: start failed — $error');
      if (!_ready.isCompleted) _ready.complete();
    }
  }

  /// Feed one signal received from the other side. Waits for [start] to
  /// finish setting up this side first (see class doc), so an offer that
  /// arrives while this device is still requesting the mic is never lost.
  Future<void> handleRemoteSignal(
    String type,
    Map<String, dynamic> data,
  ) async {
    if (_disposed) return;
    await _ready.future;

    final pc = _pc;
    if (pc == null || _disposed) return;

    try {
      switch (type) {
        case 'offer':
        case 'answer':
          await pc.setRemoteDescription(
            RTCSessionDescription(
              data['sdp'] as String?,
              data['type'] as String?,
            ),
          );
          _remoteDescriptionSet = true;
          for (final candidate in _pendingCandidates) {
            await pc.addCandidate(candidate);
          }
          _pendingCandidates.clear();

          if (type == 'offer') {
            final answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            await sendSignal('answer', answer.toMap() as Map<String, dynamic>);
          }
        case 'ice-candidate':
          final candidate = RTCIceCandidate(
            data['candidate'] as String?,
            data['sdpMid'] as String?,
            (data['sdpMLineIndex'] as num?)?.toInt(),
          );
          if (_remoteDescriptionSet) {
            await pc.addCandidate(candidate);
          } else {
            _pendingCandidates.add(candidate);
          }
      }
    } catch (error) {
      debugPrint('IntercomWebRtcSession: handleRemoteSignal failed — $error');
    }
  }

  /// Flips the local mic track's `enabled` flag — the other side simply
  /// stops receiving audio, no renegotiation needed. Returns the new muted
  /// state.
  bool toggleMute() {
    _muted = !_muted;
    for (final track in _localStream?.getAudioTracks() ?? const []) {
      track.enabled = !_muted;
    }
    return _muted;
  }

  Future<void> setSpeaker(bool on) async {
    try {
      await Helper.setSpeakerphoneOn(on);
    } catch (error) {
      debugPrint('IntercomWebRtcSession: setSpeaker failed — $error');
    }
  }

  /// Releases the mic and closes the peer connection. Safe to call more
  /// than once and safe to call mid-[start].
  Future<void> dispose() async {
    if (_disposed) return;
    _disposed = true;

    try {
      for (final track in _localStream?.getTracks() ?? const []) {
        await track.stop();
      }
      await _localStream?.dispose();
    } catch (error) {
      debugPrint(
        'IntercomWebRtcSession: releasing local stream failed — $error',
      );
    }
    _localStream = null;

    try {
      await _pc?.close();
    } catch (error) {
      debugPrint(
        'IntercomWebRtcSession: closing peer connection failed — $error',
      );
    }
    _pc = null;
  }
}
