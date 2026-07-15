import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/guest/sos/domain/sos_alert.dart';

/// Raised when an emergency could not be sent, carrying a user-facing [message].
class SosException implements Exception {
  SosException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to the SOS endpoints with the tablet's own device token, which is what
/// scopes every call to this room.
class SosRepository {
  SosRepository({Dio? dio})
      : _dio = dio ??
            Dio(BaseOptions(
              connectTimeout: const Duration(seconds: 8),
              receiveTimeout: const Duration(seconds: 8),
            ));

  final Dio _dio;

  Options _auth(String deviceToken) => Options(headers: {
        'Authorization': 'Bearer $deviceToken',
        'Accept': 'application/json',
      });

  /// The open alert for this room, if any. Called at launch so a tablet that
  /// restarted mid-emergency comes back showing "Help is on the way".
  Future<SosAlert?> active(String deviceToken) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('sos/active')),
        options: _auth(deviceToken),
      );
      final data = response.data?['data'];
      return data is Map
          ? SosAlert.fromJson(data.cast<String, dynamic>())
          : null;
    } catch (error) {
      debugPrint('SosRepository: active fetch failed — $error');
      return null;
    }
  }

  /// Raise the emergency. The backend is idempotent, so pressing twice cannot
  /// bury the real alert under duplicates.
  Future<SosAlert> raise(String deviceToken) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('sos')),
        options: _auth(deviceToken),
      );
      return SosAlert.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw SosException(_messageFrom(error));
    } catch (error) {
      debugPrint('SosRepository: raise failed — $error');
      throw SosException('Could not send the alert. Please call reception.');
    }
  }

  /// The guest stands the alert down.
  Future<SosAlert> cancel(String deviceToken, int alertId) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('sos/$alertId/cancel')),
        options: _auth(deviceToken),
      );
      return SosAlert.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw SosException(_messageFrom(error));
    } catch (error) {
      debugPrint('SosRepository: cancel failed — $error');
      throw SosException('Could not cancel the alert.');
    }
  }

  String _messageFrom(DioException error) {
    final data = error.response?.data;
    if (data is Map && data['message'] is String && (data['message'] as String).isNotEmpty) {
      return data['message'] as String;
    }
    if (error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout) {
      // The one message that matters when the network is gone.
      return 'No connection. Please call reception on the room phone.';
    }
    return 'Could not send the alert. Please call reception.';
  }
}
