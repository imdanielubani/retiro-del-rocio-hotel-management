import 'package:flutter/foundation.dart';

/// An item a housekeeper found while turning over a room (or a common area) —
/// logged, then eventually handed back to its owner or disposed of
/// (`GET /housekeeping/lost-found`).
@immutable
class LostFoundItem {
  const LostFoundItem({
    required this.id,
    this.roomUnitId,
    this.roomNumber,
    this.roomName,
    required this.itemDescription,
    this.notes,
    this.foundByName,
    this.foundLabel,
    required this.status,
    required this.statusLabel,
    required this.isUnclaimed,
    this.claimantName,
    this.claimantContact,
  });

  final int id;
  final int? roomUnitId;
  final String? roomNumber;
  final String? roomName;
  final String itemDescription;
  final String? notes;
  final String? foundByName;
  final String? foundLabel;

  /// unclaimed | returned | disposed.
  final String status;
  final String statusLabel;
  final bool isUnclaimed;
  final String? claimantName;
  final String? claimantContact;

  factory LostFoundItem.fromJson(Map<String, dynamic> json) => LostFoundItem(
    id: (json['id'] as num?)?.toInt() ?? 0,
    roomUnitId: (json['room_unit_id'] as num?)?.toInt(),
    roomNumber: json['room_number'] as String?,
    roomName: json['room_name'] as String?,
    itemDescription: json['item_description'] as String? ?? '',
    notes: json['notes'] as String?,
    foundByName: json['found_by_name'] as String?,
    foundLabel: json['found_label'] as String?,
    status: json['status'] as String? ?? 'unclaimed',
    statusLabel: json['status_label'] as String? ?? 'Unclaimed',
    isUnclaimed: json['is_unclaimed'] as bool? ?? true,
    claimantName: json['claimant_name'] as String?,
    claimantContact: json['claimant_contact'] as String?,
  );
}
