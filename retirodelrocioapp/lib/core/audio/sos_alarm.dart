import 'dart:async';
import 'dart:math';

import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// A loud, looping emergency siren for the security SOS trigger.
///
/// The two-tone "nee-naw" waveform is synthesised in Dart and played from bytes,
/// so there is no audio asset to ship. It loops until [stop]/[dispose], and
/// pulses the tablet with haptics alongside the sound. Every call is guarded —
/// a device with no audio must never take the alert overlay down with it.
class SosAlarm {
  /// No fixed `playerId` — see `NotificationChime`'s own doc for why: a
  /// hardcoded id makes every instance share one native player, which
  /// silently breaks playback the moment a second instance exists. Only one
  /// `SosAlarm` is alive at a time today, but there's no reason to leave the
  /// same trap in a second sound class.
  final AudioPlayer _player = AudioPlayer();
  Timer? _haptics;
  bool _started = false;

  /// The default [AudioContextAndroid] requests [AndroidAudioFocus.gain] —
  /// exclusive, indefinite focus intended for music/video — before every
  /// playback. That negotiation can silently stall or be denied, which is
  /// exactly the kind of failure an emergency siren cannot afford (see
  /// `NotificationChime`, which hit the same bug). `usageType: alarm` routes
  /// this to the device's alarm stream — the one stream that bypasses ringer
  /// mode and Do Not Disturb — and `audioFocus: none` skips the negotiation
  /// entirely, so the siren starts immediately every time.
  static final audioContext = AudioContext(
    android: const AudioContextAndroid(
      contentType: AndroidContentType.sonification,
      usageType: AndroidUsageType.alarm,
      audioFocus: AndroidAudioFocus.none,
    ),
    iOS: AudioContextIOS(
      category: AVAudioSessionCategory.playback,
      options: const {AVAudioSessionOptions.mixWithOthers},
    ),
  );

  /// Start the siren (idempotent).
  Future<void> start() async {
    if (_started) return;
    _started = true;

    try {
      await _player.setReleaseMode(ReleaseMode.loop);
      await _player.play(BytesSource(_sirenWav()), volume: 1, ctx: audioContext);
    } catch (error) {
      debugPrint('SosAlarm: audio failed — $error');
    }

    // Pulse in time with the siren, so the alert is felt as well as heard.
    HapticFeedback.heavyImpact();
    _haptics = Timer.periodic(
      const Duration(milliseconds: 700),
      (_) => HapticFeedback.heavyImpact(),
    );
  }

  /// Silence the siren and stop the haptics.
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

  /// A ~1.4s emergency siren: two alternating tones, 16-bit PCM mono @ 44.1kHz,
  /// with short fades at each tone's edges so the loop does not click.
  Uint8List _sirenWav() {
    const sampleRate = 44100;
    const toneA = 740.0; // Hz
    const toneB = 988.0; // Hz
    const segment = 0.35; // seconds per tone
    const amplitude = 0.78;

    final perSeg = (sampleRate * segment).round();
    final totalSamples = perSeg * 2;
    final fade = (sampleRate * 0.008).round(); // ~8ms anti-click ramp

    final dataBytes = totalSamples * 2; // 16-bit
    final out = ByteData(44 + dataBytes);

    void ascii(int offset, String s) {
      for (var i = 0; i < s.length; i++) {
        out.setUint8(offset + i, s.codeUnitAt(i));
      }
    }

    // RIFF / WAVE header (PCM, mono, 16-bit).
    ascii(0, 'RIFF');
    out.setUint32(4, 36 + dataBytes, Endian.little);
    ascii(8, 'WAVE');
    ascii(12, 'fmt ');
    out.setUint32(16, 16, Endian.little); // subchunk size
    out.setUint16(20, 1, Endian.little); // PCM
    out.setUint16(22, 1, Endian.little); // mono
    out.setUint32(24, sampleRate, Endian.little);
    out.setUint32(28, sampleRate * 2, Endian.little); // byte rate
    out.setUint16(32, 2, Endian.little); // block align
    out.setUint16(34, 16, Endian.little); // bits per sample
    ascii(36, 'data');
    out.setUint32(40, dataBytes, Endian.little);

    for (var i = 0; i < totalSamples; i++) {
      final inA = i < perSeg;
      final freq = inA ? toneA : toneB;
      final s = inA ? i : i - perSeg; // sample index within the segment

      var env = 1.0;
      if (s < fade) {
        env = s / fade;
      } else if (s > perSeg - fade) {
        env = (perSeg - s) / fade;
      }

      final value = amplitude * env * sin(2 * pi * freq * (s / sampleRate));
      final sample = (value * 32767).round().clamp(-32768, 32767);
      out.setInt16(44 + i * 2, sample, Endian.little);
    }

    return out.buffer.asUint8List();
  }
}
