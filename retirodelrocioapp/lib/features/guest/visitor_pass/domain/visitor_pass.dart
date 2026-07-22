import 'package:flutter/foundation.dart';

/// Lifecycle of a visitor pass: pending at the gate → verified (let in) or
/// denied (turned away); or cancelled / expired.
enum VisitorPassStatus { pending, verified, denied, cancelled, expired }

/// A visitor pass a checked-in guest issued from the in-room tablet.
@immutable
class VisitorPass {
  const VisitorPass({
    required this.id,
    required this.reference,
    required this.status,
    required this.visitorName,
    required this.code,
    this.onlineCode,
    this.offlineCode,
    this.visitorEmail,
    this.visitorPhone,
    this.createdAt,
  });

  final int id;

  /// Human pass reference, e.g. "VP-2607-001".
  final String reference;

  final VisitorPassStatus status;
  final String visitorName;

  /// The visitor's primary code — the one-time TTLock (online) code when the gate
  /// lock is reachable, otherwise the manual offline code.
  final String code;

  /// The one-time TTLock passcode the visitor punches into the gate lock, or null
  /// when the lock was offline at issue time.
  final String? onlineCode;

  /// The manual code security keys at the gate — always present, and the fallback
  /// whenever the online code is unavailable.
  final String? offlineCode;

  final String? visitorEmail;
  final String? visitorPhone;
  final DateTime? createdAt;

  /// True when the visitor has a self-service TTLock code to use at the lock.
  bool get hasOnlineCode => (onlineCode ?? '').isNotEmpty;

  /// Up-to-two-letter initials for the visitor's avatar disc.
  String get initials {
    final parts = visitorName.trim().split(RegExp(r'\s+'));
    final letters = parts.where((p) => p.isNotEmpty).take(2).map((p) => p[0]);
    return letters.isEmpty ? 'V' : letters.join().toUpperCase();
  }

  factory VisitorPass.fromJson(Map<String, dynamic> json) => VisitorPass(
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
        code: json['code'] as String? ?? '——————',
        onlineCode: json['online_code'] as String?,
        offlineCode:
            json['offline_code'] as String? ?? json['code'] as String?,
        visitorEmail: json['visitor_email'] as String?,
        visitorPhone: json['visitor_phone'] as String?,
        createdAt:
            DateTime.tryParse(json['created_at'] as String? ?? '')?.toLocal(),
      );
}
