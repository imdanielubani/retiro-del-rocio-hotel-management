import 'package:flutter/foundation.dart';

/// Lifecycle of an SOS incident as security sees it.
enum IncidentStatus { active, acknowledged, resolved, cancelled }

/// An open emergency on the security dashboard — a guest's SOS awaiting or
/// receiving a response.
@immutable
class SecurityIncident {
  const SecurityIncident({
    required this.id,
    required this.status,
    this.caseNo,
    this.roomNumber,
    this.suiteName,
    this.guestName,
    this.raisedAt,
    this.acknowledgedAt,
    this.acknowledgedBy,
    this.resolvedAt,
    this.resolvedBy,
    this.cancelledAt,
  });

  final int id;
  final IncidentStatus status;

  /// Human case reference, e.g. "SOS-2607-140".
  final String? caseNo;
  final String? roomNumber;
  final String? suiteName;
  final String? guestName;
  final DateTime? raisedAt;

  final DateTime? acknowledgedAt;

  /// Name of the officer who acknowledged it, once someone has.
  final String? acknowledgedBy;

  final DateTime? resolvedAt;

  /// Name of the officer who resolved it.
  final String? resolvedBy;

  final DateTime? cancelledAt;

  /// A fresh, unacknowledged emergency — the loud red banner.
  bool get isActive => status == IncidentStatus.active;

  /// Seen by security and being handled — the calmer "responding" state.
  bool get isAcknowledged => status == IncidentStatus.acknowledged;

  bool get isResolved => status == IncidentStatus.resolved;

  bool get isCancelled => status == IncidentStatus.cancelled;

  /// Dealt with, one way or another — no further action from security.
  bool get isClosed => isResolved || isCancelled;

  String get guestLabel => guestName ?? 'Guest';

  String get roomLabel =>
      roomNumber != null ? 'Room $roomNumber' : 'Unknown room';

  factory SecurityIncident.fromJson(Map<String, dynamic> json) =>
      SecurityIncident(
        id: (json['id'] as num).toInt(),
        status: switch (json['status'] as String?) {
          'acknowledged' => IncidentStatus.acknowledged,
          'resolved' => IncidentStatus.resolved,
          'cancelled' => IncidentStatus.cancelled,
          _ => IncidentStatus.active,
        },
        caseNo: json['case_no'] as String?,
        roomNumber: json['room_number'] as String?,
        suiteName: json['suite_name'] as String?,
        guestName: json['guest_name'] as String?,
        raisedAt: DateTime.tryParse(
          json['raised_at'] as String? ?? '',
        )?.toLocal(),
        acknowledgedAt: DateTime.tryParse(
          json['acknowledged_at'] as String? ?? '',
        )?.toLocal(),
        acknowledgedBy: json['acknowledged_by'] as String?,
        resolvedAt: DateTime.tryParse(
          json['resolved_at'] as String? ?? '',
        )?.toLocal(),
        resolvedBy: json['resolved_by'] as String?,
        cancelledAt: DateTime.tryParse(
          json['cancelled_at'] as String? ?? '',
        )?.toLocal(),
      );
}
