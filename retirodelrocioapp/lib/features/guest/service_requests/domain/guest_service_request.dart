import 'package:flutter/material.dart';

/// Maps the admin catalog's icon keys (`HousekeepingRequestType::ICONS` on
/// the backend) to the Material icon this app renders for it. Any key the
/// admin catalog returns that isn't in this map — or none at all, if the
/// fetch fails — falls back to the generic cleaning icon.
const Map<String, IconData> _housekeepingIconsByKey = {
  'dry_cleaning': Icons.dry_cleaning_rounded,
  'soap': Icons.soap_rounded,
  'do_not_disturb_on': Icons.do_not_disturb_on_rounded,
  'cleaning_services': Icons.cleaning_services_rounded,
  'more_horiz': Icons.more_horiz_rounded,
  'bed': Icons.bed_rounded,
  'iron': Icons.iron_rounded,
  'local_laundry_service': Icons.local_laundry_service_rounded,
  'bathtub': Icons.bathtub_rounded,
  'water_drop': Icons.water_drop_rounded,
  'coffee': Icons.coffee_rounded,
  'checkroom': Icons.checkroom_rounded,
  'fact_check': Icons.fact_check_rounded,
  'room_service': Icons.room_service_rounded,
};

/// A housekeeping ask a guest can raise — fetched from the admin-managed
/// catalog (`GET /service-requests/types`), so a new type an admin adds
/// shows up here without an app update.
@immutable
class HousekeepingRequestTypeOption {
  const HousekeepingRequestTypeOption({required this.key, required this.label, required this.icon});

  final String key;
  final String label;
  final IconData icon;

  factory HousekeepingRequestTypeOption.fromJson(Map<String, dynamic> json) => HousekeepingRequestTypeOption(
    key: json['key'] as String? ?? 'other',
    label: json['label'] as String? ?? 'Other',
    icon: _housekeepingIconsByKey[json['icon'] as String?] ?? Icons.cleaning_services_rounded,
  );

  @override
  bool operator ==(Object other) => other is HousekeepingRequestTypeOption && other.key == key;

  @override
  int get hashCode => key.hashCode;
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
