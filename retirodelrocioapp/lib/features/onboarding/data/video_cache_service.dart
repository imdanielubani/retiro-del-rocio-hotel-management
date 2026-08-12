import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:path_provider/path_provider.dart';

/// Downloads a remote video once and caches it on the device, then serves the
/// local file on every subsequent launch.
///
/// Cloudinary is used purely as origin **storage** — the app does not stream
/// from it at runtime. A sidecar `.url` file records the source URL so the
/// cache is refreshed automatically when the URL changes.
class VideoCacheService {
  VideoCacheService({Dio? dio})
    : _dio =
          dio ??
          Dio(
            BaseOptions(
              connectTimeout: const Duration(seconds: 15),
              receiveTimeout: const Duration(minutes: 3),
            ),
          );

  final Dio _dio;

  /// A real video is far larger than this; anything smaller is treated as a
  /// failed/partial download (e.g. a "still transcoding" placeholder response)
  /// and is not cached — guarding against a poisoned cache.
  static const int _minValidBytes = 200 * 1024; // 200 KB

  /// Returns the local cached file for [remoteUrl], downloading it first if the
  /// cache is missing or points at a different URL. Returns `null` on failure.
  Future<File?> getCachedVideo(
    String remoteUrl, {
    String name = 'onboarding',
  }) async {
    try {
      final dir = await getApplicationSupportDirectory();
      final file = File('${dir.path}/$name.mp4');
      final marker = File('${dir.path}/$name.url');

      if (await _isValidCache(file, marker, remoteUrl)) {
        debugPrint('VideoCache: hit ${file.path}');
        return file;
      }

      debugPrint('VideoCache: downloading $remoteUrl');
      final tmp = File('${dir.path}/$name.download');
      await _dio.download(remoteUrl, tmp.path);

      final size = await tmp.exists() ? await tmp.length() : 0;
      if (size < _minValidBytes) {
        debugPrint(
          'VideoCache: download too small ($size bytes) — discarding.',
        );
        if (await tmp.exists()) await tmp.delete();
        return null;
      }

      if (await file.exists()) await file.delete();
      await tmp.rename(file.path);
      await marker.writeAsString(remoteUrl);
      debugPrint('VideoCache: saved ${file.path} ($size bytes)');
      return file;
    } catch (error, stackTrace) {
      debugPrint('VideoCache: failed — $error');
      debugPrintStack(stackTrace: stackTrace);
      return null;
    }
  }

  /// Deletes the cached file so it is re-downloaded next time. Call this when a
  /// cached file fails to play (e.g. it was cached before finishing transcoding).
  Future<void> evict({String name = 'onboarding'}) async {
    try {
      final dir = await getApplicationSupportDirectory();
      for (final f in [
        File('${dir.path}/$name.mp4'),
        File('${dir.path}/$name.url'),
      ]) {
        if (await f.exists()) await f.delete();
      }
      debugPrint('VideoCache: evicted "$name".');
    } catch (error) {
      debugPrint('VideoCache: evict failed — $error');
    }
  }

  Future<bool> _isValidCache(File file, File marker, String remoteUrl) async {
    if (!await file.exists() || await file.length() < _minValidBytes)
      return false;
    if (!await marker.exists()) return false;
    return (await marker.readAsString()).trim() == remoteUrl;
  }
}
