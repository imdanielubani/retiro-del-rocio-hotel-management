import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:retirodelrocioapp/features/authentication/domain/staff_session.dart';

/// Securely persists the signed-in staff session (token in the OS keystore).
class AuthSessionStore {
  static const _key = 'staff_session';
  static const _storage = FlutterSecureStorage();

  Future<void> save(StaffSession session) async {
    try {
      await _storage.write(key: _key, value: jsonEncode(session.toJson()));
    } catch (error) {
      debugPrint('AuthSessionStore: save failed — $error');
    }
  }

  Future<StaffSession?> read() async {
    try {
      final raw = await _storage.read(key: _key);
      if (raw == null) return null;
      return StaffSession.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (error) {
      debugPrint('AuthSessionStore: read failed — $error');
      return null;
    }
  }

  Future<void> clear() async {
    try {
      await _storage.delete(key: _key);
    } catch (error) {
      debugPrint('AuthSessionStore: clear failed — $error');
    }
  }
}
