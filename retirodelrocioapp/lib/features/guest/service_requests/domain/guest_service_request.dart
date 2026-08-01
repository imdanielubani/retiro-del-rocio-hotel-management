import 'package:flutter/material.dart';

/// A housekeeping ask a guest can raise — mirrors the backend's
/// `HousekeepingRequest::TYPES`.
enum HousekeepingRequestType {
  towels('towels', 'Towels', Icons.dry_cleaning_rounded),
  amenities('amenities', 'Amenities', Icons.soap_rounded),
  dnd('dnd', 'Do Not Disturb', Icons.do_not_disturb_on_rounded),
  makeUpRoom('make_up_room', 'Make Up Room', Icons.cleaning_services_rounded),
  other('other', 'Other', Icons.more_horiz_rounded);

  const HousekeepingRequestType(this.value, this.label, this.icon);

  final String value;
  final String label;
  final IconData icon;
}

/// How urgent a maintenance fault is — mirrors `WorkOrder::PRIORITIES`.
enum MaintenancePriority {
  low('low', 'Low'),
  medium('medium', 'Medium'),
  high('high', 'High'),
  urgent('urgent', 'Urgent');

  const MaintenancePriority(this.value, this.label);

  final String value;
  final String label;
}

/// One entry in the guest's Service Request history — a housekeeping ask or
/// a maintenance fault, whichever it was (`GET /service-requests`).
@immutable
class GuestServiceRequest {
  const GuestServiceRequest({
    required this.id,
    required this.category,
    required this.title,
    this.detail,
    required this.status,
    required this.statusLabel,
    required this.isOpen,
    this.time,
  });

  final int id;

  /// 'housekeeping' or 'maintenance'.
  final String category;
  final String title;
  final String? detail;
  final String status;
  final String statusLabel;
  final bool isOpen;
  final DateTime? time;

  bool get isHousekeeping => category == 'housekeeping';

  factory GuestServiceRequest.fromJson(Map<String, dynamic> json) => GuestServiceRequest(
    id: (json['id'] as num?)?.toInt() ?? 0,
    category: json['category'] as String? ?? 'housekeeping',
    title: json['title'] as String? ?? 'Request',
    detail: json['detail'] as String?,
    status: json['status'] as String? ?? 'pending',
    statusLabel: json['status_label'] as String? ?? 'Pending',
    isOpen: json['is_open'] as bool? ?? true,
    time: DateTime.tryParse(json['created_at'] as String? ?? ''),
  );
}
