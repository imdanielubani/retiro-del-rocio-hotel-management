import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/guest/notifications/domain/guest_notification.dart';

/// Raised when a notifications action could not be completed, carrying a
/// user-facing message.
class GuestNotificationException implements Exception {
  GuestNotificationException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to the Notifications endpoints with the tablet's own device token,
/// which is what scopes every call to this room's checked-in guest — the
/// server never trusts a booking/notification id on its own.
class GuestNotificationRepository {
  GuestNotificationRepository({Dio? dio})
    : _dio =
          dio ??
          Dio(
            BaseOptions(
              connectTimeout: const Duration(seconds: 8),
              receiveTimeout: const Duration(seconds: 8),
            ),
          );

  final Dio _dio;

  Options _auth(String deviceToken) => Options(
    headers: {
      'Authorization': 'Bearer $deviceToken',
      'Accept': 'application/json',
    },
  );

  Future<List<GuestNotification>> fetch(String deviceToken) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/notifications')),
        options: _auth(deviceToken),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows
          .map((r) => GuestNotification.fromJson((r as Map).cast()))
          .toList();
    } on DioException catch (error) {
      throw GuestNotificationException(_messageFrom(error));
    } catch (error) {
      debugPrint('GuestNotificationRepository: fetch failed — $error');
      throw GuestNotificationException('Could not load your notifications.');
    }
  }

  Future<void> markRead(String deviceToken, int id) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/notifications/$id/read')),
        options: _auth(deviceToken),
      );
    } on DioException catch (error) {
      throw GuestNotificationException(_messageFrom(error));
    } catch (error) {
      debugPrint('GuestNotificationRepository: markRead failed — $error');
      throw GuestNotificationException('Could not update that notification.');
    }
  }

  Future<void> markAllRead(String deviceToken) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/notifications/read-all')),
        options: _auth(deviceToken),
      );
    } on DioException catch (error) {
      throw GuestNotificationException(_messageFrom(error));
    } catch (error) {
      debugPrint('GuestNotificationRepository: markAllRead failed — $error');
      throw GuestNotificationException('Could not update your notifications.');
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
