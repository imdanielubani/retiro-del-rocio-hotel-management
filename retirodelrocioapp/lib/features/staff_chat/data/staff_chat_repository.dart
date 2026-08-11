import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/staff_chat/domain/staff_chat_message.dart';
import 'package:retirodelrocioapp/features/staff_chat/domain/staff_channel.dart';

/// Raised when a chat action could not be completed, carrying a user-facing
/// message.
class StaffChatException implements Exception {
  StaffChatException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to the shared Staff Chat endpoints with the signed-in staffer's own
/// JWT — the station is derived from the token server-side, so this is
/// identical whichever tablet (Reception/Housekeeping/Maintenance/Security)
/// instantiates it.
class StaffChatRepository {
  StaffChatRepository({Dio? dio})
    : _dio =
          dio ??
          Dio(
            BaseOptions(
              connectTimeout: const Duration(seconds: 8),
              receiveTimeout: const Duration(seconds: 15),
              sendTimeout: const Duration(seconds: 15),
            ),
          );

  final Dio _dio;

  Options _auth(String token) => Options(
    headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json'},
  );

  Future<List<StaffChannel>> channels(String token) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('staff/chat/channels')),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => StaffChannel.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('StaffChatRepository: channels failed — $error');
      return const [];
    }
  }

  Future<List<StaffChatMessage>> messages(String token, String role) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('staff/chat/channels/$role/messages')),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows
          .map((r) => StaffChatMessage.fromJson((r as Map).cast()))
          .toList();
    } catch (error) {
      debugPrint('StaffChatRepository: messages failed — $error');
      return const [];
    }
  }

  Future<StaffChatMessage> send(String token, String role, String body) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('staff/chat/channels/$role/messages')),
        data: {'body': body},
        options: _auth(token),
      );
      return StaffChatMessage.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw StaffChatException(_messageFrom(error));
    } catch (error) {
      debugPrint('StaffChatRepository: send failed — $error');
      throw StaffChatException(
        'Could not send this message. Please try again.',
      );
    }
  }

  /// Fire-and-forget "I'm typing" signal. Failures are swallowed — a missed
  /// typing indicator is never worth surfacing.
  Future<void> sendTyping(String token, String role) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('staff/chat/channels/$role/typing')),
        options: _auth(token),
      );
    } catch (error) {
      debugPrint('StaffChatRepository: sendTyping failed — $error');
    }
  }

  String _messageFrom(DioException error) {
    final data = error.response?.data;
    if (data is Map && data['errors'] is Map) {
      final errors = (data['errors'] as Map).values;
      if (errors.isNotEmpty &&
          errors.first is List &&
          (errors.first as List).isNotEmpty) {
        return (errors.first as List).first.toString();
      }
    }
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
        return 'Could not send this message. Please try again.';
    }
  }
}
