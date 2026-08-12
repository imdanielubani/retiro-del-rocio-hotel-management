import 'dart:convert';

import 'package:flutter/foundation.dart';

/// An authenticated staff member on a staff tablet.
@immutable
class StaffSession {
  const StaffSession({
    required this.token,
    required this.name,
    required this.email,
    required this.role,
    this.roles = const [],
    this.expiresAt,
  });

  /// The staffer's JWT (separate from the device token).
  final String token;
  final String name;
  final String email;

  /// The active role this tablet/station is locked to (e.g. "reception").
  final String role;

  /// All roles the user holds.
  final List<String> roles;

  /// When the JWT expires (from the token's `exp` claim).
  final DateTime? expiresAt;

  Duration? get timeLeft => expiresAt?.difference(DateTime.now());

  bool get isExpired => expiresAt != null && DateTime.now().isAfter(expiresAt!);

  factory StaffSession.fromLoginResponse(
    Map<String, dynamic> json,
    String activeRole,
  ) {
    final user = (json['user'] as Map?)?.cast<String, dynamic>() ?? const {};
    return StaffSession(
      token: json['token'] as String? ?? '',
      role: json['role'] as String? ?? activeRole,
      name: user['name'] as String? ?? 'Staff',
      email: user['email'] as String? ?? '',
      roles:
          (user['roles'] as List?)?.map((e) => e.toString()).toList() ??
          const [],
      expiresAt: _expiry(json),
    );
  }

  /// Reads `expires_at` from the response, or falls back to the JWT `exp` claim.
  static DateTime? _expiry(Map<String, dynamic> json) {
    final iso = json['expires_at'] as String?;
    if (iso != null) {
      final parsed = DateTime.tryParse(iso);
      if (parsed != null) return parsed;
    }
    return _expFromJwt(json['token'] as String?);
  }

  static DateTime? _expFromJwt(String? token) {
    if (token == null) return null;
    final parts = token.split('.');
    if (parts.length != 3) return null;
    try {
      final normalized = base64Url.normalize(parts[1]);
      final payload =
          jsonDecode(utf8.decode(base64Url.decode(normalized)))
              as Map<String, dynamic>;
      final exp = payload['exp'];
      if (exp is int) return DateTime.fromMillisecondsSinceEpoch(exp * 1000);
    } catch (_) {}
    return null;
  }

  Map<String, dynamic> toJson() => {
    'token': token,
    'name': name,
    'email': email,
    'role': role,
    'roles': roles,
    'expires_at': expiresAt?.toIso8601String(),
  };

  factory StaffSession.fromJson(Map<String, dynamic> json) => StaffSession(
    token: json['token'] as String? ?? '',
    name: json['name'] as String? ?? 'Staff',
    email: json['email'] as String? ?? '',
    role: json['role'] as String? ?? '',
    roles:
        (json['roles'] as List?)?.map((e) => e.toString()).toList() ?? const [],
    expiresAt: DateTime.tryParse(json['expires_at'] as String? ?? ''),
  );

  /// Human label for the role, e.g. "reception" → "Reception".
  String get roleLabel =>
      role.isEmpty ? 'Staff' : role[0].toUpperCase() + role.substring(1);
}
