import 'package:flutter/material.dart';

/// The board category a room falls into on reception's read-only Room
/// Status screen — one room, one bucket, priority-ordered by the backend
/// (occupied first, then maintenance, then preparing, then dirty).
enum ReceptionRoomBoardStatus {
  occupied('Occupied', Icons.person_rounded),
  maintenance('Maintenance', Icons.build_rounded),
  preparing('Preparing', Icons.cleaning_services_rounded),
  dirty('Dirty', Icons.do_not_disturb_on_outlined),
  ready('Ready', Icons.check_circle_rounded);

  const ReceptionRoomBoardStatus(this.label, this.icon);

  final String label;
  final IconData icon;

  static ReceptionRoomBoardStatus fromApi(String? value) =>
      ReceptionRoomBoardStatus.values.firstWhere(
        (s) => s.name == value,
        orElse: () => ready,
      );
}

/// One room on reception's Room Status board (`GET /reception/room-status`)
/// — a read-only view combining the room's occupancy (from its booking),
/// housekeeping's cleanliness flag, and whether maintenance has an open
/// fault against it.
///
/// Named `Entry` (rather than the shorter `ReceptionRoomStatus`) because
/// that name is already taken by the small occupied/dirty/maintenance tally
/// on the dashboard overview (`reception_overview.dart`) — this is the full
/// per-room row on the dedicated board screen.
@immutable
class ReceptionRoomStatusEntry {
  const ReceptionRoomStatusEntry({
    required this.id,
    required this.number,
    this.roomName,
    required this.occupancy,
    required this.occupancyLabel,
    required this.housekeepingStatus,
    required this.housekeepingStatusLabel,
    this.guestName,
    this.hasOpenWorkOrder = false,
    required this.boardStatus,
    required this.boardStatusLabel,
    this.updatedLabel,
  });

  final int id;
  final String number;
  final String? roomName;
  final String occupancy;
  final String occupancyLabel;
  final String housekeepingStatus;
  final String housekeepingStatusLabel;
  final String? guestName;
  final bool hasOpenWorkOrder;
  final ReceptionRoomBoardStatus boardStatus;
  final String boardStatusLabel;
  final String? updatedLabel;

  factory ReceptionRoomStatusEntry.fromJson(Map<String, dynamic> json) =>
      ReceptionRoomStatusEntry(
        id: (json['id'] as num?)?.toInt() ?? 0,
        number: json['number'] as String? ?? '—',
        roomName: json['room_name'] as String?,
        occupancy: json['occupancy'] as String? ?? 'available',
        occupancyLabel: json['occupancy_label'] as String? ?? 'Available',
        housekeepingStatus: json['housekeeping_status'] as String? ?? 'clean',
        housekeepingStatusLabel:
            json['housekeeping_status_label'] as String? ?? 'Clean',
        guestName: json['guest_name'] as String?,
        hasOpenWorkOrder: json['has_open_work_order'] as bool? ?? false,
        boardStatus: ReceptionRoomBoardStatus.fromApi(
          json['board_status'] as String?,
        ),
        boardStatusLabel: json['board_status_label'] as String? ?? 'Ready',
        updatedLabel: json['updated_label'] as String?,
      );
}

/// The Room Status board in one payload: headline counters plus every room
/// (`GET /reception/room-status`).
@immutable
class ReceptionRoomStatusBoard {
  const ReceptionRoomStatusBoard({
    required this.total,
    required this.occupied,
    required this.maintenance,
    required this.preparing,
    required this.dirty,
    required this.rooms,
  });

  final int total;
  final int occupied;
  final int maintenance;
  final int preparing;
  final int dirty;
  final List<ReceptionRoomStatusEntry> rooms;

  static const empty = ReceptionRoomStatusBoard(
    total: 0,
    occupied: 0,
    maintenance: 0,
    preparing: 0,
    dirty: 0,
    rooms: [],
  );

  factory ReceptionRoomStatusBoard.fromJson(Map<String, dynamic> json) {
    final stats = (json['stats'] as Map?)?.cast<String, dynamic>() ?? const {};
    final rooms = (json['rooms'] as List?) ?? const [];

    return ReceptionRoomStatusBoard(
      total: (stats['total'] as num?)?.toInt() ?? 0,
      occupied: (stats['occupied'] as num?)?.toInt() ?? 0,
      maintenance: (stats['maintenance'] as num?)?.toInt() ?? 0,
      preparing: (stats['preparing'] as num?)?.toInt() ?? 0,
      dirty: (stats['dirty'] as num?)?.toInt() ?? 0,
      rooms: rooms
          .whereType<Map>()
          .map(
            (e) => ReceptionRoomStatusEntry.fromJson(e.cast<String, dynamic>()),
          )
          .toList(),
    );
  }
}
