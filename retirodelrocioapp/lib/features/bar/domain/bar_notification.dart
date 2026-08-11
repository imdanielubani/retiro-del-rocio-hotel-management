import 'package:flutter/foundation.dart';

/// A new-order alert on the Bar Tablet's notification feed
/// (`GET /bar/notifications`).
@immutable
class BarNotification {
  const BarNotification({
    required this.id,
    required this.category,
    required this.title,
    required this.message,
    required this.diningOrderId,
    required this.orderCode,
    required this.createdLabel,
    required this.read,
  });

  final int id;
  final String category;
  final String title;
  final String message;
  final int? diningOrderId;
  final String? orderCode;
  final String? createdLabel;
  final bool read;

  factory BarNotification.fromJson(Map<String, dynamic> json) =>
      BarNotification(
        id: (json['id'] as num?)?.toInt() ?? 0,
        category: json['category'] as String? ?? '',
        title: json['title'] as String? ?? '',
        message: json['message'] as String? ?? '',
        diningOrderId: (json['dining_order_id'] as num?)?.toInt(),
        orderCode: json['order_code'] as String?,
        createdLabel: json['created_label'] as String?,
        read: json['read'] as bool? ?? false,
      );
}
