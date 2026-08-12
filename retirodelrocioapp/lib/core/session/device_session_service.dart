import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/core/storage/device_session_store.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';

/// Thrown when the backend no longer recognises this tablet — it was deleted or
/// unpaired in the admin dashboard, so its device token is dead. The app must
/// forget its session and go back to device setup.
class DeviceRevokedException implements Exception {
  const DeviceRevokedException();

  @override
  String toString() => 'This tablet is no longer paired.';
}

/// Confirms the stored pairing is still valid with the backend.
///
/// Deleting a tablet in the admin dashboard revokes its tokens, but the app
/// keeps its pairing on disk — without this check it would keep booting into
/// the welcome screen for a device the hotel no longer knows about.
class DeviceSessionService {
  DeviceSessionService({Dio? dio, DeviceSessionStore? store})
    : _dio =
          dio ??
          Dio(
            BaseOptions(
              connectTimeout: const Duration(seconds: 8),
              receiveTimeout: const Duration(seconds: 8),
            ),
          ),
      _store = store ?? DeviceSessionStore();

  final Dio _dio;
  final DeviceSessionStore _store;

  /// Re-checks [device] against `GET /v1/tablets/me`.
  ///
  /// Returns the refreshed device (its room or role may have been reassigned in
  /// the dashboard), or `null` if the pairing was revoked — in which case the
  /// stored session is deleted. A network failure is **not** treated as a
  /// revocation: an offline tablet keeps working with what it has.
  Future<ProvisionedDevice?> verify(ProvisionedDevice device) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/me')),
        options: Options(
          headers: {
            'Authorization': 'Bearer ${device.token}',
            'Accept': 'application/json',
          },
        ),
      );

      final json = (response.data?['device'] as Map?)?.cast<String, dynamic>();
      if (json == null) return device;

      final refreshed = ProvisionedDevice.fromDeviceJson(
        json,
        token: device.token,
      );
      await _store.save(refreshed);
      return refreshed;
    } on DioException catch (error) {
      if (_isRevoked(error)) {
        debugPrint('DeviceSessionService: pairing revoked — clearing session.');
        await _store.clear();
        return null;
      }
      // Offline or server down: keep the tablet running as paired.
      debugPrint('DeviceSessionService: verify skipped — ${error.type}');
      return device;
    }
  }

  /// Forgets the pairing (after a revocation detected elsewhere).
  Future<void> clear() => _store.clear();

  /// 401 (token gone), 403 (unpaired) and 404 (device deleted) all mean the
  /// backend has disowned this tablet. Anything else is a transport problem.
  static bool _isRevoked(DioException error) {
    final code = error.response?.statusCode;
    return code == 401 || code == 403 || code == 404;
  }

  static bool isRevokedResponse(DioException error) => _isRevoked(error);
}
