import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/guest/spa/domain/spa_service.dart';

/// Raised when a spa action could not be completed, carrying a user-facing
/// message.
class SpaException implements Exception {
  SpaException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to the Spa & Wellness endpoints with the tablet's own device token,
/// which is what scopes every call to this room's checked-in guest.
class SpaRepository {
  SpaRepository({Dio? dio})
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

  /// The bookable services and today's fixed appointment slots.
  Future<SpaCatalog> fetch(String deviceToken) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/spa/services')),
        options: _auth(deviceToken),
      );
      return SpaCatalog.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw SpaException(_messageFrom(error));
    } catch (error) {
      debugPrint('SpaRepository: fetch failed — $error');
      throw SpaException('Could not load spa services.');
    }
  }

  /// The guest's own confirmed spa sessions, newest first — however they
  /// were paid. Failures fall back to an empty list so the screen still
  /// renders.
  Future<List<SpaAppointment>> appointments(String deviceToken) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/spa/appointments')),
        options: _auth(deviceToken),
      );
      final list = (response.data?['data'] as List?) ?? const [];
      return list
          .whereType<Map>()
          .map((e) => SpaAppointment.fromJson(e.cast<String, dynamic>()))
          .toList();
    } catch (error) {
      debugPrint('SpaRepository: appointments failed — $error');
      return const [];
    }
  }

  /// Charge a session straight to the room's folio — confirmed immediately,
  /// no Paystack round trip.
  Future<SpaBookingConfirmation> bookToRoom(
    String deviceToken,
    String serviceSlug,
    String time,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/spa/book')),
        data: {'service_slug': serviceSlug, 'time': time},
        options: _auth(deviceToken),
      );
      return SpaBookingConfirmation.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw SpaException(_messageFrom(error));
    } catch (error) {
      debugPrint('SpaRepository: bookToRoom failed — $error');
      throw SpaException('Could not book that session. Please try again.');
    }
  }

  /// Price the session and open a Paystack charge. Returns the hosted
  /// checkout URL and the priced summary (subtotal, VAT, total).
  Future<SpaBookingQuote> initializePaystack(
    String deviceToken,
    String serviceSlug,
    String time,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/spa/initialize')),
        data: {'service_slug': serviceSlug, 'time': time},
        options: _auth(deviceToken),
      );
      return SpaBookingQuote.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw SpaException(_messageFrom(error));
    } catch (error) {
      debugPrint('SpaRepository: initializePaystack failed — $error');
      throw SpaException('Could not start the payment. Please try again.');
    }
  }

  /// Verify the paid [reference] and confirm the booking.
  Future<SpaBookingConfirmation> confirmPaystack(
    String deviceToken,
    String reference,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/spa/confirm')),
        data: {'reference': reference},
        options: _auth(deviceToken),
      );
      return SpaBookingConfirmation.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw SpaException(_messageFrom(error));
    } catch (error) {
      debugPrint('SpaRepository: confirmPaystack failed — $error');
      throw SpaException('Could not confirm your booking. Please try again.');
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
