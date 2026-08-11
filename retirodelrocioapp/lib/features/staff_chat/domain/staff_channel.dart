import 'package:flutter/foundation.dart';

/// One other station this station can message — a peer tablet
/// (Reception/Housekeeping/Maintenance/Security) or the Admin dashboard, as
/// it appears in a Chat screen's conversation list (`GET /staff/chat/channels`).
@immutable
class StaffChannel {
  const StaffChannel({
    required this.role,
    required this.label,
    required this.online,
    required this.lastMessage,
    required this.lastMessageLabel,
    required this.unreadCount,
  });

  final String role;
  final String label;
  final bool online;
  final String? lastMessage;
  final String? lastMessageLabel;
  final int unreadCount;

  factory StaffChannel.fromJson(Map<String, dynamic> json) => StaffChannel(
    role: json['role'] as String? ?? '',
    label: json['label'] as String? ?? '',
    online: json['online'] as bool? ?? false,
    lastMessage: json['last_message'] as String?,
    lastMessageLabel: json['last_message_label'] as String?,
    unreadCount: (json['unread_count'] as num?)?.toInt() ?? 0,
  );
}
