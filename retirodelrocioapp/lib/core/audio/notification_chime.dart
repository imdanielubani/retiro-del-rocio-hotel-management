import 'dart:math';

import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';

/// A short, pleasant two-note "ding" for a new guest notification arriving.
///
/// Synthesised in Dart and played from bytes, so there is no audio asset to
/// ship — the same approach as `SosAlarm`, just a single quiet chime instead
/// of a looping siren. Every call is guarded: a device with no audio (or a
/// mid-flight dispose) must never crash the realtime signal handler that
/// triggers it.
class NotificationChime {
  final AudioPlayer _player = AudioPlayer(playerId: 'notification-chime');

  /// Play the chime once. Safe to call repeatedly — a busy player is simply
  /// restarted from the top, so a burst of notifications never queues sounds.
  Future<void> play() async {
    try {
      await _player.stop();
      await _player.setReleaseMode(ReleaseMode.stop);
      await _player.setVolume(0.7);
      await _player.play(BytesSource(_chimeWav()));
    } catch (error) {
      debugPrint('NotificationChime: audio failed — $error');
    }
  }

  Future<void> dispose() async {
    try {
      await _player.dispose();
    } catch (_) {}
  }

  /// A ~0.5s two-note chime (a rising major sixth, like a soft doorbell):
  /// 880Hz then 1318Hz, 16-bit PCM mono @ 44.1kHz, each note enveloped so
  /// neither the attack nor the tail clicks.
  Uint8List _chimeWav() {
    const sampleRate = 44100;
    const noteA = 880.0; // Hz — A5
    const noteB = 1318.5; // Hz — E6
    const segment = 0.24; // seconds per note
    const amplitude = 0.5;

    final perSeg = (sampleRate * segment).round();
    final totalSamples = perSeg * 2;
    final attack = (sampleRate * 0.01).round(); // ~10ms
    final release = (sampleRate * 0.12).round(); // ~120ms soft tail

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
    out.setUint16(20, 1, Endian.little); // PCM
    out.setUint16(22, 1, Endian.little); // mono
    out.setUint32(24, sampleRate, Endian.little);
    out.setUint32(28, sampleRate * 2, Endian.little);
    out.setUint16(32, 2, Endian.little);
    out.setUint16(34, 16, Endian.little);
    ascii(36, 'data');
    out.setUint32(40, dataBytes, Endian.little);

    for (var i = 0; i < totalSamples; i++) {
      final inA = i < perSeg;
      final freq = inA ? noteA : noteB;
      final s = inA ? i : i - perSeg;

      var env = 1.0;
      if (s < attack) {
        env = s / attack;
      } else if (s > perSeg - release) {
        env = max(0.0, (perSeg - s) / release);
      }

      final value = amplitude * env * sin(2 * pi * freq * (s / sampleRate));
      final sample = (value * 32767).round().clamp(-32768, 32767);
      out.setInt16(44 + i * 2, sample, Endian.little);
    }

    return out.buffer.asUint8List();
  }
}
