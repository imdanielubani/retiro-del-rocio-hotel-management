import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:retirodelrocioapp/core/config/api_config.dart';
import 'package:retirodelrocioapp/core/session/device_session_service.dart';
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
    } on DioException catch (error) {
      // The tablet was deleted or unpaired in the dashboard — surface that so
      // the app can drop back to device setup instead of polling a dead token.
      if (DeviceSessionService.isRevokedResponse(error)) {
        throw const DeviceRevokedException();
      }
      debugPrint('RoomStatusRepository: fetch failed — $error');
      return null;
    } catch (error) {
      debugPrint('RoomStatusRepository: fetch failed — $error');
      return null;
    }
  }
}
