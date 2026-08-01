import 'package:flutter/material.dart';

/// The categories a housekeeping notification belongs to. Today the backend
/// only ever sends `guest` (a housekeeping request raised), but the server's
/// `category` column is a free string, so an unrecognised value falls back
/// to [message] rather than crashing the feed — same convention as the
/// guest, reception and security tablets' notification categories.
enum HousekeepingNotificationCategory {
  guest('Guest', Icons.person_rounded),
  message('Message', Icons.chat_bubble_rounded);

  const HousekeepingNotificationCategory(this.label, this.icon);

  final String label;
  final IconData icon;

  static HousekeepingNotificationCategory fromApi(String? value) =>
      HousekeepingNotificationCategory.values.firstWhere(
        (c) => c.name == value,
        orElse: () => message,
      );
}

/// A single entry in housekeeping's Notifications feed
/// (`GET /housekeeping/notifications`).
@immutable
class HousekeepingNotification {
  const HousekeepingNotification({
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
  final HousekeepingNotificationCategory category;
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

  factory HousekeepingNotification.fromJson(Map<String, dynamic> json) =>
      HousekeepingNotification(
        id: (json['id'] as num?)?.toInt() ?? 0,
        category: HousekeepingNotificationCategory.fromApi(
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

  HousekeepingNotification copyWith({bool? read}) => HousekeepingNotification(
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
