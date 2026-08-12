import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/maintenance_notification.dart';

/// Raised when a notifications action could not be completed, carrying a
/// user-facing message.
class MaintenanceNotificationException implements Exception {
  MaintenanceNotificationException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to maintenance's Notifications endpoints with the signed-in
/// technician's staff JWT — the role is enforced server-side on every call.
class MaintenanceNotificationRepository {
  MaintenanceNotificationRepository({Dio? dio})
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

  Future<List<MaintenanceNotification>> fetch(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/notifications')),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows
          .map((r) => MaintenanceNotification.fromJson((r as Map).cast()))
          .toList();
    } on DioException catch (error) {
      throw MaintenanceNotificationException(_messageFrom(error));
    } catch (error) {
      debugPrint('MaintenanceNotificationRepository: fetch failed — $error');
      throw MaintenanceNotificationException(
        'Could not load the notifications.',
      );
    }
  }

  Future<void> markRead(String token, int id) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/notifications/$id/read')),
        options: _auth(token),
      );
    } on DioException catch (error) {
      throw MaintenanceNotificationException(_messageFrom(error));
    } catch (error) {
      debugPrint('MaintenanceNotificationRepository: markRead failed — $error');
      throw MaintenanceNotificationException(
        'Could not update that notification.',
      );
    }
  }

  Future<void> markAllRead(String token) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('maintenance/notifications/read-all')),
        options: _auth(token),
      );
    } on DioException catch (error) {
      throw MaintenanceNotificationException(_messageFrom(error));
    } catch (error) {
      debugPrint(
        'MaintenanceNotificationRepository: markAllRead failed — $error',
      );
      throw MaintenanceNotificationException(
        'Could not update the notifications.',
      );
    }
  }

  String _messageFrom(DioException error) {
    final data = error.response?.data;
    if (data is Map &&
        data['message'] is String &&
        (data['message'] as String).isNotEmpty) {
      return data['message'] as String;
    }
    if (error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout) {
      return 'No connection. Please try again.';
    }
    return 'Something went wrong. Please try again.';
  }
}
