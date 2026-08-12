import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';

class PasswordResetException implements Exception {
  PasswordResetException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// OTP password-reset flow: request a code, verify it, set a new password.
class PasswordResetRepository {
  PasswordResetRepository({Dio? dio})
    : _dio =
          dio ??
          Dio(
            BaseOptions(
              connectTimeout: const Duration(seconds: 10),
              receiveTimeout: const Duration(seconds: 10),
            ),
          );

  final Dio _dio;

  Future<void> sendOtp(String email) =>
      _post('auth/forgot-password', {'email': email.trim()});

  Future<void> verifyOtp(String email, String otp) =>
      _post('auth/verify-otp', {'email': email.trim(), 'otp': otp.trim()});

  Future<void> resetPassword({
    required String email,
    required String otp,
    required String password,
  }) => _post('auth/reset-password', {
    'email': email.trim(),
    'otp': otp.trim(),
    'password': password,
    'password_confirmation': password,
  });

  Future<void> _post(String path, Map<String, dynamic> data) async {
    try {
      await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint(path)),
        data: data,
        options: Options(headers: {'Accept': 'application/json'}),
      );
    } on DioException catch (error) {
      throw PasswordResetException(_message(error));
    } catch (error) {
      debugPrint('PasswordResetRepository: unexpected error — $error');
      throw PasswordResetException('Something went wrong. Please try again.');
    }
  }

  String _message(DioException error) {
    if (error.response?.statusCode == 429) {
      return 'Too many attempts. Please wait a moment and try again.';
    }
    final data = error.response?.data;
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        if (first is List && first.isNotEmpty) return first.first.toString();
      }
      final message = data['message'];
      if (message is String && message.isNotEmpty) return message;
    }
    if (error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout) {
      return 'Cannot reach the server. Check the connection.';
    }
    return 'Something went wrong. Please try again.';
  }
}
