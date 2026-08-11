import 'package:flutter/material.dart';

/// The categories a guest notification belongs to — also the filter chips
/// on the Notifications screen (Figma 140:3647).
enum NotificationCategory {
  dining('Dinning', Icons.restaurant_rounded),
  spa('Spa', Icons.spa_rounded),
  gym('Gym', Icons.fitness_center_rounded),
  cinema('Cinema', Icons.movie_rounded),
  payment('Payment', Icons.receipt_long_rounded),
  message('Message', Icons.chat_bubble_rounded);

  const NotificationCategory(this.label, this.icon);

  final String label;
  final IconData icon;

  /// Falls back to [message] for a category the app doesn't recognise yet, so
  /// a new backend category never crashes the feed.
  static NotificationCategory fromApi(String? value) => NotificationCategory
      .values
      .firstWhere((c) => c.name == value, orElse: () => message);
}

/// A single entry in the guest's Notifications feed (`GET /tablets/notifications`).
@immutable
class GuestNotification {
  const GuestNotification({
    required this.id,
    required this.category,
    required this.title,
    required this.message,
    required this.time,
    this.read = false,
  });

  final int id;
  final NotificationCategory category;
  final String title;
  final String message;
  final DateTime time;
  final bool read;

  factory GuestNotification.fromJson(Map<String, dynamic> json) =>
      GuestNotification(
        id: (json['id'] as num?)?.toInt() ?? 0,
        category: NotificationCategory.fromApi(json['category'] as String?),
        title: json['title'] as String? ?? '',
        message: json['message'] as String? ?? '',
        time:
            DateTime.tryParse(json['created_at'] as String? ?? '') ??
            DateTime.now(),
        read: json['read'] as bool? ?? false,
      );

  GuestNotification copyWith({bool? read}) => GuestNotification(
    id: id,
    category: category,
    title: title,
    message: message,
    time: time,
    read: read ?? this.read,
  );
}
