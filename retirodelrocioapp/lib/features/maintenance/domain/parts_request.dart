import 'package:flutter/foundation.dart';

/// A technician's request for a part against a work order — the Requests
/// tab's list (`GET /maintenance/parts-requests`).
@immutable
class PartsRequest {
  const PartsRequest({
    required this.id,
    required this.workOrderId,
    this.workOrderTitle,
    this.locationLabel,
    required this.partName,
    required this.quantity,
    this.note,
    required this.status,
    required this.statusLabel,
    this.requestedBy,
    this.createdLabel,
  });

  final int id;
  final int workOrderId;
  final String? workOrderTitle;
  final String? locationLabel;
  final String partName;
  final int quantity;
  final String? note;
  final String status;
  final String statusLabel;
  final String? requestedBy;
  final String? createdLabel;

  bool get isPending => status == 'pending';
  bool get isFulfilled => status == 'fulfilled';
  bool get isDenied => status == 'denied';

  factory PartsRequest.fromJson(Map<String, dynamic> json) => PartsRequest(
    id: (json['id'] as num?)?.toInt() ?? 0,
    workOrderId: (json['work_order_id'] as num?)?.toInt() ?? 0,
    workOrderTitle: json['work_order_title'] as String?,
    locationLabel: json['location_label'] as String?,
    partName: json['part_name'] as String? ?? 'Part',
    quantity: (json['quantity'] as num?)?.toInt() ?? 1,
    note: json['note'] as String?,
    status: json['status'] as String? ?? 'pending',
    statusLabel: json['status_label'] as String? ?? 'Pending',
    requestedBy: json['requested_by'] as String?,
    createdLabel: json['created_label'] as String?,
  );
}
