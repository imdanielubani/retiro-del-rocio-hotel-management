import 'package:flutter/foundation.dart';

/// One side of an [IntercomCall] — either a guest's room, or a staff role.
@immutable
class IntercomParty {
  const IntercomParty({
    this.roomUnitId,
    this.role,
    required this.label,
    this.sublabel,
  });

  final int? roomUnitId;
  final String? role;
  final String label;
  final String? sublabel;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is IntercomParty &&
          other.roomUnitId == roomUnitId &&
          other.role == role &&
          other.label == label &&
          other.sublabel == sublabel);

  @override
  int get hashCode => Object.hash(roomUnitId, role, label, sublabel);

  factory IntercomParty.fromJson(Map<String, dynamic> json) => IntercomParty(
    roomUnitId: (json['room_unit_id'] as num?)?.toInt(),
    role: json['role'] as String?,
    label: json['label'] as String? ?? '',
    sublabel: json['sublabel'] as String?,
  );
}

enum IntercomCallStatus { ringing, accepted, declined, cancelled, ended }

IntercomCallStatus _statusFromJson(String? value) => switch (value) {
  'accepted' => IntercomCallStatus.accepted,
  'declined' => IntercomCallStatus.declined,
  'cancelled' => IntercomCallStatus.cancelled,
  'ended' => IntercomCallStatus.ended,
  _ => IntercomCallStatus.ringing,
};

/// A voice-call signal between a guest's room tablet and Reception — no real
/// audio, just the ringing/answer/decline/hang-up state both call screens
/// render (`GET .../intercom/calls/current`).
@immutable
class IntercomCall {
  const IntercomCall({
    required this.id,
    required this.status,
    required this.from,
    required this.to,
    required this.startedAt,
    this.answeredAt,
    this.endedAt,
  });

  final int id;
  final IntercomCallStatus status;
  final IntercomParty from;
  final IntercomParty to;
  final DateTime startedAt;
  final DateTime? answeredAt;
  final DateTime? endedAt;

  bool get isRinging => status == IntercomCallStatus.ringing;

  bool get isConnected => status == IntercomCallStatus.accepted;

  /// True while the call still needs to be shown — ringing or connected.
  bool get isActive => isRinging || isConnected;

  bool get hasEnded => !isActive;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is IntercomCall &&
          other.id == id &&
          other.status == status &&
          other.from == from &&
          other.to == to &&
          other.answeredAt == answeredAt &&
          other.endedAt == endedAt);

  @override
  int get hashCode => Object.hash(id, status, from, to, answeredAt, endedAt);

  factory IntercomCall.fromJson(Map<String, dynamic> json) => IntercomCall(
    id: (json['id'] as num).toInt(),
    status: _statusFromJson(json['status'] as String?),
    from: IntercomParty.fromJson((json['from'] as Map).cast()),
    to: IntercomParty.fromJson((json['to'] as Map).cast()),
    startedAt:
        DateTime.tryParse(json['started_at'] as String? ?? '') ??
        DateTime.now(),
    answeredAt: json['answered_at'] != null
        ? DateTime.tryParse(json['answered_at'] as String)
        : null,
    endedAt: json['ended_at'] != null
        ? DateTime.tryParse(json['ended_at'] as String)
        : null,
  );
}
