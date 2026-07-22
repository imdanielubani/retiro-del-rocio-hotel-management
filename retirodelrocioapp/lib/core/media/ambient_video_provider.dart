import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/onboarding/data/video_cache_service.dart';
import 'package:video_player/video_player.dart';

/// The ambient hotel video, hosted on Cloudinary (stored there so the large
/// file never needs to live in git). `vc_h264,q_auto` delivers a compact,
/// universally-playable H.264 encoding.
const String kAmbientVideoUrl =
    'https://res.cloudinary.com/pr080it8/video/upload/vc_h264,q_auto/v1783772687/Onboarding-video_xaqjmp.mp4';

/// A single, app-wide ambient video.
///
/// Downloaded from Cloudinary **once** and cached on the device, then played
/// from the local file — so after the first launch it plays fully offline.
/// Kept alive for the whole session so it loops and plays continuously across
/// every screen without restarting.
final ambientVideoProvider = FutureProvider<VideoPlayerController?>((ref) async {
  final cache = VideoCacheService();

  // Download-once → local file (offline on every subsequent launch).
  final file = await cache.getCachedVideo(kAmbientVideoUrl);
  if (file == null) {
    debugPrint('AmbientVideo: not available yet (no cache/network) — fallback.');
    return null;
  }

  final controller = VideoPlayerController.file(file);
  try {
    await controller.initialize();
    await controller.setLooping(true);
    await controller.setVolume(0);
    await controller.play();
    debugPrint('AmbientVideo: playing (${controller.value.size}).');
  } catch (error) {
    debugPrint('AmbientVideo: init FAILED — $error');
    await controller.dispose();
    await cache.evict(); // drop a bad cache so it re-downloads next launch
    return null;
  }

  ref.keepAlive();
  ref.onDispose(controller.dispose);
  return controller;
});
