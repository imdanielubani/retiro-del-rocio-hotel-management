import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/guest/bills/domain/bill.dart';

/// Raised when a bill action could not be completed, carrying a user-facing
/// message.
class BillException implements Exception {
  BillException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Talks to the My Bills endpoints with the tablet's own device token, which
/// is what scopes every call to this room's checked-in guest.
class BillsRepository {
  BillsRepository({Dio? dio})
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

  /// The itemised folio for the checked-in guest.
  Future<Bill> fetch(String deviceToken) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/my-bills')),
        options: _auth(deviceToken),
      );
      return Bill.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw BillException(_messageFrom(error));
    } catch (error) {
      debugPrint('BillsRepository: fetch failed — $error');
      throw BillException('Could not load your bill.');
    }
  }

  /// Price the outstanding balance and open a Paystack charge.
  Future<BillPaymentQuote> initializePaystack(String deviceToken) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/my-bills/initialize')),
        options: _auth(deviceToken),
      );
      return BillPaymentQuote.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw BillException(_messageFrom(error));
    } catch (error) {
      debugPrint('BillsRepository: initializePaystack failed — $error');
      throw BillException('Could not start the payment. Please try again.');
    }
  }

  /// Verify the paid [reference] and record the settlement.
  Future<BillPaymentConfirmation> confirmPaystack(
    String deviceToken,
    String reference,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/my-bills/confirm')),
        data: {'reference': reference},
        options: _auth(deviceToken),
      );
      return BillPaymentConfirmation.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw BillException(_messageFrom(error));
    } catch (error) {
      debugPrint('BillsRepository: confirmPaystack failed — $error');
      throw BillException('Could not confirm your payment. Please try again.');
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
