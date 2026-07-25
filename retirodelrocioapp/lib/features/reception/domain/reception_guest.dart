import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/features/reception/domain/reception_booking_row.dart';

/// A guest in the reception guest list, rolled up from their bookings.
@immutable
class ReceptionGuestSummary {
  const ReceptionGuestSummary({
    required this.key,
    required this.name,
    this.email,
    this.phone,
    this.stays = 0,
    this.lastStayLabel,
    this.inHouse = false,
  });

  /// Stable identity used to open the profile, e.g. "email:ada@mail.com".
  final String key;
  final String name;
  final String? email;
  final String? phone;
  final int stays;
  final String? lastStayLabel;

  /// True while any of the guest's bookings is currently checked in.
  final bool inHouse;

  String get initials {
    final parts = name.trim().split(RegExp(r'\s+'));
    final letters = parts.where((p) => p.isNotEmpty).take(2).map((p) => p[0]);
    return letters.isEmpty ? 'G' : letters.join().toUpperCase();
  }

  factory ReceptionGuestSummary.fromJson(Map<String, dynamic> json) =>
      ReceptionGuestSummary(
        key: json['key'] as String? ?? '',
        name: json['name'] as String? ?? 'Guest',
        email: json['email'] as String?,
        phone: json['phone'] as String?,
        stays: (json['stays'] as num?)?.toInt() ?? 0,
        lastStayLabel: json['last_stay_label'] as String?,
        inHouse: json['in_house'] as bool? ?? false,
      );
}

/// Preferences derived from a guest's stay history — nothing invented, just what
/// they have chosen before.
@immutable
class GuestPreferences {
  const GuestPreferences({
    this.favouriteRoom,
    this.usualPartySize,
    this.usesAirportPickup = false,
  });

  final String? favouriteRoom;
  final int? usualPartySize;
  final bool usesAirportPickup;

  factory GuestPreferences.fromJson(Map<String, dynamic> json) =>
      GuestPreferences(
        favouriteRoom: json['favourite_room'] as String?,
        usualPartySize: (json['usual_party_size'] as num?)?.toInt(),
        usesAirportPickup: json['uses_airport_pickup'] as bool? ?? false,
      );
}

/// A guest's lifetime figures.
@immutable
class GuestStats {
  const GuestStats({
    this.totalStays = 0,
    this.totalNights = 0,
    this.totalSpendLabel = '',
    this.firstSeenLabel,
  });

  final int totalStays;
  final int totalNights;
  final String totalSpendLabel;
  final String? firstSeenLabel;

  factory GuestStats.fromJson(Map<String, dynamic> json) => GuestStats(
        totalStays: (json['total_stays'] as num?)?.toInt() ?? 0,
        totalNights: (json['total_nights'] as num?)?.toInt() ?? 0,
        totalSpendLabel: json['total_spend_label'] as String? ?? '',
        firstSeenLabel: json['first_seen_label'] as String?,
      );
}

/// A guest's full record: contact, stats, preferences and stay history.
@immutable
class ReceptionGuestProfile {
  const ReceptionGuestProfile({
    required this.key,
    required this.name,
    this.email,
    this.phone,
    this.inHouse = false,
    this.stats = const GuestStats(),
    this.preferences = const GuestPreferences(),
    this.history = const [],
  });

  final String key;
  final String name;
  final String? email;
  final String? phone;
  final bool inHouse;
  final GuestStats stats;
  final GuestPreferences preferences;
  final List<ReceptionBookingRow> history;

  String get initials {
    final parts = name.trim().split(RegExp(r'\s+'));
    final letters = parts.where((p) => p.isNotEmpty).take(2).map((p) => p[0]);
    return letters.isEmpty ? 'G' : letters.join().toUpperCase();
  }

  factory ReceptionGuestProfile.fromJson(Map<String, dynamic> json) =>
      ReceptionGuestProfile(
        key: json['key'] as String? ?? '',
        name: json['name'] as String? ?? 'Guest',
        email: json['email'] as String?,
        phone: json['phone'] as String?,
        inHouse: json['in_house'] as bool? ?? false,
        stats: GuestStats.fromJson(
          (json['stats'] as Map?)?.cast<String, dynamic>() ?? const {},
        ),
        preferences: GuestPreferences.fromJson(
          (json['preferences'] as Map?)?.cast<String, dynamic>() ?? const {},
        ),
        history: ((json['history'] as List?) ?? const [])
            .whereType<Map>()
            .map((e) => ReceptionBookingRow.fromJson(e.cast<String, dynamic>()))
            .toList(),
      );
}
