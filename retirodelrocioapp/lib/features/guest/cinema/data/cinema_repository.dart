import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/guest/cinema/domain/cinema_service.dart';

/// Raised when a cinema action could not be completed, carrying a
/// user-facing message.
class CinemaException implements Exception {
  CinemaException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to the Cinema endpoints with the tablet's own device token, which is
/// what scopes every call to this room's checked-in guest.
class CinemaRepository {
  CinemaRepository({Dio? dio})
    : _dio =
          dio ??
          Dio(
            BaseOptions(
              connectTimeout: const Duration(seconds: 8),
              receiveTimeout: const Duration(seconds: 15),
              sendTimeout: const Duration(seconds: 15),
            ),
          );

  final Dio _dio;

  Options _auth(String deviceToken) => Options(
    headers: {
      'Authorization': 'Bearer $deviceToken',
      'Accept': 'application/json',
    },
  );

  /// The bookable movies, snacks and private rooms.
  Future<CinemaCatalog> fetch(String deviceToken) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/cinema/movies')),
        options: _auth(deviceToken),
      );
      return CinemaCatalog.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw CinemaException(_messageFrom(error));
    } catch (error) {
      debugPrint('CinemaRepository: fetch failed — $error');
      throw CinemaException('Could not load the cinema.');
    }
  }

  /// Which private rooms are already taken for a movie's showing.
  Future<List<String>> roomAvailability(
    String deviceToken,
    String movieSlug,
    String date,
    String time,
  ) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(
          ApiConfig.endpoint('tablets/cinema/$movieSlug/availability'),
        ).replace(queryParameters: {'date': date, 'time': time}),
        options: _auth(deviceToken),
      );
      final taken = (response.data?['data'] as Map?)?['taken_rooms'] as List?;
      return (taken ?? const []).map((r) => r as String).toList();
    } catch (error) {
      debugPrint('CinemaRepository: roomAvailability failed — $error');
      return const [];
    }
  }

  /// The guest's own confirmed cinema bookings, newest first — however they
  /// were paid. Failures fall back to an empty list so the screen still
  /// renders.
  Future<List<CinemaBookingAppointment>> bookings(String deviceToken) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/cinema/bookings')),
        options: _auth(deviceToken),
      );
      final list = (response.data?['data'] as List?) ?? const [];
      return list
          .whereType<Map>()
          .map(
            (e) => CinemaBookingAppointment.fromJson(e.cast<String, dynamic>()),
          )
          .toList();
    } catch (error) {
      debugPrint('CinemaRepository: bookings failed — $error');
      return const [];
    }
  }

  /// Charge a private room straight to the room's folio — confirmed
  /// immediately, no Paystack round trip.
  Future<CinemaBookingConfirmation> bookToRoom(
    String deviceToken,
    Map<String, dynamic> payload,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/cinema/book')),
        data: payload,
        options: _auth(deviceToken),
      );
      return CinemaBookingConfirmation.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw CinemaException(_messageFrom(error));
    } catch (error) {
      debugPrint('CinemaRepository: bookToRoom failed — $error');
      throw CinemaException('Could not book that room. Please try again.');
    }
  }

  /// Price the booking and open a Paystack charge. Returns the hosted
  /// checkout URL and the priced summary (subtotal, VAT, total).
  Future<CinemaBookingQuote> initializePaystack(
    String deviceToken,
    Map<String, dynamic> payload,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/cinema/initialize')),
        data: payload,
        options: _auth(deviceToken),
      );
      return CinemaBookingQuote.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw CinemaException(_messageFrom(error));
    } catch (error) {
      debugPrint('CinemaRepository: initializePaystack failed — $error');
      throw CinemaException('Could not start the payment. Please try again.');
    }
  }

  /// Verify the paid [reference] and confirm the booking.
  Future<CinemaBookingConfirmation> confirmPaystack(
    String deviceToken,
    String reference,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/cinema/confirm')),
        data: {'reference': reference},
        options: _auth(deviceToken),
      );
      return CinemaBookingConfirmation.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw CinemaException(_messageFrom(error));
    } catch (error) {
      debugPrint('CinemaRepository: confirmPaystack failed — $error');
      throw CinemaException(
        'Could not confirm your booking. Please try again.',
      );
    }
  }

  String _messageFrom(DioException error) {
    final data = error.response?.data;
    if (data is Map &&
        data['message'] is String &&
        (data['message'] as String).isNotEmpty) {
      return data['message'] as String;
    }
    switch (error.type) {
      case DioExceptionType.connectionError:
      case DioExceptionType.connectionTimeout:
        return 'No connection. Please try again.';
      default:
        return 'Something went wrong. Please try again.';
    }
  }
}
