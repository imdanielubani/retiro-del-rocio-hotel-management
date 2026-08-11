import 'package:flutter/foundation.dart';

/// Who sent a [ChatMessage] — the guest, or a staff member replying from the
/// front desk.
enum ChatSender {
  guest,
  staff;

  static ChatSender fromApi(String? value) =>
      value == 'staff' ? ChatSender.staff : ChatSender.guest;
}

/// One message in the guest's Concierge Chat thread with the front desk
/// (`GET /chat/messages`).
@immutable
class ChatMessage {
  const ChatMessage({
    required this.id,
    required this.sender,
    required this.senderName,
    required this.body,
    required this.isMine,
    required this.timeLabel,
    required this.createdAt,
  });

  final int id;
  final ChatSender sender;
  final String? senderName;
  final String body;

  /// True for a message the guest themself sent — the right-aligned, gold
  /// bubble; false renders left-aligned as the front desk's reply.
  final bool isMine;

  final String timeLabel;
  final DateTime createdAt;

  factory ChatMessage.fromJson(Map<String, dynamic> json) => ChatMessage(
    id: (json['id'] as num?)?.toInt() ?? 0,
    sender: ChatSender.fromApi(json['sender_type'] as String?),
    senderName: json['sender_name'] as String?,
    body: json['body'] as String? ?? '',
    isMine: json['is_mine'] as bool? ?? false,
    timeLabel: json['time_label'] as String? ?? '',
    createdAt:
        DateTime.tryParse(json['created_at'] as String? ?? '') ??
        DateTime.now(),
  );
}
