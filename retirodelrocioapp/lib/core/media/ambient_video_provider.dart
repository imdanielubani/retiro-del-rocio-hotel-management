import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:retirodelrocioapp/features/onboarding/data/onboarding_repository.dart';
import 'package:retirodelrocioapp/features/onboarding/data/video_cache_service.dart';
import 'package:video_player/video_player.dart';

/// A single, app-wide ambient hotel video.
///
/// Initialized once and kept alive for the whole session (`keepAlive`), so it
/// keeps looping and playing **continuously across every screen** — navigating
/// never recreates or restarts it. Cloudinary is storage only: the file is
/// downloaded once and played locally.
final ambientVideoProvider = FutureProvider<VideoPlayerController?>((ref) async {
  ref.keepAlive();
  final cache = VideoCacheService();

  final url = await OnboardingRepository().fetchBackgroundVideoUrl();
  if (url == null) {
    debugPrint('AmbientVideo: no URL — screens use the gradient/image fallback.');
    return null;
  }

  final file = await cache.getCachedVideo(url);
  if (file == null) {
    debugPrint('AmbientVideo: not cached — fallback.');
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

  ref.onDispose(controller.dispose);
  return controller;
});
