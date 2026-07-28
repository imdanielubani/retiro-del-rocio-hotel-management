import 'package:flutter/foundation.dart';

/// The reservation header of the My Stay screen — the room, its photo, the stay
/// window and the nightly rate.
@immutable
class StayReservation {
  const StayReservation({
    required this.roomName,
    this.unitLabel,
    this.imageUrl,
    this.checkIn,
    this.checkOut,
    required this.nights,
    required this.ratePerNight,
    required this.rateLabel,
  });

  final String roomName;

  /// e.g. "Room 101".
  final String? unitLabel;
  final String? imageUrl;

  /// Arrival / departure carrying the hotel's policy times. `toLocal()` puts the
  /// ISO offset back on the tablet's clock.
  final DateTime? checkIn;
  final DateTime? checkOut;

  final int nights;
  final int ratePerNight;
  final String rateLabel;

  factory StayReservation.fromJson(
    Map<String, dynamic> json,
  ) => StayReservation(
    roomName: (json['room_name'] as String?)?.trim().isNotEmpty == true
        ? json['room_name'] as String
        : 'Your Room',
    unitLabel: json['unit_label'] as String?,
    imageUrl: json['image_url'] as String?,
    checkIn: DateTime.tryParse(json['check_in'] as String? ?? '')?.toLocal(),
    checkOut: DateTime.tryParse(json['check_out'] as String? ?? '')?.toLocal(),
    nights: (json['nights'] as num?)?.toInt() ?? 0,
    ratePerNight: (json['rate_per_night'] as num?)?.toInt() ?? 0,
    rateLabel: json['rate_label'] as String? ?? '',
  );
}

/// The guests on the reservation — the room owner and how many are in the party.
@immutable
class StayGuests {
  const StayGuests({required this.primaryName, required this.partySize});

  final String primaryName;
  final int partySize;

  factory StayGuests.fromJson(Map<String, dynamic> json) => StayGuests(
    primaryName: (json['primary_name'] as String?)?.trim().isNotEmpty == true
        ? json['primary_name'] as String
        : 'Guest',
    partySize: (json['party_size'] as num?)?.toInt() ?? 1,
  );
}

/// A visitor the guest has invited during the stay, shown in the Guests card —
/// their name and a guest-facing status (Invited · Inside · Checked out).
@immutable
class StayVisitor {
  const StayVisitor({
    required this.name,
    required this.initials,
    required this.status,
    required this.statusLabel,
  });

  final String name;
  final String initials;

  /// The collapsed status key: pending · inside · exited · denied · …
  final String status;
  final String statusLabel;

  factory StayVisitor.fromJson(Map<String, dynamic> json) => StayVisitor(
    name: (json['name'] as String?)?.trim().isNotEmpty == true
        ? json['name'] as String
        : 'Visitor',
    initials: (json['initials'] as String?)?.trim().isNotEmpty == true
        ? json['initials'] as String
        : 'V',
    status: json['status'] as String? ?? '',
    statusLabel: json['status_label'] as String? ?? '',
  );
}

/// One line in the Stay Summary card, e.g. Room Rate · NGN 7,500 × 5 · NGN 37,500.
@immutable
class StaySummaryLine {
  const StaySummaryLine({
    required this.label,
    this.sub,
    required this.amountLabel,
  });

  final String label;
  final String? sub;
  final String amountLabel;

  factory StaySummaryLine.fromJson(Map<String, dynamic> json) =>
      StaySummaryLine(
        label: json['label'] as String? ?? '',
        sub: (json['sub'] as String?)?.trim().isEmpty == true
            ? null
            : json['sub'] as String?,
        amountLabel: json['amount_label'] as String? ?? '',
      );
}

/// A single room inclusion, e.g. High-Speed WiFi · Included.
@immutable
class StayInclusion {
  const StayInclusion({required this.title, required this.value});

  final String title;
  final String value;

  factory StayInclusion.fromJson(Map<String, dynamic> json) => StayInclusion(
    title: json['title'] as String? ?? '',
    value: json['value'] as String? ?? '',
  );
}

/// The result of extending the stay — the extra nights, their cost and the new
/// checkout date, used by the confirmation and success dialogs.
@immutable
class StayExtension {
  const StayExtension({
    required this.additionalNights,
    required this.additionalCost,
    required this.additionalCostLabel,
    this.newCheckOut,
    required this.newCheckOutLabel,
  });

  final int additionalNights;
  final int additionalCost;
  final String additionalCostLabel;
  final DateTime? newCheckOut;
  final String newCheckOutLabel;

  factory StayExtension.fromJson(Map<String, dynamic> json) => StayExtension(
    additionalNights: (json['additional_nights'] as num?)?.toInt() ?? 0,
    additionalCost: (json['additional_cost'] as num?)?.toInt() ?? 0,
    additionalCostLabel: json['additional_cost_label'] as String? ?? '',
    newCheckOut: DateTime.tryParse(
      json['new_check_out'] as String? ?? '',
    )?.toLocal(),
    newCheckOutLabel: json['new_check_out_label'] as String? ?? '',
  );
}

/// A priced, Paystack-ready extension (from `POST /tablets/extend-stay/initialize`).
///
/// Carries the hosted-checkout URL to open, the [reference] to verify with, and
/// the priced summary the Payment Summary popup renders (subtotal, VAT, total).
@immutable
class ExtensionQuote {
  const ExtensionQuote({
    required this.authorizationUrl,
    required this.callbackUrl,
    required this.reference,
    required this.additionalNights,
    required this.subtotalLabel,
    required this.vatLabel,
    required this.totalLabel,
  });

  final String authorizationUrl;

  /// The URL Paystack redirects to once the charge finishes — the tablet's
  /// WebView watches for it to know payment is done.
  final String callbackUrl;
  final String reference;
  final int additionalNights;
  final String subtotalLabel;
  final String vatLabel;
  final String totalLabel;

  factory ExtensionQuote.fromJson(Map<String, dynamic> json) => ExtensionQuote(
    authorizationUrl: json['authorization_url'] as String? ?? '',
    callbackUrl: json['callback_url'] as String? ?? '',
    reference: json['reference'] as String? ?? '',
    additionalNights: (json['additional_nights'] as num?)?.toInt() ?? 0,
    subtotalLabel: json['subtotal_label'] as String? ?? '',
    vatLabel: json['vat_label'] as String? ?? '',
    totalLabel: json['total_label'] as String? ?? '',
  );
}

/// The whole My Stay payload (from `GET /v1/tablets/my-stay`).
@immutable
class GuestStay {
  const GuestStay({
    required this.reservation,
    required this.guests,
    required this.visitors,
    required this.summaryLines,
    required this.summaryTotalLabel,
    required this.currentBillLabel,
    required this.currentBillDue,
    required this.inclusions,
    this.extension,
  });

  final StayReservation reservation;
  final StayGuests guests;
  final List<StayVisitor> visitors;
  final List<StaySummaryLine> summaryLines;
  final String summaryTotalLabel;

  /// What's currently charged to the room — the same balance the My Bills
  /// screen shows. Not the stay's full cost (that's [summaryTotalLabel]).
  final String currentBillLabel;

  /// True when [currentBillLabel] is a real outstanding balance (> 0).
  final bool currentBillDue;
  final List<StayInclusion> inclusions;

  /// Present only on the response to an extension.
  final StayExtension? extension;

  factory GuestStay.fromJson(Map<String, dynamic> json) {
    final summary =
        (json['summary'] as Map?)?.cast<String, dynamic>() ?? const {};

    List<T> parse<T>(Object? list, T Function(Map<String, dynamic>) build) =>
        ((list as List?) ?? const [])
            .whereType<Map>()
            .map((e) => build(e.cast<String, dynamic>()))
            .toList();

    return GuestStay(
      reservation: StayReservation.fromJson(
        (json['reservation'] as Map?)?.cast<String, dynamic>() ?? const {},
      ),
      guests: StayGuests.fromJson(
        (json['guests'] as Map?)?.cast<String, dynamic>() ?? const {},
      ),
      visitors: parse(json['visitors'], StayVisitor.fromJson),
      summaryLines: parse(summary['lines'], StaySummaryLine.fromJson),
      summaryTotalLabel: summary['total_label'] as String? ?? '',
      currentBillLabel: json['current_bill_label'] as String? ?? '',
      currentBillDue: json['current_bill_due'] as bool? ?? false,
      inclusions: parse(json['inclusions'], StayInclusion.fromJson),
      extension: json['extension'] is Map
          ? StayExtension.fromJson(
              (json['extension'] as Map).cast<String, dynamic>(),
            )
          : null,
    );
  }
}
