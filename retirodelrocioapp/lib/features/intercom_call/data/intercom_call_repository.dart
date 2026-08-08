import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/intercom_call/domain/intercom_call.dart';

/// Raised when a call action could not be completed, carrying a user-facing
/// message.
class IntercomCallException implements Exception {
  IntercomCallException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to the Intercom call endpoints — shared by the guest tablet
/// (`intercom/calls/*`) and Reception (`reception/intercom/calls/*`), which
/// are otherwise identical in shape. [basePath] picks which audience this
/// instance speaks for.
class IntercomCallRepository {
  IntercomCallRepository({required this.basePath, Dio? dio})
    : _dio =
          dio ??
          Dio(
            BaseOptions(
              connectTimeout: const Duration(seconds: 8),
              receiveTimeout: const Duration(seconds: 15),
              sendTimeout: const Duration(seconds: 15),
            ),
          );

  final String basePath;
  final Dio _dio;

  Options _auth(String token) => Options(
    headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json'},
  );

  /// GET .../current — this identity's active call, either side, or null.
  Future<IntercomCall?> current(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('$basePath/current')),
        options: _auth(token),
      );
      final data = response.data?['data'];
      return data is Map ? IntercomCall.fromJson(data.cast()) : null;
    } catch (error) {
      debugPrint('IntercomCallRepository: current failed — $error');
      return null;
    }
  }

  /// POST .../ — place a call. [body] carries whatever the audience needs
  /// to identify the target (nothing for the guest tablet, which can only
  /// call Reception; `{'booking_id': ...}` for Reception calling a room).
  Future<IntercomCall> place(String token, [Map<String, dynamic>? body]) =>
      _action(token, '', body ?? const {});

  Future<IntercomCall> answer(String token, int callId) =>
      _action(token, '/$callId/answer', const {});

  Future<IntercomCall> decline(String token, int callId) =>
      _action(token, '/$callId/decline', const {});

  Future<IntercomCall> end(String token, int callId) =>
      _action(token, '/$callId/end', const {});

  /// Relay a WebRTC offer/answer/ICE candidate to the other side of
  /// [callId]. Fire-and-forget from the caller's point of view — a dropped
  /// signal just means that one candidate/attempt is lost, not that the
  /// whole call fails, so failures are swallowed rather than surfaced.
  Future<void> signal(
    String token,
    int callId,
    String type,
    Map<String, dynamic> data,
  ) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('$basePath/$callId/signal')),
        data: {'type': type, 'data': data},
        options: _auth(token),
      );
    } catch (error) {
      debugPrint('IntercomCallRepository: signal failed — $error');
    }
  }

  Future<IntercomCall> _action(
    String token,
    String suffix,
    Map<String, dynamic> body,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('$basePath$suffix')),
        data: body,
        options: _auth(token),
      );
      return IntercomCall.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw IntercomCallException(_messageFrom(error));
    } catch (error) {
      debugPrint('IntercomCallRepository: action failed — $error');
      throw IntercomCallException('Something went wrong. Please try again.');
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
