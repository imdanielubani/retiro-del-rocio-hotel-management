import 'package:flutter/foundation.dart';

/// One in-house guest's Concierge Chat conversation, as it appears in
/// Reception's Chat list (`GET /reception/chat/guests`).
@immutable
class ReceptionGuestConversation {
  const ReceptionGuestConversation({
    required this.bookingId,
    required this.roomUnitId,
    required this.guestName,
    required this.roomLabel,
    required this.guestOnline,
    required this.lastMessage,
    required this.lastMessageLabel,
    required this.unreadCount,
  });

  final int bookingId;

  /// The guest's room, so the thread can open a realtime subscription for
  /// this specific conversation's typing signal — null for a booking with
  /// no room unit on record (never happens for a checked-in stay, but the
  /// API doesn't guarantee it).
  final int? roomUnitId;

  final String guestName;
  final String roomLabel;
  final bool guestOnline;
  final String? lastMessage;
  final String? lastMessageLabel;
  final int unreadCount;

  String get initials {
    final parts = guestName.trim().split(RegExp(r'\s+'));
    final letters = parts.where((p) => p.isNotEmpty).take(2).map((p) => p[0]);
    return letters.isEmpty ? '?' : letters.join().toUpperCase();
  }

  factory ReceptionGuestConversation.fromJson(Map<String, dynamic> json) =>
      ReceptionGuestConversation(
        bookingId: (json['booking_id'] as num?)?.toInt() ?? 0,
        roomUnitId: (json['room_unit_id'] as num?)?.toInt(),
        guestName: json['guest_name'] as String? ?? 'Guest',
        roomLabel: json['room_label'] as String? ?? '',
        guestOnline: json['guest_online'] as bool? ?? false,
        lastMessage: json['last_message'] as String?,
        lastMessageLabel: json['last_message_label'] as String?,
        unreadCount: (json['unread_count'] as num?)?.toInt() ?? 0,
      );
}

/// One department's internal channel with the front desk, as it appears in
/// Reception's Chat list (`GET /reception/chat/staff`).
@immutable
class ReceptionStaffConversation {
  const ReceptionStaffConversation({
    required this.department,
    required this.label,
    required this.online,
    required this.lastMessage,
    required this.lastMessageLabel,
    required this.unreadCount,
  });

  final String department;
  final String label;
  final bool online;
  final String? lastMessage;
  final String? lastMessageLabel;
  final int unreadCount;

  factory ReceptionStaffConversation.fromJson(Map<String, dynamic> json) =>
      ReceptionStaffConversation(
        department: json['department'] as String? ?? '',
        label: json['label'] as String? ?? '',
        online: json['online'] as bool? ?? false,
        lastMessage: json['last_message'] as String?,
        lastMessageLabel: json['last_message_label'] as String?,
        unreadCount: (json['unread_count'] as num?)?.toInt() ?? 0,
      );
}
