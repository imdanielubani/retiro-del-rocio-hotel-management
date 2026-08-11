import 'package:flutter/foundation.dart';

/// Lifecycle of a visitor pass as security sees it.
enum VisitorPassStatus { pending, verified, denied, cancelled, expired }

/// A visitor pass as the security tablet renders it — for the Visitor
/// Verification list and the matched-pass card. Carries the host + contact so the
/// officer can confirm the visitor against the room they claim to be visiting.
@immutable
class VisitorPassRecord {
  const VisitorPassRecord({
    required this.id,
    required this.reference,
    required this.status,
    required this.visitorName,
    required this.code,
    this.onlineCode,
    this.offlineCode,
    this.hostName,
    this.roomNumber,
    this.suiteName,
    this.email,
    this.phone,
    this.submittedLabel,
    this.createdAt,
    this.isInside = false,
    this.arrivalLabel,
  });

  final int id;
  final String reference;
  final VisitorPassStatus status;
  final String visitorName;

  /// The code the officer is most likely to key — the live online (TTLock) code
  /// when present, otherwise the offline code.
  final String code;

  /// The one-time TTLock code (present only while live on the lock).
  final String? onlineCode;

  /// The manual code security keys at the gate.
  final String? offlineCode;

  final String? hostName;
  final String? roomNumber;
  final String? suiteName;
  final String? email;
  final String? phone;

  /// Pre-formatted "10:32PM" submitted time from the server.
  final String? submittedLabel;
  final DateTime? createdAt;

  /// True for a verified visitor who has not yet been checked out.
  final bool isInside;

  /// Pre-formatted "10:32PM" the visitor was admitted at the gate.
  final String? arrivalLabel;

  bool get isPending => status == VisitorPassStatus.pending;
  bool get isVerified => status == VisitorPassStatus.verified;

  /// True when the visitor holds a live TTLock code (the "Online Code").
  bool get hasOnlineCode => (onlineCode ?? '').isNotEmpty;

  String get initials {
    final parts = visitorName.trim().split(RegExp(r'\s+'));
    final letters = parts.where((p) => p.isNotEmpty).take(2).map((p) => p[0]);
    return letters.isEmpty ? 'V' : letters.join().toUpperCase();
  }

  factory VisitorPassRecord.fromJson(Map<String, dynamic> json) {
    final online = json['online_code'] as String?;
    final offline =
        json['offline_code'] as String? ?? json['code'] as String?;

    return VisitorPassRecord(
      id: (json['id'] as num).toInt(),
      reference: json['reference'] as String? ?? '',
      status: switch (json['status'] as String?) {
        'verified' => VisitorPassStatus.verified,
        'denied' => VisitorPassStatus.denied,
        'cancelled' => VisitorPassStatus.cancelled,
        'expired' => VisitorPassStatus.expired,
        _ => VisitorPassStatus.pending,
      },
      visitorName: (json['visitor_name'] as String?)?.trim().isNotEmpty == true
          ? json['visitor_name'] as String
          : 'Visitor',
      code: (online ?? offline) ?? '——————',
      onlineCode: online,
      offlineCode: offline,
      hostName: json['host_name'] as String?,
      roomNumber: json['room_number'] as String?,
      suiteName: json['suite_name'] as String?,
      email: json['email'] as String?,
      phone: json['phone'] as String?,
      submittedLabel: json['submitted_label'] as String?,
      createdAt:
          DateTime.tryParse(json['created_at'] as String? ?? '')?.toLocal(),
      isInside: json['is_inside'] as bool? ?? false,
      arrivalLabel: json['arrival_label'] as String?,
    );
  }
}
