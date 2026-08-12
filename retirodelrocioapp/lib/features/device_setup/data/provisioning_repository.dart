import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/core/storage/device_session_store.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';

/// Raised when provisioning fails, carrying a user-facing [message].
class ProvisioningException implements Exception {
  ProvisioningException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Binds this tablet to the hotel via `POST /v1/tablets/provision` and persists
/// the resulting session.
class ProvisioningRepository {
  ProvisioningRepository({Dio? dio, DeviceSessionStore? store})
    : _dio =
          dio ??
          Dio(
            BaseOptions(
              connectTimeout: const Duration(seconds: 10),
              receiveTimeout: const Duration(seconds: 10),
            ),
          ),
      _store = store ?? DeviceSessionStore();

  final Dio _dio;
  final DeviceSessionStore _store;

  /// Pairs using the JSON payload decoded from a provisioning QR. This is the
  /// only way in: pairing by a typed code was removed, so a device can never be
  /// bound without the token the dashboard mints.
  Future<ProvisionedDevice> provisionWithQrPayload(
    Map<String, dynamic> payload,
  ) {
    final deviceCode = payload['device_code'] as String?;
    if (deviceCode == null || deviceCode.isEmpty) {
      throw ProvisioningException('This QR code is not a valid setup code.');
    }
    return _provision(
      deviceCode: deviceCode,
      provisionToken: payload['provision_token'] as String?,
    );
  }

  Future<ProvisionedDevice> _provision({
    required String deviceCode,
    String? provisionToken,
  }) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/provision')),
        data: {
          'device_code': deviceCode,
          if (provisionToken != null && provisionToken.isNotEmpty)
            'provision_token': provisionToken,
          'app_version': '1.0.0',
        },
      );
      final device = ProvisionedDevice.fromProvisionResponse(response.data!);
      await _store.save(device);
      return device;
    } on DioException catch (error) {
      throw ProvisioningException(_messageFrom(error));
    } catch (error) {
      debugPrint('ProvisioningRepository: unexpected error — $error');
      throw ProvisioningException('Something went wrong. Please try again.');
    }
  }

  String _messageFrom(DioException error) {
    final data = error.response?.data;
    if (data is Map) {
      // Laravel validation: { message, errors: { field: [msg] } }
      final errors = data['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        if (first is List && first.isNotEmpty) return first.first.toString();
      }
      final message = data['message'];
      if (message is String && message.isNotEmpty) return message;
    }
    if (error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.connectionError) {
      return 'Cannot reach the server. Check the connection and try again.';
    }
    return 'Invalid or expired setup code.';
  }
}
