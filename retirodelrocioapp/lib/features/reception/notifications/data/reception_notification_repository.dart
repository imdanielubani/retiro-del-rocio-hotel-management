import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/reception/notifications/domain/reception_notification.dart';

/// Raised when a notifications action could not be completed, carrying a
/// user-facing message.
class ReceptionNotificationException implements Exception {
  ReceptionNotificationException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to the reception Notifications endpoints with the signed-in
/// receptionist's staff JWT — the role is enforced server-side on every call.
class ReceptionNotificationRepository {
  ReceptionNotificationRepository({Dio? dio})
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

  Future<List<ReceptionNotification>> fetch(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/notifications')),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows
          .map((r) => ReceptionNotification.fromJson((r as Map).cast()))
          .toList();
    } on DioException catch (error) {
      throw ReceptionNotificationException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionNotificationRepository: fetch failed — $error');
      throw ReceptionNotificationException(
        'Could not load the notifications.',
      );
    }
  }

  Future<void> markRead(String token, int id) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/notifications/$id/read')),
        options: _auth(token),
      );
    } on DioException catch (error) {
      throw ReceptionNotificationException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionNotificationRepository: markRead failed — $error');
      throw ReceptionNotificationException(
        'Could not update that notification.',
      );
    }
  }

  Future<void> markAllRead(String token) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/notifications/read-all')),
        options: _auth(token),
      );
    } on DioException catch (error) {
      throw ReceptionNotificationException(_messageFrom(error));
    } catch (error) {
      debugPrint(
        'ReceptionNotificationRepository: markAllRead failed — $error',
      );
      throw ReceptionNotificationException(
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
    switch (error.type) {
      case DioExceptionType.connectionError:
      case DioExceptionType.connectionTimeout:
        return 'No connection. Please try again.';
      default:
        return 'Something went wrong. Please try again.';
    }
  }
}
