import 'package:flutter/material.dart';

/// The categories a security notification belongs to. Today the backend only
/// ever sends `guest` (a visitor invited), but the server's `category` column
/// is a free string, so an unrecognised value falls back to [message] rather
/// than crashing the feed — same convention as the guest and reception
/// tablets' notification categories.
enum SecurityNotificationCategory {
  guest('Guest', Icons.person_rounded),
  message('Message', Icons.chat_bubble_rounded);

  const SecurityNotificationCategory(this.label, this.icon);

  final String label;
  final IconData icon;

  static SecurityNotificationCategory fromApi(String? value) =>
      SecurityNotificationCategory.values.firstWhere(
        (c) => c.name == value,
        orElse: () => message,
      );
}

/// A single entry in security's Notifications feed
/// (`GET /security/notifications`).
@immutable
class SecurityNotification {
  const SecurityNotification({
    required this.id,
    required this.category,
    required this.title,
    required this.message,
    required this.time,
    this.read = false,
    this.suiteName,
    this.roomNumber,
    this.guestName,
  });

  final int id;
  final SecurityNotificationCategory category;
  final String title;
  final String message;
  final DateTime time;
  final bool read;

  /// Which room and guest this notification is about, when it has one —
  /// null for a notification not tied to a booking.
  final String? suiteName;
  final String? roomNumber;
  final String? guestName;

  /// True when any of [suiteName]/[roomNumber]/[guestName] is set, so the
  /// card knows whether to render the room/guest line at all.
  bool get hasRoomContext =>
      (suiteName ?? '').isNotEmpty ||
      (roomNumber ?? '').isNotEmpty ||
      (guestName ?? '').isNotEmpty;

  factory SecurityNotification.fromJson(Map<String, dynamic> json) =>
      SecurityNotification(
        id: (json['id'] as num?)?.toInt() ?? 0,
        category: SecurityNotificationCategory.fromApi(
          json['category'] as String?,
        ),
        title: json['title'] as String? ?? '',
        message: json['message'] as String? ?? '',
        time:
            DateTime.tryParse(json['created_at'] as String? ?? '') ??
            DateTime.now(),
        read: json['read'] as bool? ?? false,
        suiteName: json['suite_name'] as String?,
        roomNumber: json['room_number'] as String?,
        guestName: json['guest_name'] as String?,
      );

  SecurityNotification copyWith({bool? read}) => SecurityNotification(
    id: id,
    category: category,
    title: title,
    message: message,
    time: time,
    read: read ?? this.read,
    suiteName: suiteName,
    roomNumber: roomNumber,
    guestName: guestName,
  );
}
