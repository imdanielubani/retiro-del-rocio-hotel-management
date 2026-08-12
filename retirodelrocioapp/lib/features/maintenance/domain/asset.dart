import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';

/// A registered hotel asset — its profile, service-history summary, and
/// preventive-maintenance due state (`GET /maintenance/assets`).
@immutable
class Asset {
  const Asset({
    required this.id,
    required this.name,
    this.category,
    required this.locationLabel,
    this.notes,
    this.serviceIntervalDays,
    this.lastServicedAt,
    this.lastServicedLabel,
    this.nextServiceDueAt,
    required this.isDueForService,
    this.serviceHistory = const [],
  });

  final int id;
  final String name;
  final String? category;
  final String locationLabel;
  final String? notes;
  final int? serviceIntervalDays;
  final DateTime? lastServicedAt;
  final String? lastServicedLabel;
  final DateTime? nextServiceDueAt;
  final bool isDueForService;

  /// Populated only on the asset detail response.
  final List<WorkOrder> serviceHistory;

  bool get isOnScheduledMaintenance => serviceIntervalDays != null;

  factory Asset.fromJson(Map<String, dynamic> json) => Asset(
    id: (json['id'] as num?)?.toInt() ?? 0,
    name: json['name'] as String? ?? 'Asset',
    category: json['category'] as String?,
    locationLabel: json['location_label'] as String? ?? 'Hotel-wide',
    notes: json['notes'] as String?,
    serviceIntervalDays: (json['service_interval_days'] as num?)?.toInt(),
    lastServicedAt: json['last_serviced_at'] != null
        ? DateTime.tryParse(json['last_serviced_at'] as String)
        : null,
    lastServicedLabel: json['last_serviced_label'] as String?,
    nextServiceDueAt: json['next_service_due_at'] != null
        ? DateTime.tryParse(json['next_service_due_at'] as String)
        : null,
    isDueForService: json['is_due_for_service'] as bool? ?? false,
    serviceHistory: ((json['service_history'] as List?) ?? const [])
        .map((o) => WorkOrder.fromJson((o as Map).cast()))
        .toList(),
  );
}
