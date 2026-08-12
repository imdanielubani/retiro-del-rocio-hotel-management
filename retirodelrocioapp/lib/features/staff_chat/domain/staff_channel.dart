import 'package:flutter/foundation.dart';

/// One other individual staff member this station can message — a specific
/// person (not just "Bar" as a department), as it appears in a Chat
/// screen's conversation list (`GET /staff/chat/channels`). Two people
/// holding the same role each show up as their own row here.
@immutable
class StaffChannel {
  const StaffChannel({
    required this.userId,
    required this.name,
    required this.role,
    required this.roleLabel,
    required this.online,
    required this.lastMessage,
    required this.lastMessageLabel,
    required this.unreadCount,
  });

  /// The contact's own user ID — the identity every message/call is
  /// addressed to, not the department they belong to.
  final int userId;

  /// The contact's real name, e.g. "Amara Chef" — shown as the primary
  /// label everywhere this channel appears.
  final String name;

  /// The contact's department, e.g. "bar" — drives the icon.
  final String role;

  /// "Bar", "Manager", etc. — shown as a secondary label under [name].
  final String roleLabel;

  final bool online;
  final String? lastMessage;
  final String? lastMessageLabel;
  final int unreadCount;

  factory StaffChannel.fromJson(Map<String, dynamic> json) => StaffChannel(
    userId: (json['user_id'] as num?)?.toInt() ?? 0,
    name: json['name'] as String? ?? '',
    role: json['role'] as String? ?? '',
    roleLabel: json['role_label'] as String? ?? '',
    online: json['online'] as bool? ?? false,
    lastMessage: json['last_message'] as String?,
    lastMessageLabel: json['last_message_label'] as String?,
    unreadCount: (json['unread_count'] as num?)?.toInt() ?? 0,
  );
}
