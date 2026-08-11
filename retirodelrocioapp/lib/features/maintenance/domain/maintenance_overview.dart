import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/features/maintenance/domain/work_order.dart';

/// The maintenance dashboard in one payload: headline counters and the open
/// orders needing attention next, urgent first (`GET /maintenance/overview`).
@immutable
class MaintenanceOverview {
  const MaintenanceOverview({
    required this.newCount,
    required this.inProgress,
    required this.urgent,
    required this.completedToday,
    required this.slaBreaches,
    required this.workOrders,
  });

  final int newCount;
  final int inProgress;
  final int urgent;
  final int completedToday;
  final int slaBreaches;
  final List<WorkOrder> workOrders;

  static const empty = MaintenanceOverview(
    newCount: 0,
    inProgress: 0,
    urgent: 0,
    completedToday: 0,
    slaBreaches: 0,
    workOrders: [],
  );

  factory MaintenanceOverview.fromJson(Map<String, dynamic> json) {
    final stats = (json['stats'] as Map?)?.cast<String, dynamic>() ?? const {};

    return MaintenanceOverview(
      newCount: (stats['new'] as num?)?.toInt() ?? 0,
      inProgress: (stats['in_progress'] as num?)?.toInt() ?? 0,
      urgent: (stats['urgent'] as num?)?.toInt() ?? 0,
      completedToday: (stats['completed_today'] as num?)?.toInt() ?? 0,
      slaBreaches: (stats['sla_breaches'] as num?)?.toInt() ?? 0,
      workOrders: ((json['work_orders'] as List?) ?? const [])
          .map((o) => WorkOrder.fromJson((o as Map).cast()))
          .toList(),
    );
  }
}
