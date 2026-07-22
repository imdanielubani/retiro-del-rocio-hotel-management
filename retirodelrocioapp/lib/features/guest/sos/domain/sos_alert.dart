import 'package:flutter/foundation.dart';

/// Lifecycle of an emergency: raised → security responding → done, or stood down.
enum SosStatus { active, acknowledged, resolved, cancelled }

/// An emergency raised from this room's tablet.
@immutable
class SosAlert {
  const SosAlert({
    required this.id,
    required this.status,
    this.roomNumber,
    this.suiteName,
    this.guestName,
    this.raisedAt,
    this.acknowledgedAt,
  });

  final int id;
  final SosStatus status;
  final String? roomNumber;
  final String? suiteName;
  final String? guestName;
  final DateTime? raisedAt;
  final DateTime? acknowledgedAt;

  /// Still awaiting or receiving a response — the tablet must keep showing it.
  bool get isOpen =>
      status == SosStatus.active || status == SosStatus.acknowledged;

  /// Security has seen it and is on their way.
  bool get isAcknowledged => status == SosStatus.acknowledged;

  factory SosAlert.fromJson(Map<String, dynamic> json) => SosAlert(
        id: (json['id'] as num).toInt(),
        status: switch (json['status'] as String?) {
          'acknowledged' => SosStatus.acknowledged,
          'resolved' => SosStatus.resolved,
          'cancelled' => SosStatus.cancelled,
          _ => SosStatus.active,
        },
        roomNumber: json['room_number'] as String?,
        suiteName: json['suite_name'] as String?,
        guestName: json['guest_name'] as String?,
        raisedAt: DateTime.tryParse(json['raised_at'] as String? ?? '')?.toLocal(),
        acknowledgedAt:
            DateTime.tryParse(json['acknowledged_at'] as String? ?? '')?.toLocal(),
      );
}
