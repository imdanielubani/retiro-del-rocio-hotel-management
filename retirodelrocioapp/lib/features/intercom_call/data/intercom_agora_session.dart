import 'dart:async';

import 'package:agora_rtc_engine/agora_rtc_engine.dart';
import 'package:flutter/foundation.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:retirodelrocioapp/features/intercom_call/data/intercom_call_repository.dart';

/// The real audio side of an Intercom call — Agora's managed RTC engine
/// carries the actual peer-to-peer audio (NAT traversal, relay, jitter
/// buffer, echo cancellation); this class only ever joins/leaves the call's
/// channel and exposes mute/speaker controls to the UI.
///
/// One instance per call, owned by [IntercomCallScreen]. Replaces the
/// hand-rolled `flutter_webrtc` offer/answer/ICE signaling this app used to
/// do itself — Agora's SDK negotiates the connection internally once both
/// sides join the same channel with valid credentials, so there is no
/// signal to relay or race to avoid here.
class IntercomAgoraSession {
  IntercomAgoraSession({required this.fetchCredentials});

  /// Fetches this device's Agora join credentials for the call — a network
  /// call, so it's injected rather than assumed available synchronously.
  final Future<AgoraCallCredentials?> Function() fetchCredentials;

  RtcEngine? _engine;
  bool _muted = false;
  bool _disposed = false;

  bool get muted => _muted;

  /// Requests the mic, initializes the Agora engine, and joins the call's
  /// channel. Safe to call once; failures (permission denied, no
  /// credentials) leave the call visually connected but silent rather than
  /// crashing the screen — the same "never block the call flow for an audio
  /// problem" rule every sound class in this app follows.
  Future<void> start() async {
    if (_disposed) return;

    try {
      final micGranted = await Permission.microphone.request();
      if (!micGranted.isGranted) {
        debugPrint('IntercomAgoraSession: microphone permission denied.');
        return;
      }
      // Android 12+ split plain BLUETOOTH into runtime-checked permissions.
      // Agora's SDK probes for a connected Bluetooth headset as part of its
      // audio routing on every call, even on devices that never pair one —
      // without this granted, that internal check can silently break audio
      // routing while join/leave and connection state still look fine. Not
      // fatal if denied (these tablets have no Bluetooth headset anyway),
      // so failures here never block the call.
      await Permission.bluetoothConnect.request();

      final credentials = await fetchCredentials();
      if (_disposed || credentials == null) {
        if (credentials == null) {
          debugPrint('IntercomAgoraSession: no credentials — cannot join.');
        }
        return;
      }

      final engine = createAgoraRtcEngine();
      await engine.initialize(
        RtcEngineContext(
          appId: credentials.appId,
          channelProfile: ChannelProfileType.channelProfileCommunication,
        ),
      );
      if (_disposed) {
        await engine.release();
        return;
      }
      _engine = engine;

      engine.registerEventHandler(
        RtcEngineEventHandler(
          onJoinChannelSuccess: (connection, elapsed) {
            debugPrint('IntercomAgoraSession: joined channel.');
          },
          onUserJoined: (connection, remoteUid, elapsed) {
            debugPrint(
              'IntercomAgoraSession: remote user joined — $remoteUid.',
            );
          },
          onUserOffline: (connection, remoteUid, reason) {
            debugPrint('IntercomAgoraSession: remote user left — $remoteUid.');
          },
          onConnectionStateChanged: (connection, state, reason) {
            debugPrint(
              'IntercomAgoraSession: connection state — $state ($reason).',
            );
          },
          onError: (err, msg) {
            debugPrint('IntercomAgoraSession: engine error — $err $msg.');
          },
        ),
      );

      await engine.enableAudio();

      // publishMicrophoneTrack/autoSubscribeAudio are NOT implied by
      // clientRoleType/channelProfile alone — Agora's own quickstart sets
      // both explicitly on every joinChannel call. Leaving them unset was
      // an earlier cause of "connects but nobody hears anything": the
      // engine joined the channel but never published this device's mic
      // track nor subscribed to the other side's.
      await engine.joinChannel(
        token: credentials.token ?? '',
        channelId: credentials.channel,
        uid: credentials.uid,
        options: const ChannelMediaOptions(
          clientRoleType: ClientRoleType.clientRoleBroadcaster,
          channelProfile: ChannelProfileType.channelProfileCommunication,
          publishMicrophoneTrack: true,
          autoSubscribeAudio: true,
        ),
      );
    } catch (error) {
      debugPrint('IntercomAgoraSession: start failed — $error');
      return;
    }

    // Speaker defaults on — these are wall/desk-mounted hotel tablets with
    // no earpiece a guest could hold up. Deliberately OUTSIDE the try/catch
    // above and routed through setSpeaker()'s own error handling: calling
    // setEnableSpeakerphone() immediately after enableAudio() can throw
    // AgoraRtcException(-3, ERR_NOT_READY) — the audio routing module isn't
    // ready for a route change that fast — and when that call shared the
    // same try block as joinChannel(), the exception aborted the whole
    // start() sequence before joinChannel() ever ran. The two tablets never
    // actually joined the channel, which looked exactly like "connects but
    // nobody hears anything" but was really "never connects at all".
    unawaited(setSpeaker(true));
  }

  /// Mutes/unmutes the local mic stream. Returns the new muted state.
  bool toggleMute() {
    _muted = !_muted;
    unawaited(_engine?.muteLocalAudioStream(_muted));
    return _muted;
  }

  Future<void> setSpeaker(bool on) async {
    try {
      await _engine?.setEnableSpeakerphone(on);
    } catch (error) {
      debugPrint('IntercomAgoraSession: setSpeaker failed — $error');
    }
  }

  /// Leaves the channel and releases the engine. Safe to call more than
  /// once and safe to call mid-[start].
  Future<void> dispose() async {
    if (_disposed) return;
    _disposed = true;

    final engine = _engine;
    _engine = null;
    if (engine == null) return;

    try {
      await engine.leaveChannel();
      await engine.release();
    } catch (error) {
      debugPrint('IntercomAgoraSession: dispose failed — $error');
    }
  }
}
