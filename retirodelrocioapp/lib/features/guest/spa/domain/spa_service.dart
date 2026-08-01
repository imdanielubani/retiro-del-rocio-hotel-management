import 'package:flutter/foundation.dart';

/// A spa category (`spa_categories` — admin-managed, e.g. "Massages",
/// "Facials"), used to group and filter the guest tablet's service grid.
@immutable
class SpaCategory {
  const SpaCategory({
    required this.id,
    required this.slug,
    required this.name,
    required this.color,
  });

  final int id;
  final String slug;
  final String name;

  /// Hex colour string, e.g. `#16a34a` — the admin's chip colour for this
  /// category, reused for the badge on each of its services' cards.
  final String color;

  factory SpaCategory.fromJson(Map<String, dynamic> json) => SpaCategory(
    id: (json['id'] as num?)?.toInt() ?? 0,
    slug: json['slug'] as String? ?? '',
    name: json['name'] as String? ?? '',
    color: json['color'] as String? ?? '#6b7280',
  );
}

/// A bookable spa & wellness service (`GET /tablets/spa/services`), sourced
/// from the same catalogue the admin dashboard and the public website manage.
@immutable
class SpaService {
  const SpaService({
    required this.id,
    required this.slug,
    required this.name,
    required this.description,
    required this.price,
    required this.priceLabel,
    required this.durationMinutes,
    required this.durationLabel,
    required this.imageUrl,
    required this.categoryId,
    required this.category,
    required this.categoryColor,
  });

  final int id;
  final String slug;
  final String name;
  final String? description;
  final int price;
  final String priceLabel;
  final int? durationMinutes;
  final String? durationLabel;
  final String? imageUrl;

  /// The admin-managed category this service belongs to — null for a
  /// service the admin hasn't categorised.
  final int? categoryId;
  final String? category;
  final String categoryColor;

  factory SpaService.fromJson(Map<String, dynamic> json) => SpaService(
    id: (json['id'] as num?)?.toInt() ?? 0,
    slug: json['slug'] as String? ?? '',
    name: json['name'] as String? ?? '',
    description: json['description'] as String?,
    price: (json['price'] as num?)?.toInt() ?? 0,
    priceLabel: json['price_label'] as String? ?? 'NGN 0',
    durationMinutes: (json['duration_minutes'] as num?)?.toInt(),
    durationLabel: json['duration_label'] as String?,
    imageUrl: json['image_url'] as String?,
    categoryId: (json['category_id'] as num?)?.toInt(),
    category: json['category'] as String?,
    categoryColor: json['category_color'] as String? ?? '#6b7280',
  );
}

/// The spa catalogue: bookable services (with their real categories) plus a
/// handful of suggested quick-pick times — the guest isn't limited to these,
/// they can pick any time of their own across the full 24-hour clock.
@immutable
class SpaCatalog {
  const SpaCatalog({
    required this.services,
    required this.categories,
    required this.availableTimes,
  });

  final List<SpaService> services;
  final List<SpaCategory> categories;
  final List<String> availableTimes;

  static const empty = SpaCatalog(services: [], categories: [], availableTimes: []);

  factory SpaCatalog.fromJson(Map<String, dynamic> json) => SpaCatalog(
    services: ((json['services'] as List?) ?? const [])
        .map((s) => SpaService.fromJson((s as Map).cast()))
        .toList(),
    categories: ((json['categories'] as List?) ?? const [])
        .map((c) => SpaCategory.fromJson((c as Map).cast()))
        .toList(),
    availableTimes: ((json['available_times'] as List?) ?? const [])
        .map((t) => t as String)
        .toList(),
  );
}

/// How the guest wants to pay for a spa booking.
enum SpaPaymentMethod { room, paystack }

/// One of the guest's own confirmed spa sessions (`GET /tablets/spa/appointments`)
/// — past or upcoming, however it was paid. Drives the "My Appointments" list.
@immutable
class SpaAppointment {
  const SpaAppointment({
    required this.id,
    required this.reference,
    required this.serviceName,
    required this.dateLabel,
    required this.time,
    required this.status,
    required this.statusLabel,
    required this.chargedToRoom,
    required this.paymentMethodLabel,
    required this.totalLabel,
  });

  final int id;
  final String reference;
  final String serviceName;
  final String? dateLabel;
  final String time;

  /// confirmed · pending · completed · cancelled.
  final String status;
  final String statusLabel;
  final bool chargedToRoom;
  final String paymentMethodLabel;
  final String totalLabel;

  factory SpaAppointment.fromJson(Map<String, dynamic> json) => SpaAppointment(
    id: (json['id'] as num?)?.toInt() ?? 0,
    reference: json['reference'] as String? ?? '',
    serviceName: json['service_name'] as String? ?? 'Spa session',
    dateLabel: json['date_label'] as String?,
    time: json['time'] as String? ?? '',
    status: json['status'] as String? ?? '',
    statusLabel: json['status_label'] as String? ?? '',
    chargedToRoom: json['charged_to_room'] as bool? ?? false,
    paymentMethodLabel: json['payment_method_label'] as String? ?? '',
    totalLabel: json['total_label'] as String? ?? 'NGN 0',
  );
}

/// The priced quote returned when opening a Paystack charge for a spa
/// booking (`POST /tablets/spa/initialize`).
@immutable
class SpaBookingQuote {
  const SpaBookingQuote({
    required this.authorizationUrl,
    required this.reference,
    required this.callbackUrl,
    required this.subtotalLabel,
    required this.vatLabel,
    required this.totalLabel,
  });

  final String authorizationUrl;
  final String reference;
  final String callbackUrl;
  final String subtotalLabel;
  final String vatLabel;
  final String totalLabel;

  factory SpaBookingQuote.fromJson(Map<String, dynamic> json) =>
      SpaBookingQuote(
        authorizationUrl: json['authorization_url'] as String? ?? '',
        reference: json['reference'] as String? ?? '',
        callbackUrl: json['callback_url'] as String? ?? '',
        subtotalLabel: json['subtotal_label'] as String? ?? 'NGN 0',
        vatLabel: json['vat_label'] as String? ?? 'NGN 0',
        totalLabel: json['total_label'] as String? ?? 'NGN 0',
      );
}

/// The confirmed booking, returned by both the room-charge and the Paystack
/// confirm endpoints — drives the "Spa Booking Confirmed" success screen.
@immutable
class SpaBookingConfirmation {
  const SpaBookingConfirmation({
    required this.reference,
    required this.serviceName,
    required this.time,
    required this.totalLabel,
  });

  final String reference;
  final String serviceName;
  final String time;
  final String totalLabel;

  factory SpaBookingConfirmation.fromJson(Map<String, dynamic> json) =>
      SpaBookingConfirmation(
        reference: json['reference'] as String? ?? '',
        serviceName: json['service_name'] as String? ?? 'Spa session',
        time: json['time'] as String? ?? '',
        totalLabel: json['total_label'] as String? ?? 'NGN 0',
      );
}
