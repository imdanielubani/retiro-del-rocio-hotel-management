import 'package:flutter/foundation.dart';

/// A verified visitor logged for today (Visitors Today list). The Visitor Pass
/// feature fills this in; until then the backend returns none and the dashboard
/// shows its empty state.
@immutable
class SecurityVisitor {
  const SecurityVisitor({
    required this.id,
    required this.name,
    this.suiteName,
    this.roomNumber,
    this.passCode,
    this.arrivalLabel,
    this.isInside = false,
    this.isVerified = true,
  });

  final int id;
  final String name;
  final String? suiteName;
  final String? roomNumber;
  final String? passCode;
  final String? arrivalLabel;
  final bool isInside;
  final bool isVerified;

  String get initials {
    final parts = name.trim().split(RegExp(r'\s+'));
    final letters = parts.where((p) => p.isNotEmpty).take(2).map((p) => p[0]);
    return letters.join().toUpperCase();
  }

  factory SecurityVisitor.fromJson(Map<String, dynamic> json) => SecurityVisitor(
        id: (json['id'] as num?)?.toInt() ?? 0,
        name: json['name'] as String? ?? 'Visitor',
        suiteName: json['suite_name'] as String?,
        roomNumber: json['room_number'] as String?,
        passCode: json['pass_code'] as String?,
        arrivalLabel: json['arrival_label'] as String?,
        isInside: json['is_inside'] as bool? ?? false,
        isVerified: json['is_verified'] as bool? ?? true,
      );
}

/// A pending visitor-pass request awaiting the officer's verification
/// (Visitor Pass Requests column). Also empty until the pass feature is built.
@immutable
class VisitorPassRequest {
  const VisitorPassRequest({
    required this.id,
    required this.name,
    this.suiteName,
    this.roomNumber,
    this.passCode,
    this.onlineCode,
    this.offlineCode,
    this.email,
    this.whatsapp,
    this.submittedLabel,
    this.arrivalLabel,
    this.isVerified = false,
  });

  final int id;
  final String name;
  final String? suiteName;
  final String? roomNumber;
  final String? passCode;
  final String? onlineCode;
  final String? offlineCode;
  final String? email;
  final String? whatsapp;
  final String? submittedLabel;
  final String? arrivalLabel;
  final bool isVerified;

  String get initials {
    final parts = name.trim().split(RegExp(r'\s+'));
    final letters = parts.where((p) => p.isNotEmpty).take(2).map((p) => p[0]);
    return letters.join().toUpperCase();
  }

  factory VisitorPassRequest.fromJson(Map<String, dynamic> json) =>
      VisitorPassRequest(
        id: (json['id'] as num?)?.toInt() ?? 0,
        name: json['name'] as String? ?? 'Visitor',
        suiteName: json['suite_name'] as String?,
        roomNumber: json['room_number'] as String?,
        passCode: json['pass_code'] as String?,
        onlineCode: json['online_code'] as String?,
        offlineCode: json['offline_code'] as String?,
        email: json['email'] as String?,
        whatsapp: json['whatsapp'] as String?,
        submittedLabel: json['submitted_label'] as String?,
        arrivalLabel: json['arrival_label'] as String?,
        isVerified: json['is_verified'] as bool? ?? false,
      );
}
