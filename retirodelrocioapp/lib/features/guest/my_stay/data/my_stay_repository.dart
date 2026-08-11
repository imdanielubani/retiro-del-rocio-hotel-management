import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:intl/intl.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/guest/my_stay/domain/guest_stay.dart';

/// Raised when a My Stay action could not be completed, carrying a user-facing
/// message.
class MyStayException implements Exception {
  MyStayException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to the My Stay endpoints with the tablet's own device token, which is
/// what scopes every call to this room's checked-in guest.
class MyStayRepository {
  MyStayRepository({Dio? dio})
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

  /// The current reservation for this room. Throws [MyStayException] on failure
  /// so the screen can offer a retry rather than a blank slate.
  Future<GuestStay> fetch(String deviceToken) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/my-stay')),
        options: _auth(deviceToken),
      );
      return GuestStay.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw MyStayException(_messageFrom(error));
    } catch (error) {
      debugPrint('MyStayRepository: fetch failed — $error');
      throw MyStayException('Could not load your stay.');
    }
  }

  /// Price the extension to [newCheckOut] and open a Paystack charge. Returns the
  /// hosted-checkout URL and the priced summary (subtotal, VAT, total).
  Future<ExtensionQuote> initializeExtension(
    String deviceToken,
    DateTime newCheckOut,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/extend-stay/initialize')),
        data: {'check_out': DateFormat('yyyy-MM-dd').format(newCheckOut)},
        options: _auth(deviceToken),
      );
      return ExtensionQuote.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw MyStayException(_messageFrom(error));
    } catch (error) {
      debugPrint('MyStayRepository: initializeExtension failed — $error');
      throw MyStayException('Could not start the payment. Please try again.');
    }
  }

  /// Extend the stay straight against the room's folio, no Paystack round
  /// trip needed. Confirmed immediately. Returns the updated stay with its
  /// `extension` details.
  Future<GuestStay> chargeToRoom(
    String deviceToken,
    DateTime newCheckOut,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/extend-stay/charge-to-room')),
        data: {'check_out': DateFormat('yyyy-MM-dd').format(newCheckOut)},
        options: _auth(deviceToken),
      );
      return GuestStay.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw MyStayException(_messageFrom(error));
    } catch (error) {
      debugPrint('MyStayRepository: chargeToRoom failed — $error');
      throw MyStayException('Could not extend your stay. Please try again.');
    }
  }

  /// Verify the paid [reference] and move the checkout to [newCheckOut]. Returns
  /// the updated stay with its `extension` details.
  Future<GuestStay> extend(
    String deviceToken,
    DateTime newCheckOut,
    String reference,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/extend-stay')),
        data: {
          'check_out': DateFormat('yyyy-MM-dd').format(newCheckOut),
          'reference': reference,
        },
        options: _auth(deviceToken),
      );
      return GuestStay.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw MyStayException(_messageFrom(error));
    } catch (error) {
      debugPrint('MyStayRepository: extend failed — $error');
      throw MyStayException(
        'Could not confirm your extension. Please try again.',
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
