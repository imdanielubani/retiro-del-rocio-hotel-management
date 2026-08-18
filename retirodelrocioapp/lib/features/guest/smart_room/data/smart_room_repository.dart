import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/core/error/messaged_exception.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/domain/smart_device.dart';
import 'package:retirodelrocioapp/features/guest/smart_room/domain/smart_scene.dart';

/// Raised when a Smart Room action could not be completed, carrying a
/// user-facing [message] — e.g. a 422 (invalid capability/value), a 502
/// (Tuya offline/unreachable) or a 403 (device/scene not in this guest's
/// room).
class SmartRoomException implements MessagedException {
  SmartRoomException(this.message);
  @override
  final String message;
  @override
  String toString() => message;
}

/// The outcome of `POST guest/room/scenes/{id}/activate`. [ok] is true only
/// on a full 200; a 207 partial failure sets [ok] false and lists
/// [failedDevices] so the UI can say exactly what didn't run.
@immutable
class SceneActivationResult {
  const SceneActivationResult({
    required this.ok,
    this.message,
    this.failedDevices = const [],
  });

  final bool ok;
  final String? message;
  final List<String> failedDevices;

  bool get isPartialFailure => !ok;
}

/// Talks to the guest Smart Room endpoints with the room tablet's Sanctum
/// device token — `device.token` — the same bearer-header and
/// `DioException` → typed-exception mapping convention as `bar_repository.dart`.
/// The server derives `room_unit_id` from the token itself, so every call
/// here is already scoped to this tablet's own room.
class SmartRoomRepository {
  SmartRoomRepository({Dio? dio})
    : _dio =
          dio ??
          Dio(
            BaseOptions(
              connectTimeout: const Duration(seconds: 8),
              receiveTimeout: const Duration(seconds: 8),
            ),
          );

  final Dio _dio;

  Options _auth(String token) => Options(
    headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json'},
  );

  /// Every device in this room, optionally narrowed to a single [type]
  /// (`light`/`ac`/`curtain`/`tv`).
  Future<List<SmartDevice>> fetchDevices(
    ProvisionedDevice device, {
    String? type,
  }) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('guest/room/devices')).replace(
          queryParameters: (type != null && type.isNotEmpty)
              ? {'type': type}
              : null,
        ),
        options: _auth(device.token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => SmartDevice.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('SmartRoomRepository: fetchDevices failed — $error');
      return const [];
    }
  }

  Future<SmartDevice?> fetchDevice(ProvisionedDevice device, int id) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('guest/room/devices/$id')),
        options: _auth(device.token),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data != null ? SmartDevice.fromJson(data) : null;
    } catch (error) {
      debugPrint('SmartRoomRepository: fetchDevice failed — $error');
      return null;
    }
  }

  /// Sends `{"capability": ..., "value": ...}` — throws [SmartRoomException]
  /// on a 422 (invalid capability/value for this device), 502 (Tuya
  /// offline/unreachable) or 403 (device not in this guest's room).
  Future<SmartDevice> sendCommand(
    ProvisionedDevice device,
    int deviceId,
    String capability,
    dynamic value,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('guest/room/devices/$deviceId/command')),
        data: {'capability': capability, 'value': value},
        options: _auth(device.token),
      );
      return SmartDevice.fromJson(
        (response.data!['data'] as Map).cast<String, dynamic>(),
      );
    } on DioException catch (error) {
      throw SmartRoomException(_messageFrom(error));
    } catch (error) {
      debugPrint('SmartRoomRepository: sendCommand failed — $error');
      throw SmartRoomException('Could not send that command.');
    }
  }

  Future<List<SmartScene>> fetchScenes(ProvisionedDevice device) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('guest/room/scenes')),
        options: _auth(device.token),
      );
      final rows = (response.data?['data'] as List?) ?? const [];
      return rows.map((r) => SmartScene.fromJson((r as Map).cast())).toList();
    } catch (error) {
      debugPrint('SmartRoomRepository: fetchScenes failed — $error');
      return const [];
    }
  }

  /// Activates a scene. A 200 (`{"ok": true}`) is a full success; a 207
  /// (`{"message", "failed_devices": [...]}`) is a partial failure — Dio's
  /// default `validateStatus` treats both as a normal response (200-299),
  /// so only a genuine error status (403/404/5xx) reaches the `catch`.
  Future<SceneActivationResult> activateScene(
    ProvisionedDevice device,
    int sceneId,
  ) async {
    try {
      final response = await _dio.postUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('guest/room/scenes/$sceneId/activate')),
        options: _auth(device.token),
      );
      final data = response.data ?? const {};
      if (response.statusCode == 207) {
        final failed = ((data['failed_devices'] as List?) ?? const [])
            .map((d) => d.toString())
            .toList();
        return SceneActivationResult(
          ok: false,
          message: data['message'] as String?,
          failedDevices: failed,
        );
      }
      return SceneActivationResult(ok: data['ok'] as bool? ?? true);
    } on DioException catch (error) {
      throw SmartRoomException(_messageFrom(error));
    } catch (error) {
      debugPrint('SmartRoomRepository: activateScene failed — $error');
      throw SmartRoomException('Could not activate this scene.');
    }
  }

  String _messageFrom(DioException error) {
    final data = error.response?.data;
    if (data is Map &&
        data['message'] is String &&
        (data['message'] as String).isNotEmpty) {
      return data['message'] as String;
    }
    if (error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout) {
      return 'No connection. Check the room network.';
    }
    return 'Something went wrong. Please try again.';
  }
}
