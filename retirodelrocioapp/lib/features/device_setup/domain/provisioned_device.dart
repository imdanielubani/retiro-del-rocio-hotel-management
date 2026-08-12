import 'package:flutter/foundation.dart';

/// A tablet that has been paired to the hotel via the provisioning API.
///
/// Guest tablets are bound to a specific room (suite + number); staff tablets
/// are locked to a [role].
@immutable
class ProvisionedDevice {
  const ProvisionedDevice({
    required this.token,
    required this.deviceCode,
    required this.deviceName,
    required this.mode,
    this.role,
    this.allocation,
    this.suiteName,
    this.roomNumber,
    this.roomUnitId,
    this.roomImageUrl,
  });

  /// Sanctum bearer token for this device's future API calls (heartbeat/sync).
  final String token;
  final String deviceCode;
  final String deviceName;

  /// `guest` or `staff`.
  final String mode;

  /// Locked role for staff tablets (e.g. `reception`).
  final String? role;

  /// Human label of the allocation (suite/room for guests, role for staff).
  final String? allocation;

  /// Suite/category name for a guest tablet, e.g. "Alba Suites".
  final String? suiteName;

  /// Physical room number for a guest tablet, e.g. "101".
  final String? roomNumber;

  /// The room this tablet is bound to. Identifies the realtime channel it
  /// listens on (`rooms.{id}`) for check-in / check-out.
  final int? roomUnitId;

  /// The room's featured photo (Property Management). Room-scoped and rarely
  /// changes, so it rides on the device record rather than the 20-second poll.
  final String? roomImageUrl;

  bool get isStaff => mode == 'staff';
  bool get isGuest => !isStaff;

  factory ProvisionedDevice.fromProvisionResponse(Map<String, dynamic> json) {
    final device =
        (json['device'] as Map?)?.cast<String, dynamic>() ?? const {};
    return ProvisionedDevice.fromDeviceJson(
      device,
      token: json['token'] as String? ?? '',
    );
  }

  /// Builds a device from a `DeviceResource` payload, keeping the token we
  /// already hold (the backend only issues one at provisioning time).
  factory ProvisionedDevice.fromDeviceJson(
    Map<String, dynamic> device, {
    required String token,
  }) {
    return ProvisionedDevice(
      token: token,
      deviceCode: device['device_code'] as String? ?? '',
      deviceName: device['device_name'] as String? ?? '',
      mode: device['mode'] as String? ?? 'guest',
      role: device['role'] as String?,
      allocation: device['allocation'] as String?,
      suiteName: device['room'] as String?,
      roomNumber: device['room_number'] as String?,
      roomUnitId: (device['room_unit_id'] as num?)?.toInt(),
      roomImageUrl: (device['room_image'] as String?)?.trim().isNotEmpty == true
          ? device['room_image'] as String
          : null,
    );
  }

  Map<String, dynamic> toJson() => {
    'token': token,
    'device_code': deviceCode,
    'device_name': deviceName,
    'mode': mode,
    'role': role,
    'allocation': allocation,
    'suite_name': suiteName,
    'room_number': roomNumber,
    'room_unit_id': roomUnitId,
    'room_image': roomImageUrl,
  };

  factory ProvisionedDevice.fromJson(Map<String, dynamic> json) =>
      ProvisionedDevice(
        token: json['token'] as String? ?? '',
        deviceCode: json['device_code'] as String? ?? '',
        deviceName: json['device_name'] as String? ?? '',
        mode: json['mode'] as String? ?? 'guest',
        role: json['role'] as String?,
        allocation: json['allocation'] as String?,
        suiteName: json['suite_name'] as String?,
        roomNumber: json['room_number'] as String?,
        roomUnitId: (json['room_unit_id'] as num?)?.toInt(),
        roomImageUrl: json['room_image'] as String?,
      );
}
