import 'package:flutter/foundation.dart';

/// One message in a staff-to-staff chat channel — shared by every station's
/// Chat screen (Reception, Housekeeping, Maintenance, Security).
@immutable
class StaffChatMessage {
  const StaffChatMessage({
    required this.id,
    required this.senderRole,
    required this.senderName,
    required this.body,
    required this.isMine,
    required this.timeLabel,
    required this.createdAt,
  });

  final int id;
  final String senderRole;
  final String? senderName;
  final String body;

  /// True for a message this station itself sent — the right-aligned, gold
  /// bubble; false renders left-aligned as the other station's message.
  final bool isMine;

  final String timeLabel;
  final DateTime createdAt;

  factory StaffChatMessage.fromJson(Map<String, dynamic> json) =>
      StaffChatMessage(
        id: (json['id'] as num?)?.toInt() ?? 0,
        senderRole: json['sender_role'] as String? ?? '',
        senderName: json['sender_name'] as String?,
        body: json['body'] as String? ?? '',
        isMine: json['is_mine'] as bool? ?? false,
        timeLabel: json['time_label'] as String? ?? '',
        createdAt:
            DateTime.tryParse(json['created_at'] as String? ?? '') ??
            DateTime.now(),
      );
}
