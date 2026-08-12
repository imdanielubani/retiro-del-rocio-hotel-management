import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:path_provider/path_provider.dart';
import 'package:retirodelrocioapp/features/device_setup/domain/provisioned_device.dart';

/// Persists the paired device (token + allocation) on disk so the tablet stays
/// provisioned across restarts. Written after a successful provisioning.
class DeviceSessionStore {
  static const _fileName = 'device_session.json';

  Future<File> _file() async {
    final dir = await getApplicationSupportDirectory();
    return File('${dir.path}/$_fileName');
  }

  Future<void> save(ProvisionedDevice device) async {
    try {
      final file = await _file();
      await file.writeAsString(jsonEncode(device.toJson()));
    } catch (error) {
      debugPrint('DeviceSessionStore: save failed — $error');
    }
  }

  Future<ProvisionedDevice?> read() async {
    try {
      final file = await _file();
      if (!await file.exists()) return null;
      final json =
          jsonDecode(await file.readAsString()) as Map<String, dynamic>;
      return ProvisionedDevice.fromJson(json);
    } catch (error) {
      debugPrint('DeviceSessionStore: read failed — $error');
      return null;
    }
  }

  Future<void> clear() async {
    try {
      final file = await _file();
      if (await file.exists()) await file.delete();
    } catch (error) {
      debugPrint('DeviceSessionStore: clear failed — $error');
    }
  }
}
