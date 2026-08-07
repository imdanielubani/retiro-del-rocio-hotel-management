import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/reception/chat/domain/reception_chat_message.dart';
import 'package:retirodelrocioapp/features/reception/chat/domain/reception_conversation.dart';

/// Raised when a chat action could not be completed, carrying a user-facing
/// message.
class ReceptionChatException implements Exception {
  ReceptionChatException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to Reception's Chat endpoints with the signed-in receptionist's
/// staff token — the role is enforced server-side on every call.
class ReceptionChatRepository {
  ReceptionChatRepository({Dio? dio})
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

  Future<List<ReceptionGuestConversation>> guestConversations(
    String token,
  ) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/chat/guests')),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows
          .map((r) => ReceptionGuestConversation.fromJson((r as Map).cast()))
          .toList();
    } catch (error) {
      debugPrint('ReceptionChatRepository: guestConversations failed — $error');
      return const [];
    }
  }

  Future<List<ReceptionStaffConversation>> staffConversations(
    String token,
  ) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('reception/chat/staff')),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows
          .map((r) => ReceptionStaffConversation.fromJson((r as Map).cast()))
          .toList();
    } catch (error) {
      debugPrint('ReceptionChatRepository: staffConversations failed — $error');
      return const [];
    }
  }

  Future<List<ReceptionChatMessage>> guestMessages(
    String token,
    int bookingId,
  ) => _messages(token, 'reception/chat/guests/$bookingId/messages');

  Future<List<ReceptionChatMessage>> staffMessages(
    String token,
    String department,
  ) => _messages(token, 'reception/chat/staff/$department/messages');

  Future<List<ReceptionChatMessage>> _messages(
    String token,
    String path,
  ) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint(path)),
        options: _auth(token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows
          .map((r) => ReceptionChatMessage.fromJson((r as Map).cast()))
          .toList();
    } catch (error) {
      debugPrint('ReceptionChatRepository: messages failed — $error');
      return const [];
    }
  }

  Future<ReceptionChatMessage> sendToGuest(
    String token,
    int bookingId,
    String body,
  ) => _send(token, 'reception/chat/guests/$bookingId/messages', body);

  Future<ReceptionChatMessage> sendToDepartment(
    String token,
    String department,
    String body,
  ) => _send(token, 'reception/chat/staff/$department/messages', body);

  /// Fire-and-forget "I'm typing" signal to a guest. Failures are swallowed
  /// — a missed typing indicator is never worth surfacing to reception.
  Future<void> sendTypingToGuest(String token, int bookingId) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(
          ApiConfig.endpoint('reception/chat/guests/$bookingId/typing'),
        ),
        options: _auth(token),
      );
    } catch (error) {
      debugPrint('ReceptionChatRepository: sendTypingToGuest failed — $error');
    }
  }

  Future<ReceptionChatMessage> _send(
    String token,
    String path,
    String body,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint(path)),
        data: {'body': body},
        options: _auth(token),
      );
      return ReceptionChatMessage.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw ReceptionChatException(_messageFrom(error));
    } catch (error) {
      debugPrint('ReceptionChatRepository: send failed — $error');
      throw ReceptionChatException(
        'Could not send this message. Please try again.',
      );
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
