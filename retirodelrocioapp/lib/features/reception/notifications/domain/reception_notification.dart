import 'package:flutter/material.dart';

/// The categories a front-desk notification belongs to — also the filter
/// chips on the reception Notifications screen (mirrors the guest tablet's
/// `NotificationCategory`).
enum ReceptionNotificationCategory {
  payment('Payment', Icons.receipt_long_rounded),
  booking('Booking', Icons.event_available_rounded),
  guest('Guest', Icons.person_rounded),
  message('Message', Icons.chat_bubble_rounded);

  const ReceptionNotificationCategory(this.label, this.icon);

  final String label;
  final IconData icon;

  /// Falls back to [message] for a category the app doesn't recognise yet, so
  /// a new backend category never crashes the feed.
  static ReceptionNotificationCategory fromApi(String? value) =>
      ReceptionNotificationCategory.values.firstWhere(
        (c) => c.name == value,
        orElse: () => message,
      );
}

/// A single entry in the front desk's Notifications feed
/// (`GET /reception/notifications`).
@immutable
class ReceptionNotification {
  const ReceptionNotification({
    required this.id,
    required this.category,
    required this.title,
    required this.message,
    required this.time,
    this.read = false,
  });

  final int id;
  final ReceptionNotificationCategory category;
  final String title;
  final String message;
  final DateTime time;
  final bool read;

  factory ReceptionNotification.fromJson(Map<String, dynamic> json) =>
      ReceptionNotification(
        id: (json['id'] as num?)?.toInt() ?? 0,
        category: ReceptionNotificationCategory.fromApi(
          json['category'] as String?,
        ),
        title: json['title'] as String? ?? '',
        message: json['message'] as String? ?? '',
        time:
            DateTime.tryParse(json['created_at'] as String? ?? '') ??
            DateTime.now(),
        read: json['read'] as bool? ?? false,
      );

  ReceptionNotification copyWith({bool? read}) => ReceptionNotification(
    id: id,
    category: category,
    title: title,
    message: message,
    time: time,
    read: read ?? this.read,
  );
}
