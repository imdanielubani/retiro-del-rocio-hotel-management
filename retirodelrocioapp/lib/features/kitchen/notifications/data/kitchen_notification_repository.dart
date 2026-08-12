import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/kitchen/domain/kitchen_notification.dart';

/// Talks to the Kitchen Tablet's notification-feed endpoints.
class KitchenNotificationRepository {
  KitchenNotificationRepository({Dio? dio})
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

  Future<List<KitchenNotification>> fetch(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('kitchen/notifications')),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows
          .map((r) => KitchenNotification.fromJson((r as Map).cast()))
          .toList();
    } catch (error) {
      debugPrint('KitchenNotificationRepository: fetch failed — $error');
      return const [];
    }
  }

  Future<void> markRead(String token, int id) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('kitchen/notifications/$id/read')),
        options: _auth(token),
      );
    } catch (error) {
      debugPrint('KitchenNotificationRepository: markRead failed — $error');
    }
  }

  Future<void> markAllRead(String token) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('kitchen/notifications/read-all')),
        options: _auth(token),
      );
    } catch (error) {
      debugPrint('KitchenNotificationRepository: markAllRead failed — $error');
    }
  }
}
