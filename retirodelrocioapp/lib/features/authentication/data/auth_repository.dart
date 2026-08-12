import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';

/// Raised on a failed sign-in. [lockedOut] is true when the server is
/// rate-limiting (too many attempts).
class AuthException implements Exception {
  AuthException(this.message, {this.lockedOut = false});
  final String message;
  final bool lockedOut;
  @override
  String toString() => message;
}

/// Staff authentication against the tablet's locked role
/// (`POST /v1/tablets/staff-login`, authenticated by the device token).
class AuthRepository {
  AuthRepository({Dio? dio})
    : _dio =
          dio ??
          Dio(
            BaseOptions(
              connectTimeout: const Duration(seconds: 10),
              receiveTimeout: const Duration(seconds: 10),
            ),
          );

  final Dio _dio;

  Future<StaffSession> staffLogin({
    required String deviceToken,
    required String email,
    required String password,
    required String activeRole,
  }) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/staff-login')),
        data: {'email': email.trim(), 'password': password},
        options: Options(
          headers: {
            'Authorization': 'Bearer $deviceToken',
            'Accept': 'application/json',
          },
        ),
      );
      return StaffSession.fromLoginResponse(response.data!, activeRole);
    } on DioException catch (error) {
      throw _mapError(error);
    } catch (error) {
      debugPrint('AuthRepository: unexpected error — $error');
      throw AuthException('Something went wrong. Please try again.');
    }
  }

  AuthException _mapError(DioException error) {
    final status = error.response?.statusCode;
    if (status == 429) {
      return AuthException(
        'Too many attempts. Please wait a moment before trying again.',
        lockedOut: true,
      );
    }
    if (status == 403) {
      return AuthException(
        'Your account is not active. Contact an administrator.',
      );
    }

    final data = error.response?.data;
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        if (first is List && first.isNotEmpty)
          return AuthException(first.first.toString());
      }
      final message = data['message'];
      if (message is String && message.isNotEmpty && status != 401) {
        return AuthException(message);
      }
    }
    if (error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout) {
      return AuthException('Cannot reach the server. Check the connection.');
    }
    return AuthException('Incorrect email or password.');
  }
}
