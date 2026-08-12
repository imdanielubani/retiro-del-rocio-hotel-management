import 'package:flutter/material.dart';

/// The categories a maintenance notification belongs to.
enum MaintenanceNotificationCategory {
  newWorkOrder('New Work Order', Icons.build_rounded),
  urgentWorkOrder('Urgent Work Order', Icons.warning_amber_rounded);

  const MaintenanceNotificationCategory(this.label, this.icon);

  final String label;
  final IconData icon;

  static MaintenanceNotificationCategory fromApi(String? value) =>
      MaintenanceNotificationCategory.values.firstWhere(
        (c) => c.name == _camel(value),
        orElse: () => newWorkOrder,
      );

  static String _camel(String? snake) {
    if (snake == null) return '';
    final parts = snake.split('_');
    return parts.first +
        parts
            .skip(1)
            .map((p) => p.isEmpty ? '' : p[0].toUpperCase() + p.substring(1))
            .join();
  }
}

/// A single entry in maintenance's Notifications feed
/// (`GET /maintenance/notifications`).
@immutable
class MaintenanceNotification {
  const MaintenanceNotification({
    required this.id,
    required this.category,
    required this.title,
    required this.message,
    required this.time,
    this.read = false,
    this.workOrderId,
    this.locationLabel,
  });

  final int id;
  final MaintenanceNotificationCategory category;
  final String title;
  final String message;
  final DateTime time;
  final bool read;
  final int? workOrderId;
  final String? locationLabel;

  factory MaintenanceNotification.fromJson(Map<String, dynamic> json) =>
      MaintenanceNotification(
        id: (json['id'] as num?)?.toInt() ?? 0,
        category: MaintenanceNotificationCategory.fromApi(
          json['category'] as String?,
        ),
        title: json['title'] as String? ?? '',
        message: json['message'] as String? ?? '',
        time:
            DateTime.tryParse(json['created_at'] as String? ?? '') ??
            DateTime.now(),
        read: json['read'] as bool? ?? false,
        workOrderId: (json['work_order_id'] as num?)?.toInt(),
        locationLabel: json['location_label'] as String?,
      );
}
