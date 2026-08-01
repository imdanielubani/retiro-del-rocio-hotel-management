import 'package:flutter/foundation.dart';

/// A bookable movie (`GET /tablets/cinema/movies`), sourced from the same
/// catalogue the admin dashboard and the public website manage. Booking is by
/// whole private room — there's no per-seat selection.
@immutable
class Movie {
  const Movie({
    required this.id,
    required this.slug,
    required this.title,
    required this.genre,
    required this.duration,
    required this.rating,
    required this.synopsis,
    required this.posterUrl,
    required this.backdropUrl,
    required this.roomPrice,
    required this.roomPriceLabel,
    required this.classification,
    required this.classificationLabel,
    required this.showtimes,
    required this.isFeatured,
  });

  final int id;
  final String slug;
  final String title;
  final String? genre;
  final String? duration;
  final String? rating;
  final String? synopsis;
  final String posterUrl;
  final String backdropUrl;
  final int roomPrice;
  final String roomPriceLabel;

  /// now_showing · coming_soon.
  final String classification;
  final String classificationLabel;
  final List<String> showtimes;
  final bool isFeatured;

  bool get isComingSoon => classification == 'coming_soon';

  factory Movie.fromJson(Map<String, dynamic> json) => Movie(
    id: (json['id'] as num?)?.toInt() ?? 0,
    slug: json['slug'] as String? ?? '',
    title: json['title'] as String? ?? '',
    genre: json['genre'] as String?,
    duration: json['duration'] as String?,
    rating: json['rating'] as String?,
    synopsis: json['synopsis'] as String?,
    posterUrl: json['poster_url'] as String? ?? '',
    backdropUrl: json['backdrop_url'] as String? ?? '',
    roomPrice: (json['room_price'] as num?)?.toInt() ?? 0,
    roomPriceLabel: json['room_price_label'] as String? ?? 'NGN 0',
    classification: json['classification'] as String? ?? 'now_showing',
    classificationLabel: json['classification_label'] as String? ?? 'Now Showing',
    showtimes: ((json['showtimes'] as List?) ?? const [])
        .map((t) => t as String)
        .toList(),
    isFeatured: json['is_featured'] as bool? ?? false,
  );
}

/// A cinema snack (`cinema_snacks`), optionally added to a booking.
@immutable
class CinemaSnack {
  const CinemaSnack({
    required this.id,
    required this.name,
    required this.price,
    required this.priceLabel,
    required this.imageUrl,
  });

  final int id;
  final String name;
  final int price;
  final String priceLabel;
  final String imageUrl;

  factory CinemaSnack.fromJson(Map<String, dynamic> json) => CinemaSnack(
    id: (json['id'] as num?)?.toInt() ?? 0,
    name: json['name'] as String? ?? '',
    price: (json['price'] as num?)?.toInt() ?? 0,
    priceLabel: json['price_label'] as String? ?? 'NGN 0',
    imageUrl: json['image_url'] as String? ?? '',
  );
}

/// The cinema catalogue: bookable movies, snacks, and the fixed set of
/// private rooms (and how many guests each seats) every showing offers.
@immutable
class CinemaCatalog {
  const CinemaCatalog({
    required this.movies,
    required this.snacks,
    required this.rooms,
    required this.seatsPerRoom,
  });

  final List<Movie> movies;
  final List<CinemaSnack> snacks;
  final List<String> rooms;
  final int seatsPerRoom;

  static const empty = CinemaCatalog(
    movies: [],
    snacks: [],
    rooms: [],
    seatsPerRoom: 4,
  );

  factory CinemaCatalog.fromJson(Map<String, dynamic> json) => CinemaCatalog(
    movies: ((json['movies'] as List?) ?? const [])
        .map((m) => Movie.fromJson((m as Map).cast()))
        .toList(),
    snacks: ((json['snacks'] as List?) ?? const [])
        .map((s) => CinemaSnack.fromJson((s as Map).cast()))
        .toList(),
    rooms: ((json['rooms'] as List?) ?? const [])
        .map((r) => r as String)
        .toList(),
    seatsPerRoom: (json['seats_per_room'] as num?)?.toInt() ?? 4,
  );
}

/// A snack the guest has picked, with how many.
@immutable
class SnackSelection {
  const SnackSelection({required this.snack, required this.qty});

  final CinemaSnack snack;
  final int qty;

  int get total => snack.price * qty;

  Map<String, dynamic> toJson() => {'id': snack.id, 'qty': qty};
}

/// How the guest wants to pay for a cinema booking.
enum CinemaPaymentMethod { room, paystack }

/// One of the guest's own confirmed cinema bookings
/// (`GET /tablets/cinema/bookings`) — past or upcoming, however it was paid.
/// Drives the "My Bookings" list.
@immutable
class CinemaBookingAppointment {
  const CinemaBookingAppointment({
    required this.id,
    required this.code,
    required this.reference,
    required this.movieTitle,
    required this.showDateLabel,
    required this.showTime,
    required this.room,
    required this.guests,
    required this.snacksLabel,
    required this.status,
    required this.statusLabel,
    required this.chargedToRoom,
    required this.paymentMethodLabel,
    required this.totalLabel,
  });

  final int id;
  final String code;
  final String reference;
  final String movieTitle;
  final String? showDateLabel;
  final String? showTime;
  final String? room;
  final int guests;
  final String snacksLabel;

  /// confirmed · used · cancelled.
  final String status;
  final String statusLabel;
  final bool chargedToRoom;
  final String paymentMethodLabel;
  final String totalLabel;

  factory CinemaBookingAppointment.fromJson(Map<String, dynamic> json) =>
      CinemaBookingAppointment(
        id: (json['id'] as num?)?.toInt() ?? 0,
        code: json['code'] as String? ?? '',
        reference: json['reference'] as String? ?? '',
        movieTitle: json['movie_title'] as String? ?? 'Cinema booking',
        showDateLabel: json['show_date_label'] as String?,
        showTime: json['show_time'] as String?,
        room: json['room'] as String?,
        guests: (json['guests'] as num?)?.toInt() ?? 0,
        snacksLabel: json['snacks_label'] as String? ?? '—',
        status: json['status'] as String? ?? '',
        statusLabel: json['status_label'] as String? ?? '',
        chargedToRoom: json['charged_to_room'] as bool? ?? false,
        paymentMethodLabel: json['payment_method_label'] as String? ?? '',
        totalLabel: json['total_label'] as String? ?? 'NGN 0',
      );
}

/// The priced quote returned when opening a Paystack charge for a cinema
/// booking (`POST /tablets/cinema/initialize`).
@immutable
class CinemaBookingQuote {
  const CinemaBookingQuote({
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

  factory CinemaBookingQuote.fromJson(Map<String, dynamic> json) =>
      CinemaBookingQuote(
        authorizationUrl: json['authorization_url'] as String? ?? '',
        reference: json['reference'] as String? ?? '',
        callbackUrl: json['callback_url'] as String? ?? '',
        subtotalLabel: json['subtotal_label'] as String? ?? 'NGN 0',
        vatLabel: json['vat_label'] as String? ?? 'NGN 0',
        totalLabel: json['total_label'] as String? ?? 'NGN 0',
      );
}

/// The confirmed booking, returned by both the room-charge and the Paystack
/// confirm endpoints — drives the "Booking Confirmed" success screen.
@immutable
class CinemaBookingConfirmation {
  const CinemaBookingConfirmation({
    required this.reference,
    required this.movieTitle,
    required this.room,
    required this.showTime,
    required this.totalLabel,
  });

  final String reference;
  final String movieTitle;
  final String? room;
  final String? showTime;
  final String totalLabel;

  factory CinemaBookingConfirmation.fromJson(Map<String, dynamic> json) =>
      CinemaBookingConfirmation(
        reference: json['reference'] as String? ?? '',
        movieTitle: json['movie_title'] as String? ?? 'Cinema booking',
        room: json['room'] as String?,
        showTime: json['show_time'] as String?,
        totalLabel: json['total_label'] as String? ?? 'NGN 0',
      );
}
