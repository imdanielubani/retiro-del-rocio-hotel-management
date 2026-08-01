import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_guest_request.dart';
import 'package:retirodelrocioapp/features/housekeeping/domain/housekeeping_room.dart';

/// The housekeeping dashboard in one payload: headline counters, the rooms
/// most needing attention, and the oldest pending guest requests
/// (`GET /housekeeping/overview`).
@immutable
class HousekeepingOverview {
  const HousekeepingOverview({
    required this.needsAttention,
    required this.dirty,
    required this.outOfOrder,
    required this.pendingRequests,
    required this.rooms,
    required this.requests,
  });

  final int needsAttention;
  final int dirty;
  final int outOfOrder;
  final int pendingRequests;
  final List<HousekeepingRoom> rooms;
  final List<HousekeepingGuestRequest> requests;

  static const empty = HousekeepingOverview(
    needsAttention: 0,
    dirty: 0,
    outOfOrder: 0,
    pendingRequests: 0,
    rooms: [],
    requests: [],
  );

  factory HousekeepingOverview.fromJson(Map<String, dynamic> json) {
    final stats = (json['stats'] as Map?)?.cast<String, dynamic>() ?? const {};

    return HousekeepingOverview(
      needsAttention: (stats['needs_attention'] as num?)?.toInt() ?? 0,
      dirty: (stats['dirty'] as num?)?.toInt() ?? 0,
      outOfOrder: (stats['out_of_order'] as num?)?.toInt() ?? 0,
      pendingRequests: (stats['pending_requests'] as num?)?.toInt() ?? 0,
      rooms: ((json['rooms'] as List?) ?? const [])
          .map((r) => HousekeepingRoom.fromJson((r as Map).cast()))
          .toList(),
      requests: ((json['requests'] as List?) ?? const [])
          .map((r) => HousekeepingGuestRequest.fromJson((r as Map).cast()))
          .toList(),
    );
  }
}
