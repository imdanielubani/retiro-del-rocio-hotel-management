import 'package:flutter/foundation.dart';

/// A maintenance fault, from report through to completion
/// (`GET /maintenance/work-orders`).
@immutable
class WorkOrder {
  const WorkOrder({
    required this.id,
    required this.title,
    this.description,
    this.roomNumber,
    this.assetLabel,
    this.assetCategory,
    required this.locationLabel,
    required this.priority,
    required this.priorityLabel,
    required this.status,
    required this.statusLabel,
    this.reportedBy,
    this.assignedToName,
    this.createdLabel,
    this.attachments = const [],
    this.slaBreached = false,
  });

  final int id;
  final String title;
  final String? description;
  final String? roomNumber;
  final String? assetLabel;
  final String? assetCategory;
  final String locationLabel;
  final String priority;
  final String priorityLabel;
  final String status;
  final String statusLabel;
  final String? reportedBy;
  final String? assignedToName;
  final String? createdLabel;

  /// Populated only on the work order detail response.
  final List<WorkOrderAttachment> attachments;

  final bool slaBreached;

  bool get isNew => status == 'new';
  bool get isAccepted => status == 'accepted';
  bool get isInProgress => status == 'in_progress';
  bool get isDone => status == 'done';

  factory WorkOrder.fromJson(Map<String, dynamic> json) => WorkOrder(
    id: (json['id'] as num?)?.toInt() ?? 0,
    title: json['title'] as String? ?? 'Work order',
    description: json['description'] as String?,
    roomNumber: json['room_number'] as String?,
    assetLabel: json['asset_label'] as String?,
    assetCategory: json['asset_category'] as String?,
    locationLabel: json['location_label'] as String? ?? 'Hotel-wide',
    priority: json['priority'] as String? ?? 'medium',
    priorityLabel: json['priority_label'] as String? ?? 'Medium',
    status: json['status'] as String? ?? 'new',
    statusLabel: json['status_label'] as String? ?? 'New',
    reportedBy: json['reported_by'] as String?,
    assignedToName: json['assigned_to_name'] as String?,
    createdLabel: json['created_label'] as String?,
    attachments: ((json['attachments'] as List?) ?? const [])
        .map((a) => WorkOrderAttachment.fromJson((a as Map).cast()))
        .toList(),
    slaBreached: json['sla_breached'] as bool? ?? false,
  );
}

/// A photo or video a technician attached to a work order.
@immutable
class WorkOrderAttachment {
  const WorkOrderAttachment({
    required this.id,
    required this.type,
    required this.url,
    this.uploadedBy,
    this.createdLabel,
  });

  final int id;
  final String type; // photo | video
  final String url;
  final String? uploadedBy;
  final String? createdLabel;

  bool get isVideo => type == 'video';

  factory WorkOrderAttachment.fromJson(Map<String, dynamic> json) => WorkOrderAttachment(
    id: (json['id'] as num?)?.toInt() ?? 0,
    type: json['type'] as String? ?? 'photo',
    url: json['url'] as String? ?? '',
    uploadedBy: json['uploaded_by'] as String?,
    createdLabel: json['created_label'] as String?,
  );
}

/// A room in the fault-report picker (`GET /maintenance/rooms`).
@immutable
class MaintenanceRoomOption {
  const MaintenanceRoomOption({required this.id, required this.number, this.roomName});

  final int id;
  final String number;
  final String? roomName;

  factory MaintenanceRoomOption.fromJson(Map<String, dynamic> json) => MaintenanceRoomOption(
    id: (json['id'] as num?)?.toInt() ?? 0,
    number: json['number'] as String? ?? '—',
    roomName: json['room_name'] as String?,
  );
}
