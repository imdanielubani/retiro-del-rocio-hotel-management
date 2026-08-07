import 'dart:async';
import 'dart:math';

import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// A looping phone-style ring for an incoming Intercom call.
///
/// Synthesised in Dart and played from bytes, same approach as
/// `SosAlarm` (`core/audio/sos_alarm.dart`) — no audio asset to ship. Two
/// short tones then a pause,
/// baked into one WAV and looped, giving the classic "ring… ring…" cadence
/// for free. A gentler haptic pulse than the SOS siren's, since a call is
/// routine, not an emergency.
class IncomingCallRingtone {
  final AudioPlayer _player = AudioPlayer(playerId: 'incoming-call-ringtone');
  Timer? _haptics;
  bool _started = false;

  /// `notificationRingtone` routes this to the device's ringtone stream —
  /// the one that respects the tablet's ringer volume — and `audioFocus:
  /// none` skips the focus negotiation that can silently stall playback
  /// (see `NotificationChime`/`SosAlarm`, which hit the same bug).
  static final audioContext = AudioContext(
    android: const AudioContextAndroid(
      contentType: AndroidContentType.sonification,
      usageType: AndroidUsageType.notificationRingtone,
      audioFocus: AndroidAudioFocus.none,
    ),
    iOS: AudioContextIOS(
      category: AVAudioSessionCategory.playback,
      options: const {AVAudioSessionOptions.mixWithOthers},
    ),
  );

  /// Start ringing (idempotent).
  Future<void> start() async {
    if (_started) return;
    _started = true;

    try {
      await _player.setReleaseMode(ReleaseMode.loop);
      await _player.play(BytesSource(_ringWav()), volume: 1, ctx: audioContext);
    } catch (error) {
      debugPrint('IncomingCallRingtone: audio failed — $error');
    }

    HapticFeedback.mediumImpact();
    _haptics = Timer.periodic(
      const Duration(milliseconds: 1600),
      (_) => HapticFeedback.mediumImpact(),
    );
  }

  /// Silence the ring and stop the haptics.
  Future<void> stop() async {
    _haptics?.cancel();
    _haptics = null;
    _started = false;
    try {
      await _player.stop();
    } catch (_) {}
  }

  Future<void> dispose() async {
    await stop();
    try {
      await _player.dispose();
    } catch (_) {}
  }

  /// A ~1.6s "ring… ring…" loop: two 220ms tones, then silence to fill out
  /// the loop, 16-bit PCM mono @ 44.1kHz, with short fades so the loop
  /// doesn't click.
  Uint8List _ringWav() {
    const sampleRate = 44100;
    const tone = 1200.0; // Hz — a bright, phone-like ring
    const toneLen = 0.22; // seconds per ring
    const gap = 0.12; // seconds between the two rings
    const tail = 1.0; // seconds of silence closing out the loop
    const amplitude = 0.75;

    final toneSamples = (sampleRate * toneLen).round();
    final gapSamples = (sampleRate * gap).round();
    final tailSamples = (sampleRate * tail).round();
    final totalSamples = toneSamples * 2 + gapSamples + tailSamples;
    final fade = (sampleRate * 0.01).round(); // ~10ms anti-click ramp

    final dataBytes = totalSamples * 2; // 16-bit
    final out = ByteData(44 + dataBytes);

    void ascii(int offset, String s) {
      for (var i = 0; i < s.length; i++) {
        out.setUint8(offset + i, s.codeUnitAt(i));
      }
    }

    ascii(0, 'RIFF');
    out.setUint32(4, 36 + dataBytes, Endian.little);
    ascii(8, 'WAVE');
    ascii(12, 'fmt ');
    out.setUint32(16, 16, Endian.little);
    out.setUint16(20, 1, Endian.little);
    out.setUint16(22, 1, Endian.little);
    out.setUint32(24, sampleRate, Endian.little);
    out.setUint32(28, sampleRate * 2, Endian.little);
    out.setUint16(32, 2, Endian.little);
    out.setUint16(34, 16, Endian.little);
    ascii(36, 'data');
    out.setUint32(40, dataBytes, Endian.little);

    int cursor = 0;
    void writeTone() {
      for (var s = 0; s < toneSamples; s++, cursor++) {
        var env = 1.0;
        if (s < fade) {
          env = s / fade;
        } else if (s > toneSamples - fade) {
          env = (toneSamples - s) / fade;
        }
        final value = amplitude * env * sin(2 * pi * tone * (s / sampleRate));
        final sample = (value * 32767).round().clamp(-32768, 32767);
        out.setInt16(44 + cursor * 2, sample, Endian.little);
      }
    }

    void writeSilence(int samples) {
      for (var s = 0; s < samples; s++, cursor++) {
        out.setInt16(44 + cursor * 2, 0, Endian.little);
      }
    }

    writeTone();
    writeSilence(gapSamples);
    writeTone();
    writeSilence(tailSamples);

    return out.buffer.asUint8List();
  }
}
