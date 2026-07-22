import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';

/// Fetches the onboarding background video URL from the backend
/// (`GET /v1/app/config`), which resolves it from Cloudinary via the server's
/// `.env`. Returns `null` when unavailable so the UI can fall back gracefully.
class OnboardingRepository {
  OnboardingRepository({Dio? dio})
      : _dio = dio ??
            Dio(BaseOptions(
              connectTimeout: const Duration(seconds: 8),
              receiveTimeout: const Duration(seconds: 8),
            ));

  final Dio _dio;

  Future<String?> fetchBackgroundVideoUrl() async {
    final endpoint = ApiConfig.endpoint('app/config');
    try {
      debugPrint('OnboardingRepository: GET $endpoint');
      final response = await _dio.getUri<Map<String, dynamic>>(Uri.parse(endpoint));
      final data = response.data?['data'] as Map<String, dynamic>?;
      final onboarding = data?['onboarding'] as Map<String, dynamic>?;
      final url = onboarding?['video_url'] as String?;
      debugPrint('OnboardingRepository: video_url = $url');
      return (url != null && url.isNotEmpty) ? url : null;
    } catch (error, stackTrace) {
      debugPrint('OnboardingRepository: config fetch FAILED ($endpoint) — $error');
      debugPrintStack(stackTrace: stackTrace);
      return null;
    }
  }
}
