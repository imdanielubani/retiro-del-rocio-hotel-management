import 'package:flutter/foundation.dart';

/// One message in a Reception Chat thread — either a guest's Concierge Chat
/// conversation (`sender_type`: guest/staff) or a staff department channel
/// (`sender_role`: reception/housekeeping/maintenance/security). Both backend
/// shapes collapse to the same fields here since the thread renders them
/// identically either way.
@immutable
class ReceptionChatMessage {
  const ReceptionChatMessage({
    required this.id,
    required this.senderName,
    required this.body,
    required this.isMine,
    required this.timeLabel,
    required this.createdAt,
  });

  final int id;
  final String? senderName;
  final String body;

  /// True for a message the front desk itself sent — the right-aligned, gold
  /// bubble; false renders left-aligned as the other side's message.
  final bool isMine;

  final String timeLabel;
  final DateTime createdAt;

  factory ReceptionChatMessage.fromJson(Map<String, dynamic> json) =>
      ReceptionChatMessage(
        id: (json['id'] as num?)?.toInt() ?? 0,
        senderName: json['sender_name'] as String?,
        body: json['body'] as String? ?? '',
        isMine: json['is_mine'] as bool? ?? false,
        timeLabel: json['time_label'] as String? ?? '',
        createdAt:
            DateTime.tryParse(json['created_at'] as String? ?? '') ??
            DateTime.now(),
      );
}
