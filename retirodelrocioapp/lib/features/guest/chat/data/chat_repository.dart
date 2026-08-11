import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/guest/chat/domain/chat_message.dart';

/// Raised when a chat message could not be sent, carrying a user-facing
/// message.
class ChatException implements Exception {
  ChatException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to the guest tablet's Concierge Chat endpoints with the tablet's own
/// device token, which is what scopes every call to this room's stay.
class ChatRepository {
  ChatRepository({Dio? dio})
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

  Options _auth(String deviceToken) => Options(
    headers: {
      'Authorization': 'Bearer $deviceToken',
      'Accept': 'application/json',
    },
  );

  /// This stay's conversation with the front desk, oldest first, plus how
  /// many staff messages were unread before this call (opening the thread
  /// marks them read server-side), whether reception is currently online,
  /// and a relative "5m ago" label for the last message — the same
  /// `diffForHumans` label reception's own guest list shows. Failures fall
  /// back to an empty, unread-free, offline thread — the screen still
  /// renders, just without history.
  Future<
    ({
      List<ChatMessage> messages,
      int unreadCount,
      bool receptionOnline,
      String? lastMessageLabel,
    })
  >
  list(String deviceToken) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('chat/messages')),
        options: _auth(deviceToken),
      );
      final data = response.data?['data'];
      final messages = data is List
          ? data
                .whereType<Map>()
                .map((e) => ChatMessage.fromJson(e.cast<String, dynamic>()))
                .toList()
          : <ChatMessage>[];
      final unreadCount =
          (response.data?['unread_count'] as num?)?.toInt() ?? 0;
      final receptionOnline =
          response.data?['reception_online'] as bool? ?? false;
      final lastMessageLabel = response.data?['last_message_label'] as String?;
      return (
        messages: messages,
        unreadCount: unreadCount,
        receptionOnline: receptionOnline,
        lastMessageLabel: lastMessageLabel,
      );
    } catch (error) {
      debugPrint('ChatRepository: list failed — $error');
      return (
        messages: const <ChatMessage>[],
        unreadCount: 0,
        receptionOnline: false,
        lastMessageLabel: null,
      );
    }
  }

  /// Send a message to the front desk.
  Future<ChatMessage> send(String deviceToken, String body) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('chat/messages')),
        data: {'body': body},
        options: _auth(deviceToken),
      );
      return ChatMessage.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw ChatException(_messageFrom(error));
    } catch (error) {
      debugPrint('ChatRepository: send failed — $error');
      throw ChatException('Could not send this message. Please try again.');
    }
  }

  /// Fire-and-forget "I'm typing" signal. Failures are swallowed — a missed
  /// typing indicator is never worth surfacing to the guest.
  Future<void> sendTyping(String deviceToken) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('chat/typing')),
        options: _auth(deviceToken),
      );
    } catch (error) {
      debugPrint('ChatRepository: sendTyping failed — $error');
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
