import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/guest/dining/domain/menu_item.dart';

/// Raised when a dining action could not be completed, carrying a
/// user-facing message.
class DiningException implements Exception {
  DiningException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to the Dining (Place Order) endpoints with the tablet's own device
/// token, which is what scopes every call to this room's checked-in guest.
class DiningRepository {
  DiningRepository({Dio? dio})
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

  /// The restaurant's bookable menu.
  Future<DiningMenu> fetchMenu(String deviceToken) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/dining/menu')),
        options: _auth(deviceToken),
      );
      return DiningMenu.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw DiningException(_messageFrom(error));
    } catch (error) {
      debugPrint('DiningRepository: fetchMenu failed — $error');
      throw DiningException('Could not load the menu.');
    }
  }

  /// The guest's own confirmed dining orders, newest first — however they
  /// were paid. Failures fall back to an empty list so the screen still
  /// renders.
  Future<List<DiningOrderSummary>> orders(String deviceToken) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/dining/orders')),
        options: _auth(deviceToken),
      );
      final list = (response.data?['data'] as List?) ?? const [];
      return list
          .whereType<Map>()
          .map((e) => DiningOrderSummary.fromJson(e.cast<String, dynamic>()))
          .toList();
    } catch (error) {
      debugPrint('DiningRepository: orders failed — $error');
      return const [];
    }
  }

  /// Charge the cart straight to the room's folio — confirmed immediately,
  /// no Paystack round trip.
  Future<DiningOrderConfirmation> bookToRoom(
    String deviceToken,
    Map<String, dynamic> payload,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/dining/book')),
        data: payload,
        options: _auth(deviceToken),
      );
      return DiningOrderConfirmation.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw DiningException(_messageFrom(error));
    } catch (error) {
      debugPrint('DiningRepository: bookToRoom failed — $error');
      throw DiningException('Could not place your order. Please try again.');
    }
  }

  /// Price the order and open a Paystack charge. Returns the hosted
  /// checkout URL and the priced summary (subtotal, service fee, total).
  Future<DiningOrderQuote> initializePaystack(
    String deviceToken,
    Map<String, dynamic> payload,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/dining/initialize')),
        data: payload,
        options: _auth(deviceToken),
      );
      return DiningOrderQuote.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw DiningException(_messageFrom(error));
    } catch (error) {
      debugPrint('DiningRepository: initializePaystack failed — $error');
      throw DiningException('Could not start the payment. Please try again.');
    }
  }

  /// Verify the paid [reference] and confirm the order.
  Future<DiningOrderConfirmation> confirmPaystack(
    String deviceToken,
    String reference,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/dining/confirm')),
        data: {'reference': reference},
        options: _auth(deviceToken),
      );
      return DiningOrderConfirmation.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw DiningException(_messageFrom(error));
    } catch (error) {
      debugPrint('DiningRepository: confirmPaystack failed — $error');
      throw DiningException('Could not confirm your order. Please try again.');
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
