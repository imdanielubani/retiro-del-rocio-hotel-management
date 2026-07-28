import 'package:audioplayers/audioplayers.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:retirodelrocioapp/core/audio/notification_chime.dart';
import 'package:retirodelrocioapp/core/audio/sos_alarm.dart';

/// [AudioContextIOS]'s constructor asserts that `mixWithOthers` (and a few
/// other options) may only be combined with the `playback`, `playAndRecord`
/// or `multiRoute` categories — combining it with `ambient` throws in debug
/// builds. `NotificationChime.play()`/`SosAlarm.start()` swallow every error
/// so a sound configured this way fails completely silently: no crash, no
/// visible error, no sound. `flutter test` runs with asserts enabled (the
/// same as a debug build), so simply *accessing* the lazily-built
/// `audioContext` here reproduces exactly what happens the first time the
/// real app tries to play either sound.
void main() {
  test('NotificationChime.audioContext builds without throwing', () {
    expect(() => NotificationChime.audioContext, returnsNormally);
  });

  test('SosAlarm.audioContext builds without throwing', () {
    expect(() => SosAlarm.audioContext, returnsNormally);
  });

  test(
    'an AudioContextIOS combining ambient with mixWithOthers is invalid '
    '(regression guard — this is the exact bug that silenced the chime)',
    () {
      expect(
        () => AudioContextIOS(
          category: AVAudioSessionCategory.ambient,
          options: const {AVAudioSessionOptions.mixWithOthers},
        ),
        throwsA(isA<AssertionError>()),
      );
    },
  );
}
