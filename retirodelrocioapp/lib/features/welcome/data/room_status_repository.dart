import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/features/welcome/domain/room_status.dart';

/// Fetches the tablet's live room occupancy using its device token.
class RoomStatusRepository {
  RoomStatusRepository({Dio? dio})
      : _dio = dio ??
            Dio(BaseOptions(
              connectTimeout: const Duration(seconds: 8),
              receiveTimeout: const Duration(seconds: 8),
            ));

  final Dio _dio;

  Future<RoomStatus?> fetch(String deviceToken) async {
    try {
      final response = await _dio.getUri<Map<String, dynamic>>(
        Uri.parse(ApiConfig.endpoint('tablets/room-status')),
        options: Options(headers: {
          'Authorization': 'Bearer $deviceToken',
          'Accept': 'application/json',
        }),
      );
      final data = (response.data?['data'] as Map?)?.cast<String, dynamic>();
      return data == null ? null : RoomStatus.fromJson(data);
    } catch (error) {
      debugPrint('RoomStatusRepository: fetch failed — $error');
      return null;
    }
  }
}
