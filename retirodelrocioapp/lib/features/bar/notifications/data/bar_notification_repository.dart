import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/bar/domain/bar_notification.dart';

/// Talks to the Bar Tablet's notification-feed endpoints.
class BarNotificationRepository {
  BarNotificationRepository({Dio? dio})
    : _dio =
          dio ??
          Dio(
            BaseOptions(
              connectTimeout: const Duration(seconds: 8),
              receiveTimeout: const Duration(seconds: 8),
            ),
          );

  final Dio _dio;

  Options _auth(String token) => Options(
    headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json'},
  );

  Future<List<BarNotification>> fetch(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/notifications')),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows
          .map((r) => BarNotification.fromJson((r as Map).cast()))
          .toList();
    } catch (error) {
      debugPrint('BarNotificationRepository: fetch failed — $error');
      return const [];
    }
  }

  Future<void> markRead(String token, int id) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/notifications/$id/read')),
        options: _auth(token),
      );
    } catch (error) {
      debugPrint('BarNotificationRepository: markRead failed — $error');
    }
  }

  Future<void> markAllRead(String token) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('bar/notifications/read-all')),
        options: _auth(token),
      );
    } catch (error) {
      debugPrint('BarNotificationRepository: markAllRead failed — $error');
    }
  }
}
