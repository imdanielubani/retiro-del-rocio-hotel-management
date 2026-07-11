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

  bool get isStaff => mode == 'staff';
  bool get isGuest => !isStaff;

  factory ProvisionedDevice.fromProvisionResponse(Map<String, dynamic> json) {
    final device = (json['device'] as Map?)?.cast<String, dynamic>() ?? const {};
    return ProvisionedDevice(
      token: json['token'] as String? ?? '',
      deviceCode: device['device_code'] as String? ?? '',
      deviceName: device['device_name'] as String? ?? '',
      mode: device['mode'] as String? ?? 'guest',
      role: device['role'] as String?,
      allocation: device['allocation'] as String?,
      suiteName: device['room'] as String?,
      roomNumber: device['room_number'] as String?,
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
      };

  factory ProvisionedDevice.fromJson(Map<String, dynamic> json) => ProvisionedDevice(
        token: json['token'] as String? ?? '',
        deviceCode: json['device_code'] as String? ?? '',
        deviceName: json['device_name'] as String? ?? '',
        mode: json['mode'] as String? ?? 'guest',
        role: json['role'] as String?,
        allocation: json['allocation'] as String?,
        suiteName: json['suite_name'] as String?,
        roomNumber: json['room_number'] as String?,
      );
}
